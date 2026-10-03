<?php
// MUST be the first thing in the file, before session or DB
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, x-auth-token, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

// Normal CORS headers for all other requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, x-auth-token, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// POST = user payment initiation (requires x-auth-token) — PhonePe v2 Standard Checkout
if ($method === 'POST') {
    include __DIR__ . '/db.php';
    include __DIR__ . '/auth_helper.php';
    include __DIR__ . '/phonepe_client.php';

    $auth = getUserIdFromRequest($conn);
    if (!$auth) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized', 'message' => 'Please log in again.']));
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $amount = isset($input['amount']) ? (float) $input['amount'] : null;
    $orderId = $input['order_id'] ?? $input['orderId'] ?? null;
    $message = $input['message'] ?? 'Order payment';
    // Website orders ("ZC-<booking id>"): charge the amount the booking was priced at on the server.
    if (is_string($orderId) && preg_match('/^ZC-(\d+-\d+)$/', $orderId, $m)) {
        $booking = $conn->prepare('SELECT price FROM service_booking WHERE unique_booking_id = ? AND user_id = ?');
        $booking->execute([$m[1], $auth['user_id']]);
        $bookingRow = $booking->fetch(PDO::FETCH_ASSOC);
        if (!$bookingRow) {
            http_response_code(404);
            die(json_encode(['error' => 'Booking not found', 'message' => 'This booking was not found for your account.']));
        }
        if ((float) $bookingRow['price'] > 0) {
            $amount = (float) $bookingRow['price'];
        }
    }
    if ($amount === null || $amount <= 0) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid amount']));
    }

    $merchantOrderId = $orderId ?: ('ORDER_' . strtoupper(bin2hex(random_bytes(8))));
    $amountPaisa = (int) round($amount * 100);
    $redirectUrl = $_ENV['PHONEPE_REDIRECT_URL'] ?? '';
    if ($redirectUrl === '') {
        http_response_code(500);
        die(json_encode(['error' => 'PHONEPE_REDIRECT_URL not configured']));
    }

    try {
        $client = get_phonepe_client();
        $payRequest = \PhonePe\payments\v2\models\request\builders\StandardCheckoutPayRequestBuilder::builder()
            ->merchantOrderId($merchantOrderId)
            ->amount($amountPaisa)
            ->redirectUrl($redirectUrl)
            ->message($message)
            ->build();

        $payResponse = $client->pay($payRequest);

        if ($payResponse->getState() === 'PENDING' && $payResponse->getRedirectUrl()) {
            // Save transaction as pending so confirmation can update it
            $stmt = $conn->prepare("
                INSERT INTO transactions (transaction_id, user_id, amount, status, payment_mode)
                VALUES (?, ?, ?, 'pending', 'PHONEPE')
                ON DUPLICATE KEY UPDATE status = 'pending'
            ");
            $stmt->execute([$merchantOrderId, $auth['user_id'], $amount]);

            echo json_encode([
                'success'         => true,
                'payment_url'      => $payResponse->getRedirectUrl(),
                'transaction_id'   => $merchantOrderId,
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Payment initiation failed', 'state' => $payResponse->getState()]);
        }
    } catch (\PhonePe\common\exceptions\PhonePeException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment gateway error', 'message' => $e->getMessage()]);
    }
    exit;
}

// GET = admin list (requires Admin-Token header)
if (empty($_ENV['ADMIN_SECRET']) || !isset($_SERVER['HTTP_ADMIN_TOKEN']) || !hash_equals((string) $_ENV['ADMIN_SECRET'], (string) $_SERVER['HTTP_ADMIN_TOKEN'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Database connection
$db = new mysqli(
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_USER'] ?? '',
    $_ENV['DB_PASSWORD'] ?? '',
    $_ENV['DB_NAME'] ?? 'zencareservice_servicy'
);

if ($db->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

try {
    // Get filters from request
    $params = [
        'page' => max(1, $_GET['page'] ?? 1),
        'limit' => min(100, $_GET['limit'] ?? 20),
        'status' => $_GET['status'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'search' => $_GET['search'] ?? null
    ];

    // Build SQL query
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
        $bindValues[] = $searchTerm;
        $bindValues[] = $searchTerm;
        $bindValues[] = $searchTerm;
    }

    // Count total records
    $countSql = "SELECT COUNT(*) as total FROM transactions";
    if (!empty($where)) {
        $countSql .= " WHERE " . implode(" AND ", $where);
    }
    
    $countStmt = $db->prepare($countSql);
    if (!empty($bindValues)) {
        $countStmt->bind_param($bindTypes, ...$bindValues);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];

    // Fetch paginated data
    $sql = "
        SELECT 
            transaction_id, 
            user_id, 
            amount, 
            status, 
            payment_mode, 
            created_at,
            updated_at
        FROM transactions
    ";
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $bindTypes .= 'ii';
    $bindValues[] = $params['limit'];
    $bindValues[] = ($params['page'] - 1) * $params['limit'];

    $stmt = $db->prepare($sql);
    $stmt->bind_param($bindTypes, ...$bindValues);
    $stmt->execute();
    $result = $stmt->get_result();

    // Format response
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => $row['transaction_id'],
            'user' => $row['user_id'],
            'amount' => (float)$row['amount'],
            'status' => $row['status'],
            'method' => $row['payment_mode'],
            'date' => $row['created_at'],
            'last_updated' => $row['updated_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'meta' => [
                'page' => (int)$params['page'],
                'limit' => (int)$params['limit'],
                'total' => (int)$total,
                'pages' => ceil($total / $params['limit'])
            ],
            'transactions' => $transactions
        ]
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