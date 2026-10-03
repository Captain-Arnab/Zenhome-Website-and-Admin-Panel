<?php
/**
 * Admin Management (Super Admin only - enforced by the 'super' route role).
 */

function admins_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 50);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['first_name', 'last_name', 'email', 'phone'], (string) $in['search'], $params);
    }
    if (isset($in['role']) && isset(ADMIN_ROLES[$in['role']])) {
        $where[] = 'role = ?';
        $params[] = $in['role'];
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM admin WHERE $sqlWhere", $params);
    $rows = q_all("SELECT * FROM admin WHERE $sqlWhere ORDER BY role = 'super_admin' DESC, id ASC LIMIT $limit OFFSET $offset", $params);

    return ok(paginated(array_map('admin_public', $rows), $total, $page, $limit));
}

function admins_get(array $in, ?array $admin): array
{
    $row = q_one('SELECT * FROM admin WHERE id = ?', [(int) ($in['id'] ?? 0)]);
    if (!$row) {
        throw not_found('Admin');
    }
    return ok(admin_public($row));
}

/** Create (no id) or update (id). Password optional on update. */
function admins_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id     = $v->int('id', 'ID', ['min' => 1]);
    $name   = $v->str('name', 'Full name', ['required' => true, 'max' => 100]);
    $mobile = $v->mobile('mobile', 'Mobile', ['required' => true]);
    $email  = $v->email('email', 'Email', ['required' => true]);
    $role   = $v->enum('role', 'Role', array_keys(ADMIN_ROLES), ['required' => true]);
    $status = $v->bool('status', false);
    $password = (string) ($in['password'] ?? '');

    if (!$id || $password !== '') {
        $v->str('password', 'Password', ['required' => true, 'min' => 8, 'max' => 72]);
        if ($password !== (string) ($in['password_confirm'] ?? '')) {
            $v->error('password_confirm', 'Passwords do not match.');
        }
    }
    $v->check();

    if ($id && !q_value('SELECT id FROM admin WHERE id = ?', [$id])) {
        throw not_found('Admin');
    }
    if (q_value('SELECT id FROM admin WHERE LOWER(email) = ? AND id <> ?', [$email, (int) $id])) {
        throw new ApiException('Another admin already uses this email.', 409, ['email' => 'Email already in use.']);
    }
    if ($id === $admin['id'] && ($role !== 'super_admin' || !$status)) {
        throw new ApiException('You cannot remove your own Super Admin access or disable yourself.', 422);
    }

    [$first, $last] = split_name($name);
    if ($id) {
        $sql = 'UPDATE admin SET first_name = ?, last_name = ?, phone = ?, email = ?, role = ?, status = ?, updated_at = ?';
        $params = [$first, $last, $mobile, $email, $role, (int) $status, now()];
        if ($password !== '') {
            $sql .= ', password = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        q($sql . ' WHERE id = ?', array_merge($params, [$id]));
        if (!$status || $password !== '') {
            q('DELETE FROM admin_sessions WHERE admin_id = ?', [$id]);
        }
        $message = 'Admin updated.';
    } else {
        q(
            'INSERT INTO admin (first_name, last_name, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$first, $last, $email, $mobile, password_hash($password, PASSWORD_DEFAULT), $role, (int) $status, now()]
        );
        $id = (int) db()->lastInsertId();
        $message = 'Admin created.';
    }

    return ok(admin_public(q_one('SELECT * FROM admin WHERE id = ?', [$id])), $message, 200);
}

function admins_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'ID', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    if ($id === $admin['id']) {
        throw new ApiException('You cannot deactivate your own account.', 422);
    }
    $row = q_one('SELECT * FROM admin WHERE id = ?', [$id]);
    if (!$row) {
        throw not_found('Admin');
    }
    if (!$status && $row['role'] === 'super_admin'
        && (int) q_value("SELECT COUNT(*) FROM admin WHERE role = 'super_admin' AND status = 1") <= 1) {
        throw new ApiException('At least one active Super Admin is required.', 422);
    }
    q('UPDATE admin SET status = ?, updated_at = ? WHERE id = ?', [(int) $status, now(), $id]);
    if (!$status) {
        q('DELETE FROM admin_sessions WHERE admin_id = ?', [$id]);
    }
    return ok(['id' => $id, 'status' => $status], $status ? 'Admin activated.' : 'Admin deactivated.');
}

function admins_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'ID', ['required' => true, 'min' => 1]);
    $v->check();
    if ($id === $admin['id']) {
        throw new ApiException('You cannot delete your own account.', 422);
    }
    $row = q_one('SELECT * FROM admin WHERE id = ?', [$id]);
    if (!$row) {
        throw not_found('Admin');
    }
    if ($row['role'] === 'super_admin' && (int) $row['status'] === 1
        && (int) q_value("SELECT COUNT(*) FROM admin WHERE role = 'super_admin' AND status = 1") <= 1) {
        throw new ApiException('At least one active Super Admin is required.', 422);
    }
    q('DELETE FROM admin_sessions WHERE admin_id = ?', [$id]);
    q('DELETE FROM admin WHERE id = ?', [$id]);
    return ok(['id' => $id], 'Admin ' . full_name($row['first_name'] ?? '', $row['last_name'] ?? '') . ' deleted.');
}

return [
    'list'       => ['GET',  'admins_list', 'super'],
    'get'        => ['GET',  'admins_get', 'super'],
    'save'       => ['POST', 'admins_save', 'super'],
    'set_status' => ['POST', 'admins_set_status', 'super'],
    'delete'     => ['POST', 'admins_delete', 'super'],
];
