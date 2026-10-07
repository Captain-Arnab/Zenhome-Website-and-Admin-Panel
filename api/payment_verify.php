<?php
/**
 * Read-only PhonePe status check for one of the customer's own transactions.
 * Customer login token required (404 for someone else's transaction).
 * Does not change the database: paymentConfirmation.php records the result.
 *
 * GET ?transaction_id=...  or POST {"transaction_id": "..."} (transactionId also accepted)
 * 200 {statusCode, message, status: success|failed|pending (kept for older apps),
 *      payment_status, state, data:{transaction_id, merchantOrderId, amount (rupees),
 *      amount_rupees, payment_details}}
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/payment_helper.php';

public_cors('GET, POST, OPTIONS');
public_require_method('GET', 'POST');

$in = public_input();
$transactionId = public_str($in, 'transaction_id') ?: public_str($in, 'transactionId');
if ($transactionId === '' || strlen($transactionId) > 64) {
    public_json(400, 'Transaction ID is required', null, ['transaction_id' => 'Required.']);
}

$conn = public_db();
$auth = requireAuth($conn);
$stmt = $conn->prepare('SELECT user_id FROM transactions WHERE transaction_id = ?');
$stmt->execute([$transactionId]);
$owner = $stmt->fetchColumn();
if ($owner === false || (int) $owner !== (int) $auth['user_id']) {
    public_json(404, 'Transaction not found.');
}

require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
require_once __DIR__ . '/phonepe_client.php';

try {
    $statusCheckResponse = get_phonepe_client()->getOrderStatus($transactionId, true);
} catch (Throwable $e) {
    error_log('[payment_verify] status check failed for ' . $transactionId . ': ' . $e->getMessage());
    public_json(502, 'Could not check the payment status right now. Please try again.');
}

$state = (string) $statusCheckResponse->getState();
$status = zc_phonepe_status($state);
$amount = $statusCheckResponse->getAmount();
$amountRupees = is_numeric($amount) ? round((float) $amount / 100, 2) : 0;

http_response_code(200);
echo json_encode([
    'statusCode'     => 200,
    'status'         => $status,
    'message'        => 'Payment status: ' . $status . '.',
    'payment_status' => $status,
    'state'          => $state,
    'data'           => [
        'transaction_id'  => $statusCheckResponse->getOrderId() ?? $transactionId,
        'merchantOrderId' => $transactionId,
        'amount'          => $amountRupees,
        'amount_rupees'   => $amountRupees,
        'payment_details' => $statusCheckResponse->getPaymentDetails(),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
