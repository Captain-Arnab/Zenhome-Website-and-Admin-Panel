<?php
/**
 * ZEN HOME EXPERTS – Send OTP via Bulk SMS Hyderabad gateway.
 * Include this file and call sendOtpSms($phone, $otp) from login.php and register.php.
 *
 * Credentials:
 * Username: ZENCARE, Password: 123Zen, Sender: ZENCAE
 * DLT: peid=1701177140036334378, tpid=1707177191868029146
 */
function sendOtpSms($mobile, $otp, $purpose = 'login') {
    // Sanitize mobile number
    $mobile = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($mobile) === 11 && substr($mobile, 0, 1) === '0') {
        $mobile = substr($mobile, 1);
    }
    if (strlen($mobile) === 12 && substr($mobile, 0, 2) === '91') {
        $mobile = substr($mobile, 2);
    }
    if (strlen($mobile) !== 10) {
        return ['success' => false, 'error' => 'Invalid mobile number'];
    }

    $user_id  = 'ZENCARE';
    $pwd      = '123Zen';
    $sender   = 'ZENCAE';
    $peid     = '1701177140036334378';
    $tpid     = '1707177191868029146';

    $message = "Your OTP is $otp for $purpose.\nPlease do not share this code with anyone.\n- ZEN HOME EXPERTS\nzenhomeexperts.com";

    // Build URL exactly as per the API format provided
    $url = "http://tra.bulksmshyderabad.co.in/websms/sendsms.aspx"
        . "?userid=" . $user_id
        . "&password=" . $pwd
        . "&sender=" . $sender
        . "&mobileno=" . urlencode($mobile)
        . "&msg=" . urlencode($message)
        . "&peid=" . $peid
        . "&tpid=" . $tpid;

    // Send via PHP CURL (as per sample code)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return response as string
    $output = curl_exec($ch);

    // Capture any CURL error
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => 'CURL error: ' . $error];
    }

    curl_close($ch);

    return ['success' => true, 'response' => $output];
}