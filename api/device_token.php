<?php
/**
 * Register / unregister the app's FCM push token for the signed-in customer.
 * Customer login token required, sent in the Authorization (or X-Auth-Token)
 * header: the body field "token" here is the FCM token, not the login token.
 *
 * POST   {token | fcm_token, platform: "android"|"ios", app_version?}  register (upsert)
 * DELETE {token | fcm_token}  (or ?fcm_token=)                         unregister
 * POST   {action: "remove", token | fcm_token}                         unregister (clients without DELETE)
 *
 * One row per FCM token (device_tokens.fcm_token is unique): registering a
 * token already linked to another account moves it to this one. A customer
 * keeps at most 10 devices; the least recently updated are dropped.
 * Nothing is sent to these tokens yet (push delivery is not wired).
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';

const DEVICE_MAX_PER_USER = 10;

public_cors('POST, DELETE, OPTIONS');
public_require_method('POST', 'DELETE');

$conn = public_db();
$auth = requireAuth($conn);
$userId = (int) $auth['user_id'];

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    $body = $_POST;
}
$body += array_intersect_key($_GET, ['fcm_token' => 1, 'platform' => 1, 'app_version' => 1, 'action' => 1]);

$fcm = public_str($body, 'fcm_token') ?: public_str($body, 'token');
if (!preg_match('/^[A-Za-z0-9:_\-.]{20,255}$/', $fcm)) {
    public_json(422, 'A valid FCM token is required.', null, ['token' => 'Send the FCM registration token (20-255 characters).']);
}

$remove = $_SERVER['REQUEST_METHOD'] === 'DELETE' || strtolower(public_str($body, 'action')) === 'remove';
if ($remove) {
    $stmt = $conn->prepare('DELETE FROM device_tokens WHERE fcm_token = ? AND user_id = ?');
    $stmt->execute([$fcm, $userId]);
    public_json(200, 'Device unregistered.', ['removed' => $stmt->rowCount()]);
}

$platform = strtolower(public_str($body, 'platform'));
$appVersion = public_str($body, 'app_version');
$errors = [];
if (!in_array($platform, ['android', 'ios'], true)) {
    $errors['platform'] = 'Use android or ios.';
}
if ($appVersion !== '' && !preg_match('/^[0-9A-Za-z.+\-]{1,20}$/', $appVersion)) {
    $errors['app_version'] = 'Use a version like 1.2.0 (max 20 characters).';
}
if ($errors) {
    public_json(422, reset($errors), null, $errors);
}

$now = date('Y-m-d H:i:s');
$conn->prepare(
    'INSERT INTO device_tokens (user_id, fcm_token, platform, app_version, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform), app_version = VALUES(app_version), updated_at = VALUES(updated_at)'
)->execute([$userId, $fcm, $platform, $appVersion !== '' ? $appVersion : null, $now, $now]);

$conn->prepare(
    'DELETE FROM device_tokens WHERE user_id = ? AND id NOT IN (
        SELECT id FROM (SELECT id FROM device_tokens WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT ' . DEVICE_MAX_PER_USER . ') keep
     )'
)->execute([$userId, $userId]);

public_json(200, 'Device registered.', ['platform' => $platform, 'app_version' => $appVersion !== '' ? $appVersion : null]);
