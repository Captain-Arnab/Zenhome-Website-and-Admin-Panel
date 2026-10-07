<?php
require_once __DIR__ . '/public_helper.php';
public_cors('GET, POST, OPTIONS');
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('user_transaction');
$legacyInput = json_decode(file_get_contents('php://input'), true);
legacy_require_user($legacy, (is_array($legacyInput) ? ($legacyInput['user_id'] ?? null) : null) ?? $_GET['user_id'] ?? null);
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Database configuration (Example using MySQLi)
$db = new mysqli(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASSWORD'],
    $_ENV['DB_NAME']
);

if ($db->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

try {
    // Get user ID from request
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? $_GET['user_id'] ?? null;
    
    if (empty($userId)) {
        throw new Exception("User ID is required");
    }

    // Fetch transactions from database
    $stmt = $db->prepare("
        SELECT transaction_id, amount, status, payment_mode, created_at 
        FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    // RECEIPT_BASE_URL is optional; fall back to this request's own host so the
    // link always resolves instead of emitting an undefined-key warning.
    $receiptBase = $_ENV['RECEIPT_BASE_URL'] ?? '';
    if ($receiptBase === '') {
        $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $receiptBase = $scheme . '://' . $host . '/api/order_details.php?order_id=';
    }

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'transaction_id' => $row['transaction_id'],
            'amount'        => (float)$row['amount'],
            'status'        => $row['status'],
            'payment_mode'  => $row['payment_mode'],
            'date'          => $row['created_at'],
            'receipt_url'   => $receiptBase . $row['transaction_id']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'count' => count($transactions),
            'transactions' => $transactions
        ]
    ]);

} catch (Throwable $e) {
    public_legacy_error($e, 'user_transaction');
} finally {
    $db->close();
}