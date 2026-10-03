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

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

// Extract data from the request
$id = $data['id'];

// Prepare SQL query to check if the category exists
$checkSql = "SELECT COUNT(*) AS count FROM service_category WHERE category_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->execute([$id]);
$checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($checkResult['count'] == 0) {
    echo json_encode(["status" => "error", "message" => "ID does not exist in DB"]);
    exit;
}

// Prepare SQL query to delete data
$sql = "DELETE FROM service_category WHERE category_id = ?";
$stmt = $conn->prepare($sql);

// Execute the query with bound parameters
if ($stmt->execute([$id])) {
    echo json_encode(["status" => "success", "message" => "Service deleted successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Error: " . $stmt->errorInfo()]);
}

// Close connection
$conn = null;
?>