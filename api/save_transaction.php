<?php
/**
 * Retired: clients could write any transaction status here (including
 * "success") without proof from PhonePe. Payments are now recorded only by
 * payments.php (pending), paymentConfirmation.php / payment_callback.php
 * (status checked with PhonePe) and the PhonePe webhook.
 * Calls are still logged in legacy_access_log so remaining callers show up.
 */
require __DIR__ . '/public_helper.php';
require_once __DIR__ . '/legacy_access.php';

public_cors('GET, POST, OPTIONS');
legacy_access('save_transaction');

header('Cache-Control: no-store');
http_response_code(410);
echo json_encode([
    'statusCode' => 410,
    'status' => 'error',
    'success' => false,
    'message' => 'This endpoint has been retired. Payments are confirmed with paymentConfirmation.php.',
    'error' => 'Gone',
], JSON_UNESCAPED_SLASHES);
