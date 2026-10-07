<?php
/**
 * ZEN HOME EXPERTS - Send OTP SMS via the Bulk SMS Hyderabad gateway.
 * Called by login.php, password_reset.php and admin/assignTechnician.php:
 * sendOtpSms($phone, $otp, $purpose).
 *
 * Gateway settings come from .env (the same keys the admin SMS module uses):
 *   SMS_GATEWAY_URL, SMS_GATEWAY_USER, SMS_GATEWAY_PASSWORD, SMS_GATEWAY_SENDER,
 *   SMS_GATEWAY_PEID, SMS_OTP_TEMPLATE_ID (DLT template id of the OTP text).
 * SMS_OTP_ENABLED=false turns OTP sending off; when it is not set OTPs are sent.
 * SMS_ENABLED only controls the admin notification/campaign SMS, not OTPs.
 *
 * Returns ['success' => bool, 'error' => string] and never exposes the
 * gateway credentials or raw response to the caller's client.
 */
require_once __DIR__ . '/runtime.php';

function sms_sender_env(string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null) ? $default : trim((string) $value);
}

function sendOtpSms($mobile, $otp, $purpose = 'login') {
    $mobile = preg_replace('/[^0-9]/', '', (string) $mobile);
    if (strlen($mobile) === 11 && substr($mobile, 0, 1) === '0') {
        $mobile = substr($mobile, 1);
    }
    if (strlen($mobile) === 12 && substr($mobile, 0, 2) === '91') {
        $mobile = substr($mobile, 2);
    }
    if (strlen($mobile) !== 10) {
        return ['success' => false, 'error' => 'Invalid mobile number'];
    }

    $enabled = strtolower(sms_sender_env('SMS_OTP_ENABLED', 'true'));
    if (in_array($enabled, ['0', 'false', 'no', 'off'], true)) {
        return ['success' => false, 'error' => 'OTP SMS sending is disabled (SMS_OTP_ENABLED=false).'];
    }

    $config = [
        'url'      => sms_sender_env('SMS_GATEWAY_URL', 'http://tra.bulksmshyderabad.co.in/websms/sendsms.aspx'),
        'user'     => sms_sender_env('SMS_GATEWAY_USER'),
        'password' => sms_sender_env('SMS_GATEWAY_PASSWORD'),
        'sender'   => sms_sender_env('SMS_GATEWAY_SENDER'),
        'peid'     => sms_sender_env('SMS_GATEWAY_PEID'),
        'tpid'     => sms_sender_env('SMS_OTP_TEMPLATE_ID'),
    ];
    foreach ($config as $key => $value) {
        if ($value === '') {
            error_log('[ZenHomeExperts SMS] OTP not sent: SMS gateway setting "' . $key . '" is missing in .env.');
            return ['success' => false, 'error' => 'SMS gateway is not configured.'];
        }
    }

    // The DLT-registered OTP template text: do not change without re-approval.
    $message = "Your OTP is $otp for $purpose.\nPlease do not share this code with anyone.\n- ZEN HOME EXPERTS\nzenhomeexperts.com";

    $url = $config['url'] . (strpos($config['url'], '?') === false ? '?' : '&') . http_build_query([
        'userid'   => $config['user'],
        'password' => $config['password'],
        'sender'   => $config['sender'],
        'mobileno' => $mobile,
        'msg'      => $message,
        'peid'     => $config['peid'],
        'tpid'     => $config['tpid'],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $output = curl_exec($ch);
    $curlError = curl_errno($ch) ? curl_error($ch) : '';
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = trim((string) $output);
    if ($curlError !== '') {
        error_log('[ZenHomeExperts SMS] OTP gateway error: ' . $curlError);
        return ['success' => false, 'error' => 'SMS gateway unreachable.'];
    }
    if ($http >= 400 || $body === '' || preg_match('/\b(error|invalid|fail(ed|ure)?|denied|insufficient|unauthori[sz]ed)\b/i', $body)) {
        error_log('[ZenHomeExperts SMS] OTP rejected by gateway (HTTP ' . $http . '): ' . mb_substr($body, 0, 300));
        return ['success' => false, 'error' => 'SMS gateway rejected the message.'];
    }
    return ['success' => true, 'error' => ''];
}
