<?php
/**
 * POST  start a PhonePe v2 Standard Checkout payment for one of the customer's
 *       own bookings. Customer login token required.
 *       {"order_id": "ZC-<unique_booking_id>", "amount"?: ignored, "message"?}
 *       The amount is the booking's server price (service_booking.price).
 *       409 when that transaction is already paid or belongs to someone else.
 *       200 {statusCode, status, message, success:true, payment_url, transaction_id, data:{...}}
 *       PhonePe returns the customer to PHONEPE_REDIRECT_URL?transactionId=<order_id>.
 *
 * GET   admin transaction list (Admin-Token header = ADMIN_SECRET).
 */
require __DIR__ . '/public_helper.php';
require_once __DIR__ . '/runtime.php';

public_cors('GET, POST, OPTIONS');

require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

/** Error body keeping the legacy "error" key next to the standard envelope. */
function payments_fail(int $code, string $error, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['statusCode' => $code, 'status' => 'error', 'success' => false, 'error' => $error, 'message' => $message] + $extra, JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    require __DIR__ . '/auth_helper.php';
    require __DIR__ . '/admin/core/status.php';
    require_once __DIR__ . '/phonepe_client.php';

    $conn = public_db();
    $auth = getUserIdFromRequest($conn);
    if (!$auth) {
        payments_fail(401, 'Unauthorized', 'Please log in again.');
    }
    $userId = (int) $auth['user_id'];

    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $orderId = $input['order_id'] ?? $input['orderId'] ?? '';
    $orderId = is_scalar($orderId) ? trim((string) $orderId) : '';
    $message = isset($input['message']) && is_scalar($input['message']) ? mb_substr(trim((string) $input['message']), 0, 100) : '';
    if (!preg_match('/^ZC-(\d+-\d+)$/', $orderId, $m)) {
        payments_fail(422, 'Invalid order', 'order_id must be "ZC-" followed by your booking ID.', ['errors' => ['order_id' => 'Use ZC-<booking id>.']]);
    }

    $stmt = $conn->prepare('SELECT ID, price, status FROM service_booking WHERE unique_booking_id = ? AND user_id = ?');
    $stmt->execute([$m[1], $userId]);
    $booking = $stmt->fetch();
    if (!$booking) {
        payments_fail(404, 'Booking not found', 'This booking was not found for your account.');
    }
    if (booking_status_canonical($booking['status']) === 'Cancelled') {
        payments_fail(409, 'Booking cancelled', 'This booking is cancelled and cannot be paid.');
    }
    $amount = (float) $booking['price'];
    if ($amount <= 0) {
        payments_fail(422, 'Invalid amount', 'Nothing to pay for this booking.');
    }

    $stmt = $conn->prepare('SELECT user_id, status FROM transactions WHERE transaction_id = ?');
    $stmt->execute([$orderId]);
    $existing = $stmt->fetch();
    if ($existing && (int) $existing['user_id'] !== $userId) {
        payments_fail(409, 'Conflict', 'This payment belongs to another account.');
    }
    if ($existing && in_array($existing['status'], ['success', 'refunded'], true)) {
        payments_fail(409, 'Already paid', 'This booking has already been paid.', ['transaction_id' => $orderId]);
    }

    $redirectUrl = trim((string) ($_ENV['PHONEPE_REDIRECT_URL'] ?? ''));
    if ($redirectUrl === '') {
        error_log('[payments] PHONEPE_REDIRECT_URL is not configured.');
        payments_fail(500, 'Payment not configured', 'Online payment is not available right now. Please choose pay after service.');
    }
    $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'transactionId=' . rawurlencode($orderId);

    try {
        $payRequest = \PhonePe\payments\v2\models\request\builders\StandardCheckoutPayRequestBuilder::builder()
            ->merchantOrderId($orderId)
            ->amount((int) round($amount * 100))
            ->redirectUrl($redirectUrl)
            ->message($message !== '' ? $message : 'Zen Home Experts booking ' . $m[1])
            ->build();
        $payResponse = get_phonepe_client()->pay($payRequest);
    } catch (Throwable $e) {
        error_log('[payments] PhonePe pay failed for ' . $orderId . ': ' . get_class($e) . ': ' . $e->getMessage());
        payments_fail(502, 'Payment gateway error', 'Could not start the online payment. Please try again or pay after service.');
    }

    if ($payResponse->getState() !== 'PENDING' || !$payResponse->getRedirectUrl()) {
        error_log('[payments] unexpected PhonePe state for ' . $orderId . ': ' . $payResponse->getState());
        payments_fail(502, 'Payment initiation failed', 'Could not start the online payment. Please try again or pay after service.', ['state' => $payResponse->getState()]);
    }

    // Pending row for paymentConfirmation / the webhook to update; never
    // downgrades a row that a concurrent confirmation just marked success.
    $conn->prepare("
        INSERT INTO transactions (transaction_id, user_id, amount, status, payment_mode)
        VALUES (?, ?, ?, 'pending', 'PHONEPE')
        ON DUPLICATE KEY UPDATE status = IF(status IN ('success', 'refunded'), status, 'pending'), amount = VALUES(amount)
    ")->execute([$orderId, $userId, $amount]);

    $result = ['payment_url' => $payResponse->getRedirectUrl(), 'transaction_id' => $orderId, 'amount' => $amount];
    http_response_code(200);
    echo json_encode(['statusCode' => 200, 'status' => 'success', 'message' => 'Payment started.', 'success' => true]
        + $result + ['data' => $result], JSON_UNESCAPED_SLASHES);
    exit;
}

// GET = admin list (requires Admin-Token header)
if ($method !== 'GET') {
    payments_fail(405, 'Method not allowed', 'Use GET or POST for this request.');
}
if (empty($_ENV['ADMIN_SECRET']) || !isset($_SERVER['HTTP_ADMIN_TOKEN']) || !hash_equals((string) $_ENV['ADMIN_SECRET'], (string) $_SERVER['HTTP_ADMIN_TOKEN'])) {
    payments_fail(401, 'Unauthorized', 'Admin token required.');
}

try {
    $db = new mysqli(
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_USER'] ?? '',
        $_ENV['DB_PASSWORD'] ?? '',
        $_ENV['DB_NAME'] ?? ''
    );
} catch (Throwable $e) {
    $db = null;
}
if (!$db || $db->connect_error) {
    error_log('[payments] admin list DB connection failed.');
    payments_fail(503, 'Database connection failed', 'Service temporarily unavailable. Please try again.');
}

try {
    $params = [
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
        'limit' => max(1, min(100, (int) ($_GET['limit'] ?? 20))),
        'status' => isset($_GET['status']) && is_string($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null,
        'date_from' => isset($_GET['date_from']) && is_string($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null,
        'date_to' => isset($_GET['date_to']) && is_string($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null,
        'search' => isset($_GET['search']) && is_string($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null,
    ];

    $where = [];
    $bindTypes = '';
    $bindValues = [];

    if ($params['status']) {
        $where[] = "status = ?";
        $bindTypes .= 's';
        $bindValues[] = $params['status'];
    }
    if ($params['date_from']) {
        $where[] = "created_at >= ?";
        $bindTypes .= 's';
        $bindValues[] = $params['date_from'];
    }
    if ($params['date_to']) {
        $where[] = "created_at <= ?";
        $bindTypes .= 's';
        $bindValues[] = $params['date_to'] . ' 23:59:59';
    }
    if ($params['search']) {
        $where[] = "(transaction_id LIKE ? OR user_id LIKE ? OR payment_mode LIKE ?)";
        $bindTypes .= 'sss';
        $searchTerm = '%' . $params['search'] . '%';
        array_push($bindValues, $searchTerm, $searchTerm, $searchTerm);
    }

    $countSql = "SELECT COUNT(*) as total FROM transactions" . ($where ? " WHERE " . implode(" AND ", $where) : '');
    $countStmt = $db->prepare($countSql);
    if ($bindValues) {
        $countStmt->bind_param($bindTypes, ...$bindValues);
    }
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['total'];

    $sql = "SELECT transaction_id, user_id, amount, status, payment_mode, created_at, updated_at FROM transactions"
        . ($where ? " WHERE " . implode(" AND ", $where) : '')
        . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $bindTypes .= 'ii';
    $bindValues[] = $params['limit'];
    $bindValues[] = ($params['page'] - 1) * $params['limit'];

    $stmt = $db->prepare($sql);
    $stmt->bind_param($bindTypes, ...$bindValues);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => $row['transaction_id'],
            'user' => $row['user_id'],
            'amount' => (float) $row['amount'],
            'status' => $row['status'],
            'method' => $row['payment_mode'],
            'date' => $row['created_at'],
            'last_updated' => $row['updated_at'],
        ];
    }

    http_response_code(200);
    echo json_encode([
        'statusCode' => 200,
        'status' => 'success',
        'success' => true,
        'data' => [
            'meta' => [
                'page' => $params['page'],
                'limit' => $params['limit'],
                'total' => $total,
                'pages' => (int) ceil($total / $params['limit']),
            ],
            'transactions' => $transactions,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[payments] admin list failed: ' . $e->getMessage());
    payments_fail(500, 'Server error', 'Could not load transactions. Please try again.');
} finally {
    $db->close();
}
