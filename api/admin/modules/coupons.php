<?php
/**
 * Coupons (new coupons table). Applied at checkout by api/book_appointment.php
 * and previewed by api/validate_coupon.php (rules in api/coupon_helper.php).
 */

function coupon_row(array $r): array
{
    $today = today();
    $validity = !(int) $r['status'] ? 'Inactive' : ($r['valid_to'] < $today ? 'Expired' : ($r['valid_from'] > $today ? 'Scheduled' : 'Active'));
    return [
        'id'          => (int) $r['id'],
        'code'        => $r['code'],
        'description' => (string) $r['description'],
        'type'        => $r['type'],
        'value'       => (float) $r['value'],
        'max'         => $r['max_discount'] !== null ? (float) $r['max_discount'] : null,
        'min'         => (float) $r['min_order'],
        'from'        => $r['valid_from'],
        'to'          => $r['valid_to'],
        'limit'       => $r['usage_limit'] !== null ? (int) $r['usage_limit'] : null,
        'per_user'    => (int) $r['per_user_limit'],
        'used'        => (int) $r['used_count'],
        'status'      => (int) $r['status'] === 1,
        'validity'    => $validity,
    ];
}

function coupons_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['code', 'description'], trim((string) $in['search']), $params);
    }
    if (!empty($in['type']) && in_array(strtolower((string) $in['type']), ['percentage', 'fixed'], true)) {
        $where[] = 'type = ?';
        $params[] = strtolower((string) $in['type']);
    }
    $validity = strtolower((string) ($in['validity'] ?? ''));
    $map = [
        'active'    => 'status = 1 AND valid_from <= ? AND valid_to >= ?',
        'scheduled' => 'status = 1 AND valid_from > ?',
        'expired'   => 'status = 1 AND valid_to < ?',
        'inactive'  => 'status = 0',
    ];
    if (isset($map[$validity])) {
        $where[] = $map[$validity];
        $params = array_merge($params, array_fill(0, substr_count($map[$validity], '?'), today()));
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM coupons WHERE $sqlWhere", $params);
    $rows = q_all("SELECT * FROM coupons WHERE $sqlWhere ORDER BY valid_to DESC, id DESC LIMIT $limit OFFSET $offset", $params);
    return ok(paginated(array_map('coupon_row', $rows), $total, $page, $limit));
}

function coupon_find(int $id): array
{
    $row = $id ? q_one('SELECT * FROM coupons WHERE id = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Coupon');
    }
    return $row;
}

function coupons_get(array $in, ?array $admin): array
{
    return ok(coupon_row(coupon_find((int) ($in['id'] ?? 0))));
}

function coupons_save(array $in, ?array $admin): array
{
    if (isset($in['code']) && is_string($in['code'])) {
        $in['code'] = strtoupper(trim($in['code']));
    }
    $v = new Validator($in);
    $id      = $v->int('id', 'Coupon', ['min' => 1]);
    $code    = $v->str('code', 'Coupon code', ['required' => true, 'pattern' => '/^[A-Z0-9]{4,20}$/', 'pattern_message' => 'Use 4-20 letters or digits.']);
    $desc    = $v->str('description', 'Description', ['max' => 100, 'default' => '']);
    $type    = $v->enum('type', 'Discount type', ['percentage', 'fixed'], ['required' => true]);
    $value   = $v->num('value', 'Discount value', ['required' => true, 'min' => 1]);
    $max     = $v->num('max', 'Max discount', ['min' => 0]);
    $min     = $v->num('min', 'Minimum order', ['min' => 0, 'default' => 0]);
    $from    = $v->date('from', 'Valid from', ['required' => true]);
    $to      = $v->date('to', 'Valid to', ['required' => true]);
    $limit   = $v->int('limit', 'Usage limit', ['min' => 0]);
    $perUser = $v->int('per_user', 'Per-user limit', ['min' => 1, 'default' => 1]);
    $status  = $v->bool('status', true);
    if ($type === 'percentage' && $value !== null && $value > 100) {
        $v->error('value', 'Percentage cannot exceed 100.');
    }
    if ($from && $to && $to < $from) {
        $v->error('to', 'End date must be on or after the start date.');
    }
    $v->check();

    if ($id) {
        coupon_find($id);
    }
    if (q_value('SELECT 1 FROM coupons WHERE code = ? AND id <> ?', [$code, $id ?? 0])) {
        throw new ApiException('This coupon code already exists.', 422, ['code' => 'Code already used.']);
    }
    $values = [$code, $desc ?: null, $type, $value, $type === 'percentage' && $max ? $max : null, $min ?? 0, $from, $to, $limit ?: null, $perUser, $status ? 1 : 0, now()];
    if ($id) {
        q('UPDATE coupons SET code = ?, description = ?, type = ?, value = ?, max_discount = ?, min_order = ?, valid_from = ?, valid_to = ?, usage_limit = ?, per_user_limit = ?, status = ?, updated_at = ? WHERE id = ?', array_merge($values, [$id]));
        return ok(coupon_row(coupon_find($id)), 'Coupon updated.');
    }
    q('INSERT INTO coupons (code, description, type, value, max_discount, min_order, valid_from, valid_to, usage_limit, per_user_limit, status, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
    return ok(coupon_row(coupon_find((int) db()->lastInsertId())), 'Coupon created.', 201);
}

function coupons_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Coupon', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    $row = coupon_find($id);
    q('UPDATE coupons SET status = ?, updated_at = ? WHERE id = ?', [$status ? 1 : 0, now(), $id]);
    return ok(['id' => $id, 'status' => $status], $row['code'] . ($status ? ' activated.' : ' deactivated.'));
}

function coupons_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Coupon', ['required' => true, 'min' => 1]);
    $v->check();
    $row = coupon_find($id);
    q('DELETE FROM coupons WHERE id = ?', [$id]);
    return ok(['id' => $id], 'Coupon ' . $row['code'] . ' deleted.');
}

return [
    'list'       => ['GET',  'coupons_list'],
    'get'        => ['GET',  'coupons_get'],
    'save'       => ['POST', 'coupons_save'],
    'set_status' => ['POST', 'coupons_set_status'],
    'delete'     => ['POST', 'coupons_delete', 'super'],
];
