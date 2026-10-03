<?php
// MUST be the first thing in the file, before session or DB
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    http_response_code(200);
    exit();
}

// Normal CORS headers for all other requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");
include '../db.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->password)) {
    echo json_encode(["message" => "Email and password are required."]);
    exit();
}

$email = $data->email;
$password = $data->password;

$query = "SELECT * FROM admin WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Passwords are bcrypt-hashed by the new admin panel; older rows may still be
// plain text. A stored hash is never accepted as a plain-text password.
$stored = $user ? (string) $user['password'] : '';
$isHash = (bool) preg_match('/^\$2[aby]\$|^\$argon2/', $stored);
$valid = $user && is_string($password) && ($isHash ? password_verify($password, $stored) : ($stored !== '' && hash_equals($stored, $password)));

if ($valid && isset($user['status']) && (string) $user['status'] === '0') {
    echo json_encode(["message" => "This admin account is disabled."]);
} elseif ($valid) {
    // Bearer token usable with the api/admin/<module>.php endpoints (additive field).
    $token = bin2hex(random_bytes(32));
    try {
        $conn->prepare("INSERT INTO admin_sessions (admin_id, token, expires_at, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$user['id'], $token, date('Y-m-d H:i:s', time() + 30 * 86400), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), date('Y-m-d H:i:s')]);
    } catch (PDOException $e) {
        $token = null; // admin_sessions table not migrated yet
    }
    unset($user['password'], $user['reset_token'], $user['token_expiry'], $user['failed_logins'], $user['locked_until']);
    echo json_encode(["message" => "Login successful.", "user" => $user, "token" => $token]);
} else {
    echo json_encode(["message" => "Invalid email or password."]);
}
?>