<?php
/**
 * HTTP layer for admin endpoints: CORS (same headers as the existing API),
 * request parsing, auth, CSRF, JSON responses.
 *
 * Endpoint files are two lines:
 *   require __DIR__ . '/core/http.php';
 *   api_serve('customers');
 *
 * Request:  ?action=<name>, GET for reads, POST for writes. Body may be
 *           JSON, form-data (file uploads) or urlencoded.
 * Response: {"statusCode":200,"status":"success","message":"...","data":{...}}
 *           {"statusCode":422,"status":"error","message":"...","errors":{"field":"..."}}
 */
require_once __DIR__ . '/bootstrap.php';

function api_cors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-CSRF-Token, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

function api_respond(int $code, string $status, string $message, $data = null, array $errors = []): void
{
    http_response_code($code);
    $body = ['statusCode' => $code, 'status' => $status, 'message' => $message];
    if ($data !== null) {
        $body['data'] = $data;
    }
    if ($errors) {
        $body['errors'] = $errors;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

/** Merged request input: query string + form/multipart + JSON body. */
function api_input(): array
{
    $input = $_GET;
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== false) {
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            throw new ApiException('Invalid JSON body.', 400);
        }
    } else {
        $input = array_merge($input, $_POST);
    }
    unset($input['token']);
    return $input;
}

function api_serve(string $module): void
{
    api_cors();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    try {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $input  = api_input();
        $action = (string) ($input['action'] ?? '');
        $routes = api_routes($module);
        $role   = isset($routes[$action]) ? ($routes[$action][2] ?? 'admin') : 'admin';

        $admin = $role === 'public' ? null : admin_current();
        if ($role !== 'public' && !$admin) {
            throw new ApiException('Unauthorized or session expired. Please log in again.', 401);
        }
        // Browser sessions must prove the request came from the admin panel.
        if ($method !== 'GET' && ($GLOBALS['zc_auth_via'] ?? '') === 'session') {
            $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? null);
            if (!csrf_valid(is_string($csrf) ? $csrf : null)) {
                // 403, not 419: Apache rewrites non-standard status codes to 500.
                throw new ApiException('Security token expired. Refresh the page and try again.', 403, ['csrf' => 'Invalid or missing CSRF token.']);
            }
        }
        if (session_status() === PHP_SESSION_ACTIVE && !in_array($action, ['login', 'logout'], true)) {
            session_write_close(); // do not block parallel requests on the session lock
        }

        $result = api_dispatch($module, $action, $method, $input, $admin);
        api_respond($result['code'], 'success', $result['message'], $result['data']);
    } catch (ApiException $e) {
        api_respond($e->getCode() ?: 400, 'error', $e->getMessage(), null, $e->errors);
    } catch (PDOException $e) {
        error_log('[ZenCare admin API] ' . $module . ': ' . $e->getMessage());
        $duplicate = ($e->errorInfo[1] ?? 0) == 1062;
        api_respond($duplicate ? 409 : 500, 'error', $duplicate ? 'A record with the same value already exists.' : 'Database error. Please try again.');
    } catch (Throwable $e) {
        error_log('[ZenCare admin API] ' . $module . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        api_respond(500, 'error', 'Server error. Please try again.');
    }
}
