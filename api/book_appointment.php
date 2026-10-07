<?php
/**
 * Create a service booking for the signed-in customer (website checkout and
 * apps). Customer login token required; any user_id in the body is ignored.
 *
 * POST JSON {
 *   category, subcategories?, date (Y-m-d, today or later), service_slot,
 *   location, landmark?,
 *   items: [{"pack_id": 12, "quantity": 1}, ...]   required, saverpacks ids
 *   coupon_code?, payment_method?: "online" | "cash" (default cash)
 * }
 * The price is always calculated here from saverpacks.price; a client
 * "amount" is accepted but ignored. service_booking.price stores the payable
 * amount. A slot already held by another non-cancelled booking is 409.
 *
 * 200 {statusCode, status, message, id, unique_booking_id, amount, discount,
 *      amount_payable, coupon?, payment_method, data:{...same, booking}}
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/coupon_helper.php';
require __DIR__ . '/booking_helper.php';
require_once __DIR__ . '/legacy_access.php';

public_cors('POST, OPTIONS');
public_require_method('POST');
legacy_access('book_appointment');

function booking_coupon_error(string $message, int $code = 422): void
{
    public_json($code, $message, null, ['coupon_code' => $message]);
}

$conn = public_db();
$auth = requireAuth($conn);
$user_id = (int) $auth['user_id'];

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    public_json(400, 'Invalid input');
}

$str = fn(string $key): string => isset($data[$key]) && is_scalar($data[$key]) ? trim((string) $data[$key]) : '';
$category = $str('category');
$subcategories = $str('subcategories') !== '' ? $str('subcategories') : null;
$location = $str('location');
$landmark = $str('landmark') !== '' ? $str('landmark') : null;
$service_slot = $str('service_slot');
$now = zc_app_now();

$errors = [];
foreach (['category' => $category, 'service_slot' => $service_slot, 'location' => $location] as $key => $value) {
    if ($value === '') {
        $errors[$key] = 'This field is required.';
    } elseif (mb_strlen($value) > 255) {
        $errors[$key] = 'Must be 255 characters or fewer.';
    }
}
if ($landmark !== null && mb_strlen($landmark) > 255) {
    $errors['landmark'] = 'Must be 255 characters or fewer.';
}
$date = zc_valid_date($str('date'));
if ($date === null) {
    $errors['date'] = 'Use a valid date (YYYY-MM-DD).';
} elseif ($date < $now->format('Y-m-d')) {
    $errors['date'] = 'The service date cannot be in the past.';
} elseif ($service_slot !== '' && zc_slot_elapsed($date, $service_slot, $now)) {
    $errors['service_slot'] = 'This time slot has already started. Please pick a later slot.';
}

$items = $data['items'] ?? null;
if (!is_array($items) || !$items) {
    $errors['items'] = 'Add at least one service (items with pack_id).';
} else {
    foreach ($items as $item) {
        if (!is_array($item) || (int) ($item['pack_id'] ?? $item['packId'] ?? 0) <= 0) {
            $errors['items'] = 'Every item needs a valid pack_id.';
            break;
        }
    }
}
if ($errors) {
    public_json(422, reset($errors), null, $errors);
}

$amount = coupon_amount_from_packs($conn, $items);
if ($amount === null) {
    public_json(422, 'Some services in your cart are no longer available. Please review your cart.', null, ['items' => 'Unknown or unavailable service.']);
}

$coupon = null;
$couponCode = $str('coupon_code');
if ($couponCode !== '') {
    $coupon = coupon_evaluate($conn, $couponCode, $amount, $user_id);
    if (!$coupon['valid']) {
        booking_coupon_error($coupon['message']);
    }
}

$discount = $coupon ? (float) $coupon['discount'] : 0.0;
$payable = round($amount - $discount, 2);
$isOnlinePayment = strtolower($str('payment_method')) === 'online' && $payable > 0;
$status = 'Pending Confirmation';
$created_at = date('Y-m-d H:i:s');

// Serialise bookings for the same date + slot so two customers can't both get it.
$lock = 'zc_slot_' . md5($date . '|' . $service_slot);
$locked = (int) $conn->query('SELECT GET_LOCK(' . $conn->quote($lock) . ', 5)')->fetchColumn() === 1;
if (!$locked) {
    public_json(409, 'This time slot is being booked right now. Please try again or pick another slot.', null, ['service_slot' => 'Slot busy.']);
}

try {
    if (in_array($service_slot, zc_booked_slots($conn, $date), true)) {
        public_json(409, 'Sorry, this time slot was just booked. Please pick another slot.', null, ['service_slot' => 'This slot is no longer available.']);
    }

    $conn->beginTransaction();

    if ($coupon) {
        // The UPDATE locks the coupon row, so concurrent checkouts with the
        // same code are serialised and the per-customer check below is exact.
        if (!coupon_redeem($conn, (int) $coupon['coupon']['id'])) {
            $conn->rollBack();
            booking_coupon_error('This coupon has reached its usage limit.', 409);
        }
        $used = $conn->prepare("SELECT COUNT(*) FROM service_booking WHERE coupon_id = ? AND user_id = ? AND LOWER(TRIM(status)) NOT IN ('cancelled', 'canceled')");
        $used->execute([(int) $coupon['coupon']['id'], $user_id]);
        if ((int) $used->fetchColumn() >= max(1, (int) $coupon['coupon']['per_user_limit'])) {
            $conn->rollBack();
            booking_coupon_error('You have already used this coupon.', 409);
        }
    }

    $stmt = $conn->prepare("INSERT INTO service_booking (category, subcategories, date, location, landmark, user_id, status, Service_Slot, created_at,
                                         price, gross_amount, coupon_id, coupon_code, discount_amount, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $category, $subcategories, $date, $location, $landmark, $user_id, $status, $service_slot, $created_at,
        (int) round($payable),
        $amount,
        $coupon ? (int) $coupon['coupon']['id'] : null,
        $coupon ? $coupon['coupon']['code'] : null,
        $coupon ? $discount : null,
        $isOnlinePayment ? 'Online' : 'Cash',
    ]);
    $id = (int) $conn->lastInsertId();
    $unique_booking_id = $user_id . '-' . $id;
    $conn->prepare('UPDATE service_booking SET unique_booking_id = ? WHERE ID = ?')->execute([$unique_booking_id, $id]);

    $conn->commit();
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('[book_appointment] ' . $e->getMessage());
    public_json(500, 'Error: could not save the booking. Please try again.');
} finally {
    $conn->query('SELECT RELEASE_LOCK(' . $conn->quote($lock) . ')');
}

// Pay-after-service booking (or nothing left to pay): confirm now. Online
// bookings get their SMS on payment success (paymentConfirmation.php / the
// webhook). An SMS failure must never fail or delay this response.
if (!$isOnlinePayment) {
    try {
        require_once __DIR__ . '/admin/core/bootstrap.php';
        notify_booking_confirmed($id);
    } catch (Throwable $e) {
        error_log('[book_appointment] notify failed: ' . $e->getMessage());
    }
}

$stmt = $conn->prepare(zc_booking_select_sql() . ' WHERE b.ID = ?');
$stmt->execute([$id]);
$booking = zc_booking_item($stmt->fetch(PDO::FETCH_ASSOC));

$result = [
    'id' => $id,
    'unique_booking_id' => $unique_booking_id,
    'amount' => $amount,
    'discount' => $discount,
    'amount_payable' => $payable,
    'payment_method' => $isOnlinePayment ? 'online' : 'cash',
];
if ($coupon) {
    $result['coupon'] = coupon_summary($coupon);
}

http_response_code(200);
echo json_encode(['statusCode' => 200, 'status' => 'success', 'message' => 'Service booking added successfully']
    + $result + ['data' => $result + ['booking' => $booking]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
