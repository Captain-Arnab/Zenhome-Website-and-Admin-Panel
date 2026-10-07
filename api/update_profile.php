<?php
/**
 * Update the signed-in customer's profile. POST (PUT/PATCH kept for older
 * app builds) with a JSON object of any of:
 *   first_name, last_name, email, phone, address, photo,
 *   password | new_password (+ current_password)
 * first_name, email and phone cannot be blanked. A password change signs out
 * every other session of this user (the current token stays valid).
 * Returns the profile under `user` (and `data`).
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';

public_cors('POST, PUT, PATCH, OPTIONS');
public_require_method('POST', 'PUT', 'PATCH');

$conn = public_db();
$auth = requireAuth($conn);
$user_id = $auth['user_id'];

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data) || empty($data)) {
    public_json(400, 'No fields to update. Send at least one of: first_name, last_name, email, phone, address, photo, or password (with current_password).');
}
unset($data['token']);

$updates = [];
$params = [];

// Optional: password change (requires current_password + new password)
if (isset($data['password']) || isset($data['new_password'])) {
    $current = $data['current_password'] ?? null;
    $new_pass = $data['password'] ?? $data['new_password'] ?? null;
    if (empty($current) || empty($new_pass) || !is_string($current) || !is_string($new_pass)) {
        public_json(400, 'To change password, send current_password and password (or new_password).');
    }
    if (strlen($new_pass) < 6 || strlen($new_pass) > 72) {
        public_json(400, 'Password must be 6 to 72 characters.', null, ['password' => 'Password must be 6 to 72 characters.']);
    }
    $stmt = $conn->prepare('SELECT password FROM users WHERE ID = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($current, (string) $row['password'])) {
        public_json(400, 'Current password is incorrect.', null, ['current_password' => 'Current password is incorrect.']);
    }
    $hashedPassword = password_hash($new_pass, PASSWORD_DEFAULT);
}

$required = ['first_name' => 'First name', 'email' => 'Email', 'phone' => 'Phone number'];
foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'photo'] as $field) {
    if (!array_key_exists($field, $data) || $data[$field] === null) {
        continue;
    }
    if (!is_scalar($data[$field])) {
        public_json(400, "Invalid value for {$field}.", null, [$field => 'Must be text.']);
    }
    $val = trim((string) $data[$field]);
    if ($val === '' && isset($required[$field])) {
        public_json(400, "{$required[$field]} cannot be empty.", null, [$field => "{$required[$field]} cannot be empty."]);
    }
    if ($field === 'email') {
        if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
            public_json(400, 'Invalid email address.', null, ['email' => 'Invalid email address.']);
        }
        $check = $conn->prepare('SELECT ID FROM users WHERE email = ? AND ID != ?');
        $check->execute([$val, $user_id]);
        if ($check->fetch()) {
            public_json(400, 'This email is already registered.', null, ['email' => 'This email is already registered.']);
        }
    }
    if ($field === 'phone') {
        $val = zc_normalize_phone($val);
        if ($val === null) {
            public_json(400, 'Invalid phone number.', null, ['phone' => 'Enter a valid 10-digit mobile number.']);
        }
        $check = $conn->prepare('SELECT ID FROM users WHERE phone = ? AND ID != ?');
        $check->execute([$val, $user_id]);
        if ($check->fetch()) {
            public_json(400, 'This phone number is already registered.', null, ['phone' => 'This phone number is already registered.']);
        }
    }
    $updates[] = "`$field` = ?";
    $params[] = $val;
}

if (isset($hashedPassword)) {
    $updates[] = '`password` = ?';
    $params[] = $hashedPassword;
}

if (empty($updates)) {
    public_json(400, 'No valid fields to update.');
}

$params[] = $user_id;
$conn->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE ID = ?')->execute($params);

if (isset($hashedPassword)) {
    $conn->prepare('DELETE FROM user_sessions WHERE user_id = ? AND token <> ?')->execute([$user_id, (string) getBearerToken()]);
}

$stmt = $conn->prepare('SELECT ID, first_name, last_name, email, phone, address, photo, status FROM users WHERE ID = ?');
$stmt->execute([$user_id]);
$profile = zc_user_profile($stmt->fetch(PDO::FETCH_ASSOC));

http_response_code(200);
echo json_encode([
    'statusCode' => 200,
    'status' => 'success',
    'message' => 'Profile updated successfully.',
    'user' => $profile,
    'data' => $profile,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
