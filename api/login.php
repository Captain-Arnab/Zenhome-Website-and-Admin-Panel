<?php
// MUST be the first thing in the file, before session or DB
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

include 'db.php';
include 'auth_helper.php';
include 'sms_sender.php';

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Invalid request body."]);
    exit();
}

// ---------- Step 2: Verify OTP and complete login ----------
if (isset($data->phone) && isset($data->otp)) {
    $phone = trim(preg_replace('/[^0-9]/', '', $data->phone));
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $phone = substr($phone, 1);
    }
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
        $phone = substr($phone, 2);
    }
    if (strlen($phone) !== 10) {
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Invalid phone number."]);
        exit();
    }

    $received_otp = trim($data->otp);

    $stmt = $conn->prepare("SELECT otp, expiry FROM otp_verification WHERE phone = ? AND first_name IS NULL");
    $stmt->execute([$phone]);
    $otpRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otpRow) {
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "OTP not found or expired. Please request a new OTP."]);
        exit();
    }
    if (time() > (int) $otpRow['expiry']) {
        $conn->prepare("DELETE FROM otp_verification WHERE phone = ? AND first_name IS NULL")->execute([$phone]);
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "OTP expired. Please request a new OTP."]);
        exit();
    }
    if ($otpRow['otp'] !== $received_otp) {
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Invalid OTP. Please try again."]);
        exit();
    }

    $userStmt = $conn->prepare("SELECT ID, first_name, last_name, email, phone, address, photo, status FROM users WHERE phone = ?");
    $userStmt->execute([$phone]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(400);
        echo json_encode(["statusCode" => 400, "status" => "error", "message" => "User not found."]);
        exit();
    }

    if (in_array(strtolower(trim((string) $user['status'])), ['inactive', 'blocked', 'disabled'], true)) {
        http_response_code(403);
        echo json_encode(["statusCode" => 403, "status" => "error", "message" => "Your account has been deactivated. Please contact support."]);
        exit();
    }

    $deleteStmt = $conn->prepare("DELETE FROM otp_verification WHERE phone = ? AND first_name IS NULL");
    $deleteStmt->execute([$phone]);

    $sessionLifetime = 12 * 3600; // 12 hours
    $token = createSession($conn, (int) $user['ID'], $sessionLifetime);

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
        "message" => "Login successful.",
        "token" => $token,
        "user" => $profile,
        "expires_in_hours" => 12,
    ]);
    exit();
}

// ---------- Step 1: Phone + Password → send OTP ----------
if (empty($data->phone) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Phone and password are required."]);
    exit();
}

$phone = trim(preg_replace('/[^0-9]/', '', $data->phone));
if (strlen($phone) === 11 && $phone[0] === '0') {
    $phone = substr($phone, 1);
}
if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
    $phone = substr($phone, 2);
}
if (strlen($phone) !== 10) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Invalid phone number."]);
    exit();
}

$stmt = $conn->prepare("SELECT ID, password, status FROM users WHERE phone = ?");
$stmt->execute([$phone]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(["statusCode" => 401, "status" => "error", "message" => "Invalid phone or password."]);
    exit();
}

if (!password_verify($data->password, $user['password'])) {
    http_response_code(401);
    echo json_encode(["statusCode" => 401, "status" => "error", "message" => "Invalid phone or password."]);
    exit();
}

// Deactivated in the admin panel. Legacy status '0' is not blocked here: some
// existing, active accounts carry it.
if (in_array(strtolower(trim((string) $user['status'])), ['inactive', 'blocked', 'disabled'], true)) {
    http_response_code(403);
    echo json_encode(["statusCode" => 403, "status" => "error", "message" => "Your account has been deactivated. Please contact support."]);
    exit();
}

$otp = (string) rand(100000, 999999);
$expiry = time() + 300; // 5 minutes

$ins = $conn->prepare("
    INSERT INTO otp_verification (phone, otp, expiry, first_name, last_name, email, address, password)
    VALUES (?, ?, ?, NULL, NULL, NULL, NULL, NULL)
    ON DUPLICATE KEY UPDATE otp = VALUES(otp), expiry = VALUES(expiry),
    first_name = NULL, last_name = NULL, email = NULL, address = NULL, password = NULL
");
$ins->execute([$phone, $otp, $expiry]);

$sms = sendOtpSms($phone, $otp, 'login');
if (!$sms['success']) {
    http_response_code(500);
    echo json_encode([
        "statusCode" => 500,
        "status" => "error",
        "message" => "Failed to send OTP. Please try again.",
    ]);
    exit();
}

echo json_encode([
    "statusCode" => 200,
    "status" => "otp_sent",
    "message" => "OTP sent to your mobile number. Enter it to complete login.",
]);
