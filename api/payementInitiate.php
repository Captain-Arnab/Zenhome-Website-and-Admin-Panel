<?php
require_once __DIR__ . '/public_helper.php';
public_cors('POST, OPTIONS');
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('payementInitiate');
$legacyInput = json_decode(file_get_contents('php://input'), true);
legacy_require_user($legacy, is_array($legacyInput) ? ($legacyInput['user_id'] ?? null) : null);
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

include __DIR__ . '/db.php';
require_once __DIR__ . '/phonepe_client.php';

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['amount']) || !is_numeric($input['amount'])) {
        throw new Exception("Amount is required and must be numeric");
    }

    $redirectUrl = $_ENV['PHONEPE_REDIRECT_URL'] ?? '';
    if ($redirectUrl === '') {
        throw new Exception("PHONEPE_REDIRECT_URL not configured");
    }

    $merchantTransactionId = 'TXN_' . strtoupper(bin2hex(random_bytes(5)));
    $amount = (float) $input['amount'];
    $amountPaisa = (int) round($amount * 100);
    $userId = is_scalar($input['user_id'] ?? null) ? (string) $input['user_id'] : null;

    $client = get_phonepe_client();
    $payRequest = \PhonePe\payments\v2\models\request\builders\StandardCheckoutPayRequestBuilder::builder()
        ->merchantOrderId($merchantTransactionId)
        ->amount($amountPaisa)
        ->redirectUrl($redirectUrl)
        ->build();

    $payResponse = $client->pay($payRequest);

    if ($payResponse->getState() !== 'PENDING' || !$payResponse->getRedirectUrl()) {
        throw new Exception("Payment initiation failed");
    }

    // Save as pending so fetch-transaction.php / the webhook can find and update it.
    $stmt = $conn->prepare("
        INSERT INTO transactions (transaction_id, user_id, amount, status, payment_mode)
        VALUES (?, ?, ?, 'pending', 'PHONEPE')
        ON DUPLICATE KEY UPDATE status = 'pending'
    ");
    $stmt->execute([$merchantTransactionId, $userId ?? ('GUEST_' . random_int(1000, 9999)), $amount]);

    // Return payment URL (same shape as the old v1 response)
    echo json_encode([
        'success' => true,
        'payment_url' => $payResponse->getRedirectUrl(),
        'transaction_id' => $merchantTransactionId
    ]);

} catch (Throwable $e) {
    public_legacy_error($e, 'payementInitiate');
}
