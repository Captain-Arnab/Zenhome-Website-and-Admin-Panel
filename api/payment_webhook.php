<?php
/**
 * PhonePe v2 Standard Checkout webhook. Configure the same URL, username
 * and password in the PhonePe dashboard (Developer Settings > Webhook,
 * Authentication Type: SHA) as PHONEPE_WEBHOOK_USERNAME/PASSWORD in .env.
 * Processing logic lives in api/phonepe_webhook_handler.php so it can be
 * unit tested without calling PhonePe or sending real SMS.
 */
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
require_once __DIR__ . '/phonepe_client.php';
require_once __DIR__ . '/phonepe_webhook_handler.php';
include __DIR__ . '/db.php';

$body = file_get_contents('php://input');
$headers = ['authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '')];

$verify = function (array $headers, string $body) {
    return get_phonepe_client()->verifyCallbackResponse(
        $headers,
        $body,
        (string) ($_ENV['PHONEPE_WEBHOOK_USERNAME'] ?? ''),
        (string) ($_ENV['PHONEPE_WEBHOOK_PASSWORD'] ?? '')
    );
};

try {
    $outcome = phonepe_handle_webhook($headers, $body, $conn, $verify);
} catch (Throwable $e) {
    error_log('[payment_webhook] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Webhook processing failed.']);
    exit;
}

$logLevel = $outcome['result']['status'] ?? 'unknown';
error_log('[payment_webhook] ' . $logLevel . ' ' . json_encode($outcome['result']));

http_response_code($outcome['http']);
header('Content-Type: application/json');
echo json_encode($outcome['result']);
