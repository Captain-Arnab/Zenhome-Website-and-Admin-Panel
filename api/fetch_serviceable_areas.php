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

// Areas (and cities) disabled in the admin panel are not offered; ?include_disabled=1 returns all.
$where = !empty($_GET['include_disabled']) ? '' : " WHERE a.status = 1 AND (a.city_id IS NULL OR EXISTS (SELECT 1 FROM cities c WHERE c.id = a.city_id AND c.status = 1))";
$stmt = $conn->query("SELECT a.id, a.name, a.pincode FROM serviceable_areas a" . $where . " ORDER BY a.sort_order ASC, a.name ASC");
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format for dropdown: { value: pincode or "name - pincode", label: "Name (Pincode)" }
$list = [];
foreach ($areas as $row) {
    $list[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'pincode' => $row['pincode'],
        'label' => $row['name'] . ' - ' . $row['pincode'],
        'value' => $row['pincode']
    ];
}

echo json_encode([
    "statusCode" => 200,
    "status" => "success",
    "serviceable_areas" => $list
]);
