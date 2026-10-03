<?php
require __DIR__ . '/core/legacy_guard.php';
include 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!$data || !isset($data['name']) || !isset($data['image'])) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

// Extract data from the request
$name = $data['name'];
$image_base64 = $data['image'];

// Extract image type and data correctly
$parts = explode(';', $image_base64);
$image_type = explode(':', $parts[0])[1]; // Extract MIME type correctly
$image_data = explode(',', $parts[1])[1];

// Check image type
$allowed_types = ['image/jpeg', 'image/png'];
if (!in_array($image_type, $allowed_types)) {
    echo json_encode(["status" => "error", "message" => "Invalid image type. Only JPEG and PNG are allowed.",
        "debug" => [
            "image_type" => $image_type]]);
    exit;
}

// Generate unique filename
$filename = sanitize_filename($name) . '.' . strtolower(substr($image_type, strpos($image_type, '/') + 1));

// Define upload path (assuming the path is accessible)
$upload_path = 'assets/services/';

// Save image to file (**This might require server-level file system access**)
$file_path = "/home/vsnl/public_html/servicy/" . $upload_path . $filename;

$image_data = base64_decode($image_data);
if (!file_put_contents($file_path, $image_data)) {
    echo json_encode(["status" => "error", "message" => "Failed to save image to file."]);
    exit;
}

// Prepare SQL query to insert data (without subcategories)
$sql = "INSERT INTO SERVICE_CATEGORY (name, image) VALUES (?, ?)";
$stmt = $conn->prepare($sql);

// Execute the query with bound parameters
if ($stmt->execute([$name, $filename])) {
    echo json_encode(["status" => "success", "message" => "Data added successfully", "id" => $conn->lastInsertId()]);
} else {
    echo json_encode(["status" => "error", "message" => "Error: " . $stmt->errorInfo()]);
}

// Close connection
$conn = null;

// Define the sanitize_filename function outside the main code block
function sanitize_filename($filename) {
    // Remove invalid characters
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    // Ensure unique filename (optional: add a timestamp or counter)
    // $filename .= '_' . time();
    return $filename;
}
?>