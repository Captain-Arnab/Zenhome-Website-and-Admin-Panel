<?php
// Shared PDO connection ($conn). Credentials come from the project .env
// (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD) - never hard-code them here.
//
// On a connection failure the error is logged (never shown) and the request
// ends with a JSON 503. Callers that handle a missing $conn themselves set
// $zcDbSoftFail = true before including this file (public_db, legacy_db,
// site_content_db, the admin bootstrap); CLI scripts are always "soft".
require_once __DIR__ . '/runtime.php';
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    }
}

$zcEnv = function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null) ? $default : (string) $value;
};
$host = $zcEnv('DB_HOST') ?: 'localhost';
$db   = $zcEnv('DB_NAME') ?: 'zencareservice_servicy';
$user = $zcEnv('DB_USER');
$pass = $zcEnv('DB_PASSWORD');
unset($zcEnv);

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $exception) {
    $conn = null;
    error_log('[ZenHomeExperts db] Connection failed: ' . $exception->getMessage());
    if (empty($zcDbSoftFail) && PHP_SAPI !== 'cli') {
        zc_json_exit(503, 'Service temporarily unavailable. Please try again.');
    }
}
