<?php
/**
 * Customer cancels one of their own bookings (apps / website).
 * Customer login token required.
 *
 * POST {"id": "<unique_booking_id, e.g. 12-38>"}   (numeric booking ID also accepted)
 * Allowed while the booking is New, Pending or Assigned; otherwise 409.
 * Same effect as Admin > Bookings > Cancel (bookings_cancel): status
 * Cancelled, cancel_reason "Cancelled by customer", cancelled_at, the coupon
 * use is released, a history row is written, and a paid online booking is
 * marked refund_status "Refund Pending" for an admin to refund.
 *
 * 200 {statusCode, status, message, data: <booking, same shape as my_bookings>}
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/booking_helper.php';

const CUSTOMER_CANCEL_REASON = 'Cancelled by customer';

public_cors('POST, OPTIONS');
public_require_method('POST');

$conn = public_db();
$auth = requireAuth($conn);
$userId = (int) $auth['user_id'];

$in = public_input();
$key = public_str($in, 'id') ?: public_str($in, 'unique_booking_id');
if (!preg_match('/^\d+(-\d+)?$/', $key)) {
    public_json(422, 'Invalid booking id.', null, ['id' => 'Use the booking ID shown on your confirmation.']);
}

$stmt = $conn->prepare('SELECT ID, status FROM service_booking WHERE user_id = ? AND (unique_booking_id = ? OR ID = ?) ORDER BY (unique_booking_id = ?) DESC LIMIT 1');
$stmt->execute([$userId, $key, ctype_digit($key) ? (int) $key : 0, $key]);
$own = $stmt->fetch();
if (!$own) {
    public_json(404, 'Booking not found.');
}
$bookingId = (int) $own['ID'];
$canonical = booking_status_canonical($own['status']);
if (!in_array($canonical, ZC_BOOKING_CANCELLABLE, true)) {
    public_json(409, $canonical === 'Cancelled'
        ? 'This booking is already cancelled.'
        : 'This booking can no longer be cancelled online. Please contact support.');
}

require_once __DIR__ . '/admin/core/bootstrap.php';
require_once __DIR__ . '/admin/modules/bookings.php';

$actor = ['id' => null, 'name' => 'Customer'];
$booking = booking_find($bookingId);
$refundQueued = false;
$pdo = db();
$pdo->beginTransaction();
try {
    // Conditional on the status we checked, so a concurrent admin change wins.
    $changed = q(
        'UPDATE service_booking SET status = ?, cancel_reason = ?, cancelled_at = ?, updated_at = ? WHERE ID = ? AND status = ?',
        [booking_status_raw('Cancelled'), CUSTOMER_CANCEL_REASON, now(), now(), $bookingId, $own['status']]
    )->rowCount();
    if ($changed !== 1) {
        $pdo->rollBack();
        public_json(409, 'This booking was just updated. Please refresh and try again.');
    }
    if ($booking['coupon_id']) {
        q('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [$booking['coupon_id']]);
    }
    if ($booking['transaction_id'] && $booking['transaction_status'] === 'success' && !$booking['refund_status']) {
        q(
            "UPDATE transactions SET refund_status = 'Refund Pending', refund_amount = amount, refund_note = ? WHERE transaction_id = ?",
            ['Booking cancelled: ' . CUSTOMER_CANCEL_REASON, $booking['transaction_id']]
        );
        $refundQueued = true;
    }
    $historyId = booking_log_history($bookingId, 'cancelled', $booking['status'], 'Cancelled', $booking['professional_id'],
        CUSTOMER_CANCEL_REASON . ($refundQueued ? ' (refund queued)' : ''), $actor);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[cancel_booking] ' . $e->getMessage());
    public_json(500, 'Could not cancel the booking. Please try again.');
}

try {
    booking_notify($historyId, booking_find($bookingId), 'cancellation', true, ['reason' => CUSTOMER_CANCEL_REASON], $actor);
} catch (Throwable $e) {
    error_log('[cancel_booking] notify failed: ' . $e->getMessage());
}

$stmt = $conn->prepare(zc_booking_select_sql() . ' WHERE b.ID = ?');
$stmt->execute([$bookingId]);
public_json(200, 'Booking cancelled.' . ($refundQueued ? ' Your refund will be processed shortly.' : ''), zc_booking_item($stmt->fetch()));
