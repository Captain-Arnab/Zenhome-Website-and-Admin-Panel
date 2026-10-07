<?php
require_once __DIR__ . '/public_helper.php';
public_cors('GET, POST, OPTIONS');
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
    $id = $_GET['packid'] ?? null; // Use GET parameter or set a default null

    if ($id) {
        // Query to fetch data
        $sql = "SELECT * FROM saverpacks WHERE packid = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Execute the query
        $stmt->execute();

        // Fetch the result
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode([
                'statusCode' => 200,
                'status' => 'success',
                'data' => $user
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'statusCode' => 404,
                'status' => 'error',
                'message' => 'User not found.'
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'statusCode' => 400,
            'status' => 'error',
            'message' => 'Invalid or missing ID.'
        ]);
    }
} catch (PDOException $e) {
    error_log('[fetchCategoryDetails] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'status' => 'error',
        'message' => 'Server error. Please try again.'
    ]);
}
?>