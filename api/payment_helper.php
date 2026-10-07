<?php
/**
 * transactions.status rules shared by paymentConfirmation.php,
 * payment_callback.php and the PhonePe webhook (phonepe_webhook_handler.php).
 *
 * - PhonePe state -> status: SUCCESS/COMPLETED -> success, FAILED/CANCELLED -> failed, else pending.
 * - success and refunded are final: a later pending/failed report never
 *   moves a row out of them.
 * - Side effects (clear the saved cart, booking-confirmed SMS) run only for
 *   the one call that actually changed the row to success.
 */

const ZC_TXN_FINAL = ['success', 'refunded'];

function zc_phonepe_status(?string $state): string
{
    return match (strtoupper((string) $state)) {
        'SUCCESS', 'COMPLETED' => 'success',
        'FAILED', 'CANCELLED' => 'failed',
        default => 'pending',
    };
}

/**
 * Applies $newStatus to a transaction if allowed.
 * @return array{found:bool, changed:bool, previous:?string, current:?string, user_id:?int, became_success:bool}
 */
function zc_txn_transition(PDO $conn, string $txnId, string $newStatus): array
{
    $stmt = $conn->prepare('SELECT user_id, status FROM transactions WHERE transaction_id = ?');
    $stmt->execute([$txnId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['found' => false, 'changed' => false, 'previous' => null, 'current' => null, 'user_id' => null, 'became_success' => false];
    }
    $previous = (string) $row['status'];
    $result = ['found' => true, 'changed' => false, 'previous' => $previous, 'current' => $previous,
        'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null, 'became_success' => false];
    if ($previous === $newStatus || in_array($previous, ZC_TXN_FINAL, true)) {
        return $result;
    }
    // Conditional on the status just read, so of two concurrent confirmations
    // only one sees the change (and runs the success side effects).
    $update = $conn->prepare('UPDATE transactions SET status = ? WHERE transaction_id = ? AND status = ?');
    $update->execute([$newStatus, $txnId, $previous]);
    if ($update->rowCount() === 1) {
        $result['changed'] = true;
        $result['current'] = $newStatus;
        $result['became_success'] = $newStatus === 'success';
    }
    return $result;
}

/** Clear the saved cart and send the booking-confirmed SMS; never throws. */
function zc_payment_success_effects(PDO $conn, string $txnId, ?int $userId, ?callable $notify = null): void
{
    try {
        if ($userId) {
            $conn->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$userId]);
        }
    } catch (Throwable $e) {
        error_log('[payment] cart clear failed for ' . $txnId . ': ' . $e->getMessage());
    }
    try {
        if ($notify) {
            $notify($txnId);
        } else {
            require_once __DIR__ . '/admin/core/bootstrap.php';
            notify_booking_confirmed_for_transaction($txnId);
        }
    } catch (Throwable $e) {
        error_log('[payment] notify failed for ' . $txnId . ': ' . $e->getMessage());
    }
}
