<?php
// vendor_details.php
// MUST be the first thing in the file, before session or DB
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Admin-Token, X-CSRF-Token");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    http_response_code(200);
    exit();
}

// Normal CORS headers for all other requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Admin-Token, X-CSRF-Token");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

require __DIR__ . '/core/legacy_guard.php';

// Database connection (shared, credentials from .env)
ob_start();
include __DIR__ . '/db.php';
$dbError = trim(ob_get_clean());
if (!isset($conn) || !$conn instanceof PDO) {
    die("Connection failed: " . $dbError);
}
// Same value types as the previous mysqli version (all strings).
$conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

// Fetch all vendor details (password hash and reset token are never returned)
$sql = "SELECT * FROM users";
$result = $conn->query($sql);

$vendors = array();
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    unset($row['password'], $row['reset_token'], $row['token_expiry']);
    $vendors[] = $row;
}

// Return the data in JSON format
header('Content-Type: application/json');
echo json_encode($vendors);

$conn = null;
?>
