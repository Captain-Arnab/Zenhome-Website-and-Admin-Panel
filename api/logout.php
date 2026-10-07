<?php
/**
 * Sign out: POST with the session token. Deletes only this session; the
 * saved cart is kept so it is still there at the next sign-in. Optional
 * body `fcm_token` also unregisters that device from push (device_tokens).
 * Always 200, even when the token was already invalid.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';

public_cors('POST, OPTIONS');
public_require_method('POST');

$conn = public_db();
$token = getBearerToken();
if ($token) {
    $auth = getUserIdFromRequest($conn);
    $body = json_decode(file_get_contents('php://input') ?: '', true);
    $fcmToken = public_str(is_array($body) ? $body : $_POST, 'fcm_token');
    if ($auth && $fcmToken !== '') {
        try {
            $conn->prepare('DELETE FROM device_tokens WHERE fcm_token = ? AND user_id = ?')->execute([$fcmToken, $auth['user_id']]);
        } catch (Throwable $e) {
            error_log('[ZenHomeExperts logout] device token cleanup failed: ' . $e->getMessage());
        }
    }
    invalidateSession($conn, $token, null);
}

public_json(200, 'Logged out successfully.');
