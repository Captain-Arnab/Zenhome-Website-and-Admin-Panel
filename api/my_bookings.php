<?php
/**
 * The signed-in customer's own service bookings (login required: the
 * existing customer Bearer token, "Authorization: Bearer <token>" or ?token=).
 *
 * GET [?page=1&limit=10&status=active|past]   newest first, paginated
 * GET ?id=<booking code "12-38" or numeric ID> one booking
 *
 * status (canonical): New, Pending, Assigned, Ongoing, Completed, Cancelled,
 * with a customer-friendly status_label. Other customers' bookings are never
 * returned (404).
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/admin/core/status.php';

const MY_BOOKING_LABELS = [
    'New'       => 'Booking Received',
    'Pending'   => 'Confirmed',
    'Assigned'  => 'Technician Assigned',
    'Ongoing'   => 'In Progress',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled',
];

public_cors('GET, OPTIONS');
public_require_method('GET');

$in = public_input();
$conn = public_db();
$auth = requireAuth($conn);
$userId = (int) $auth['user_id'];

$select = "SELECT b.ID, b.unique_booking_id, b.category, b.subcategories, b.date, b.Service_Slot, b.location, b.landmark,
        b.status, b.price, b.gross_amount, b.discount_amount, b.coupon_code, b.payment_method, b.payment_status,
        b.technician_name, b.technician_phone, b.created_at, b.completed_at, b.cancel_reason,
        t.status AS txn_status, t.refund_status AS txn_refund
    FROM service_booking b
    LEFT JOIN transactions t ON t.transaction_id = CONCAT('ZC-', b.unique_booking_id)";

function my_booking_out(array $b): array
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
        'status_label'   => MY_BOOKING_LABELS[$status],
        'amount'         => $amount,
        'gross_amount'   => $b['gross_amount'] !== null ? (float) $b['gross_amount'] : $amount + $discount,
        'discount'       => $discount,
        'coupon_code'    => $b['coupon_code'],
        'payment_method' => strtolower((string) $b['payment_method']) === 'online' || $b['txn_status'] !== null ? 'Online' : 'Pay after service',
        'payment_status' => $payment,
        'technician'     => $showTechnician && $b['technician_name'] ? ['name' => $b['technician_name'], 'phone' => $b['technician_phone']] : null,
        'cancel_reason'  => $status === 'Cancelled' ? $b['cancel_reason'] : null,
        'created_at'     => $b['created_at'],
        'completed_at'   => $b['completed_at'],
    ];
}

$key = public_str($in, 'id');
if ($key !== '') {
    if (!preg_match('/^\d+(-\d+)?$/', $key)) {
        public_json(422, 'Invalid booking id.', null, ['id' => 'Use the booking ID shown on your confirmation.']);
    }
    $stmt = $conn->prepare("$select WHERE b.user_id = ? AND (b.unique_booking_id = ? OR b.ID = ?) ORDER BY (b.unique_booking_id = ?) DESC LIMIT 1");
    $stmt->execute([$userId, $key, ctype_digit($key) ? (int) $key : 0, $key]);
    $row = $stmt->fetch();
    if (!$row) {
        public_json(404, 'Booking not found.');
    }
    public_json(200, 'Booking details.', my_booking_out($row));
}

[$page, $limit, $offset] = public_page_params($in, 10, 50);
$where = 'b.user_id = ?';
$params = [$userId];
$filter = strtolower(public_str($in, 'status'));
if ($filter !== '') {
    if (!in_array($filter, ['active', 'past'], true)) {
        public_json(422, 'Invalid status filter.', null, ['status' => 'Use active or past.']);
    }
    $where .= ' AND ' . booking_status_sql('b.status') . ($filter === 'past' ? '' : ' NOT') . " IN ('Completed', 'Cancelled')";
}

$count = $conn->prepare("SELECT COUNT(*) FROM service_booking b WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $conn->prepare("$select WHERE $where ORDER BY b.created_at DESC, b.ID DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);

public_json(200, 'Your bookings.', [
    'items'      => array_map('my_booking_out', $stmt->fetchAll()),
    'pagination' => public_pagination($page, $limit, $total),
]);
