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

// Get data from the request (all values below are bound, never concatenated into SQL)
$data = json_decode(file_get_contents('php://input'), true);
$scalar = fn($key) => is_array($data) && isset($data[$key]) && is_scalar($data[$key]) ? trim((string) $data[$key]) : '';
$userId = (int) $scalar('userId');
$technicianName = mb_substr($scalar('technicianName'), 0, 50);
$technicianPhone = mb_substr(preg_replace('/\D/', '', $scalar('technicianPhone')), -10);
$uniqueBookingId = generateUniqueBookingId();

/*
 * LEGACY_ASSIGN_STRICT=true in .env: assign only the booking given by
 * bookingId (service_booking.ID) or uniqueBookingId, which must belong to
 * userId; keep its booking id; send the OTP by SMS and never return it.
 * Default (false): the original behaviour below (all of the customer's
 * bookings, OTP in the response).
 */
if (env_flag('LEGACY_ASSIGN_STRICT')) {
    $bookingId = (int) $scalar('bookingId');
    $uniqueRef = mb_substr($scalar('uniqueBookingId'), 0, 50);
    if ($userId <= 0 || ($bookingId <= 0 && $uniqueRef === '')) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "userId and bookingId (or uniqueBookingId) are required."]);
        exit;
    }
    if (!preg_match('/^[6-9]\d{9}$/', $technicianPhone) || $technicianName === '') {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Enter the technician's name and a valid 10-digit mobile number."]);
        exit;
    }
    try {
        $find = $conn->prepare($bookingId > 0
            ? "SELECT ID, unique_booking_id FROM service_booking WHERE ID = ? AND user_id = ?"
            : "SELECT ID, unique_booking_id FROM service_booking WHERE unique_booking_id = ? AND user_id = ?");
        $find->execute([$bookingId > 0 ? $bookingId : $uniqueRef, $userId]);
        $booking = $find->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Booking not found for this user."]);
            exit;
        }
        $bookingRef = (string) ($booking['unique_booking_id'] ?: $uniqueBookingId);
        $otp = generateOTP();
        $conn->prepare("UPDATE service_booking SET status = 'technician_assigned', technician_name = ?, technician_phone = ?, unique_booking_id = ?, otp = ? WHERE ID = ?")
            ->execute([$technicianName, $technicianPhone, $bookingRef, password_hash($otp, PASSWORD_DEFAULT), (int) $booking['ID']]);
    } catch (PDOException $e) {
        error_log('[assignTechnician] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Could not assign the technician. Please try again."]);
        exit;
    }
    if (!function_exists('sendOtpSms')) {
        require_once __DIR__ . '/../sms_sender.php';
    }
    $sms = sendOtpSms($technicianPhone, $otp, 'service completion');
    if (empty($sms['success'])) {
        error_log('[assignTechnician] OTP SMS failed: ' . ($sms['error'] ?? 'unknown'));
    }
    echo json_encode([
        "status" => "success",
        "message" => !empty($sms['success']) ? "Technician assigned. The OTP was sent to the technician by SMS." : "Technician assigned, but the OTP SMS could not be sent. Please try again.",
        "booking_id" => $bookingRef,
        "sms_sent" => !empty($sms['success']),
    ]);
    exit;
}

// Prepare SQL query to fetch confirmed bookings
$sql = "SELECT * FROM service_booking WHERE user_id = :userId";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

try {
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($result)) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "No bookings found for this user."]);
        exit;
    }

    // Prepare the UPDATE statement
    $updateSql = "UPDATE service_booking SET status = 'technician_assigned', technician_name = ?, technician_phone = ?, unique_booking_id = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateSql);

    // Execute the UPDATE statement
    if ($updateStmt->execute([$technicianName, $technicianPhone, $uniqueBookingId, $userId])) {

        $otp = generateOTP();

        // Hash and store OTP
        $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
        $otpSql = "UPDATE service_booking SET otp = ? WHERE unique_booking_id = ?";
        $otpStmt = $conn->prepare($otpSql);
        $otpStmt->execute([$hashedOtp, $uniqueBookingId]);

        $isTestMode = true; // Set to true for testing, false for production

        if ($isTestMode) {
            echo json_encode(["status" => "success", "message" => "Technician assigned successfully with the OTP: " . $otp, "booking_id" => $uniqueBookingId]);
            exit;
        }

        // Production Logic (With SMS Gateway)
        $smsStatus = sendSMS($technicianPhone, "Your booking ID is: " . $uniqueBookingId . ". OTP for confirmation: " . $otp, $isTestMode);

        if ($smsStatus['status'] == 'success') {
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Booking assigned and OTP sent.", "booking_id" => $uniqueBookingId]);
        } else {
            error_log("SMS sending error: " . $smsStatus['message']);
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Booking assigned, but there was an issue sending the OTP. Check logs.", "booking_id" => $uniqueBookingId]);
        }

    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update bookings"]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

$conn = null;


function generateUniqueBookingId() {
    return uniqid();
}

function generateOTP($length = 6) {
    $characters = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $otp;
}

function sendSMS($phoneNumber, $message, $isTestMode) {
    if ($isTestMode) {
        return [
            'status' => 'success',
            'message' => 'SMS sent successfully (TEST MODE)',
        ];
    } else {
        $apiKey = 'YOUR_SMS_API_KEY'; // Replace with your API key
        $apiSecret = 'YOUR_SMS_API_SECRET'; // Replace with your API secret

        // ... (Your SMS API call logic here) ...

        $response =  [
            'status' => 'success', // Or 'error'
            'message' => 'SMS sent successfully', // Or error message
        ];

        return $response;
    }
}

?>