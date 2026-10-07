<?php
/**
 * Check a PhonePe payment with PhonePe and record the result (website return
 * page and apps). Customer login token required; the transaction must belong
 * to that customer (404 otherwise).
 *
 * GET ?transactionId=ZC-12-38   or POST {"transactionId": "..."} (JSON or form;
 * transaction_id / merchantOrderId also accepted)
 *
 * 200 {statusCode, status:"success", message, success:true, payment_status,
 *      merchantOrderId, merchantTransactionId, transactionId, state,
 *      amount (paise), amount_rupees, paymentDetails, data:{same}}
 * The saved cart is cleared and the booking-confirmed SMS sent only when this
 * call moves the transaction to success; success is never undone.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/payment_helper.php';
require_once __DIR__ . '/legacy_access.php';

public_cors('GET, POST, OPTIONS');
public_require_method('GET', 'POST');
legacy_access('paymentConfirmation');

function confirmation_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['statusCode' => $code, 'status' => 'error', 'success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

$in = public_input();
$txnId = public_str($in, 'transactionId') ?: public_str($in, 'transaction_id') ?: public_str($in, 'merchantOrderId');
if ($txnId === '' || strlen($txnId) > 64) {
    confirmation_fail(400, 'Missing transactionId');
}

$conn = public_db();
$auth = requireAuth($conn);

$stmt = $conn->prepare('SELECT user_id, status FROM transactions WHERE transaction_id = ?');
$stmt->execute([$txnId]);
$txn = $stmt->fetch();
if (!$txn || (int) $txn['user_id'] !== (int) $auth['user_id']) {
    confirmation_fail(404, 'Transaction not found.');
}

require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
require_once __DIR__ . '/phonepe_client.php';

try {
    $statusCheckResponse = get_phonepe_client()->getOrderStatus($txnId, true);
} catch (Throwable $e) {
    error_log('[paymentConfirmation] status check failed for ' . $txnId . ': ' . $e->getMessage());
    confirmation_fail(502, 'Could not check the payment status right now. Please try again.');
}

$state = (string) $statusCheckResponse->getState();
$orderId = $statusCheckResponse->getOrderId();
$amount = $statusCheckResponse->getAmount();
$newStatus = zc_phonepe_status($state);

$t = zc_txn_transition($conn, $txnId, $newStatus);
if ($t['became_success']) {
    zc_payment_success_effects($conn, $txnId, $t['user_id']);
}

$result = [
    'payment_status'        => $t['current'] ?? $newStatus,
    'merchantOrderId'       => $txnId,
    'merchantTransactionId' => $orderId,
    'transactionId'         => $orderId,
    'state'                 => $state,
    'amount'                => $amount,
    'amount_rupees'         => is_numeric($amount) ? round((float) $amount / 100, 2) : null,
    'paymentDetails'        => $statusCheckResponse->getPaymentDetails(),
];

http_response_code(200);
echo json_encode(['statusCode' => 200, 'status' => 'success', 'message' => 'Payment status: ' . $result['payment_status'] . '.', 'success' => true]
    + $result + ['data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
