<?php
require_once __DIR__ . '/public_helper.php';
public_cors('GET, OPTIONS');
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('user_orders');
legacy_require_user($legacy, $_GET['user_id'] ?? null);
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
    // Get and validate user ID
    $userId = $_GET['user_id'] ?? null;
    if (empty($userId)) {
        throw new Exception("User ID parameter is required");
    }

    // Pagination parameters
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = max(1, min(50, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    // Build base query
    $query = "
        SELECT 
            t.transaction_id as order_id,
            t.amount,
            t.status,
            t.payment_mode,
            t.created_at as order_date,
            o.items,
            o.shipping_address
        FROM transactions t
        LEFT JOIN orders o ON t.transaction_id = o.transaction_id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ";

    // Prepare and execute
    $stmt = $db->prepare($query);
    $stmt->bind_param("sii", $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    // Format response
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'order_id' => $row['order_id'],
            'amount' => (float)$row['amount'],
            'status' => $row['status'],
            'payment_method' => $row['payment_mode'],
            'date' => $row['order_date'],
            'items' => json_decode($row['items'], true) ?? [],
            'shipping' => json_decode($row['shipping_address'], true) ?? null
        ];
    }

    // Get total count for pagination
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM transactions 
        WHERE user_id = ?
    ");
    $countStmt->bind_param("s", $userId);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];

    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'orders' => $orders,
            'pagination' => [
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_items' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ]
    ]);

} catch (Throwable $e) {
    public_legacy_error($e, 'user_orders');
} finally {
    $db->close();
}