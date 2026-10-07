<?php
/**
 * Customer "forgot password" by SMS OTP (no login needed).
 *
 * POST ?action=request {phone}                     sends a 6-digit OTP to a registered number
 * POST ?action=reset   {phone, otp, password}      sets the new password, signs out all devices
 *
 * The request step always answers the same way, so it can't be used to find
 * out which numbers are registered. OTPs are stored hashed, expire after 10
 * minutes and allow 5 wrong tries; at most 3 OTPs per hour, 60 s apart.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';
require __DIR__ . '/sms_sender.php';

const RESET_OTP_TTL = 600;
const RESET_MAX_ATTEMPTS = 5;
const RESET_MAX_SENDS = 3;
const RESET_RESEND_GAP = 60;
const RESET_MIN_PASSWORD = 6;

public_cors('POST, OPTIONS');
public_require_method('POST');

$in = public_input();
$action = public_str($in, 'action') ?: (public_str($in, 'otp') !== '' ? 'reset' : 'request');
if (!in_array($action, ['request', 'reset'], true)) {
    public_json(422, 'Unknown action.', null, ['action' => 'Use request or reset.']);
}

$phone = zc_normalize_phone(public_str($in, 'phone'));
if ($phone === null) {
    public_json(422, 'Enter a valid 10-digit mobile number.', null, ['phone' => 'Enter a valid 10-digit mobile number.']);
}

$conn = public_db();
$stmt = $conn->prepare('SELECT ID, status FROM users WHERE phone = ? LIMIT 1');
$stmt->execute([$phone]);
$user = $stmt->fetch();
$disabled = $user && in_array(strtolower(trim((string) $user['status'])), ['inactive', 'blocked', 'disabled'], true);

$tz = new DateTimeZone($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');
$now = new DateTimeImmutable('now', $tz);
$fmt = fn(DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s');

$row = null;
if ($user) {
    $stmt = $conn->prepare('SELECT * FROM password_resets WHERE user_id = ?');
    $stmt->execute([(int) $user['ID']]);
    $row = $stmt->fetch() ?: null;
}

if ($action === 'request') {
    $sentMessage = 'If this number is registered, an OTP has been sent to it. It is valid for 10 minutes.';
    if (!$user || $disabled) {
        public_json(200, $sentMessage, ['expires_in' => RESET_OTP_TTL]);
    }

    $windowStart = $row ? new DateTimeImmutable($row['window_started_at'], $tz) : null;
    $inWindow = $windowStart && $now->getTimestamp() - $windowStart->getTimestamp() < 3600;
    if ($row) {
        $wait = RESET_RESEND_GAP - ($now->getTimestamp() - (new DateTimeImmutable($row['last_sent_at'], $tz))->getTimestamp());
        if ($wait > 0) {
            public_json(429, "Please wait {$wait} seconds before requesting another OTP.", ['retry_after' => $wait]);
        }
        if ($inWindow && (int) $row['sent_count'] >= RESET_MAX_SENDS) {
            public_json(429, 'Too many OTP requests. Please try again in an hour.');
        }
    }

    $otp = (string) random_int(100000, 999999);
    $conn->prepare(
        'INSERT INTO password_resets (user_id, otp_hash, expires_at, attempts, sent_count, window_started_at, last_sent_at)
         VALUES (?, ?, ?, 0, 1, ?, ?)
         ON DUPLICATE KEY UPDATE otp_hash = VALUES(otp_hash), expires_at = VALUES(expires_at), attempts = 0,
             sent_count = ?, window_started_at = ?, last_sent_at = VALUES(last_sent_at)'
    )->execute([
        (int) $user['ID'], password_hash($otp, PASSWORD_DEFAULT), $fmt($now->modify('+' . RESET_OTP_TTL . ' seconds')), $fmt($now), $fmt($now),
        $inWindow ? (int) $row['sent_count'] + 1 : 1, $inWindow ? $row['window_started_at'] : $fmt($now),
    ]);

    $sms = sendOtpSms($phone, $otp, 'password reset');
    if (empty($sms['success'])) {
        $conn->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['ID']]);
        error_log('[ZenHomeExperts password reset] SMS failed: ' . ($sms['error'] ?? 'unknown'));
        public_json(502, 'We could not send the OTP right now. Please try again in a few minutes.');
    }
    public_json(200, $sentMessage, ['expires_in' => RESET_OTP_TTL]);
}

// action = reset
$otp = public_str($in, 'otp');
$password = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
$errors = [];
if (!preg_match('/^\d{6}$/', $otp)) {
    $errors['otp'] = 'Enter the 6-digit OTP.';
}
if (strlen($password) < RESET_MIN_PASSWORD) {
    $errors['password'] = 'Password must be at least ' . RESET_MIN_PASSWORD . ' characters.';
} elseif (strlen($password) > 72) {
    $errors['password'] = 'Password must be 72 characters or fewer.';
}
if ($errors) {
    public_json(422, reset($errors), null, $errors);
}

$invalid = fn(string $message) => public_json(422, $message, null, ['otp' => $message]);
if (!$user || $disabled || !$row) {
    $invalid('OTP not found or expired. Please request a new OTP.');
}
if ($now > new DateTimeImmutable($row['expires_at'], $tz) || (int) $row['attempts'] >= RESET_MAX_ATTEMPTS) {
    $conn->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['ID']]);
    $invalid((int) $row['attempts'] >= RESET_MAX_ATTEMPTS ? 'Too many wrong tries. Please request a new OTP.' : 'OTP expired. Please request a new OTP.');
}
if (!password_verify($otp, $row['otp_hash'])) {
    $conn->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE user_id = ?')->execute([(int) $user['ID']]);
    $left = RESET_MAX_ATTEMPTS - (int) $row['attempts'] - 1;
    $invalid($left > 0 ? "Incorrect OTP. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.' : 'Too many wrong tries. Please request a new OTP.');
}

$conn->beginTransaction();
$conn->prepare('UPDATE users SET password = ? WHERE ID = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['ID']]);
$conn->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['ID']]);
invalidateSession($conn, null, (int) $user['ID']);
$conn->commit();

public_json(200, 'Your password has been changed. Please sign in with your new password.');
