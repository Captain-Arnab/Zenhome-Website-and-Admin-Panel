<?php
header('Content-Type: application/json');
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('order_details');
legacy_require_owner($legacy, 'SELECT user_id FROM transactions WHERE transaction_id = ?', [is_string($_GET['order_id'] ?? null) ? $_GET['order_id'] : ''], 'Order');
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Database connection
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
    // Validate order ID
    $orderId = $_GET['order_id'] ?? null;
    if (empty($orderId) || !preg_match('/^TXN_[A-Z0-9]{10}$/', $orderId)) {
        throw new Exception("Valid order ID is required (format: TXN_XXXXXXXXXX)");
    }

    // Main order query
    $stmt = $db->prepare("
        SELECT 
            t.transaction_id as order_id,
            t.user_id,
            t.amount,
            t.status,
            t.payment_mode,
            t.created_at as order_date,
            t.phonepe_data,
            o.items,
            o.shipping_address,
            s.schedule_date,
            s.cleaner_id,
            s.status as service_status
        FROM transactions t
        LEFT JOIN orders o ON t.transaction_id = o.transaction_id
        LEFT JOIN service_schedule s ON t.transaction_id = s.order_id
        WHERE t.transaction_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Order not found");
    }

    $order = $result->fetch_assoc();

    // Service type comes from the first cart item (orders.items); no dedicated column exists.
    $orderItems = json_decode($order['items'], true) ?? [];
    $firstItem = is_array($orderItems) ? ($orderItems[0] ?? null) : null;
    $serviceType = (is_array($firstItem) ? ($firstItem['type'] ?? $firstItem['name'] ?? $firstItem['category'] ?? null) : null) ?? 'Standard Cleaning';

    // Format response
    $response = [
        'order_id' => $order['order_id'],
        'user_id' => $order['user_id'],
        'amount' => (float)$order['amount'],
        'status' => $order['status'],
        'payment_method' => $order['payment_mode'],
        'order_date' => $order['order_date'],
        'service' => [
            'type' => $serviceType,
            'schedule' => [
                'date' => $order['schedule_date'],
                'cleaner_id' => $order['cleaner_id'],
                'status' => $order['service_status'] ?? 'pending'
            ]
        ],
        'items' => $orderItems,
        'shipping_address' => json_decode($order['shipping_address'], true),
        'payment_details' => json_decode($order['phonepe_data'], true)
    ];

    // Mask sensitive payment data
    if (isset($response['payment_details']['cardNumber'])) {
        $response['payment_details']['cardNumber'] = '•••• •••• •••• ' . 
            substr($response['payment_details']['cardNumber'], -4);
    }

    echo json_encode([
        'success' => true,
        'data' => $response
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