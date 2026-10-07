<?php
/**
 * Customer rating for one of their own completed bookings (mobile app).
 * Customer login token required; a user_id in the body is ignored.
 *
 * POST JSON {unique_booking_id, rating (1-5), feedback}
 *   unique_booking_id may be the booking code ("12-38") or the numeric ID.
 * 201 {"statusCode":201,"status":"success","message":"Data saved successfully",
 *      "review_id":..,"review_status":..,"data":{review_id, review:{rating,feedback,status}}}
 *
 * Saved to ratings_feedback with status "Pending" (an admin approves it in
 * Admin > Reviews) or "Approved" when REVIEWS_AUTO_APPROVE=true in .env.
 * Rating the same booking again updates the review and re-queues it.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/booking_helper.php';

public_cors('POST, OPTIONS');
public_require_method('POST');

function rating_fail(int $code, string $message, array $errors = []): void
{
    http_response_code($code);
    $body = ['statusCode' => $code, 'status' => 'error', 'error' => $message, 'message' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$conn = public_db();
$auth = requireAuth($conn);
$user_id = (int) $auth['user_id'];

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data) || !isset($data['unique_booking_id'], $data['rating'])
    || !is_scalar($data['unique_booking_id']) || !is_scalar($data['rating']) || (isset($data['feedback']) && !is_scalar($data['feedback']))) {
    rating_fail(400, 'Missing or invalid data. Please provide unique_booking_id, rating and feedback.');
}

$booking_ref = trim((string) $data['unique_booking_id'], " \"");
$rating = is_numeric($data['rating']) ? (float) $data['rating'] : 0;
$feedback = trim(strip_tags((string) ($data['feedback'] ?? '')));

if ($booking_ref === '') {
    rating_fail(400, 'Missing or invalid data. Please provide unique_booking_id, rating and feedback.', ['unique_booking_id' => 'Required.']);
}
if ($rating < 1 || $rating > 5 || floor($rating) != $rating) {
    rating_fail(422, 'Rating must be a whole number from 1 to 5.', ['rating' => 'Use 1 to 5.']);
}
if (mb_strlen($feedback) > 2000) {
    rating_fail(422, 'Feedback can be at most 2000 characters.', ['feedback' => 'At most 2000 characters.']);
}

try {
    $stmt = $conn->prepare('SELECT ID, user_id, status FROM service_booking WHERE unique_booking_id = ? OR ID = ? ORDER BY (unique_booking_id = ?) DESC LIMIT 1');
    $stmt->execute([$booking_ref, ctype_digit($booking_ref) ? (int) $booking_ref : 0, $booking_ref]);
    $booking = $stmt->fetch();
    if (!$booking || $booking['user_id'] === null || (int) $booking['user_id'] !== $user_id) {
        // Same answer for "not yours" and "missing", so ids can't be probed.
        rating_fail(404, 'Booking not found.');
    }
    if (booking_status_canonical($booking['status']) !== 'Completed') {
        rating_fail(409, 'You can rate a booking once the service is completed.');
    }

    $autoApprove = in_array(strtolower((string) ($_ENV['REVIEWS_AUTO_APPROVE'] ?? getenv('REVIEWS_AUTO_APPROVE') ?: '')), ['1', 'true', 'yes', 'on'], true);
    $status = $autoApprove ? 'Approved' : 'Pending';
    $now = date('Y-m-d H:i:s');

    $existing = $conn->prepare('SELECT id FROM ratings_feedback WHERE user_id = ? AND unique_booking_id = ? ORDER BY id DESC LIMIT 1');
    $existing->execute([$user_id, (int) $booking['ID']]);
    $reviewId = $existing->fetchColumn();

    if ($reviewId) {
        $conn->prepare('UPDATE ratings_feedback SET rating = ?, feedback = ?, booking_ref = ?, status = ?, moderated_by = NULL, moderated_at = NULL, updated_at = ? WHERE id = ?')
            ->execute([(int) $rating, $feedback, mb_substr($booking_ref, 0, 50), $status, $now, $reviewId]);
    } else {
        $conn->prepare('INSERT INTO ratings_feedback (user_id, unique_booking_id, booking_ref, rating, feedback, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$user_id, (int) $booking['ID'], mb_substr($booking_ref, 0, 50), (int) $rating, $feedback, $status, $now]);
        $reviewId = $conn->lastInsertId();
    }
} catch (PDOException $e) {
    error_log('[rating.php] ' . $e->getMessage());
    rating_fail(500, 'Error saving data. Please try again.');
}

http_response_code(201);
echo json_encode([
    'statusCode' => 201,
    'status' => 'success',
    'message' => 'Data saved successfully',
    'review_id' => (int) $reviewId,
    'review_status' => $status,
    'data' => [
        'review_id' => (int) $reviewId,
        'review' => ['rating' => (int) $rating, 'feedback' => $feedback, 'status' => $status],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
