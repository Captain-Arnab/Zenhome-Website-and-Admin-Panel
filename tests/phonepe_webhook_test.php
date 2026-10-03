<?php
/**
 * Plain-PHP test for api/phonepe_webhook_handler.php (no PHPUnit in this
 * project; same CLI-script convention as database/migrate.php). Uses a
 * stub verifier in place of the real PhonePe SDK, so no network call is
 * made and no real SMS is sent. Uses the dev database with disposable
 * test rows, all removed at the end (even on failure).
 *
 * Run:  php tests/phonepe_webhook_test.php
 */
chdir(__DIR__ . '/..');
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
require_once __DIR__ . '/../api/phonepe_webhook_handler.php';
include __DIR__ . '/../api/db.php';
/** @var PDO $conn */

$failures = 0;
$passed = 0;

function check(string $label, bool $condition): void
{
    global $failures, $passed;
    if ($condition) {
        echo "  PASS  $label\n";
        $passed++;
    } else {
        echo "  FAIL  $label\n";
        $failures++;
    }
}

/** Stub CallbackResponse: only getPayload()->getMerchantOrderId()/getState() are used by the handler. */
function stub_callback(string $merchantOrderId, string $state): object
{
    $payload = new class($merchantOrderId, $state) {
        public function __construct(private string $id, private string $state) {}
        public function getMerchantOrderId() { return $this->id; }
        public function getState() { return $this->state; }
    };
    return new class($payload) {
        public function __construct(private object $payload) {}
        public function getPayload() { return $this->payload; }
    };
}

function cleanup(PDO $conn, string $txnId, string $userId): void
{
    $conn->prepare('DELETE FROM transactions WHERE transaction_id = ?')->execute([$txnId]);
    $conn->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$userId]);
}

// cart.user_id has a FK to users.ID, so a real (disposable) user row is needed for the cart-clear assertion.
$testUserId = '9997779';
$conn->prepare('DELETE FROM users WHERE ID = ?')->execute([$testUserId]);
$conn->prepare("INSERT INTO users (ID, first_name, last_name, email, phone, password) VALUES (?, 'QA', 'Webhook', 'qa-webhook-test@example.com', '9997779000', 'x')")->execute([$testUserId]);

// --- Case 1: success ---
$txn = 'QA_TXN_SUCCESS_' . bin2hex(random_bytes(3));
$conn->prepare("INSERT INTO transactions (transaction_id, user_id, amount, status, payment_mode) VALUES (?, ?, 100, 'pending', 'PHONEPE')")->execute([$txn, $testUserId]);
$conn->prepare("INSERT INTO cart (user_id, items) VALUES (?, '[{\"id\":1}]') ON DUPLICATE KEY UPDATE items = VALUES(items)")->execute([$testUserId]);
$verify = fn($h, $b) => stub_callback($txn, 'COMPLETED');
$res = phonepe_handle_webhook([], '{}', $conn, $verify);
check('success: http 200', $res['http'] === 200);
check('success: status ok', $res['result']['status'] === 'ok');
check('success: applied_status success', $res['result']['applied_status'] === 'success');
$row = $conn->prepare('SELECT status FROM transactions WHERE transaction_id = ?');
$row->execute([$txn]);
check('success: transaction row updated to success', $row->fetchColumn() === 'success');
$cartLeft = $conn->prepare('SELECT COUNT(*) FROM cart WHERE user_id = ?');
$cartLeft->execute([$testUserId]);
check('success: cart cleared', (int) $cartLeft->fetchColumn() === 0);

// --- Case 2: duplicate callback (same success event delivered again) ---
$res2 = phonepe_handle_webhook([], '{}', $conn, $verify);
check('duplicate: http 200', $res2['http'] === 200);
check('duplicate: status duplicate', $res2['result']['status'] === 'duplicate');
cleanup($conn, $txn, $testUserId);

// --- Case 3: failed ---
$txn = 'QA_TXN_FAILED_' . bin2hex(random_bytes(3));
$conn->prepare("INSERT INTO transactions (transaction_id, user_id, amount, status, payment_mode) VALUES (?, ?, 100, 'pending', 'PHONEPE')")->execute([$txn, $testUserId]);
$verify = fn($h, $b) => stub_callback($txn, 'FAILED');
$res = phonepe_handle_webhook([], '{}', $conn, $verify);
check('failed: http 200', $res['http'] === 200);
check('failed: applied_status failed', $res['result']['applied_status'] === 'failed');
$row = $conn->prepare('SELECT status FROM transactions WHERE transaction_id = ?');
$row->execute([$txn]);
check('failed: transaction row updated to failed', $row->fetchColumn() === 'failed');
cleanup($conn, $txn, $testUserId);

// --- Case 4: pending (e.g. an intermediate state callback) ---
$txn = 'QA_TXN_PENDING_' . bin2hex(random_bytes(3));
$conn->prepare("INSERT INTO transactions (transaction_id, user_id, amount, status, payment_mode) VALUES (?, ?, 100, 'pending', 'PHONEPE')")->execute([$txn, $testUserId]);
$verify = fn($h, $b) => stub_callback($txn, 'PENDING');
$res = phonepe_handle_webhook([], '{}', $conn, $verify);
check('pending: http 200', $res['http'] === 200);
// Already pending -> this is itself a duplicate/no-op of the existing state.
check('pending: status duplicate (already pending)', $res['result']['status'] === 'duplicate');
cleanup($conn, $txn, $testUserId);

// --- Case 5: invalid signature ---
$txn = 'QA_TXN_BADSIG_' . bin2hex(random_bytes(3));
$conn->prepare("INSERT INTO transactions (transaction_id, user_id, amount, status, payment_mode) VALUES (?, ?, 100, 'pending', 'PHONEPE')")->execute([$txn, $testUserId]);
$verify = function ($h, $b) {
    throw new \PhonePe\common\exceptions\PhonePeException('Invalid callback');
};
$res = phonepe_handle_webhook([], '{}', $conn, $verify);
check('invalid signature: http 401', $res['http'] === 401);
check('invalid signature: status invalid_signature', $res['result']['status'] === 'invalid_signature');
$row = $conn->prepare('SELECT status FROM transactions WHERE transaction_id = ?');
$row->execute([$txn]);
check('invalid signature: transaction untouched (still pending)', $row->fetchColumn() === 'pending');
cleanup($conn, $txn, $testUserId);

// --- Case 6: unknown order ---
$unknownTxn = 'QA_TXN_DOES_NOT_EXIST_' . bin2hex(random_bytes(3));
$verify = fn($h, $b) => stub_callback($unknownTxn, 'COMPLETED');
$res = phonepe_handle_webhook([], '{}', $conn, $verify);
check('unknown order: http 200 (acknowledged, nothing to update)', $res['http'] === 200);
check('unknown order: status unknown_order', $res['result']['status'] === 'unknown_order');

// Final safety net: remove anything left over from this test run.
$conn->prepare("DELETE FROM transactions WHERE transaction_id LIKE 'QA_TXN_%'")->execute();
$conn->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$testUserId]);
$conn->prepare('DELETE FROM users WHERE ID = ?')->execute([$testUserId]);

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
