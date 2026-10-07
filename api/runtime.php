<?php
/**
 * PHP error handling for every request. Included first by api/db.php,
 * api/site_content.php and the admin bootstrap.
 *
 * Errors are never shown to visitors unless APP_DEBUG=true in .env; they are
 * written to APP_LOG_DIR/php_errors.log (default logs/, which is blocked
 * from the web by logs/.htaccess and the root .htaccess).
 */
if (defined('ZC_RUNTIME')) {
    return;
}
define('ZC_RUNTIME', true);

(function (): void {
    $root = dirname(__DIR__);
    if (is_file($root . '/vendor/autoload.php')) {
        require_once $root . '/vendor/autoload.php';
        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }
    }
    $env = function (string $key): string {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($value === false || $value === null) ? '' : trim((string) $value);
    };

    $debug = in_array(strtolower($env('APP_DEBUG')), ['1', 'true', 'yes', 'on'], true);
    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('display_startup_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');

    $dir = $env('APP_LOG_DIR') !== '' ? rtrim($env('APP_LOG_DIR'), '/\\') : $root . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        ini_set('error_log', $dir . '/php_errors.log');
    }
})();

if (!function_exists('zc_json_exit')) {
    /** Ends the request with the standard JSON envelope; HTTP code and statusCode always match. */
    function zc_json_exit(int $code, string $message, $data = null, array $errors = []): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
        }
        $body = ['statusCode' => $code, 'status' => $code < 400 ? 'success' : 'error', 'message' => $message];
        if ($data !== null) {
            $body['data'] = $data;
        }
        if ($errors) {
            $body['errors'] = $errors;
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

// API scripts answer uncaught errors with the JSON 500 envelope instead of an
// empty body; website pages keep the normal PHP error page.
if (PHP_SAPI !== 'cli' && strpos(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/api/') !== false) {
    set_exception_handler(function (Throwable $e): void {
        error_log('[ZenHomeExperts API] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        zc_json_exit(500, 'Server error. Please try again.');
    });
}

if (!function_exists('app_url')) {
    /** Canonical site base URL (APP_URL in .env). One place to change the domain. */
    function app_url(string $path = ''): string
    {
        $base = $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? getenv('APP_URL') ?: 'https://zenhomeexperts.com';
        return rtrim((string) $base, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}
