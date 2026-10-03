<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS");
    http_response_code(200);
    exit();
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS");
header("Content-Type: application/json");

include 'db.php';
include 'auth_helper.php';

$auth = requireAuth($conn);
$user_id = $auth['user_id'];

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "status" => "error",
        "message" => "No fields to update. Send at least one of: first_name, last_name, email, phone, address, photo, or password (with current_password).",
    ]);
    exit;
}

// Optional: password change (requires current_password + new password)
if (isset($data['password']) || isset($data['new_password'])) {
    $current = $data['current_password'] ?? null;
    $new_pass = $data['password'] ?? $data['new_password'] ?? null;
    if (empty($current) || empty($new_pass)) {
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "status" => "error",
            "message" => "To change password, send current_password and password (or new_password).",
        ]);
        exit;
    }
    $stmt = $conn->prepare("SELECT password FROM users WHERE ID = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($current, $row['password'])) {
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "status" => "error",
            "message" => "Current password is incorrect.",
        ]);
        exit;
    }
    $data['_hashed_password'] = password_hash($new_pass, PASSWORD_DEFAULT);
}

$allowed = ['first_name', 'last_name', 'email', 'phone', 'address', 'photo'];
$updates = [];
$params = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $data) || $data[$field] === null) {
        continue;
    }
    $val = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
    if ($field === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Invalid email address."]);
        exit;
    }
    if ($field === 'phone' && $val !== '') {
        $val = preg_replace('/[^0-9]/', '', (string) $val);
        if (strlen($val) === 11 && $val[0] === '0') {
            $val = substr($val, 1);
        }
        if (strlen($val) === 12 && substr($val, 0, 2) === '91') {
            $val = substr($val, 2);
        }
        if (strlen($val) !== 10) {
            http_response_code(400);
            echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Invalid phone number."]);
            exit;
        }
    }
    $updates[] = "`$field` = ?";
    $params[] = $val;
}

if (isset($data['_hashed_password'])) {
    $updates[] = "`password` = ?";
    $params[] = $data['_hashed_password'];
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "status" => "error",
        "message" => "No valid fields to update.",
    ]);
    exit;
}

// Uniqueness: if updating email or phone, ensure not taken by another user
if (array_key_exists('email', $data) && trim($data['email']) !== '') {
    $check = $conn->prepare("SELECT ID FROM users WHERE email = ? AND ID != ?");
    $check->execute([trim($data['email']), $user_id]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "This email is already registered."]);
        exit;
    }
}
if (array_key_exists('phone', $data) && trim($data['phone']) !== '') {
    $phone = preg_replace('/[^0-9]/', '', $data['phone']);
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $phone = substr($phone, 1);
    }
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
        $phone = substr($phone, 2);
    }
    $check = $conn->prepare("SELECT ID FROM users WHERE phone = ? AND ID != ?");
    $check->execute([$phone, $user_id]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "This phone number is already registered."]);
        exit;
    }
}

$params[] = $user_id;
$sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->execute($params);

// Return updated profile (same shape as fetch_user / login)
$stmt = $conn->prepare("SELECT ID, first_name, last_name, email, phone, address, photo, status FROM users WHERE ID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profile = [
    "id" => (int) $user['ID'],
    "first_name" => $user['first_name'],
    "last_name" => $user['last_name'],
    "email" => $user['email'],
    "phone" => $user['phone'],
    "address" => $user['address'],
    "photo" => $user['photo'],
    "status" => $user['status'],
];

echo json_encode([
    "statusCode" => 200,
    "status" => "success",
    "message" => "Profile updated successfully.",
    "user" => $profile,
]);
