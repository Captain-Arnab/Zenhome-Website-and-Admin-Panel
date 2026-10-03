<?php
// CORS – allow token via header or query (browser/Flutter)
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
$auth = $token ? getUserIdFromRequest($conn) : null;

if (!$auth) {
    http_response_code(401);
    $message = empty($token)
        ? "No token sent. For GET use: check_session.php?token=YOUR_TOKEN (use the token from login response)."
        : "Session expired or invalid. Please log in again.";
    echo json_encode([
        "statusCode" => 401,
        "status" => "error",
        "message" => $message,
        "logged_in" => false
    ]);
    exit;
}

echo json_encode([
    "statusCode" => 200,
    "status" => "success",
    "logged_in" => true,
    "user" => $auth['user'],
    "user_id" => $auth['user_id']
]);
