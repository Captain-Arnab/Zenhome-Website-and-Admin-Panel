<?php
/**
 * Pure PhonePe v2 webhook processing logic, kept separate from
 * api/payment_webhook.php (the HTTP entry point) so it can be unit tested
 * with a stub verifier and a disposable transaction row - no real PhonePe
 * API call, no real SMS, no network access.
 *
 * Same state mapping as api/paymentConfirmation.php (the working v2 status
 * flow): SUCCESS/COMPLETED -> success, FAILED -> failed, else -> pending.
 */

/**
 * @param array<string,string> $headers   Lowercase header name => value. Only 'authorization' is read.
 * @param string               $body      Raw webhook POST body.
 * @param PDO                  $conn      Same shared connection api/db.php returns.
 * @param callable             $verify    fn(array $headers, string $body): CallbackResponse: wraps
 *                                        get_phonepe_client()->verifyCallbackResponse(...); throws on
 *                                        a bad signature. Swappable with a stub in tests.
 * @return array{http:int, result:array}  HTTP status to send and the JSON body to log/echo.
 */
function phonepe_handle_webhook(array $headers, string $body, PDO $conn, callable $verify): array
{
    try {
        $callback = $verify($headers, $body);
    } catch (Throwable $e) {
        return ['http' => 401, 'result' => ['status' => 'invalid_signature', 'message' => $e->getMessage()]];
    }

    $payload = $callback->getPayload();
    $merchantOrderId = (string) ($payload->getMerchantOrderId() ?? '');
    $rawState = strtoupper((string) ($payload->getState() ?? ''));
    $newStatus = match ($rawState) {
        'SUCCESS', 'COMPLETED' => 'success',
        'FAILED' => 'failed',
        default => 'pending',
    };

    if ($merchantOrderId === '') {
        return ['http' => 200, 'result' => ['status' => 'ignored', 'message' => 'Callback had no merchantOrderId.']];
    }

    $stmt = $conn->prepare('SELECT transaction_id, user_id, status FROM transactions WHERE transaction_id = ?');
    $stmt->execute([$merchantOrderId]);
    $txn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$txn) {
        // Nothing to update; acknowledge so PhonePe does not keep retrying an order we don't have.
        return ['http' => 200, 'result' => ['status' => 'unknown_order', 'transaction_id' => $merchantOrderId]];
    }

    if ($txn['status'] === $newStatus) {
        // Same callback delivered again (or the status check already applied it): no-op, not an error.
        return ['http' => 200, 'result' => ['status' => 'duplicate', 'transaction_id' => $merchantOrderId, 'current_status' => $newStatus]];
    }

    $conn->prepare('UPDATE transactions SET status = ? WHERE transaction_id = ?')->execute([$newStatus, $merchantOrderId]);
    if ($newStatus === 'success') {
        $conn->prepare('DELETE FROM cart WHERE user_id = ?')->execute([(int) $txn['user_id']]);
    }

    return ['http' => 200, 'result' => ['status' => 'ok', 'transaction_id' => $merchantOrderId, 'applied_status' => $newStatus, 'previous_status' => $txn['status']]];
}
