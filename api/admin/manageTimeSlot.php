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

// Get data from the request (replace with your actual method)
$data = json_decode(file_get_contents('php://input'), true);
$userId = is_array($data) && isset($data['userId']) && is_scalar($data['userId']) ? (string) $data['userId'] : '';
$newSlot = is_array($data) && isset($data['newSlot']) && is_scalar($data['newSlot']) ? trim((string) $data['newSlot']) : '';

try {
    // Bookings with a technician assigned (prepared statements only)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM Service_booking WHERE user_id = ? AND status = 'technician_assigned'");
    $stmt->execute([$userId]);

    if ((int) $stmt->fetchColumn() === 0) {
        http_response_code(404); // Not Found
        echo json_encode(["status" => "error", "message" => "No bookings found for the user with technician assigned"]);
        exit;
    }

    $update = $conn->prepare("UPDATE Service_booking SET service_slot = ? WHERE user_id = ? AND status = 'technician_assigned'");
    $update->execute([mb_substr($newSlot, 0, 255), $userId]);

    http_response_code(200); // OK
    echo json_encode(["status" => "success", "message" => "Booking slot updated successfully"]);
} catch (PDOException $e) {
    // Handle update failure
    error_log('[manageTimeSlot] ' . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Failed to update booking slot: database error"]);
}

// Close connection
$conn = null;

?>
