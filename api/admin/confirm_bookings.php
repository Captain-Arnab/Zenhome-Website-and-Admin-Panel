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
include 'db.php'; // Include your database connection file

// Set content type to JSON
header('Content-Type: application/json');

// Get user ID from request body (assuming JSON input)
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "User ID is missing"]);
    exit;
}

$userId = $data['user_id'];

// Prepare SQL query to update booking status
$sql = "UPDATE Service_booking SET status = 'booking confirmed' WHERE user_id = :user_id AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT); 

try {
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        echo json_encode(["status" => "success", "message" => "Booking confirmed successfully"]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(["status" => "error", "message" => "No pending bookings found for this user"]);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

// Close connection
$conn = null;
?>