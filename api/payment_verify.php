<?php
// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, x-auth-token, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    http_response_code(200);
    exit();
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, x-auth-token, X-Requested-With");
header("Content-Type: application/json");

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
include __DIR__ . '/phonepe_client.php';

$transactionId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $transactionId = $input['transaction_id'] ?? $input['transactionId'] ?? null;
} else {
    $transactionId = $_GET['transaction_id'] ?? $_GET['transactionId'] ?? null;
}

if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Transaction ID is required']);
    exit;
}

try {
    $client = get_phonepe_client();
    $statusCheckResponse = $client->getOrderStatus($transactionId, true);

    $state = $statusCheckResponse->getState();
    $status = ($state === 'COMPLETED' || $state === 'SUCCESS') ? 'success' : (($state === 'FAILED' || $state === 'CANCELLED') ? 'failed' : 'pending');

    $amount = $statusCheckResponse->getAmount();
    $amountRupees = is_numeric($amount) ? (float) $amount / 100 : 0;

    echo json_encode([
        'status' => $status,
        'state'  => $state,
        'data'   => [
            'transaction_id' => $statusCheckResponse->getOrderId() ?? $transactionId,
            'amount'         => $amountRupees,
            'payment_details' => $statusCheckResponse->getPaymentDetails(),
        ],
    ]);
} catch (\PhonePe\common\exceptions\PhonePeException $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
