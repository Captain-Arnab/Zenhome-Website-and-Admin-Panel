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

require __DIR__ . '/core/legacy_guard.php';
include 'db.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->userId)) {
    http_response_code(400);
    echo json_encode(["statusCode"=> "201","status" => "error", "message" => "User ID is required."]);
    exit();
}

$userId = $data->userId;

// Check if user exists
$checkQuery = "SELECT COUNT(*) AS count FROM users WHERE ID = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->execute([$userId]);
$checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($checkResult['count'] == 0) {
    echo json_encode(["statusCode"=> "201","status" => "error", "message" => "User does not exist in DB"]);
    exit;
}

// Delete the user
$deleteQuery = "DELETE FROM users WHERE ID = ?";
$deleteStmt = $conn->prepare($deleteQuery);

if ($deleteStmt->execute([$userId])) {
    http_response_code(200);
    echo json_encode(["statusCode"=> "200","status" => "success", "message" => "User deleted successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["statusCode"=> "500","status" => "error", "message" => "Failed to delete user."]);
}


$conn->close();

?>