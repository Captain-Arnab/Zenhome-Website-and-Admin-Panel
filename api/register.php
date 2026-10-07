<?php
/**
 * Customer sign-up. POST {firstname, lastname, email, phone, address, password}
 * (first_name / last_name are accepted too). No token is issued: the user
 * signs in through login.php afterwards. 201 on success.
 *
 * Rules: valid email, password 6-72 characters, Indian mobile ^[6-9]\d{9}$
 * (+91 / leading 0 stripped). At most 10 sign-ups per hour per IP.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';

const REGISTER_MAX_PER_HOUR = 10;

public_cors('POST, OPTIONS');
public_require_method('POST');

$in = public_input();
$first_name = public_str($in, 'firstname') ?: public_str($in, 'first_name');
$last_name = public_str($in, 'lastname') ?: public_str($in, 'last_name');
$email = public_str($in, 'email');
$address = public_str($in, 'address');
$password = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';

if ($first_name === '' || $email === '' || public_str($in, 'phone') === '' || $address === '' || $password === '') {
    public_json(400, 'All fields are required.');
}

$errors = [];
if (mb_strlen($first_name) > 100) {
    $errors['firstname'] = 'First name is too long.';
}
if (mb_strlen($last_name) > 100) {
    $errors['lastname'] = 'Last name is too long.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $errors['email'] = 'Enter a valid email address.';
}
$phone = zc_normalize_phone($in['phone']);
if ($phone === null) {
    $errors['phone'] = 'Enter a valid 10-digit mobile number.';
}
if (strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters.';
} elseif (strlen($password) > 72) {
    $errors['password'] = 'Password must be 72 characters or fewer.';
}
if ($errors) {
    public_json(400, reset($errors), null, $errors);
}

$conn = public_db();
if (!zc_rate_limit_hit($conn, 'register:ip:' . zc_client_ip_key(), REGISTER_MAX_PER_HOUR, 3600)) {
    header('Retry-After: 3600');
    public_json(429, 'Too many sign-up attempts. Please try again later.');
}

$check_stmt = $conn->prepare('SELECT ID FROM users WHERE email = ? OR phone = ?');
$check_stmt->execute([$email, $phone]);
if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
    public_json(400, 'User with this email or phone already exists.');
}

try {
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, phone, address, password, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Active')
    ");
    $stmt->execute([$first_name, $last_name, $email, $phone, $address, password_hash($password, PASSWORD_DEFAULT)]);
    $user_id = (int) $conn->lastInsertId();
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        public_json(400, 'User with this email or phone already exists.');
    }
    error_log('[ZenHomeExperts register] ' . $e->getMessage());
    public_json(500, 'Registration failed. Please try again.');
}

http_response_code(201);
echo json_encode([
    'statusCode' => 201,
    'status' => 'success',
    'message' => 'Registration successful. You can now log in with your phone and password.',
    'user_id' => $user_id,
    'data' => ['user_id' => $user_id],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
