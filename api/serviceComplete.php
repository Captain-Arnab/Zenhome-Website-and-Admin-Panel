<?php

include 'db.php';
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('serviceComplete');

// API endpoint for OTP verification (with user ID and service ID)

// Get data from the request (assuming it's JSON data)
$data = json_decode(file_get_contents('php://input'), true);

legacy_require_user($legacy, $data['user_id'] ?? null);
legacy_require_owner($legacy, 'SELECT user_id FROM Service_booking WHERE ID = ?', [is_scalar($data['id'] ?? null) ? (int) $data['id'] : 0], 'Booking');

$enteredOtp = $data['otp'] ?? null;
$userId = $data['user_id'] ?? null;
$serviceId = $data['id'] ?? null;


// Check if any of the required parameters are missing
if ($enteredOtp === null || $userId === null || $serviceId === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing or incorrect parameters (otp, user_id, service_id)."]);
    exit;
}

try {
    // Retrieve the hashed OTP from the database (using both user ID and service ID)
    $sql = "SELECT otp FROM Service_booking WHERE user_id = :userId AND ID = :serviceId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['otp'])) {
        $hashedOtpFromDb = $result['otp'];

        // Verify the entered OTP against the stored hash
        if (password_verify($enteredOtp, $hashedOtpFromDb)) {
            // OTP is correct. Update booking status (or perform other actions)
            $updateSql = "UPDATE Service_booking SET status = 'Service Complete' WHERE user_id = ? AND id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$userId, $serviceId]);

            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Service Completed, please rate your experience."]);

        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Incorrect OTP."]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Booking not found or OTP is invalid for this user and service."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

$conn = null;

?>