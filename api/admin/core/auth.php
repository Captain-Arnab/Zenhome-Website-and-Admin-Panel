<?php
/**
 * Admin authentication.
 *
 * Two ways to authenticate, both checked on every admin endpoint:
 *  - Admin panel (browser): PHP session cookie "ZCADMIN" + X-CSRF-Token
 *    header on every state-changing request.
 *  - API clients (apps/tools): "Authorization: Bearer <token>" (or ?token=),
 *    the same transport as api/auth_helper.php, validated against
 *    admin_sessions. Token requests are not cookie based, so no CSRF.
 */

const ADMIN_ROLES = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
];
const ADMIN_TOKEN_LIFETIME = 2592000;   // 30 days, same as user tokens
const ADMIN_MAX_FAILED     = 5;
const ADMIN_LOCK_MINUTES   = 15;

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('ZCADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Public shape of an admin row (never includes password/reset token). */
function admin_public(array $row): array
{
    $role = $row['role'] ?? 'admin';
    return [
        'id'         => (int) $row['id'],
        'name'       => full_name($row['first_name'] ?? '', $row['last_name'] ?? ''),
        'first_name' => $row['first_name'] ?? '',
        'last_name'  => $row['last_name'] ?? '',
        'email'      => $row['email'] ?? '',
        'mobile'     => $row['phone'] ?? '',
        'role'       => $role,
        'role_label' => ADMIN_ROLES[$role] ?? 'Admin',
        'status'     => (bool) ($row['status'] ?? 1),
        'last_login' => $row['last_login_at'] ?? null,
        'alerts_read_at' => $row['alerts_read_at'] ?? null,
    ];
}

/** Bearer token from header/query/body (reuses api/auth_helper.php). */
function admin_request_token(): ?string
{
    require_once ZC_ROOT . '/api/auth_helper.php';
    $token = getBearerToken();
    return ($token && preg_match('/^[a-f0-9]{64}$/', $token)) ? $token : null;
}

/**
 * Resolves the authenticated admin for this request, or null.
 * Sets $GLOBALS['zc_auth_via'] to 'token' or 'session'.
 */
function admin_current(): ?array
{
    static $resolved = false, $admin = null;
    if ($resolved) {
        return $admin;
    }
    $resolved = true;

    $token = admin_request_token();
    if ($token !== null) {
        $row = q_one(
            'SELECT a.* FROM admin_sessions s JOIN admin a ON a.id = s.admin_id
             WHERE s.token = ? AND s.expires_at > ? AND a.status = 1',
            [$token, now()]
        );
        if ($row) {
            $GLOBALS['zc_auth_via'] = 'token';
            return $admin = admin_public($row);
        }
        return null;
    }

    admin_session_start();
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $row = q_one('SELECT * FROM admin WHERE id = ? AND status = 1', [$id]);
    if (!$row) {
        unset($_SESSION['admin_id'], $_SESSION['admin_role']);
        return null;
    }
    $_SESSION['admin_role'] = $row['role'];
    $GLOBALS['zc_auth_via'] = 'session';
    return $admin = admin_public($row);
}

function admin_is_super(?array $admin): bool
{
    return ($admin['role'] ?? '') === 'super_admin';
}

function require_super_admin(?array $admin): void
{
    if (!admin_is_super($admin)) {
        throw new ApiException('Only a Super Admin can do this.', 403);
    }
}

/* --------------------------------------------------------------------------
 * CSRF (session-authenticated, state-changing requests)
 * ------------------------------------------------------------------------ */

function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['zc_csrf'])) {
        $_SESSION['zc_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['zc_csrf'];
}

function csrf_valid(?string $token): bool
{
    admin_session_start();
    return is_string($token) && !empty($_SESSION['zc_csrf']) && hash_equals($_SESSION['zc_csrf'], $token);
}

/* --------------------------------------------------------------------------
 * Login / logout
 * ------------------------------------------------------------------------ */

/**
 * Verifies credentials with lockout. Legacy rows that still hold a
 * plain-text password are accepted once and re-hashed with password_hash().
 *
 * @param bool $startSession  log the browser session in (admin panel)
 * @param bool $issueToken    create an admin_sessions token (API clients)
 */
function admin_login(string $email, string $password, bool $startSession = true, bool $issueToken = false): array
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        throw new ApiException('Email and password are required.', 422, array_filter([
            'email'    => $email === '' ? 'Email is required.' : null,
            'password' => $password === '' ? 'Password is required.' : null,
        ]));
    }

    $row = q_one('SELECT * FROM admin WHERE LOWER(email) = ?', [$email]);
    $invalid = new ApiException('Invalid email or password.', 401);
    if (!$row) {
        password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // equal timing
        throw $invalid;
    }

    if (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
        $minutes = max(1, (int) ceil((strtotime($row['locked_until']) - time()) / 60));
        throw new ApiException("Too many failed attempts. Try again in $minutes minute(s).", 429);
    }

    $stored = (string) $row['password'];
    $isHash = (bool) preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $stored);
    $valid  = $isHash ? password_verify($password, $stored) : hash_equals($stored, $password);

    if (!$valid) {
        $failed = (int) $row['failed_logins'] + 1;
        $lock   = $failed >= ADMIN_MAX_FAILED ? date('Y-m-d H:i:s', time() + ADMIN_LOCK_MINUTES * 60) : null;
        q('UPDATE admin SET failed_logins = ?, locked_until = ? WHERE id = ?', [$lock ? 0 : $failed, $lock, $row['id']]);
        throw $invalid;
    }
    if ((int) $row['status'] !== 1) {
        throw new ApiException('This admin account is disabled. Contact the Super Admin.', 403);
    }

    $rehash = !$isHash || password_needs_rehash($stored, PASSWORD_DEFAULT);
    q(
        'UPDATE admin SET failed_logins = 0, locked_until = NULL, last_login_at = ?' . ($rehash ? ', password = ?' : '') . ' WHERE id = ?',
        $rehash ? [now(), password_hash($password, PASSWORD_DEFAULT), $row['id']] : [now(), $row['id']]
    );
    $row['last_login_at'] = now();

    $result = ['admin' => admin_public($row)];

    if ($startSession) {
        admin_session_start();
        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int) $row['id'];
        $_SESSION['admin_role'] = $row['role'];
        unset($_SESSION['zc_csrf']);
        $result['csrf_token'] = csrf_token();
    }

    if ($issueToken) {
        $token = bin2hex(random_bytes(32));
        q(
            'INSERT INTO admin_sessions (admin_id, token, expires_at, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $row['id'], $token, date('Y-m-d H:i:s', time() + ADMIN_TOKEN_LIFETIME),
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                now(),
            ]
        );
        $result['token']      = $token;
        $result['expires_at'] = date('Y-m-d H:i:s', time() + ADMIN_TOKEN_LIFETIME);
    }

    return $result;
}

function admin_logout(): void
{
    $token = admin_request_token();
    if ($token !== null) {
        q('DELETE FROM admin_sessions WHERE token = ?', [$token]);
    }
    admin_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
