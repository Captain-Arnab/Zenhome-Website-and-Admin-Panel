<?php
/**
 * The signed-in customer's own service bookings (login required: the
 * existing customer Bearer token, "Authorization: Bearer <token>" or ?token=).
 *
 * GET [?page=1&limit=10&status=active|past]   newest first, paginated
 * GET ?id=<booking code "12-38" or numeric ID> one booking
 *
 * status (canonical): New, Pending, Assigned, Ongoing, Completed, Cancelled,
 * with a customer-friendly status_label. Each item also carries the
 * customer's own review ({rating, feedback, status} or null), can_cancel and
 * can_review. Other customers' bookings are never returned (404).
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/booking_helper.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

$in = public_input();
$conn = public_db();
$auth = requireAuth($conn);
$userId = (int) $auth['user_id'];

$select = zc_booking_select_sql();

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
    public_json(200, 'Booking details.', zc_booking_item($row));
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
    'items'      => array_map('zc_booking_item', $stmt->fetchAll()),
    'pagination' => public_pagination($page, $limit, $total),
]);
