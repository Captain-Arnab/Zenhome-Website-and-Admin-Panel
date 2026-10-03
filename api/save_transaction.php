<?php
header('Content-Type: application/json');
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('save_transaction');
$legacyInput = json_decode(file_get_contents('php://input'), true);
legacy_require_user($legacy, is_array($legacyInput) ? ($legacyInput['user_id'] ?? null) : null);
legacy_require_owner($legacy, 'SELECT user_id FROM transactions WHERE transaction_id = ?', [is_array($legacyInput) && is_scalar($legacyInput['transaction_id'] ?? null) ? (string) $legacyInput['transaction_id'] : ''], 'Transaction');
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Initialize Database
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
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['transaction_id', 'user_id', 'amount', 'status'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Prepare transaction data
    $transaction = [
        'transaction_id' => $input['transaction_id'],
        'user_id'       => $input['user_id'],
        'amount'        => (float)$input['amount'],
        'status'        => in_array($input['status'], ['pending','success','failed','refunded']) 
                          ? $input['status'] 
                          : 'pending',
        'payment_mode'  => $input['payment_mode'] ?? 'UNKNOWN',
        'phonepe_data'  => json_encode($input['phonepe_response'] ?? []),
        'metadata'      => json_encode($input['metadata'] ?? [])
    ];

    // Upsert transaction (insert or update if exists)
    $stmt = $db->prepare("
        INSERT INTO transactions (
            transaction_id, 
            user_id, 
            amount, 
            status, 
            payment_mode,
            phonepe_data,
            metadata
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            payment_mode = VALUES(payment_mode),
            phonepe_data = VALUES(phonepe_data),
            metadata = VALUES(metadata),
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->bind_param(
        "ssdssss",
        $transaction['transaction_id'],
        $transaction['user_id'],
        $transaction['amount'],
        $transaction['status'],
        $transaction['payment_mode'],
        $transaction['phonepe_data'],
        $transaction['metadata']
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to save transaction: " . $stmt->error);
    }

    // Return saved transaction
    $savedTransaction = [
        'transaction_id' => $transaction['transaction_id'],
        'status'        => $transaction['status'],
        'amount'        => $transaction['amount'],
        'payment_mode'  => $transaction['payment_mode'],
        'timestamp'     => date('Y-m-d H:i:s')
    ];

    echo json_encode([
        'success' => true,
        'data' => $savedTransaction
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    $db->close();
}