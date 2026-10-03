<?php
/**
 * Route registry + dispatcher shared by the HTTP endpoints (core/http.php)
 * and the admin pages' in-process client (admin/includes/api-client.php),
 * so both go through the same auth, role checks and validation.
 *
 * A module file (modules/<module>.php) returns its routes:
 *   return [
 *       'list'   => ['GET',  'customers_list'],              // any admin
 *       'delete' => ['POST', 'customers_delete', 'super'],   // Super Admin only
 *       'login'  => ['POST', 'auth_login', 'public'],        // no auth
 *   ];
 * Handlers: function (array $input, ?array $admin): array  (return ok(...))
 */

function api_routes(string $module): array
{
    static $cache = [];
    if (!preg_match('/^[a-z_]+$/', $module)) {
        throw new ApiException('Unknown API module.', 404);
    }
    if (!isset($cache[$module])) {
        $file = dirname(__DIR__) . '/modules/' . $module . '.php';
        if (!is_file($file)) {
            throw new ApiException('Unknown API module.', 404);
        }
        $cache[$module] = require $file;
    }
    return $cache[$module];
}

/**
 * Runs one action. $admin must already be authenticated by the caller
 * (null only for public routes).
 */
function api_dispatch(string $module, string $action, string $method, array $input, ?array $admin): array
{
    $routes = api_routes($module);
    if ($action === '' || !isset($routes[$action])) {
        throw new ApiException('Unknown action "' . $action . '". Available: ' . implode(', ', array_keys($routes)), 404);
    }
    [$allowedMethod, $handler] = $routes[$action];
    $role = $routes[$action][2] ?? 'admin';

    if (strtoupper($method) !== $allowedMethod) {
        throw new ApiException("Use $allowedMethod for this action.", 405);
    }
    if ($role !== 'public') {
        if (!$admin) {
            throw new ApiException('Unauthorized or session expired. Please log in again.', 401);
        }
        if ($role === 'super') {
            require_super_admin($admin);
        }
    }

    $result = $handler($input, $admin);
    if (!is_array($result) || !array_key_exists('code', $result)) {
        $result = ok($result);
    }
    return $result;
}
