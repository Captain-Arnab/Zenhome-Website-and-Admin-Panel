<?php
/**
 * Pure PhonePe v2 webhook processing logic, kept separate from
 * api/payment_webhook.php (the HTTP entry point) so it can be unit tested
 * with a stub verifier and a disposable transaction row - no real PhonePe
 * API call, no real SMS, no network access.
 *
 * State mapping and the "success is final" rule live in api/payment_helper.php
 * (shared with paymentConfirmation.php and payment_callback.php).
 */
require_once __DIR__ . '/payment_helper.php';

/**
 * @param array<string,string> $headers   Lowercase header name => value. Only 'authorization' is read.
 * @param string               $body      Raw webhook POST body.
 * @param PDO                  $conn      Same shared connection api/db.php returns.
 * @param callable             $verify    fn(array $headers, string $body): CallbackResponse: wraps
 *                                        get_phonepe_client()->verifyCallbackResponse(...); throws on
 *                                        a bad signature. Swappable with a stub in tests.
 * @param callable             $notify    fn(string $transactionId): void: sends the booking-confirmed
 *                                        SMS (customer + admin) when the transaction is a website
 *                                        booking payment. Called only on a fresh (non-duplicate)
 *                                        success. Swappable with a no-op stub in tests - never a real SMS there.
 * @return array{http:int, result:array}  HTTP status to send and the JSON body to log/echo.
 */
function phonepe_handle_webhook(array $headers, string $body, PDO $conn, callable $verify, callable $notify): array
{
    try {
        $callback = $verify($headers, $body);
    } catch (Throwable $e) {
        return ['http' => 401, 'result' => ['status' => 'invalid_signature', 'message' => $e->getMessage()]];
    }

    $payload = $callback->getPayload();
    $merchantOrderId = (string) ($payload->getMerchantOrderId() ?? '');
    $newStatus = zc_phonepe_status($payload->getState());

    if ($merchantOrderId === '') {
        return ['http' => 200, 'result' => ['status' => 'ignored', 'message' => 'Callback had no merchantOrderId.']];
    }

    $t = zc_txn_transition($conn, $merchantOrderId, $newStatus);

    if (!$t['found']) {
        // Nothing to update; acknowledge so PhonePe does not keep retrying an order we don't have.
        return ['http' => 200, 'result' => ['status' => 'unknown_order', 'transaction_id' => $merchantOrderId]];
    }

    if ($t['previous'] === $newStatus) {
        // Same callback delivered again (or the status check already applied it): no-op, not an error.
        return ['http' => 200, 'result' => ['status' => 'duplicate', 'transaction_id' => $merchantOrderId, 'current_status' => $newStatus]];
    }

    if (!$t['changed']) {
        // success/refunded are final: a late failed/pending event never undoes a payment.
        return ['http' => 200, 'result' => ['status' => 'ignored_final', 'transaction_id' => $merchantOrderId, 'current_status' => $t['current'], 'reported_status' => $newStatus]];
    }

    if ($t['became_success']) {
        zc_payment_success_effects($conn, $merchantOrderId, $t['user_id'], $notify);
    }

    return ['http' => 200, 'result' => ['status' => 'ok', 'transaction_id' => $merchantOrderId, 'applied_status' => $newStatus, 'previous_status' => $t['previous']]];
}
