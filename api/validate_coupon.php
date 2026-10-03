<?php
/**
 * Coupon preview for the website/app checkout (no booking is created and
 * the coupon is not used up here; book_appointment.php applies it).
 *
 * POST JSON {coupon_code, amount}            amount = booking subtotal
 *        or {coupon_code, items: [{pack_id, quantity}]}  priced from saverpacks
 * Optional login token (Authorization: Bearer / ?token=) or user_id adds the
 * per-customer limit check.
 * 200 data: {code, description, type, value, max_discount, min_order, valid_to, amount, discount, final_amount}
 * 422 errors.coupon_code: reason (expired, minimum amount, already used ...)
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/coupon_helper.php';

public_cors('POST, OPTIONS');
public_require_method('POST');

$in = public_input();
$conn = public_db();

$code = public_str($in, 'coupon_code') ?: public_str($in, 'code');
if ($code === '') {
    public_json(422, 'Enter a coupon code.', null, ['coupon_code' => 'Enter a coupon code.']);
}

$amount = coupon_amount_from_packs($conn, $in['items'] ?? null);
if ($amount === null && !empty($in['items'])) {
    public_json(422, 'Some services in your cart are no longer available. Please review your cart.', null, ['items' => 'Unknown or unavailable service.']);
}
if ($amount === null) {
    $raw = public_str($in, 'amount');
    if (!is_numeric($raw) || (float) $raw <= 0 || (float) $raw > 1000000) {
        public_json(422, 'A valid booking amount is required.', null, ['amount' => 'Send the booking subtotal.']);
    }
    $amount = round((float) $raw, 2);
}

$userId = null;
if (getBearerToken() !== null) {
    $auth = getUserIdFromRequest($conn);
    if (!$auth) {
        public_json(401, 'Unauthorized or session expired. Please log in again.');
    }
    $userId = (int) $auth['user_id'];
} elseif (ctype_digit(public_str($in, 'user_id'))) {
    $userId = (int) public_str($in, 'user_id');
}

try {
    $result = coupon_evaluate($conn, $code, $amount, $userId);
} catch (PDOException $e) {
    error_log('[validate_coupon] ' . $e->getMessage());
    public_json(500, 'Could not check the coupon. Please try again.');
}

if (!$result['valid']) {
    public_json(422, $result['message'], null, ['coupon_code' => $result['message']]);
}
public_json(200, 'Coupon applied. You save Rs ' . number_format($result['discount'], 0) . '.', coupon_summary($result));
