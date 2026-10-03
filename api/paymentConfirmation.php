<?php

// MUST be the first thing in the file, before any other code
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, x-auth-token, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, x-auth-token, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('paymentConfirmation');

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
include __DIR__ . '/db.php';
include __DIR__ . '/phonepe_client.php';

// Get transaction ID from request (POST or GET)
$merchantTransactionId = $_POST['transactionId'] ?? $_GET['transactionId'] ?? null;

if (!$merchantTransactionId) {
    echo json_encode([
        "success" => false,
        "message" => "Missing transactionId"
    ]);
    exit;
}

legacy_require_owner($legacy, 'SELECT user_id FROM transactions WHERE transaction_id = ?', [is_scalar($merchantTransactionId) ? (string) $merchantTransactionId : ''], 'Transaction', true);

try {
    $client = get_phonepe_client();

    // Fetch order status
    $statusCheckResponse = $client->getOrderStatus($merchantTransactionId, true);

    $state = $statusCheckResponse->getState();
    $orderId = $statusCheckResponse->getOrderId();

    // Prepare JSON response (v2 SDK: orderId, state, amount, getPaymentDetails)
    $response = [
        "success" => true,
        "merchantTransactionId" => $orderId,
        "transactionId" => $orderId,
        "state" => $state,
        "amount" => $statusCheckResponse->getAmount(),
        "paymentDetails" => $statusCheckResponse->getPaymentDetails(),
    ];

    // Handle DB update and cart clear on success (v2 may return COMPLETED or SUCCESS)
    if ($state === "SUCCESS" || $state === "COMPLETED") {
        $stmt = $conn->prepare("SELECT user_id FROM transactions WHERE transaction_id = ?");
        $stmt->execute([$merchantTransactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $conn->prepare("UPDATE transactions SET status = 'success' WHERE transaction_id = ?")->execute([$merchantTransactionId]);
            $uid = (int) $row['user_id'];
            $conn->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$uid]);
        }
    } elseif ($state === "FAILED") {
        $conn->prepare("UPDATE transactions SET status = 'failed' WHERE transaction_id = ?")->execute([$merchantTransactionId]);
    } elseif ($state === "PENDING") {
        $conn->prepare("UPDATE transactions SET status = 'pending' WHERE transaction_id = ?")->execute([$merchantTransactionId]);
    }

    echo json_encode($response);

} catch (\PhonePe\common\exceptions\PhonePeException $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}