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
    return [
        'user_id' => (int) $row['user_id'],
        'user' => [
            'id' => (int) $row['user_id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'photo' => $row['photo'],
            'status' => $row['status'],
        ]
    ];
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
