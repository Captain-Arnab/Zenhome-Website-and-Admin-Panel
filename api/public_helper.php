<?php
/**
 * Shared bits for the public / customer endpoints added for the website and
 * apps (banners, CMS pages, coupon preview, support tickets).
 * Same CORS headers and JSON envelope as the existing customer API:
 *   {"statusCode":200,"status":"success","message":"...","data":{...}}
 *   {"statusCode":422,"status":"error","message":"...","errors":{"field":"..."}}
 */

if (!defined('PUBLIC_HELPER_LOADED')) {
    define('PUBLIC_HELPER_LOADED', true);
}

/** CORS + preflight. Call first. */
function public_cors(string $methods = 'GET, OPTIONS'): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With');
    header('Access-Control-Allow-Methods: ' . $methods);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
}

function public_json(int $code, string $message, $data = null, array $errors = []): void
{
    http_response_code($code);
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

/**
 * 200 JSON that browsers / apps may cache but must revalidate every time
 * (no stale data after admin edits): the ETag is a hash of the body and an
 * unchanged response is answered with an empty 304.
 */
function public_json_revalidate(string $message, $data): void
{
    $body = json_encode(['statusCode' => 200, 'status' => 'success', 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $etag = '"' . md5($body) . '"';
    header('Cache-Control: no-cache');
    header('ETag: ' . $etag);
    $sent = array_map('trim', explode(',', str_replace('W/', '', $_SERVER['HTTP_IF_NONE_MATCH'] ?? '')));
    if (in_array($etag, $sent, true)) {
        http_response_code(304);
        exit;
    }
    http_response_code(200);
    echo $body;
    exit;
}

function public_require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        public_json(405, 'Use ' . implode(' or ', $methods) . ' for this request.');
    }
}

/** Query string merged with a JSON or form body. */
function public_input(): array
{
    $input = $_GET;
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            public_json(400, 'Invalid JSON body.');
        }
    } else {
        $input = array_merge($input, $_POST);
    }
    unset($input['token']);
    return $input;
}

/** @return array{0:int,1:int,2:int} [page, limit, offset] */
function public_page_params(array $in, int $default = 20, int $max = 50): array
{
    $page = max(1, (int) ($in['page'] ?? 1));
    $limit = min($max, max(1, (int) ($in['limit'] ?? $default)));
    return [$page, $limit, ($page - 1) * $limit];
}

function public_pagination(int $page, int $limit, int $total): array
{
    return ['page' => $page, 'limit' => $limit, 'total_items' => $total, 'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0];
}

/** Trimmed string input, or '' (arrays/objects are ignored). */
function public_str(array $in, string $key): string
{
    return isset($in[$key]) && is_scalar($in[$key]) ? trim((string) $in[$key]) : '';
}

/** PDO from api/db.php without leaking its error output into the JSON. */
function public_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    ob_start();
    require __DIR__ . '/db.php';
    $output = trim(ob_get_clean());
    if (!isset($conn) || !$conn instanceof PDO) {
        error_log('[ZenCare public API] DB connection failed: ' . $output);
        public_json(500, 'Service temporarily unavailable. Please try again.');
    }
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->exec('SET NAMES utf8mb4');
    return $pdo = $conn;
}

/** Absolute URL of the website root (https://host/path/), for image links in API responses. */
function public_site_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/';
}
