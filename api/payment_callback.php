<?php
// Handle payment success/failure after user returns from PhonePe redirect.
// Redirects to baseUrl (e.g. https://zencareservice.com) with ?payment=success|failed&txn=ORDER_ID
// so the app can show order history (success) or keep cart (failed).
$status = $_GET['status'] ?? $_GET['state'] ?? 'failed';
$txnId = $_GET['transactionId'] ?? $_GET['merchantTransactionId'] ?? $_GET['orderId'] ?? '';

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
$baseUrl = rtrim($_ENV['PAYMENT_CALLBACK_BASE_URL'] ?? 'https://zencareservice.com', '/');
if ($baseUrl === '') {
    $baseUrl = 'https://zencareservice.com';
}

$isSuccess = ($status === 'success' || $status === 'COMPLETED' || $status === 'SUCCESS');
$paymentResult = $isSuccess ? 'success' : 'failed';
$params = ['payment' => $paymentResult];
if ($txnId !== '') {
    $params['txn'] = $txnId;
}
$query = '?' . http_build_query($params);
header('Location: ' . $baseUrl . $query);
exit;