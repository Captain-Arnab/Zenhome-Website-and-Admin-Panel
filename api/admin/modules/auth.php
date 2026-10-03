<?php
/**
 * Auth & current admin: login (token for API clients), logout, me,
 * profile update, change password, CSRF token.
 */

/** POST {email, password} -> {admin, token, expires_at}. Browser panel logs in via admin/login.php. */
function auth_login(array $in, ?array $admin): array
{
    $result = admin_login((string) ($in['email'] ?? ''), (string) ($in['password'] ?? ''), false, true);
    return ok($result, 'Login successful.');
}

function auth_logout(array $in, ?array $admin): array
{
    admin_logout();
    return ok(null, 'Logged out.');
}

function auth_me(array $in, ?array $admin): array
{
    return ok($admin);
}

function auth_csrf(array $in, ?array $admin): array
{
    return ok(['csrf_token' => csrf_token()]);
}

function auth_profile_update(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $name   = $v->str('name', 'Full name', ['required' => true, 'max' => 100]);
    $mobile = $v->mobile('mobile', 'Mobile', ['required' => true]);
    $email  = $v->email('email', 'Email', ['required' => true]);
    $v->check();

    if (q_value('SELECT id FROM admin WHERE LOWER(email) = ? AND id <> ?', [$email, $admin['id']])) {
        throw new ApiException('Another admin already uses this email.', 409, ['email' => 'Email already in use.']);
    }
    [$first, $last] = split_name($name);
    q('UPDATE admin SET first_name = ?, last_name = ?, phone = ?, email = ?, updated_at = ? WHERE id = ?', [$first, $last, $mobile, $email, now(), $admin['id']]);

    return ok(admin_public(q_one('SELECT * FROM admin WHERE id = ?', [$admin['id']])), 'Profile updated.');
}

function auth_change_password(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $current = (string) ($in['current_password'] ?? '');
    $new     = $v->str('new_password', 'New password', ['required' => true, 'min' => 8, 'max' => 72]);
    $confirm = (string) ($in['confirm_password'] ?? '');
    if ($current === '') {
        $v->error('current_password', 'Current password is required.');
    }
    if ($new !== null && $new !== $confirm) {
        $v->error('confirm_password', 'Passwords do not match.');
    }
    if ($new !== null && (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new))) {
        $v->error('new_password', 'Use at least one letter and one number.');
    }
    $v->check();

    $row = q_one('SELECT password FROM admin WHERE id = ?', [$admin['id']]);
    $stored = (string) $row['password'];
    $isHash = (bool) preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $stored);
    if (!($isHash ? password_verify($current, $stored) : hash_equals($stored, $current))) {
        throw new ApiException('Current password is incorrect.', 422, ['current_password' => 'Current password is incorrect.']);
    }
    q('UPDATE admin SET password = ?, updated_at = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), now(), $admin['id']]);

    // Sign out API tokens issued with the old password.
    q('DELETE FROM admin_sessions WHERE admin_id = ?', [$admin['id']]);

    return ok(null, 'Password changed.');
}

return [
    'login'           => ['POST', 'auth_login', 'public'],
    'logout'          => ['POST', 'auth_logout'],
    'me'              => ['GET',  'auth_me'],
    'csrf'            => ['GET',  'auth_csrf'],
    'profile_update'  => ['POST', 'auth_profile_update'],
    'change_password' => ['POST', 'auth_change_password'],
];
