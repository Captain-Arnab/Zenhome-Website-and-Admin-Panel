<?php
header('Content-Type: application/json');
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('fetch-transaction');
$legacyInput = json_decode(file_get_contents('php://input'), true);
$legacyTxn = (is_array($legacyInput) ? ($legacyInput['transaction_id'] ?? null) : null) ?? $_GET['transaction_id'] ?? '';
legacy_require_owner($legacy, 'SELECT user_id FROM transactions WHERE transaction_id = ?', [is_scalar($legacyTxn) ? (string) $legacyTxn : ''], 'Transaction', true);
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

require_once __DIR__ . '/phonepe_client.php';

try {
    // Get transaction ID from request
    $input = json_decode(file_get_contents('php://input'), true);
    $transactionId = $input['transaction_id'] ?? $_GET['transaction_id'] ?? null;

    if (empty($transactionId)) {
        throw new Exception("Transaction ID is required");
    }

    // Fetch order status (v2 SDK)
    $client = get_phonepe_client();
    $statusResponse = $client->getOrderStatus($transactionId, true);

    $state = strtoupper((string) $statusResponse->getState());
    $paymentDetails = $statusResponse->getPaymentDetails();
    $firstDetail = is_array($paymentDetails) ? ($paymentDetails[0] ?? null) : null;

    // Standardize response format (same shape as the old v1 response)
    $data = [
        'transaction_id' => $transactionId,
        'amount'        => ((float) $statusResponse->getAmount()) / 100, // paise to rupees
        'status'        => mapStatus($state),
        'payment_mode'  => is_object($firstDetail) && method_exists($firstDetail, 'getPaymentMode') ? $firstDetail->getPaymentMode() : null,
        'timestamp'     => date('c'),
        'user_reference' => null,
        'raw_response'  => $statusResponse->jsonSerialize(),
    ];

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (\PhonePe\common\exceptions\PhonePeException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper function for status mapping (same as the working v2 status flow in paymentConfirmation.php)
function mapStatus($state) {
    if ($state === 'SUCCESS' || $state === 'COMPLETED') return 'success';
    if ($state === 'FAILED') return 'failed';
    return 'pending';
}
