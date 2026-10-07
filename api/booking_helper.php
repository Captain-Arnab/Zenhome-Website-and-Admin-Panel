<?php
/**
 * Shared customer-booking rules for book_appointment.php, checkSlot.php,
 * my_bookings.php and cancel_booking.php: time slots, the app clock and the
 * booking object returned to the website / app.
 */
require_once __DIR__ . '/admin/core/status.php';

/** Slots offered by checkSlot.php (one booking per slot per date). */
const ZC_BOOKING_SLOTS = [
    '10:00 AM - 11:00 AM',
    '11:00 AM - 12:00 PM',
    '12:00 PM - 01:00 PM',
    '01:00 PM - 02:00 PM',
    '02:00 PM - 03:00 PM',
    '03:00 PM - 04:00 PM',
    '04:00 PM - 05:00 PM',
    '05:00 PM - 06:00 PM',
];

const ZC_BOOKING_LABELS = [
    'New'       => 'Booking Received',
    'Pending'   => 'Confirmed',
    'Assigned'  => 'Technician Assigned',
    'Ongoing'   => 'In Progress',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled',
];

/** Canonical statuses a customer may still cancel from. */
const ZC_BOOKING_CANCELLABLE = ['New', 'Pending', 'Assigned'];

function zc_app_now(): DateTimeImmutable
{
    $tz = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
    try {
        return new DateTimeImmutable('now', new DateTimeZone($tz));
    } catch (Throwable $e) {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    }
}

/** Y-m-d string that is a real calendar date, else null. */
function zc_valid_date($value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y) ? $value : null;
}

/** True when the slot's start time ("10:00 AM - 11:00 AM") on $date has passed. */
function zc_slot_elapsed(string $date, string $slot, DateTimeImmutable $now): bool
{
    if ($date !== $now->format('Y-m-d')) {
        return $date < $now->format('Y-m-d');
    }
    $start = trim(explode('-', $slot)[0]);
    $at = DateTimeImmutable::createFromFormat('Y-m-d h:i A', $date . ' ' . strtoupper($start), $now->getTimezone());
    return $at !== false && $at <= $now;
}

/** Slot labels already held by a non-cancelled booking on $date. */
function zc_booked_slots(PDO $conn, string $date): array
{
    $stmt = $conn->prepare("SELECT DISTINCT Service_Slot FROM service_booking
        WHERE date = ? AND Service_Slot IS NOT NULL AND LOWER(TRIM(status)) NOT IN ('cancelled', 'canceled')");
    $stmt->execute([$date]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** SELECT for the customer booking object (append WHERE / ORDER). */
function zc_booking_select_sql(): string
{
    return "SELECT b.ID, b.unique_booking_id, b.category, b.subcategories, b.date, b.Service_Slot, b.location, b.landmark,
            b.status, b.price, b.gross_amount, b.discount_amount, b.coupon_code, b.payment_method, b.payment_status,
            b.technician_name, b.technician_phone, b.created_at, b.completed_at, b.cancel_reason, b.cancelled_at,
            t.status AS txn_status, t.refund_status AS txn_refund,
            r.rating AS review_rating, r.feedback AS review_feedback, r.status AS review_status
        FROM service_booking b
        LEFT JOIN transactions t ON t.transaction_id = CONCAT('ZC-', b.unique_booking_id)
        LEFT JOIN ratings_feedback r ON r.id = (
            SELECT MAX(r2.id) FROM ratings_feedback r2 WHERE r2.unique_booking_id = b.ID AND r2.user_id = b.user_id
        )";
}

/** One booking as returned by my_bookings.php / cancel_booking.php. */
function zc_booking_item(array $b): array
{
    $status = booking_status_canonical($b['status']);
    if ($b['txn_refund'] === 'Processed' || strtolower((string) $b['payment_status']) === 'refunded') {
        $payment = 'Refunded';
    } elseif ($b['txn_status'] !== null) {
        $payment = ['success' => 'Paid', 'failed' => 'Failed'][$b['txn_status']] ?? 'Pending';
    } else {
        $payment = strtolower((string) $b['payment_status']) === 'paid' ? 'Paid' : 'Pending';
    }
    $showTechnician = in_array($status, ['Assigned', 'Ongoing'], true);
    $amount = (float) ($b['price'] ?? 0);
    $discount = (float) ($b['discount_amount'] ?? 0);

    return [
        'id'             => (int) $b['ID'],
        'booking_id'     => $b['unique_booking_id'] !== '' ? $b['unique_booking_id'] : (string) $b['ID'],
        'category'       => (string) $b['category'],
        'services'       => (string) ($b['subcategories'] ?? ''),
        'date'           => $b['date'],
        'slot'           => (string) ($b['Service_Slot'] ?? ''),
        'address'        => (string) $b['location'],
        'landmark'       => (string) ($b['landmark'] ?? ''),
        'status'         => $status,
        'status_label'   => ZC_BOOKING_LABELS[$status],
        'amount'         => $amount,
        'gross_amount'   => $b['gross_amount'] !== null ? (float) $b['gross_amount'] : $amount + $discount,
        'discount'       => $discount,
        'coupon_code'    => $b['coupon_code'],
        'payment_method' => strtolower((string) $b['payment_method']) === 'online' || $b['txn_status'] !== null ? 'Online' : 'Pay after service',
        'payment_status' => $payment,
        'refund_status'  => $b['txn_refund'] ?? null,
        'technician'     => $showTechnician && $b['technician_name'] ? ['name' => $b['technician_name'], 'phone' => $b['technician_phone']] : null,
        'cancel_reason'  => $status === 'Cancelled' ? $b['cancel_reason'] : null,
        'can_cancel'     => in_array($status, ZC_BOOKING_CANCELLABLE, true),
        'can_review'     => $status === 'Completed',
        'review'         => $b['review_rating'] !== null
            ? ['rating' => (int) $b['review_rating'], 'feedback' => (string) ($b['review_feedback'] ?? ''), 'status' => (string) $b['review_status']]
            : null,
        'created_at'     => $b['created_at'],
        'completed_at'   => $b['completed_at'],
        'cancelled_at'   => $b['cancelled_at'] ?? null,
    ];
}
