<?php
/**
 * Customer sign-in, two steps (POST only, JSON or form body):
 *
 *   1. {phone, password}  -> password checked, 6-digit OTP sent by SMS
 *                            (status "otp_sent"; data.expires_in, data.resend_after)
 *   2. {phone, otp}       -> session token (top-level token/user kept for the
 *                            website and older app builds; also under data)
 *
 * OTPs live in login_otps: stored hashed, valid 5 minutes, 5 wrong tries,
 * at most 3 sends per hour per phone and 60 s between sends. Token lifetime
 * is LOGIN_TOKEN_DAYS (default 30).
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/sms_sender.php';

const LOGIN_OTP_TTL = 300;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_MAX_SENDS = 3;
const LOGIN_RESEND_GAP = 60;

public_cors('POST, OPTIONS');
public_require_method('POST');

/** public_json() plus extra top-level keys the existing clients read. */
function login_json(int $code, string $status, string $message, array $top = [], $data = null, array $errors = []): void {
    http_response_code($code);
    $body = ['statusCode' => $code, 'status' => $status, 'message' => $message] + $top;
    if ($data !== null) {
        $body['data'] = $data;
    }
    if ($errors) {
        $body['errors'] = $errors;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$in = public_input();
$phone = zc_normalize_phone($in['phone'] ?? '');
if (public_str($in, 'phone') === '') {
    public_json(400, 'Phone number is required.', null, ['phone' => 'Phone number is required.']);
}
if ($phone === null) {
    public_json(400, 'Invalid phone number.', null, ['phone' => 'Enter a valid 10-digit mobile number.']);
}

$conn = public_db();
$isDisabled = fn(array $u): bool => in_array(strtolower(trim((string) $u['status'])), ['inactive', 'blocked', 'disabled'], true);
$deleteOtp = fn() => $conn->prepare('DELETE FROM login_otps WHERE phone = ?')->execute([$phone]);

// ---------- Step 2: Verify OTP and complete login ----------
if (isset($in['otp']) && public_str($in, 'otp') !== '') {
    $otp = public_str($in, 'otp');

    $stmt = $conn->prepare('SELECT otp_hash, expires_at, attempts FROM login_otps WHERE phone = ?');
    $stmt->execute([$phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        public_json(400, 'OTP not found or expired. Please request a new OTP.', null, ['otp' => 'OTP not found or expired.']);
    }
    if ((int) $row['attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $deleteOtp();
        public_json(400, 'Too many wrong tries. Please request a new OTP.', null, ['otp' => 'Too many wrong tries.']);
    }
    if (time() > strtotime($row['expires_at'])) {
        $deleteOtp();
        public_json(400, 'OTP expired. Please request a new OTP.', null, ['otp' => 'OTP expired.']);
    }
    if (!preg_match('/^\d{6}$/', $otp) || !password_verify($otp, $row['otp_hash'])) {
        $left = LOGIN_MAX_ATTEMPTS - (int) $row['attempts'] - 1;
        if ($left > 0) {
            $conn->prepare('UPDATE login_otps SET attempts = attempts + 1 WHERE phone = ?')->execute([$phone]);
            $message = "Invalid OTP. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.';
        } else {
            $deleteOtp();
            $message = 'Too many wrong tries. Please request a new OTP.';
        }
        public_json(400, $message, ['attempts_left' => max(0, $left)], ['otp' => $message]);
    }

    $userStmt = $conn->prepare('SELECT ID, first_name, last_name, email, phone, address, photo, status FROM users WHERE phone = ?');
    $userStmt->execute([$phone]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $deleteOtp();
        public_json(401, 'User not found.');
    }
    if ($isDisabled($user)) {
        $deleteOtp();
        public_json(403, 'Your account has been deactivated. Please contact support.');
    }

    $deleteOtp();
    $lifetime = zc_login_token_lifetime();
    $token = createSession($conn, (int) $user['ID'], $lifetime);
    $profile = zc_user_profile($user);
    $expiresAt = date('c', time() + $lifetime);

    login_json(200, 'success', 'Login successful.', [
        'token' => $token,
        'user' => $profile,
        'expires_in_hours' => intdiv($lifetime, 3600),
    ], [
        'token' => $token,
        'user' => $profile,
        'expires_in' => $lifetime,
        'expires_at' => $expiresAt,
    ]);
}

// ---------- Step 1: Phone + Password -> send OTP ----------
$password = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
if ($password === '') {
    public_json(400, 'Phone and password are required.', null, ['password' => 'Password is required.']);
}

$stmt = $conn->prepare('SELECT ID, password, status FROM users WHERE phone = ?');
$stmt->execute([$phone]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, (string) $user['password'])) {
    public_json(401, 'Invalid phone or password.');
}

// Deactivated in the admin panel. Legacy status '0' is not blocked here: some
// existing, active accounts carry it.
if ($isDisabled($user)) {
    public_json(403, 'Your account has been deactivated. Please contact support.');
}

$stmt = $conn->prepare('SELECT sent_count, window_started_at, last_sent_at FROM login_otps WHERE phone = ?');
$stmt->execute([$phone]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

$now = time();
$inWindow = $row && $now - strtotime($row['window_started_at']) < 3600;
if ($row) {
    $wait = LOGIN_RESEND_GAP - ($now - strtotime($row['last_sent_at']));
    if ($wait > 0) {
        header('Retry-After: ' . $wait);
        public_json(429, "Please wait {$wait} seconds before requesting another OTP.", ['retry_after' => $wait]);
    }
    if ($inWindow && (int) $row['sent_count'] >= LOGIN_MAX_SENDS) {
        $wait = 3600 - ($now - strtotime($row['window_started_at']));
        header('Retry-After: ' . $wait);
        public_json(429, 'Too many OTP requests. Please try again in an hour.', ['retry_after' => $wait]);
    }
}

$otp = (string) random_int(100000, 999999);
$fmt = fn(int $t): string => date('Y-m-d H:i:s', $t);
$conn->prepare(
    'INSERT INTO login_otps (phone, otp_hash, expires_at, attempts, sent_count, window_started_at, last_sent_at)
     VALUES (?, ?, ?, 0, ?, ?, ?)
     ON DUPLICATE KEY UPDATE otp_hash = VALUES(otp_hash), expires_at = VALUES(expires_at), attempts = 0,
         sent_count = VALUES(sent_count), window_started_at = VALUES(window_started_at), last_sent_at = VALUES(last_sent_at)'
)->execute([
    $phone, password_hash($otp, PASSWORD_DEFAULT), $fmt($now + LOGIN_OTP_TTL),
    $inWindow ? (int) $row['sent_count'] + 1 : 1, $inWindow ? $row['window_started_at'] : $fmt($now), $fmt($now),
]);

$sms = sendOtpSms($phone, $otp, 'login');
if (empty($sms['success'])) {
    // Keep the row (and its send count) so a failing gateway can't be retried
    // past the hourly limit, but make the unsent code unusable.
    $conn->prepare('UPDATE login_otps SET expires_at = ? WHERE phone = ?')->execute([$fmt($now - 1), $phone]);
    error_log('[ZenHomeExperts login] OTP SMS failed: ' . ($sms['error'] ?? 'unknown'));
    public_json(502, 'Failed to send OTP. Please try again.');
}

login_json(200, 'otp_sent', 'OTP sent to your mobile number. Enter it to complete login.', [], [
    'expires_in' => LOGIN_OTP_TTL,
    'resend_after' => LOGIN_RESEND_GAP,
]);
