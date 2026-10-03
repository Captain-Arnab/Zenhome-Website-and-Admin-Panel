<?php
/**
 * Admin check for the pre-existing api/admin/*.php endpoints (fetch_users,
 * remove_user, orders, assignTechnician ...). Their only client was the old
 * Flutter admin; the customer apps do not call api/admin/*.
 *
 * Accepted credentials (any one):
 *  - admin panel session cookie (ZCADMIN); non-GET requests also need the
 *    X-CSRF-Token header (or _csrf field), like the new admin API
 *  - admin Bearer token from api/admin/login.php or auth.php?action=login
 *  - "Admin-Token: <ADMIN_SECRET>" header, when ADMIN_SECRET is set in .env
 *
 * Anything else gets 401 JSON and the endpoint does not run.
 */
require_once __DIR__ . '/bootstrap.php';

(function (): void {
    $deny = function (int $code, string $message): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['statusCode' => $code, 'status' => 'error', 'message' => $message]);
        exit;
    };

    try {
        $secret = (string) env_value('ADMIN_SECRET', '');
        $header = (string) ($_SERVER['HTTP_ADMIN_TOKEN'] ?? '');
        if ($secret !== '' && $header !== '' && hash_equals($secret, $header)) {
            return;
        }

        $admin = admin_current();
        if (!$admin) {
            $deny(401, 'Unauthorized. Admin login required.');
        }
        if (($GLOBALS['zc_auth_via'] ?? '') === 'session') {
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
                $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
                if (!csrf_valid(is_string($csrf) ? $csrf : null)) {
                    $deny(403, 'Security token expired. Refresh the page and try again.');
                }
            }
            session_write_close();
        }
    } catch (Throwable $e) {
        error_log('[ZenCare legacy admin guard] ' . $e->getMessage());
        $deny(500, 'Server error. Please try again.');
    }
})();
