<?php

header('Content-Type: application/json');

include 'db.php'; // Include your database connection file
require_once __DIR__ . '/legacy_access.php';
$legacy = legacy_access('checkSlot');

// Get the date from the request (e.g., from URL parameter or request body)
$data = json_decode(file_get_contents('php://input'), true);
$date = $data['date'];
if (empty($date)) {
 http_response_code(400); // Bad Request
 echo json_encode(["status" => "error", "message" => "Date parameter is missing"]);
 exit;
}

try {
 // Prepare SQL query to fetch booked slots for the given date
$sql = "SELECT service_slot FROM service_booking WHERE date = :date";
 $stmt = $conn->prepare($sql);
 $stmt->bindParam(':date', $date, PDO::PARAM_STR);
 $stmt->execute();
 $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN); 

 // Define available time slots (assuming 'service_slot' is a column in service_booking) 
 $availableSlots = [
"10:00 AM - 11:00 AM",
"11:00 AM - 12:00 PM",
 "12:00 PM - 01:00 PM",
 "01:00 PM - 02:00 PM",
 "02:00 PM - 03:00 PM",
 "03:00 PM - 04:00 PM",
 "04:00 PM - 05:00 PM",
 "05:00 PM - 06:00 PM",
];

// Remove booked slots from available slots
foreach ($bookedSlots as $bookedSlot) { 
if (($key = array_search($bookedSlot, $availableSlots)) !== false) {
 unset($availableSlots[$key]);
 }
 }

 // Send available slots as JSON response
 http_response_code(200); // OK
 echo json_encode([
"status" => "success",
 "message" => "Available slots fetched successfully",
 "data" => array_values($availableSlots)
 ]);

} catch (PDOException $e) {
 http_response_code(500); // Internal Server Error
 echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}

// Close connection
$conn = null;

?>

