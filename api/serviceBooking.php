<?php
include 'db.php';
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('serviceBooking');
$legacyInput = json_decode(file_get_contents('php://input'), true);
legacy_require_user($legacy, is_array($legacyInput) ? ($legacyInput['user_id'] ?? null) : null);

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!$data || !isset($data['category']) || !isset($data['subcategories']) || !isset($data['date']) 
    || !isset($data['location']) || !isset($data['user_id']) || !isset($data['service_slot']) 
    || !isset($data['created_at']) || !isset($data['status']) || !isset($data['price']) || !isset($data['technician_name'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// Validate subcategories (optional)
foreach ($data['subcategories'] as $subcategory) {
    if (!isset($subcategory['subcategory'])) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Invalid subcategory format"]);
        exit;
    }
}

// Extract data
$category = $data['category'];
$date = $data['date'];
$location = $data['location'];
$landmark = isset($data['landmark']) ? $data['landmark'] : null;
$user_id = $data['user_id'];
$service_slot = $data['service_slot'];
$created_at = $data['created_at'];
$status = $data['status'];
$price = $data['price'];
$technician_name = $data['technician_name'];

// Process subcategories (no change needed here)
$subcategories_data = [];
foreach ($data['subcategories'] as $subcategory) {
    $subcategories_data[] = [$subcategory['subcategory']];
}

// Convert subcategories_data to JSON string
$subcategories_json = json_encode($subcategories_data);

// Prepare and execute SQL query
$sql = "INSERT INTO service_booking (category, subcategories, date, location, landmark, user_id, 
                                     status, service_slot, created_at, price, technician_name) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

try {
    $stmt->execute([$category, $subcategories_json, $date, $location, $landmark, $user_id, 
                    $status, $service_slot, $created_at, $price, $technician_name]);
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    exit;
}

// Get the unique ID of the newly inserted record
$id = $conn->lastInsertId();

// Generate unique booking ID in the format "SERVICY-digits"
$unique_booking_id = "SERVICY-" . str_pad($id, 5, '0', STR_PAD_LEFT); 

// Optionally, update the record with the unique booking ID
$update_sql = "UPDATE service_booking SET unique_booking_id = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->execute([$unique_booking_id, $id]);

// Send success response
http_response_code(201); // Created
echo json_encode([
    "status" => "success",
    "message" => "Service booking added successfully",
    "unique_booking_id" => $unique_booking_id,
    "subcategories" => $subcategories_data
]);

// Close connection
$conn = null; 
?>
