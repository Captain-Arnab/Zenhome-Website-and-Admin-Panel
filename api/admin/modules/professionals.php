<?php
/**
 * Service professionals (service_partners). Internal only: no professional
 * login. Website partner registrations arrive as status 'Pending' and an
 * admin activates them. Lists use the same JSON format as
 * api/partner_registration.php for primary_category / serviceable_areas.
 */

function professionals_where(array $in, array &$params): string
{
    $where = ['1 = 1'];
    if (!empty($in['search'])) {
        $where[] = like_clause(['p.full_name', 'p.mobile', 'p.email'], trim((string) $in['search']), $params);
    }
    if (!empty($in['category'])) {
        $where[] = 'p.primary_category LIKE ?';
        $params[] = '%' . $in['category'] . '%';
    }
    if (!empty($in['area'])) {
        $where[] = 'p.serviceable_areas LIKE ?';
        $params[] = '%' . $in['area'] . '%';
    }
    $status = strtolower((string) ($in['status'] ?? ''));
    if ($status === 'active') {
        $where[] = "LOWER(p.status) IN ('active', '1', 'approved')";
    } elseif ($status === 'inactive') {
        $where[] = "LOWER(COALESCE(p.status, '')) NOT IN ('active', '1', 'approved', 'pending', '')";
    } elseif ($status === 'pending') {
        $where[] = "LOWER(COALESCE(p.status, '')) IN ('pending', '')";
    }
    return implode(' AND ', $where);
}

function professionals_summary(): array
{
    $rows = array_map('professional_row', q_all(professional_select_sql()));
    $active = array_filter($rows, fn($p) => $p['status']);
    $onJob = array_filter($active, fn($p) => $p['active_jobs'] > 0);
    return [
        'total'   => count($rows),
        'active'  => count($active),
        'pending' => count(array_filter($rows, fn($p) => $p['status_label'] === 'Pending')),
        'on_job'  => count($onJob),
        'free'    => count($active) - count($onJob),
    ];
}

/** Areas used by professionals + serviceable areas (filter dropdown). */
function professionals_area_options(): array
{
    $areas = [];
    foreach (q_all('SELECT name FROM serviceable_areas ORDER BY name') as $r) {
        $areas[strtolower($r['name'])] = $r['name'];
    }
    foreach (q_all("SELECT serviceable_areas FROM service_partners WHERE serviceable_areas IS NOT NULL AND serviceable_areas <> ''") as $r) {
        foreach (text_list($r['serviceable_areas']) as $a) {
            $areas[strtolower($a)] = $a;
        }
    }
    natcasesort($areas);
    return array_values($areas);
}

function professionals_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $params = [];
    $where = professionals_where($in, $params);
    $total = (int) q_value("SELECT COUNT(*) FROM service_partners p WHERE $where", $params);
    $rows = q_all(professional_select_sql() . " WHERE $where ORDER BY p.full_name LIMIT $limit OFFSET $offset", $params);
    $extra = [];
    if (!empty($in['with_summary'])) {
        $extra['summary'] = professionals_summary();
        $extra['areas'] = professionals_area_options();
    }
    return ok(paginated(array_map('professional_row', $rows), $total, $page, $limit, $extra));
}

/** Active professionals for the assign modal (category match first). */
function professionals_options(array $in, ?array $admin): array
{
    $rows = array_map('professional_row', q_all(professional_select_sql() . " WHERE LOWER(p.status) IN ('active', '1', 'approved') ORDER BY p.full_name"));
    $category = strtolower(trim((string) ($in['category'] ?? '')));
    foreach ($rows as &$p) {
        $p['match'] = $category !== '' && (bool) array_filter($p['categories'], fn($c) => str_contains($category, strtolower($c)) || str_contains(strtolower($c), $category));
    }
    unset($p);
    usort($rows, fn($a, $b) => [$b['match'], $a['active_jobs'], $a['name']] <=> [$a['match'], $b['active_jobs'], $b['name']]);
    return ok(['items' => $rows]);
}

function professional_find(int $id): array
{
    $row = $id ? q_one(professional_select_sql() . ' WHERE p.id = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Professional');
    }
    return $row;
}

function professionals_jobs(array $in, ?array $admin): array
{
    $id = (int) professional_find((int) ($in['id'] ?? 0))['id'];
    [$page, $limit, $offset] = page_params($in, 50);
    $filter = ['professional_id' => $id];
    $jobs = strtolower((string) ($in['jobs'] ?? ''));
    if ($jobs === 'completed') {
        $filter['status'] = 'Completed';
    }
    if ($jobs === 'current') {
        $params = [];
        $where = '(' . booking_status_where('Assigned', $params) . ' OR ' . booking_status_where('Ongoing', $params) . ')';
        $total = (int) q_value("SELECT COUNT(*) FROM service_booking b WHERE b.professional_id = ? AND $where", array_merge([$id], $params));
        $rows = q_all(booking_select_sql() . " WHERE b.professional_id = ? AND $where ORDER BY b.date ASC LIMIT $limit OFFSET $offset", array_merge([$id], $params));
        return ok(paginated(array_map('booking_row', $rows), $total, $page, $limit));
    }
    [$items, $total] = booking_query($filter, $limit, $offset, 'b.date DESC, b.ID DESC');
    return ok(paginated($items, $total, $page, $limit));
}

function professionals_get(array $in, ?array $admin): array
{
    $row = professional_find((int) ($in['id'] ?? 0));
    return ok([
        'professional' => professional_row($row) + [
            'alternate_mobile' => format_mobile($row['alternate_mobile'] ?? ''),
            'address'          => (string) ($row['current_address'] ?? ''),
            'pincode'          => (string) ($row['pincode'] ?? ''),
        ],
        'jobs' => professionals_jobs(['id' => $row['id'], 'jobs' => $in['jobs'] ?? '', 'limit' => $in['limit'] ?? 50, 'page' => $in['page'] ?? 1], $admin)['data'],
    ]);
}

function professionals_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id       = $v->int('id', 'Professional', ['min' => 1]);
    $name     = $v->str('name', 'Full name', ['required' => true, 'max' => 80]);
    $mobile   = $v->mobile('mobile', 'Mobile number', ['required' => true]);
    $category = $v->str('category', 'Category', ['required' => true, 'max' => 120]);
    $areas    = $v->strings('areas');
    $remarks  = $v->str('remarks', 'Remarks', ['max' => 500, 'default' => '']);
    $status   = $v->bool('status', true);
    if (!$areas) {
        $v->error('areas', 'Select at least one area.');
    }
    $v->check();

    $existing = $id ? professional_find($id) : null;
    if (q_value('SELECT 1 FROM service_partners WHERE mobile = ? AND id <> ?', [$mobile, $id ?? 0])) {
        throw new ApiException('Another professional already uses this mobile number.', 422, ['mobile' => 'Mobile number already registered.']);
    }

    $values = [$name, $mobile, json_encode([$category]), json_encode($areas, JSON_UNESCAPED_UNICODE), $remarks ?: null, $status ? 'Active' : 'Inactive', now()];
    if ($existing) {
        $categories = text_list($existing['primary_category']);
        if ($categories && strcasecmp($categories[0], $category) === 0) {
            $values[2] = $existing['primary_category']; // keep extra categories from registration
        }
        q('UPDATE service_partners SET full_name = ?, mobile = ?, primary_category = ?, serviceable_areas = ?, remarks = ?, status = ?, updated_at = ? WHERE id = ?', array_merge($values, [$id]));
        if ($existing['full_name'] !== $name || $existing['mobile'] !== $mobile) {
            // Customer app reads the technician from the booking row.
            q('UPDATE service_booking SET technician_name = ?, technician_phone = ? WHERE professional_id = ?', [mb_substr($name, 0, 50), $mobile, $id]);
        }
    } else {
        q('INSERT INTO service_partners (full_name, mobile, primary_category, serviceable_areas, remarks, status, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', $values);
        $id = (int) db()->lastInsertId();
    }
    return ok(professional_row(professional_find($id)), $existing ? 'Professional updated.' : 'Professional added.', $existing ? 200 : 201);
}

function professionals_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Professional', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    $row = professional_find($id);
    q('UPDATE service_partners SET status = ?, updated_at = ? WHERE id = ?', [$status ? 'Active' : 'Inactive', now(), $id]);
    $message = $row['full_name'] . ($status ? ' activated.' : ' deactivated.');
    if (!$status && (int) $row['active_jobs'] > 0) {
        $message .= ' They still have ' . (int) $row['active_jobs'] . ' active job(s) - reassign them if needed.';
    }
    return ok(['id' => $id, 'status' => $status, 'active_jobs' => (int) $row['active_jobs']], $message);
}

function professionals_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Professional', ['required' => true, 'min' => 1]);
    $v->check();
    $row = professional_find($id);
    if ((int) q_value('SELECT COUNT(*) FROM service_booking WHERE professional_id = ?', [$id]) > 0) {
        throw new ApiException('This professional has bookings in their history. Deactivate them instead of deleting.', 409);
    }
    q('DELETE FROM service_partners WHERE id = ?', [$id]);
    return ok(['id' => $id], $row['full_name'] . ' deleted.');
}

return [
    'list'       => ['GET',  'professionals_list'],
    'options'    => ['GET',  'professionals_options'],
    'get'        => ['GET',  'professionals_get'],
    'jobs'       => ['GET',  'professionals_jobs'],
    'save'       => ['POST', 'professionals_save'],
    'set_status' => ['POST', 'professionals_set_status'],
    'delete'     => ['POST', 'professionals_delete', 'super'],
];
