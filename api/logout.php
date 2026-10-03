<?php
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

$token = getBearerToken();
if ($token) {
    $stmt = $conn->prepare("SELECT user_id FROM user_sessions WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $conn->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$row['user_id']]);
    }
    invalidateSession($conn, $token, null);
}

echo json_encode([
    "statusCode" => 200,
    "status" => "success",
    "message" => "Logged out successfully."
]);
