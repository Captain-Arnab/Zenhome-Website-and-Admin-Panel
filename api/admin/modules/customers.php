<?php
/**
 * Customers (users table, registered from the website / apps).
 * Admins can view, activate/deactivate; delete is Super Admin only and
 * refused when the customer has bookings or payments.
 */

function customers_select_sql(): string
{
    return "SELECT u.ID, u.first_name, u.last_name, u.email, u.phone, u.address, u.status, u.photo, u.created_at,
            (SELECT COUNT(*) FROM service_booking b WHERE b.user_id = u.ID) AS booking_count,
            (SELECT MAX(b.date) FROM service_booking b WHERE b.user_id = u.ID) AS last_booking,
            (SELECT b.location FROM service_booking b WHERE b.user_id = u.ID ORDER BY b.created_at DESC, b.ID DESC LIMIT 1) AS last_location,
            (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.user_id = CAST(u.ID AS CHAR) AND t.status = 'success') AS online_spent,
            (SELECT COALESCE(SUM(b.price), 0) FROM service_booking b
                LEFT JOIN transactions t2 ON t2.transaction_id = CONCAT('ZC-', b.unique_booking_id)
                WHERE b.user_id = u.ID AND LOWER(b.payment_status) = 'paid' AND t2.id IS NULL) AS cash_spent
        FROM users u";
}

function customer_row(array $r): array
{
    $place = address_parts(($r['last_location'] ?? '') ?: ($r['address'] ?? ''));
    $name = full_name($r['first_name'], $r['last_name']);
    return [
        'id'           => (int) $r['ID'],
        'name'         => $name !== '' ? $name : 'Customer #' . $r['ID'],
        'email'        => (string) $r['email'],
        'mobile'       => format_mobile($r['phone']),
        'raw_mobile'   => (string) $r['phone'],
        'address'      => (string) ($r['address'] ?? ''),
        'city'         => $place['city'],
        'area'         => $place['area'],
        'bookings'     => (int) ($r['booking_count'] ?? 0),
        'spent'        => (float) ($r['online_spent'] ?? 0) + (float) ($r['cash_spent'] ?? 0),
        'last_booking' => $r['last_booking'] ?? null,
        'joined'       => $r['created_at'],
        'status'       => customer_is_active($r['status']),
    ];
}

function customers_where(array $in, array &$params): string
{
    $where = ['1 = 1'];
    if (!empty($in['search'])) {
        $where[] = like_clause(['u.first_name', 'u.last_name', 'u.email', 'u.phone', "CONCAT(u.first_name, ' ', u.last_name)"], trim((string) $in['search']), $params);
    }
    $status = strtolower((string) ($in['status'] ?? ''));
    if ($status === 'active') {
        $where[] = 'NOT ' . customer_inactive_sql();
    } elseif ($status === 'inactive') {
        $where[] = customer_inactive_sql();
    }
    if (!empty($in['city'])) {
        $where[] = '(u.address LIKE ? OR EXISTS (SELECT 1 FROM service_booking cb WHERE cb.user_id = u.ID AND cb.location LIKE ?))';
        $params[] = '%' . $in['city'] . '%';
        $params[] = '%' . $in['city'] . '%';
    }
    if (!empty($in['joined_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['joined_from'])) {
        $where[] = 'u.created_at >= ?';
        $params[] = $in['joined_from'] . ' 00:00:00';
    }
    if (!empty($in['joined_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['joined_to'])) {
        $where[] = 'u.created_at <= ?';
        $params[] = $in['joined_to'] . ' 23:59:59';
    }
    return implode(' AND ', $where);
}

function customers_summary(): array
{
    $total = (int) q_value('SELECT COUNT(*) FROM users');
    $inactive = (int) q_value('SELECT COUNT(*) FROM users u WHERE ' . customer_inactive_sql());
    return [
        'total'          => $total,
        'active'         => $total - $inactive,
        'inactive'       => $inactive,
        'new_this_month' => (int) q_value('SELECT COUNT(*) FROM users WHERE created_at >= ?', [date('Y-m-01') . ' 00:00:00']),
    ];
}

/** Cities seen in customer booking addresses (for the filter dropdown). */
function customers_cities(): array
{
    $cities = [];
    foreach (q_all("SELECT DISTINCT location FROM service_booking WHERE location IS NOT NULL AND location <> '' LIMIT 1000") as $r) {
        $city = address_parts($r['location'])['city'];
        if ($city !== '') {
            $cities[strtolower($city)] = $city;
        }
    }
    foreach (q_all("SELECT name FROM cities WHERE status = 1") as $r) {
        $cities[strtolower($r['name'])] = $r['name'];
    }
    natcasesort($cities);
    return array_values($cities);
}

function customers_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in);
    $params = [];
    $where = customers_where($in, $params);
    $order = order_by($in, [
        'name'     => 'u.first_name',
        'joined'   => 'u.created_at',
        'bookings' => 'booking_count',
    ], 'u.ID DESC');

    $total = (int) q_value("SELECT COUNT(*) FROM users u WHERE $where", $params);
    $rows = q_all(customers_select_sql() . " WHERE $where ORDER BY $order LIMIT $limit OFFSET $offset", $params);

    $extra = [];
    if (!empty($in['with_summary'])) {
        $extra['summary'] = customers_summary();
        $extra['cities'] = customers_cities();
    }
    return ok(paginated(array_map('customer_row', $rows), $total, $page, $limit, $extra));
}

function customers_summary_action(array $in, ?array $admin): array
{
    return ok(customers_summary());
}

function customer_find(int $id): array
{
    $row = $id ? q_one(customers_select_sql() . ' WHERE u.ID = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Customer');
    }
    return $row;
}

/**
 * No saved-address table exists: addresses = profile address + distinct
 * addresses used on bookings (newest first; newest is the default).
 */
function customers_addresses(array $in, ?array $admin): array
{
    $row = customer_find((int) ($in['id'] ?? 0));
    $addresses = [];
    $seen = [];
    foreach (q_all('SELECT location, landmark, MAX(created_at) AS used_at FROM service_booking WHERE user_id = ? AND location IS NOT NULL AND location <> \'\' GROUP BY location, landmark ORDER BY used_at DESC LIMIT 20', [$row['ID']]) as $a) {
        $key = strtolower(trim($a['location']));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $landmark = trim(preg_replace(['/\s*\|?\s*Contact:.*$/u', '/\s*\|?\s*Note:[^|]*/u'], '', (string) $a['landmark']), ' |');
        $addresses[] = ['label' => 'Booking address', 'line' => $a['location'], 'landmark' => $landmark, 'default' => !$addresses, 'used_at' => $a['used_at']];
    }
    $profile = trim((string) $row['address']);
    if ($profile !== '' && !isset($seen[strtolower($profile)])) {
        $addresses[] = ['label' => 'Profile address', 'line' => $profile, 'landmark' => '', 'default' => !$addresses, 'used_at' => null];
    }
    return ok(['items' => $addresses]);
}

function customers_bookings(array $in, ?array $admin): array
{
    $id = (int) customer_find((int) ($in['id'] ?? 0))['ID'];
    [$page, $limit, $offset] = page_params($in, 50);
    [$items, $total] = booking_query(['customer_id' => $id], $limit, $offset);
    return ok(paginated($items, $total, $page, $limit));
}

function customers_payments(array $in, ?array $admin): array
{
    $id = (int) customer_find((int) ($in['id'] ?? 0))['ID'];
    [$page, $limit, $offset] = page_params($in, 50);
    [$items, $total] = payment_query(['customer_id' => $id], $limit, $offset);
    return ok(paginated($items, $total, $page, $limit));
}

function customers_get(array $in, ?array $admin): array
{
    $row = customer_find((int) ($in['id'] ?? 0));
    $in = ['id' => $row['ID'], 'limit' => $in['limit'] ?? 100];
    return ok([
        'customer'  => customer_row($row),
        'addresses' => customers_addresses($in, $admin)['data']['items'],
        'bookings'  => customers_bookings($in, $admin)['data'],
        'payments'  => customers_payments($in, $admin)['data'],
    ]);
}

/**
 * Deactivation writes 'Inactive', which api/login.php refuses. (Legacy '0',
 * written by api/admin/deactivate_user.php, also exists on accounts that
 * are still in use, so login does not block it.)
 */
function customers_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Customer', ['required' => true, 'min' => 1]);
    $active = $v->bool('status');
    $v->check();
    $row = customer_find($id);

    q('UPDATE users SET status = ? WHERE ID = ?', [$active ? '1' : 'Inactive', $id]);
    if (!$active) {
        q('DELETE FROM user_sessions WHERE user_id = ?', [$id]);
    }
    $name = full_name($row['first_name'], $row['last_name']) ?: 'Customer';
    return ok(['id' => $id, 'status' => $active], $name . ($active ? ' activated.' : ' deactivated and logged out of all devices.'));
}

function customers_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Customer', ['required' => true, 'min' => 1]);
    $v->check();
    $row = customer_find($id);

    if ((int) $row['booking_count'] > 0 || (int) q_value('SELECT COUNT(*) FROM transactions WHERE user_id = ?', [(string) $id]) > 0) {
        throw new ApiException('This customer has bookings or payments. Deactivate the account instead of deleting it.', 409);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('DELETE FROM user_sessions WHERE user_id = ?', [$id]);
        q('DELETE FROM cart WHERE user_id = ?', [$id]);
        q('DELETE FROM users WHERE ID = ?', [$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ok(['id' => $id], 'Customer deleted.');
}

return [
    'list'       => ['GET',  'customers_list'],
    'summary'    => ['GET',  'customers_summary_action'],
    'get'        => ['GET',  'customers_get'],
    'addresses'  => ['GET',  'customers_addresses'],
    'bookings'   => ['GET',  'customers_bookings'],
    'payments'   => ['GET',  'customers_payments'],
    'set_status' => ['POST', 'customers_set_status'],
    'delete'     => ['POST', 'customers_delete', 'super'],
];
