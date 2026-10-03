<?php
/**
 * Customer rating for a booking (mobile app).
 *
 * POST JSON {user_id, unique_booking_id, rating (1-5), feedback}
 *   unique_booking_id may be the booking code ("12-38", "SERVICY-00038")
 *   or the numeric booking ID.
 * 201 {"message":"Data saved successfully", ...}   (same as before, plus review_id/status)
 *
 * Saved to ratings_feedback with status "Pending" (an admin approves it in
 * Admin > Reviews) or "Approved" when REVIEWS_AUTO_APPROVE=true in .env.
 * Rating the same booking again updates the review and re-queues it.
 */
include 'db.php';
include 'auth_helper.php';

// Set content type to JSON
header('Content-Type: application/json');

function rating_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(["status" => "error", "error" => $message, "message" => $message]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) || !isset($data['user_id'], $data['unique_booking_id'], $data['rating'], $data['feedback'])
    || !is_scalar($data['user_id']) || !is_scalar($data['unique_booking_id']) || !is_scalar($data['rating']) || !is_scalar($data['feedback'])) {
    // Handle missing or invalid data (e.g., send an error response)
    http_response_code(400); // Bad Request
    echo json_encode(array("error" => "Missing or invalid data.  Please provide user_id, booking_id, rating, and feedback."));
    exit; // Stop further execution
}

if (!isset($conn) || !$conn instanceof PDO) {
    rating_fail(500, "Error saving data: database connection failed.");
}

$user_id = (int) $data['user_id'];
$booking_ref = trim((string) $data['unique_booking_id'], " \"");
$rating = is_numeric($data['rating']) ? (float) $data['rating'] : 0;
$feedback = trim(strip_tags((string) $data['feedback']));

if ($user_id <= 0 || $booking_ref === '') {
    rating_fail(400, "Missing or invalid data.  Please provide user_id, booking_id, rating, and feedback.");
}
if ($rating < 1 || $rating > 5 || floor($rating) != $rating) {
    rating_fail(422, "Rating must be a whole number from 1 to 5.");
}
if (mb_strlen($feedback) > 2000) {
    rating_fail(422, "Feedback can be at most 2000 characters.");
}

// When the app sends its login token, it must belong to the same customer.
if (getBearerToken() !== null) {
    $auth = getUserIdFromRequest($conn);
    if (!$auth) {
        rating_fail(401, "Unauthorized or session expired. Please log in again.");
    }
    if ($auth['user_id'] !== $user_id) {
        rating_fail(403, "You can only rate your own bookings.");
    }
}

try {
    $stmt = $conn->prepare("SELECT ID, user_id FROM Service_booking WHERE unique_booking_id = ? OR ID = ? ORDER BY (unique_booking_id = ?) DESC LIMIT 1");
    $stmt->execute([$booking_ref, ctype_digit($booking_ref) ? (int) $booking_ref : 0, $booking_ref]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        rating_fail(404, "Booking not found.");
    }
    if ($booking['user_id'] !== null && (int) $booking['user_id'] !== $user_id) {
        rating_fail(403, "You can only rate your own bookings.");
    }

    $autoApprove = in_array(strtolower((string) ($_ENV['REVIEWS_AUTO_APPROVE'] ?? getenv('REVIEWS_AUTO_APPROVE') ?: '')), ['1', 'true', 'yes', 'on'], true);
    $status = $autoApprove ? 'Approved' : 'Pending';
    $now = date('Y-m-d H:i:s');

    $existing = $conn->prepare("SELECT id FROM ratings_feedback WHERE user_id = ? AND unique_booking_id = ? ORDER BY id DESC LIMIT 1");
    $existing->execute([$user_id, (int) $booking['ID']]);
    $reviewId = $existing->fetchColumn();

    if ($reviewId) {
        $conn->prepare("UPDATE ratings_feedback SET rating = ?, feedback = ?, booking_ref = ?, status = ?, moderated_by = NULL, moderated_at = NULL, updated_at = ? WHERE id = ?")
            ->execute([(int) $rating, $feedback, mb_substr($booking_ref, 0, 50), $status, $now, $reviewId]);
    } else {
        $conn->prepare("INSERT INTO ratings_feedback (user_id, unique_booking_id, booking_ref, rating, feedback, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$user_id, (int) $booking['ID'], mb_substr($booking_ref, 0, 50), (int) $rating, $feedback, $status, $now]);
        $reviewId = $conn->lastInsertId();
    }
} catch (PDOException $e) {
    error_log('[rating.php] ' . $e->getMessage());
    rating_fail(500, "Error saving data. Please try again.");
}

// Successful insertion
http_response_code(201); // Created
echo json_encode(array(
    "message" => "Data saved successfully",
    "status" => "success",
    "review_id" => (int) $reviewId,
    "review_status" => $status,
));

$conn = null;
?>
