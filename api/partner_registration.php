<?php
/**
 * ZEN HOME EXPERTS – Service Partner Registration API
 * POST: Submit full partner form (from Flutter Web / Android).
 * Data is stored in `service_partners` table.
 */

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

include __DIR__ . '/db.php';

// Optional: save base64 image to uploads and return path
function saveBase64File($base64Data, $subdir, $prefix = 'file') {
    if (empty($base64Data) || strpos($base64Data, 'data:') !== 0) {
        return $base64Data; // not base64, return as-is (URL/path)
    }
    if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $m)) {
        $ext = 'bin';
        if (preg_match('/image\/(jpeg|jpg|png|gif|webp)/', $m[1], $img)) $ext = $img[1] === 'jpeg' ? 'jpg' : $img[1];
        if (preg_match('/application\/pdf/', $m[1])) $ext = 'pdf';
        $dir = __DIR__ . '/uploads/partners/' . $subdir;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . '/' . $prefix . '_' . uniqid() . '.' . $ext;
        $decoded = base64_decode($m[2], true);
        if ($decoded !== false && file_put_contents($path, $decoded)) {
            return 'uploads/partners/' . $subdir . '/' . basename($path);
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['statusCode' => 405, 'status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['statusCode' => 400, 'status' => 'error', 'message' => 'Invalid JSON body.']);
    exit;
}

// Required fields (basic)
$required = ['full_name', 'mobile', 'email'];
foreach ($required as $f) {
    if (empty(trim($data[$f] ?? ''))) {
        http_response_code(400);
        echo json_encode(['statusCode' => 400, 'status' => 'error', 'message' => "Required field missing: $f"]);
        exit;
    }
}

// Optional file fields: if value is base64 data URL, save to disk and store path
$fileFields = [
    'photo' => 'photo',
    'aadhaar_front' => 'aadhaar',
    'aadhaar_back' => 'aadhaar',
    'pan_file' => 'kyc',
    'police_verification' => 'kyc',
    'cancelled_cheque' => 'bank',
    'certification_file' => 'certification',
];
foreach ($fileFields as $key => $subdir) {
    if (!empty($data[$key])) {
        $saved = saveBase64File($data[$key], $subdir, $key);
        if ($saved) $data[$key] = $saved;
    }
}

// Map form fields to DB columns (snake_case from app)
$map = [
    'full_name' => 'full_name',
    'mobile' => 'mobile',
    'alternate_mobile' => 'alternate_mobile',
    'email' => 'email',
    'dob' => 'dob',
    'gender' => 'gender',
    'photo' => 'photo',
    'current_address' => 'current_address',
    'permanent_address' => 'permanent_address',
    'city' => 'city',
    'district' => 'district',
    'state' => 'state',
    'pincode' => 'pincode',
    'landmark' => 'landmark',
    'serviceable_areas' => 'serviceable_areas',   // JSON string or comma-separated
    'primary_category' => 'primary_category',     // JSON string or text
    'sub_services' => 'sub_services',             // JSON string or text
    'experience' => 'experience',
    'certification_file' => 'certification_file',
    'training_institute' => 'training_institute',
    'previous_company' => 'previous_company',
    'own_tools' => 'own_tools',
    'purchase_kit' => 'purchase_kit',
    'working_days' => 'working_days',              // e.g. ["Mon","Tue"] or JSON string
    'time_slots' => 'time_slots',
    'aadhaar_front' => 'aadhaar_front',
    'aadhaar_back' => 'aadhaar_back',
    'pan_file' => 'pan_file',
    'police_verification' => 'police_verification',
    'gst_number' => 'gst_number',
    'account_holder_name' => 'account_holder_name',
    'bank_name' => 'bank_name',
    'account_number' => 'account_number',
    'ifsc' => 'ifsc',
    'cancelled_cheque' => 'cancelled_cheque',
    'commission_accept' => 'commission_accept',
    'tds_accept' => 'tds_accept',
    'weekly_payout_accept' => 'weekly_payout_accept',
    'agreement_accept' => 'agreement_accept',
    'digital_signature' => 'digital_signature',
    'rating_accept' => 'rating_accept',
    'cancellation_policy_accept' => 'cancellation_policy_accept',
    'non_circumvention_accept' => 'non_circumvention_accept',
    'code_of_conduct_accept' => 'code_of_conduct_accept',
    'emergency_name' => 'emergency_name',
    'emergency_mobile' => 'emergency_mobile',
    'reference_name' => 'reference_name',
    'reference_mobile' => 'reference_mobile',
    'criminal_record' => 'criminal_record',
    'competitor_platform' => 'competitor_platform',
    'self_employment_declaration' => 'self_employment_declaration',
    'background_verification_consent' => 'background_verification_consent',
    'final_consent' => 'final_consent',
];

$cols = [];
$vals = [];
$placeholders = [];

foreach ($map as $formKey => $dbCol) {
    $cols[] = "`$dbCol`";
    $placeholders[] = '?';
    $v = $data[$formKey] ?? null;
    if ($v === null || $v === '') {
        $vals[] = null;
        continue;
    }
    if (in_array($formKey, ['serviceable_areas', 'primary_category', 'sub_services', 'working_days']) && is_array($v)) {
        $vals[] = json_encode($v);
    } elseif (in_array($formKey, ['own_tools', 'purchase_kit', 'commission_accept', 'tds_accept', 'weekly_payout_accept',
        'agreement_accept', 'rating_accept', 'cancellation_policy_accept', 'non_circumvention_accept', 'code_of_conduct_accept',
        'criminal_record', 'competitor_platform', 'self_employment_declaration', 'background_verification_consent', 'final_consent'])) {
        $vals[] = ($v === true || $v === 1 || $v === '1' || strtolower($v) === 'yes') ? 1 : 0;
    } else {
        $vals[] = is_string($v) ? trim($v) : $v;
    }
}

$cols[] = '`status`';
$placeholders[] = '?';
$vals[] = 'Pending';

$sql = 'INSERT INTO service_partners (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($vals);
    $partnerId = (int) $conn->lastInsertId();
    http_response_code(201);
    echo json_encode([
        'statusCode' => 201,
        'status' => 'success',
        'message' => 'Partner registration submitted successfully.',
        'partner_id' => $partnerId,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'status' => 'error',
        'message' => 'Registration failed. Please try again.',
    ]);
    error_log('[partner_registration] ' . $e->getMessage());
}
