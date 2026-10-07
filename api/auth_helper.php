<?php
/**
 * Auth helper: validate Bearer token and return user_id.
 * Include this in APIs that require login. Use getUserIdFromRequest().
 */

if (!defined('AUTH_HELPER_LOADED')) {
    define('AUTH_HELPER_LOADED', true);
}

/**
 * Get Bearer token from query, headers, or body.
 * For GET requests (e.g. check_session), use ?token=YOUR_TOKEN so it works in all browsers/servers.
 * @return string|null
 */
function getBearerToken() {
    // 1. Query string first – most reliable for GET (browsers, CORS, no header stripping)
    if (isset($_GET['token']) && is_string($_GET['token'])) {
        $t = trim($_GET['token']);
        if ($t !== '') return $t;
    }
    if (isset($_POST['token']) && is_string($_POST['token'])) {
        $t = trim($_POST['token']);
        if ($t !== '') return $t;
    }

    // 2. Headers (Authorization / X-Auth-Token)
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = '';
    foreach ($headers as $k => $v) {
        if (strcasecmp($k, 'Authorization') === 0 && is_string($v)) {
            $authHeader = $v;
            break;
        }
    }
    if ($authHeader === '' && !empty($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if ($authHeader !== '') {
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
            $t = trim($m[1]);
            if ($t !== '') return $t;
        }
        $t = trim($authHeader);
        if ($t !== '') return $t;
    }
    foreach (['X-Auth-Token', 'X-Authorization'] as $name) {
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $name) === 0 && is_string($v) && trim($v) !== '') {
                $v = trim($v);
                if (stripos($v, 'Bearer ') === 0) $v = trim(substr($v, 7));
                if ($v !== '') return $v;
            }
        }
    }

    // 3. JSON body (for POST)
    $input = @json_decode(file_get_contents('php://input'), true);
    if (!empty($input['token']) && is_string($input['token'])) {
        $t = trim($input['token']);
        if ($t !== '') return $t;
    }

    return null;
}

/**
 * Validate token and return user_id, or null if invalid/expired.
 * Requires $conn (PDO) to be available.
 * @param PDO $conn
 * @return array|null ['user_id' => int, 'user' => array] or null
 */
function getUserIdFromRequest($conn) {
    $token = getBearerToken();
    if (empty($token)) {
        return null;
    }
    $stmt = $conn->prepare("
        SELECT s.user_id, u.first_name, u.last_name, u.email, u.phone, u.address, u.photo, u.status 
        FROM user_sessions s 
        JOIN users u ON u.ID = s.user_id 
        WHERE s.token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['ID'] = $row['user_id'];
    return [
        'user_id' => (int) $row['user_id'],
        'user' => zc_user_profile($row),
    ];
}

/**
 * The customer object every auth endpoint returns. `photo` stays the raw
 * stored value (older app builds read it); `photo_url` is absolute or null.
 */
function zc_user_profile(array $row): array {
    return [
        'id' => (int) ($row['ID'] ?? $row['id'] ?? 0),
        'first_name' => $row['first_name'] ?? null,
        'last_name' => $row['last_name'] ?? null,
        'email' => $row['email'] ?? null,
        'phone' => $row['phone'] ?? null,
        'address' => $row['address'] ?? null,
        'photo' => $row['photo'] ?? null,
        'photo_url' => zc_photo_url($row['photo'] ?? null),
        'status' => $row['status'] ?? null,
    ];
}

/**
 * Indian mobile number normalised to 10 digits (drops +91 / leading 0), or
 * null unless it matches ^[6-9]\d{9}$. The one phone rule for login,
 * register, update_profile and password_reset.
 */
function zc_normalize_phone($raw): ?string {
    if (!is_scalar($raw)) {
        return null;
    }
    $digits = preg_replace('/\D/', '', (string) $raw);
    if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = substr($digits, 1);
    }
    return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
}

/**
 * Absolute https URL of a stored profile photo (users.photo), or null.
 * Accepts a full URL, a site-relative path or a bare legacy filename.
 */
function zc_photo_url($photo): ?string {
    require_once __DIR__ . '/runtime.php';
    $photo = trim((string) $photo);
    if ($photo === '' || stripos($photo, 'data:') === 0) {
        return null;
    }
    if (preg_match('#^https?://#i', $photo)) {
        return $photo;
    }
    $root = dirname(__DIR__);
    $photo = ltrim(str_replace('\\', '/', $photo), '/');
    if (strpos($photo, '..') !== false) {
        return null;
    }
    $candidates = strpos($photo, '/') !== false ? [$photo] : ['uploads/' . $photo, 'api/uploads/' . $photo, 'images/' . $photo];
    foreach ($candidates as $path) {
        if (is_file($root . '/' . $path)) {
            return app_url($path);
        }
    }
    return null;
}

/**
 * Fixed-window counter in api_rate_limits. Returns false when $key already
 * used $max hits in the current window. Fails open (and logs) if the table
 * is missing, so an unapplied migration never blocks sign-in.
 */
function zc_rate_limit_hit($conn, string $key, int $max, int $windowSeconds): bool {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('SELECT hits, window_started_at FROM api_rate_limits WHERE rate_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || strtotime($row['window_started_at']) + $windowSeconds <= time()) {
            $conn->prepare('INSERT INTO api_rate_limits (rate_key, hits, window_started_at) VALUES (?, 1, ?)
                ON DUPLICATE KEY UPDATE hits = 1, window_started_at = VALUES(window_started_at)')->execute([$key, $now]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        $conn->prepare('UPDATE api_rate_limits SET hits = hits + 1 WHERE rate_key = ?')->execute([$key]);
        return true;
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts rate limit] ' . $e->getMessage());
        return true;
    }
}

/** Short stable key for the caller's IP (never stores the raw address). */
function zc_client_ip_key(): string {
    return substr(hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 32);
}

/** Customer login token lifetime in seconds: LOGIN_TOKEN_DAYS (default 30, 1-365). */
function zc_login_token_lifetime(): int {
    $days = (int) ($_ENV['LOGIN_TOKEN_DAYS'] ?? $_SERVER['LOGIN_TOKEN_DAYS'] ?? getenv('LOGIN_TOKEN_DAYS') ?: 30);
    return max(1, min(365, $days)) * 86400;
}

/**
 * Require auth: if not valid, send 401 JSON and exit.
 * @param PDO $conn
 * @return array ['user_id' => int, 'user' => array]
 */
function requireAuth($conn) {
    $auth = getUserIdFromRequest($conn);
    if (!$auth) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'statusCode' => 401,
            'status' => 'error',
            'message' => 'Unauthorized or session expired. Please log in again.'
        ]);
        exit;
    }
    return $auth;
}

/**
 * Create a new session for user_id, return token.
 * @param PDO $conn
 * @param int $userId
 * @param int $lifetimeSeconds default 30 days
 * @return string token
 */
function createSession($conn, $userId, $lifetimeSeconds = 2592000) {
    $token = trim(bin2hex(random_bytes(32)));
    $expiresAt = date('Y-m-d H:i:s', time() + $lifetimeSeconds);
    $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([(int) $userId, $token, $expiresAt]);
    return $token;
}

/**
 * Invalidate session(s) for token or for user_id.
 * @param PDO $conn
 * @param string|null $token if provided, delete this session
 * @param int|null $userId if provided, delete all sessions for this user
 */
function invalidateSession($conn, $token = null, $userId = null) {
    if ($token) {
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE token = ?");
        $stmt->execute([$token]);
    }
    if ($userId !== null) {
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
}
