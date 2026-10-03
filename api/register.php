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

$data = json_decode(file_get_contents("php://input"));

if (
    empty($data->firstname) ||
    empty($data->lastname) ||
    empty($data->email) ||
    empty($data->phone) ||
    empty($data->address) ||
    empty($data->password)
) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "status" => "error", "message" => "All fields are required."]);
    exit();
}

$first_name = trim($data->firstname);
$last_name  = trim($data->lastname);
$email      = trim($data->email);
$phone      = trim(preg_replace('/[^0-9]/', '', $data->phone));
if (strlen($phone) === 11 && $phone[0] === '0') {
    $phone = substr($phone, 1);
}
if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
    $phone = substr($phone, 2);
}
$address    = trim($data->address);
$password   = password_hash($data->password, PASSWORD_DEFAULT);

if (strlen($phone) !== 10) {
    http_response_code(400);
    echo json_encode(["statusCode" => 400, "status" => "error", "message" => "Invalid phone number."]);
    exit();
}

$check_stmt = $conn->prepare("SELECT ID FROM users WHERE email = ? OR phone = ?");
$check_stmt->execute([$email, $phone]);
if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "status" => "error",
        "message" => "User with this email or phone already exists.",
    ]);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, phone, address, password, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Active')
    ");
    $stmt->execute([$first_name, $last_name, $email, $phone, $address, $password]);
    $user_id = (int) $conn->lastInsertId();
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(400);
        echo json_encode([
            "statusCode" => 400,
            "status" => "error",
            "message" => "User with this email or phone already exists.",
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "statusCode" => 500,
            "status" => "error",
            "message" => "Registration failed. Please try again.",
        ]);
    }
    exit();
}

http_response_code(201);
echo json_encode([
    "statusCode" => 200,
    "status" => "success",
    "message" => "Registration successful. You can now log in with your phone and password.",
    "user_id" => $user_id,
]);
