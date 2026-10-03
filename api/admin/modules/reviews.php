<?php
/**
 * Ratings & reviews (ratings_feedback, written by the customer apps).
 * ratings_feedback.unique_booking_id holds service_booking.ID.
 * Moderation status: Pending (new) / Approved / Hidden.
 */

function review_row(array $r): array
{
    $items = booking_items($r['subcategories'] ?? '');
    $code = trim((string) ($r['booking_code'] ?? ''), ' "');
    return [
        'id'           => (int) $r['id'],
        'customer_id'  => $r['user_id'] !== null ? (int) $r['user_id'] : null,
        'customer'     => full_name($r['first_name'] ?? '', $r['last_name'] ?? '') ?: 'Customer #' . $r['user_id'],
        'booking_id'   => $r['booking_id'] !== null ? (int) $r['booking_id'] : null,
        'booking'      => $r['booking_id'] !== null ? ($code !== '' ? $code : '#' . $r['booking_id']) : '',
        'service'      => $items ? implode(', ', array_column($items, 'name')) : (string) ($r['category'] ?? ''),
        'professional' => (string) (($r['pro_name'] ?? '') ?: ($r['technician_name'] ?? '')),
        'rating'       => (float) $r['rating'],
        'text'         => (string) $r['feedback'],
        'date'         => $r['created_at'],
        'status'       => $r['status'] ?: 'Pending',
        'approved'     => $r['status'] === 'Approved',
        'featured'     => !empty($r['featured']),
    ];
}

function reviews_from_sql(): string
{
    return 'FROM ratings_feedback r
        LEFT JOIN users u ON u.ID = r.user_id
        LEFT JOIN service_booking b ON b.ID = r.unique_booking_id
        LEFT JOIN service_partners p ON p.id = b.professional_id';
}

function reviews_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['u.first_name', 'u.last_name', 'r.feedback', 'b.subcategories', 'b.unique_booking_id'], trim((string) $in['search']), $params);
    }
    if (!empty($in['rating'])) {
        $where[] = 'FLOOR(r.rating) = ?';
        $params[] = (int) $in['rating'];
    }
    if (!empty($in['status']) && in_array(ucfirst(strtolower((string) $in['status'])), ['Pending', 'Approved', 'Hidden'], true)) {
        $where[] = 'r.status = ?';
        $params[] = ucfirst(strtolower((string) $in['status']));
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value('SELECT COUNT(*) ' . reviews_from_sql() . " WHERE $sqlWhere", $params);
    $rows = q_all(
        'SELECT r.*, u.first_name, u.last_name, b.ID AS booking_id, b.unique_booking_id AS booking_code, b.subcategories, b.category, b.technician_name, p.full_name AS pro_name '
        . reviews_from_sql() . " WHERE $sqlWhere ORDER BY r.created_at DESC, r.id DESC LIMIT $limit OFFSET $offset",
        $params
    );
    $extra = [];
    if (!empty($in['with_summary'])) {
        $extra['summary'] = reviews_summary();
    }
    return ok(paginated(array_map('review_row', $rows), $total, $page, $limit, $extra));
}

function reviews_summary(): array
{
    $agg = q_one("SELECT COUNT(*) AS total, AVG(rating) AS avg_rating, SUM(status = 'Approved') AS approved, SUM(status = 'Hidden') AS hidden, SUM(status = 'Pending') AS pending FROM ratings_feedback");
    $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach (q_all('SELECT LEAST(5, GREATEST(1, FLOOR(rating))) AS s, COUNT(*) AS c FROM ratings_feedback GROUP BY s') as $r) {
        $distribution[(int) $r['s']] = (int) $r['c'];
    }
    return [
        'total'        => (int) $agg['total'],
        'average'      => $agg['avg_rating'] !== null ? round((float) $agg['avg_rating'], 1) : 0,
        'approved'     => (int) $agg['approved'],
        'hidden'       => (int) $agg['hidden'],
        'pending'      => (int) $agg['pending'],
        'distribution' => $distribution,
    ];
}

/** status: Approved | Hidden (or a boolean toggle: true = Approved). */
function reviews_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Review', ['required' => true, 'min' => 1]);
    $raw = strtolower(trim((string) ($in['status'] ?? '')));
    $status = in_array($raw, ['approved', 'approve'], true) ? 'Approved' : (in_array($raw, ['hidden', 'hide'], true) ? 'Hidden' : ($v->bool('status') ? 'Approved' : 'Hidden'));
    $v->check();
    if (!q_value('SELECT 1 FROM ratings_feedback WHERE id = ?', [$id])) {
        throw not_found('Review');
    }
    // A review that is no longer Approved cannot stay featured on the homepage.
    if ($status !== 'Approved') {
        q('UPDATE ratings_feedback SET status = ?, moderated_by = ?, moderated_at = ?, featured = 0 WHERE id = ?', [$status, $admin['id'], now(), $id]);
    } else {
        q('UPDATE ratings_feedback SET status = ?, moderated_by = ?, moderated_at = ? WHERE id = ?', [$status, $admin['id'], now(), $id]);
    }
    return ok(['id' => $id, 'status' => $status, 'approved' => $status === 'Approved'], $status === 'Approved' ? 'Review approved.' : 'Review hidden.');
}

/** Feature/unfeature an Approved review on the homepage testimonials section (uses the shared statusSwitch widget, payload key "status"). */
function reviews_set_featured(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Review', ['required' => true, 'min' => 1]);
    $featured = $v->bool('status');
    $v->check();
    $row = q_one('SELECT status FROM ratings_feedback WHERE id = ?', [$id]);
    if (!$row) {
        throw not_found('Review');
    }
    if ($featured && $row['status'] !== 'Approved') {
        throw new ApiException('Only an approved review can be featured.', 422, ['status' => 'Only an approved review can be featured.']);
    }
    q('UPDATE ratings_feedback SET featured = ? WHERE id = ?', [$featured ? 1 : 0, $id]);
    return ok(['id' => $id, 'featured' => $featured], $featured ? 'Review featured on the homepage.' : 'Review removed from the homepage.');
}

function reviews_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Review', ['required' => true, 'min' => 1]);
    $v->check();
    if (!q_value('SELECT 1 FROM ratings_feedback WHERE id = ?', [$id])) {
        throw not_found('Review');
    }
    q('DELETE FROM ratings_feedback WHERE id = ?', [$id]);
    return ok(['id' => $id], 'Review deleted.');
}

function reviews_summary_action(array $in, ?array $admin): array
{
    return ok(reviews_summary());
}

return [
    'list'       => ['GET',  'reviews_list'],
    'summary'    => ['GET',  'reviews_summary_action'],
    'set_status' => ['POST', 'reviews_set_status'],
    'set_featured' => ['POST', 'reviews_set_featured'],
    'delete'     => ['POST', 'reviews_delete', 'super'],
];
