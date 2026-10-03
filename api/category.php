<?php
include 'db.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

try {
    // Categories disabled in the admin panel are hidden; ?include_disabled=1 returns all.
    $where = !empty($_GET['include_disabled']) ? '' : ' WHERE status = 1';
    $stmt = $conn->prepare("SELECT CATEGORY_ID, NAME, IMAGE FROM SERVICE_CATEGORY" . $where . " ORDER BY sort_order, CATEGORY_ID");
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = $categories; // Simplest solution if SUBCATEGORIES is not used.

    $response = json_encode([
        'status' => 'success',
        'categories' => $result
    ]);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON encoding error: " . json_last_error_msg());
    }

    echo $response;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch categories.',
        'error' => $e->getMessage()
    ]);
    error_log("Error fetching categories: " . $e->getMessage());
}
?>