<?php
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('fetchCategory');

// Database connection (shared api/db.php, credentials from .env)
ob_start();
include __DIR__ . '/db.php';
ob_end_clean();

try {
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new PDOException('Connection failed');
    }
    $pdo = $conn;

    // ID to fetch the data
    $id = $_GET['category_id'] ?? null; // Use GET parameter or set a default null

    if ($id) {
        // Query to fetch data
        $sql = "SELECT subcategory FROM SaverPacks WHERE category_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Execute the query
        $stmt->execute();

        // Fetch the result
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode([
                'status' => 'success',
                'data' => $user
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found.'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or missing ID.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>