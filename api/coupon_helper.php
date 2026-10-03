<?php
/**
 * Coupon rules shared by api/book_appointment.php (checkout) and
 * api/validate_coupon.php (preview). Coupons are managed in Admin > Coupons.
 *
 * A code applies when it exists, is active, today is within valid_from..valid_to
 * (APP_TIMEZONE), the booking amount meets min_order, the total usage_limit
 * is not used up and the customer has not reached per_user_limit
 * (cancelled bookings do not count). Discounts are whole rupees.
 */

if (!defined('COUPON_HELPER_LOADED')) {
    define('COUPON_HELPER_LOADED', true);
}

function coupon_today(): string
{
    $tz = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
    try {
        return (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
    } catch (Exception $e) {
        return date('Y-m-d');
    }
}

/** "save10 " -> "SAVE10", or null when the format is impossible. */
function coupon_normalize_code($code): ?string
{
    if (!is_scalar($code)) {
        return null;
    }
    $code = strtoupper(trim((string) $code));
    return preg_match('/^[A-Z0-9]{4,20}$/', $code) ? $code : null;
}

/** Discount in whole rupees for $amount (never more than $amount). */
function coupon_discount(array $coupon, float $amount): float
{
    if ($coupon['type'] === 'percentage') {
        $discount = $amount * (float) $coupon['value'] / 100;
        if ($coupon['max_discount'] !== null && (float) $coupon['max_discount'] > 0) {
            $discount = min($discount, (float) $coupon['max_discount']);
        }
    } else {
        $discount = (float) $coupon['value'];
    }
    return (float) min(round($discount), $amount);
}

/**
 * Checks a code against a booking amount.
 * @return array ['valid' => true, 'coupon' => row, 'amount', 'discount', 'final_amount']
 *            or ['valid' => false, 'message' => reason]
 */
function coupon_evaluate(PDO $conn, $rawCode, float $amount, ?int $userId = null): array
{
    $code = coupon_normalize_code($rawCode);
    if ($code === null) {
        return ['valid' => false, 'message' => 'Enter a valid coupon code.'];
    }
    if ($amount <= 0) {
        return ['valid' => false, 'message' => 'The booking amount is required to apply a coupon.'];
    }

    $stmt = $conn->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    $today = coupon_today();

    if (!$coupon || (int) $coupon['status'] !== 1) {
        return ['valid' => false, 'message' => 'This coupon code is not valid.'];
    }
    if ($coupon['valid_from'] > $today) {
        return ['valid' => false, 'message' => 'This coupon is valid from ' . date('d M Y', strtotime($coupon['valid_from'])) . '.'];
    }
    if ($coupon['valid_to'] < $today) {
        return ['valid' => false, 'message' => 'This coupon has expired.'];
    }
    if ($amount < (float) $coupon['min_order']) {
        return ['valid' => false, 'message' => 'Add services worth Rs ' . number_format((float) $coupon['min_order'], 0) . ' or more to use this coupon.'];
    }
    if ($coupon['usage_limit'] !== null && (int) $coupon['usage_limit'] > 0 && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
        return ['valid' => false, 'message' => 'This coupon has reached its usage limit.'];
    }
    if ($userId) {
        $used = $conn->prepare("SELECT COUNT(*) FROM Service_booking WHERE coupon_id = ? AND user_id = ? AND LOWER(TRIM(status)) NOT IN ('cancelled', 'canceled')");
        $used->execute([(int) $coupon['id'], $userId]);
        if ((int) $used->fetchColumn() >= max(1, (int) $coupon['per_user_limit'])) {
            return ['valid' => false, 'message' => 'You have already used this coupon.'];
        }
    }

    $discount = coupon_discount($coupon, $amount);
    return [
        'valid'        => true,
        'coupon'       => $coupon,
        'amount'       => round($amount, 2),
        'discount'     => $discount,
        'final_amount' => round($amount - $discount, 2),
    ];
}

/** Public shape of an applied coupon for API responses. */
function coupon_summary(array $result): array
{
    $c = $result['coupon'];
    return [
        'code'         => $c['code'],
        'description'  => (string) $c['description'],
        'type'         => $c['type'],
        'value'        => (float) $c['value'],
        'max_discount' => $c['max_discount'] !== null ? (float) $c['max_discount'] : null,
        'min_order'    => (float) $c['min_order'],
        'valid_to'     => $c['valid_to'],
        'amount'       => $result['amount'],
        'discount'     => $result['discount'],
        'final_amount' => $result['final_amount'],
    ];
}

/**
 * Counts one use. Atomic: fails (false) when the coupon was deactivated or
 * its usage limit was reached by a concurrent booking.
 */
function coupon_redeem(PDO $conn, int $couponId): bool
{
    $stmt = $conn->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND status = 1 AND (usage_limit IS NULL OR usage_limit = 0 OR used_count < usage_limit)');
    $stmt->execute([$couponId]);
    return $stmt->rowCount() === 1;
}

/** Gives a use back (booking cancelled). */
function coupon_release(PDO $conn, int $couponId): void
{
    $conn->prepare('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?')->execute([$couponId]);
}

/**
 * Server-side amount for app bookings that send SaverPacks ids:
 * items = [{"pack_id": 12, "quantity": 1}, ...]. Returns null when no
 * usable ids were sent (the caller then uses the "amount" it was given).
 */
function coupon_amount_from_packs(PDO $conn, $items): ?float
{
    if (!is_array($items) || !$items) {
        return null;
    }
    $qty = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (int) ($item['pack_id'] ?? $item['packId'] ?? 0);
        if ($id > 0) {
            $qty[$id] = ($qty[$id] ?? 0) + max(1, min(20, (int) ($item['quantity'] ?? 1)));
        }
    }
    if (!$qty || count($qty) > 50) {
        return null;
    }
    // Only bookable services: enabled pack in an active category.
    $stmt = $conn->prepare('SELECT sp.packId, sp.price FROM SaverPacks sp JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = sp.category_Id
        WHERE sp.status = 1 AND c.status = 1 AND sp.packId IN (' . implode(',', array_fill(0, count($qty), '?')) . ')');
    $stmt->execute(array_keys($qty));
    $total = 0.0;
    $found = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $total += (float) $row['price'] * $qty[(int) $row['packId']];
        $found++;
    }
    return $found === count($qty) ? round($total, 2) : null;
}
