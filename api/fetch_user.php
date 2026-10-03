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

$auth = getUserIdFromRequest($conn);
if (!$auth) {
    http_response_code(401);
    echo json_encode([
        "statusCode" => 401,
        "status" => "error",
        "message" => "Unauthorized or session expired. Please log in again.",
    ]);
    exit;
}

// Return full profile for the logged-in user (same as auth, but explicit profile endpoint)
echo json_encode([
    "statusCode" => 200,
    "status" => "success",
    "data" => $auth['user'],
]);
