<?php
include 'db.php';
include 'auth_helper.php';
include 'coupon_helper.php';
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('book_appointment');

// Set content type to JSON
header('Content-Type: application/json');

/*
 * Optional fields (older apps can keep sending the original payload):
 *   amount       booking subtotal before discount (website cart total)
 *   items        [{"pack_id": 12, "quantity": 1}] - saverpacks ids; when sent,
 *                the subtotal is calculated here from saverpacks.price
 *   coupon_code  applied with the rules in coupon_helper.php
 * The response then also carries "amount", "discount", "amount_payable"
 * and "coupon". service_booking.price stores the payable amount.
 */

function booking_coupon_error(string $message, int $code = 422): void
{
    http_response_code($code);
    echo json_encode(["status" => "error", "message" => $message, "errors" => ["coupon_code" => $message]]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Check if the required fields are provided
if (!$data || !isset($data['category']) || !isset($data['service_slot']) || !isset($data['date']) || !isset($data['location']) || !isset($data['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

// Extract data from the request
$category = $data['category'];
$subcategories = $data['subcategories'] ?? null; // Now a string, not a JSON array
$date = $data['date'];
$location = $data['location'];
$landmark = isset($data['landmark']) ? $data['landmark'] : null;
$user_id = $data['user_id'];  // Get the user ID from the request
$service_slot = $data['service_slot'];
$status = "Pending Confirmation"; // Default status

legacy_require_user($legacy, $user_id);

// A login token, when sent, must belong to the same customer.
if (getBearerToken() !== null) {
    $auth = getUserIdFromRequest($conn);
    if ($auth && (int) $auth['user_id'] !== (int) $user_id) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "You can only book for your own account."]);
        exit;
    }
}

// Booking amount: saverpacks prices when pack ids are sent, else the given amount.
$amount = coupon_amount_from_packs($conn, $data['items'] ?? null);
if ($amount === null && !empty($data['items'])) {
    http_response_code(422);
    echo json_encode(["status" => "error", "message" => "Some services in your cart are no longer available. Please review your cart.", "errors" => ["items" => "Unknown or unavailable service."]]);
    exit;
}
if ($amount === null && isset($data['amount']) && is_numeric($data['amount']) && (float) $data['amount'] > 0 && (float) $data['amount'] <= 1000000) {
    $amount = round((float) $data['amount'], 2);
}

$coupon = null;
$couponCode = isset($data['coupon_code']) && is_scalar($data['coupon_code']) ? trim((string) $data['coupon_code']) : '';
if ($couponCode !== '') {
    if ($amount === null) {
        booking_coupon_error('The booking amount is required to apply a coupon.');
    }
    $coupon = coupon_evaluate($conn, $couponCode, $amount, (int) $user_id);
    if (!$coupon['valid']) {
        booking_coupon_error($coupon['message']);
    }
}

$discount = $coupon ? $coupon['discount'] : null;
$payable = $amount !== null ? round($amount - (float) $discount, 2) : null;

// Get the current timestamp for `created_at`
$created_at = date("Y-m-d H:i:s");

try {
    $conn->beginTransaction();

    if ($coupon) {
        // The UPDATE locks the coupon row, so concurrent checkouts with the
        // same code are serialised and the per-customer check below is exact.
        if (!coupon_redeem($conn, (int) $coupon['coupon']['id'])) {
            $conn->rollBack();
            booking_coupon_error('This coupon has reached its usage limit.', 409);
        }
        $used = $conn->prepare("SELECT COUNT(*) FROM service_booking WHERE coupon_id = ? AND user_id = ? AND LOWER(TRIM(status)) NOT IN ('cancelled', 'canceled')");
        $used->execute([(int) $coupon['coupon']['id'], (int) $user_id]);
        if ((int) $used->fetchColumn() >= max(1, (int) $coupon['coupon']['per_user_limit'])) {
            $conn->rollBack();
            booking_coupon_error('You have already used this coupon.', 409);
        }
    }

    // Prepare SQL query to insert data
    $sql = "INSERT INTO service_booking (category, subcategories, date, location, landmark, user_id, status, service_slot, created_at,
                                         price, gross_amount, coupon_id, coupon_code, discount_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $category, $subcategories, $date, $location, $landmark, $user_id, $status, $service_slot, $created_at,
        $payable !== null ? (int) round($payable) : null,
        $amount,
        $coupon ? (int) $coupon['coupon']['id'] : null,
        $coupon ? $coupon['coupon']['code'] : null,
        $discount,
    ]);

    // Get the unique ID of the newly inserted record
    $id = $conn->lastInsertId();

    // Generate a unique booking ID by concatenating user_id and record ID
    $unique_booking_id = $user_id . "-" . $id;

    // Update the record with the unique booking ID
    $update_sql = "UPDATE service_booking SET unique_booking_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([$unique_booking_id, $id]);

    $conn->commit();
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('[book_appointment] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error: could not save the booking. Please try again."]);
    exit;
}

// Send the response with the unique booking ID
$response = [
    "status" => "success",
    "message" => "Service booking added successfully",
    "unique_booking_id" => $unique_booking_id  // Send the unique booking ID
];
if ($amount !== null) {
    $response["amount"] = $amount;
    $response["discount"] = (float) $discount;
    $response["amount_payable"] = $payable;
}
if ($coupon) {
    $response["coupon"] = coupon_summary($coupon);
}
echo json_encode($response);

// Close connection
$conn = null;
?>
