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

// Get the status from the frontend using GET
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['status'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "status is missing"]);
    exit;
} // Debugging
$status = $data['status'];
// Prepare SQL query to fetch pending bookings
$sql = "SELECT * FROM service_booking WHERE status = :status";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':status', $status, PDO::PARAM_STR);

try {
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);


if ($result) { // Check if any rows were returned
        http_response_code(200); // OK
        echo json_encode([
            "status" => "success",
            "message" => "Bookings with status '$status' fetched successfully",
            "data" => $result
        ]);
    } else {
        http_response_code(200); // OK (but no data)
        echo json_encode([
            "status" => "success",
            "message" => "No bookings found with status '$status'",
            "data" => [] // Return an empty array
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

// Close connection
$conn = null;
?>