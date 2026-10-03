<?php
/**
 * Admin API bootstrap: config, DB connection and shared helpers.
 * Produces no output, so it is safe to include from the admin pages
 * (in-process calls) as well as from the HTTP endpoints (core/http.php).
 */
if (defined('ZC_ADMIN_API')) {
    return;
}
define('ZC_ADMIN_API', true);
define('ZC_ROOT', dirname(__DIR__, 3));            // project root (website)
require_once ZC_ROOT . '/api/runtime.php';

// .env values (DB, PhonePe, SMS gateway ...). Same loader as api/payments.php.
if (is_file(ZC_ROOT . '/vendor/autoload.php')) {
    require_once ZC_ROOT . '/vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable(ZC_ROOT)->safeLoad();
    }
}

function env_value(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

function env_flag(string $key, bool $default = false): bool
{
    $value = env_value($key);
    return $value === null ? $default : in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

date_default_timezone_set(env_value('APP_TIMEZONE', 'Asia/Kolkata'));

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/status.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/router.php';

/**
 * Shared PDO connection from api/db.php (same file the public API uses).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    ob_start();
    require ZC_ROOT . '/api/db.php';
    $output = trim(ob_get_clean());
    if (!isset($conn) || !$conn instanceof PDO) {
        error_log('[ZenCare admin API] DB connection failed: ' . $output);
        throw new ApiException('Database connection failed.', 500);
    }
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->exec('SET NAMES utf8mb4');
    $conn->exec("SET time_zone = '" . date('P') . "'");
    return $pdo = $conn;
}
