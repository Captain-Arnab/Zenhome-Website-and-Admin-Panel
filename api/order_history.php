<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    http_response_code(200);
    exit();
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

include 'db.php';
include 'auth_helper.php';

$auth = requireAuth($conn);
$user_id = (string) $auth['user_id'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("
    SELECT 
        t.transaction_id AS order_id,
        t.amount,
        t.status,
        t.payment_mode,
        t.created_at AS order_date,
        o.items,
        o.shipping_address
    FROM transactions t
    LEFT JOIN orders o ON t.transaction_id = o.transaction_id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT " . (int) $limit . " OFFSET " . (int) $offset
);
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM transactions WHERE user_id = ?");
$countStmt->execute([$user_id]);
$total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orders = [];
foreach ($rows as $row) {
    $orders[] = [
        'order_id' => $row['order_id'],
        'amount' => (float) $row['amount'],
        'status' => $row['status'],
        'payment_mode' => $row['payment_mode'],
        'order_date' => $row['order_date'],
        'items' => isset($row['items']) ? (is_string($row['items']) ? json_decode($row['items'], true) : $row['items']) : [],
        'shipping_address' => isset($row['shipping_address']) ? (is_string($row['shipping_address']) ? json_decode($row['shipping_address'], true) : $row['shipping_address']) : null,
    ];
}

echo json_encode([
    'statusCode' => 200,
    'status' => 'success',
    'data' => [
        'orders' => $orders,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_items' => $total,
            'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
        ],
    ],
]);
