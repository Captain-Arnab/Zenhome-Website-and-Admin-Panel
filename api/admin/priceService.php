<?php
// MUST be the first thing in the file, before session or DB
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    http_response_code(200);
    exit();
}

// Normal CORS headers for all other requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

require __DIR__ . '/core/legacy_guard.php';
include 'db.php'; // Include your database connection file

// Get data from the request (replace with your actual method)
$data = json_decode(file_get_contents('php://input'), true);
$categoryId = $data['category_id'];
$subcategories = $data['subcategories'];

// Validate input
if (!$data || !isset($categoryId) || !isset($subcategories)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

try {
    // Validate subcategories
    foreach ($subcategories as $subcategory) {
        if (!is_string(key($subcategory)) || !isset($subcategory[key($subcategory)])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid subcategory format.']);
            exit;
        }
    }

    // Fetch existing category data
    $sql = "SELECT subcategories FROM service_category WHERE CATEGORY_ID = :categoryId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Category not found.']);
        exit;
    }

    $existingSubcategories = json_decode($result['subcategories'], true);

    // Update prices in existing data
    foreach ($subcategories as $subcategory) {
        $subcategoryName = key($subcategory);
        foreach ($existingSubcategories as &$existingSubcategory) {
            if ($existingSubcategory['subcategory'] === $subcategoryName) {
                $existingSubcategory['price'] = $subcategory[$subcategoryName]; 
                break; 
            }
        }
    }

    // Convert updated subcategories to JSON string
    $updatedSubcategoriesJson = json_encode($existingSubcategories);

    // Update subcategories in the database
    $updateSql = "UPDATE service_category SET subcategories = :updatedSubcategories WHERE CATEGORY_ID = :categoryId";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bindParam(':updatedSubcategories', $updatedSubcategoriesJson, PDO::PARAM_STR);
    $updateStmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
    $updateStmt->execute(); 

    // Prepare the desired output format
    $output = [
        "category_id" => $categoryId,
        "subcategories" => $existingSubcategories
    ];

    // Send success response
    http_response_code(200);
    echo json_encode($output);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage() . ' - ' . $e->getCode()]);
}

// Close connection
$conn = null;

?>