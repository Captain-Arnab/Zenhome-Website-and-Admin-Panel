<?php
/**
 * Service partner sign-up (legacy form). Request: JSON body as before.
 * Response: {"status":"success"} or {"status":"error","message":"..."}.
 *
 * Uses the shared connection (api/db.php, credentials from .env), validates
 * the input, rejects an already registered mobile number and limits each IP
 * to SAVE_PARTNER_MAX_PER_HOUR attempts (counted in legacy_access_log).
 * Only columns present in service_partners are written.
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");

const SAVE_PARTNER_MAX_PER_HOUR = 10;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function partner_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(["status" => "error", "message" => $message]);
    exit;
}

require_once __DIR__ . '/api/legacy_access.php';
$conn = legacy_db();
if (!$conn) {
    partner_fail(500, "DB Connection Failed");
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    partner_fail(405, "Use POST");
}

// Rate limit per IP (this attempt included).
try {
    $recent = $conn->prepare("SELECT COUNT(*) FROM legacy_access_log WHERE endpoint = 'save_service_partner' AND ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $recent->execute([legacy_client_ip()]);
    $attempts = (int) $recent->fetchColumn();
} catch (Throwable $e) {
    $attempts = 0;
}
$logId = legacy_log('save_service_partner', 'none', null);
$logCtx = ['log_id' => $logId];
if ($attempts >= SAVE_PARTNER_MAX_PER_HOUR) {
    legacy_log_outcome($logCtx, 'rate_limited');
    partner_fail(429, "Too many requests. Please try again later.");
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    legacy_log_outcome($logCtx, 'invalid');
    partner_fail(400, "Invalid JSON Data");
}

function partner_text(array $data, string $key, int $max): ?string
{
    $value = $data[$key] ?? null;
    if ($value === null || is_array($value)) {
        return null;
    }
    $value = trim(strip_tags((string) $value));
    return $value === '' ? null : mb_substr($value, 0, $max);
}

/** Yes/no answers stored in tinyint columns: 1, 0 or null when not answered. */
function partner_flag(array $data, string $key): ?int
{
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true) ? 1 : 0;
}

function partner_mobile(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $digits = preg_replace('/\D/', '', $value);
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : '';
}

$errors = [];
$fullName = partner_text($data, 'full_name', 150);
if ($fullName === null || mb_strlen($fullName) < 2) {
    $errors[] = "Full name is required.";
}
$mobile = partner_mobile(partner_text($data, 'mobile', 20));
if (!$mobile) {
    $errors[] = $mobile === null ? "Mobile number is required." : "Enter a valid 10-digit mobile number.";
}
$email = partner_text($data, 'email', 150);
if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Enter a valid email address.";
}
$dob = partner_text($data, 'dob', 20);
if ($dob !== null) {
    $parsed = null;
    foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'] as $format) {
        $d = DateTime::createFromFormat('!' . $format, $dob);
        if ($d && $d->format($format) === $dob) {
            $parsed = $d;
            break;
        }
    }
    if (!$parsed || $parsed > new DateTime('-16 years') || $parsed < new DateTime('-100 years')) {
        $errors[] = "Enter a valid date of birth.";
    } else {
        $dob = $parsed->format('Y-m-d');
    }
}
$pincode = partner_text($data, 'pincode', 10);
if ($pincode !== null && !preg_match('/^\d{6}$/', $pincode)) {
    $errors[] = "Enter a valid 6-digit pincode.";
}
$emergencyMobile = partner_mobile(partner_text($data, 'emergency_mobile', 20));
if ($emergencyMobile === '') {
    $errors[] = "Enter a valid emergency contact number.";
}
$ifsc = partner_text($data, 'ifsc', 20);
if ($ifsc !== null) {
    $ifsc = strtoupper($ifsc);
    if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
        $errors[] = "Enter a valid IFSC code.";
    }
}
$aadhaar = partner_text($data, 'aadhaar', 20);
if ($aadhaar !== null) {
    $aadhaar = preg_replace('/\D/', '', $aadhaar);
    if (!preg_match('/^\d{12}$/', $aadhaar)) {
        $errors[] = "Enter a valid 12-digit Aadhaar number.";
    }
}
$pan = partner_text($data, 'pan', 10);
if ($pan !== null) {
    $pan = strtoupper($pan);
    if (!preg_match('/^[A-Z]{5}\d{4}[A-Z]$/', $pan)) {
        $errors[] = "Enter a valid PAN number.";
    }
}
$account = partner_text($data, 'account', 50);
if ($account !== null && !preg_match('/^\d{6,20}$/', $account)) {
    $errors[] = "Enter a valid bank account number.";
}
if ($errors) {
    legacy_log_outcome($logCtx, 'invalid');
    partner_fail(422, implode(' ', $errors));
}

$categories = null;
if (isset($data['selectedCategories']) && is_array($data['selectedCategories'])) {
    $list = array_filter(array_map(fn($c) => is_scalar($c) ? trim(strip_tags((string) $c)) : '', $data['selectedCategories']), 'strlen');
    $categories = $list ? mb_substr(implode(",", $list), 0, 2000) : null;
}

$row = [
    'full_name'            => $fullName,
    'mobile'               => $mobile,
    'email'                => $email,
    'dob'                  => $dob,
    'gender'               => partner_text($data, 'gender', 20),
    'current_address'      => partner_text($data, 'address', 1000),
    'city'                 => partner_text($data, 'city', 100),
    'state'                => partner_text($data, 'state', 100),
    'pincode'              => $pincode,
    'primary_category'     => $categories,
    'experience'           => partner_text($data, 'experience', 50),
    'training_institute'   => partner_text($data, 'institute', 150),
    'own_tools'            => partner_flag($data, 'tools'),
    'aadhaar_number'       => $aadhaar,
    'pan_number'           => $pan,
    'bank_name'            => partner_text($data, 'bank', 150),
    'account_number'       => $account,
    'ifsc'                 => $ifsc,
    'commission_accept'    => partner_flag($data, 'commission_accept'),
    'tds_accept'           => partner_flag($data, 'tds_accept'),
    'weekly_payout_accept' => partner_flag($data, 'weekly_payout_accept'),
    'agreement_accept'     => partner_flag($data, 'agreement_accept'),
    'emergency_name'       => partner_text($data, 'emergency_name', 150),
    'emergency_mobile'     => $emergencyMobile,
    'criminal_record'      => partner_flag($data, 'criminal_record'),
    'competitor_platform'  => partner_flag($data, 'competitor'),
    'status'               => 'Pending',
];

try {
    $existing = array_flip($conn->query("SHOW COLUMNS FROM service_partners")->fetchAll(PDO::FETCH_COLUMN));
    $row = array_intersect_key($row, $existing);

    $dup = $conn->prepare("SELECT 1 FROM service_partners WHERE mobile = ? LIMIT 1");
    $dup->execute([$mobile]);
    if ($dup->fetchColumn()) {
        legacy_log_outcome($logCtx, 'duplicate');
        partner_fail(409, "This mobile number is already registered.");
    }

    $columns = '`' . implode('`, `', array_keys($row)) . '`';
    $marks = implode(', ', array_fill(0, count($row), '?'));
    $conn->prepare("INSERT INTO service_partners ($columns) VALUES ($marks)")->execute(array_values($row));
} catch (PDOException $e) {
    error_log('[save_service_partner] ' . $e->getMessage());
    legacy_log_outcome($logCtx, 'error');
    partner_fail(500, "Could not save your details. Please try again.");
}

legacy_log_outcome($logCtx, 'saved');
echo json_encode(["status" => "success"]);
