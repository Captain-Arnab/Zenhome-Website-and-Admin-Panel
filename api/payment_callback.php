<?php
/**
 * Browser return URL after PhonePe checkout (PHONEPE_REDIRECT_URL).
 * payments.php appends ?transactionId=<merchantOrderId>. The status is asked
 * from PhonePe (getOrderStatus), never taken from the query string, and is
 * recorded with the same rules as paymentConfirmation.php. Then redirects to
 * PAYMENT_CALLBACK_BASE_URL (default APP_URL) with
 *   ?payment=success|failed|pending&txn=<merchantOrderId>
 * "pending" also covers an unknown order or a status check that failed; the
 * website / app then confirms with paymentConfirmation.php.
 */
require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$txnId = '';
foreach (['transactionId', 'merchantOrderId', 'merchantTransactionId', 'orderId'] as $key) {
    if (isset($_GET[$key]) && is_string($_GET[$key]) && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $_GET[$key])) {
        $txnId = $_GET[$key];
        break;
    }
}

$paymentResult = 'pending';
if ($txnId !== '') {
    $zcDbSoftFail = true;
    require __DIR__ . '/db.php';
    try {
        if (!isset($conn) || !$conn instanceof PDO) {
            throw new RuntimeException('database unavailable');
        }
        $known = $conn->prepare('SELECT 1 FROM transactions WHERE transaction_id = ?');
        $known->execute([$txnId]);
        if ($known->fetchColumn()) {
            require_once __DIR__ . '/phonepe_client.php';
            require_once __DIR__ . '/payment_helper.php';
            $state = get_phonepe_client()->getOrderStatus($txnId, false)->getState();
            $t = zc_txn_transition($conn, $txnId, zc_phonepe_status($state));
            if ($t['became_success']) {
                zc_payment_success_effects($conn, $txnId, $t['user_id']);
            }
            $paymentResult = in_array($t['current'], ['success', 'refunded'], true) ? 'success'
                : ($t['current'] === 'failed' ? 'failed' : 'pending');
        }
    } catch (Throwable $e) {
        error_log('[payment_callback] status check failed for ' . $txnId . ': ' . $e->getMessage());
        $paymentResult = 'pending';
    }
}

$baseUrl = rtrim($_ENV['PAYMENT_CALLBACK_BASE_URL'] ?? '', '/');
if ($baseUrl === '') {
    $baseUrl = app_url();
}
$params = ['payment' => $paymentResult];
if ($txnId !== '') {
    $params['txn'] = $txnId;
}
header('Cache-Control: no-store');
header('Location: ' . $baseUrl . (strpos($baseUrl, '?') === false ? '?' : '&') . http_build_query($params));
exit;
