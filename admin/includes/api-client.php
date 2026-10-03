<?php
/**
 * Server-side API client for the admin pages.
 *
 * Pages read data through api('module.action', $params), which runs the same
 * handlers as the HTTP endpoints in api/admin/ in-process (same auth, role
 * checks and validation, no HTTP round trip). Writes go from the browser to
 * the HTTP endpoints via assets/js/api.js (session + CSRF header).
 *
 * Errors never break a page: they are collected in $GLOBALS['apiErrors'] and
 * shown as an alert under the top bar (includes/topbar.php).
 */
require_once dirname(__DIR__, 2) . '/api/admin/core/bootstrap.php';

/** Rows loaded per list page. Lists are filtered/paged client-side (DataTables). */
const ADMIN_LIST_LIMIT = 500;

$GLOBALS['apiErrors']  = [];
$GLOBALS['apiNotices'] = [];

/** Logged-in admin, or redirect to the login page. */
function admin_require_login(): array
{
    $admin = admin_current();
    if (!$admin) {
        $uri   = $_SERVER['REQUEST_URI'] ?? '';
        $query = (string) parse_url($uri, PHP_URL_QUERY);
        $next  = basename((string) parse_url($uri, PHP_URL_PATH)) . ($query !== '' ? '?' . $query : '');
        header('Location: login.php' . ($next !== '' && $next !== 'index.php' ? '?next=' . urlencode($next) : ''));
        exit;
    }
    return $admin;
}

/**
 * In-process read. Returns the response "data" or $default on failure.
 * A 404 is returned as $default silently when $quiet404 is true (detail pages
 * render their own "not found" state).
 */
function api(string $call, array $params = [], $default = [], bool $quiet404 = false)
{
    [$module, $action] = array_pad(explode('.', $call, 2), 2, '');
    try {
        return api_dispatch($module, $action, 'GET', $params, admin_current())['data'];
    } catch (ApiException $e) {
        if (!($quiet404 && $e->getCode() === 404)) {
            $GLOBALS['apiErrors'][] = $e->getMessage();
        }
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts admin page] ' . $call . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $GLOBALS['apiErrors'][] = 'Could not load some data. Please refresh the page.';
    }
    return $default;
}

/**
 * List read with the admin page limit. Adds a notice when the result was
 * truncated so admins know to narrow the filters.
 * @return array{items:array,pagination:array}+array
 */
function api_list(string $call, array $params = [], string $what = 'records'): array
{
    $data = api($call, $params + ['limit' => ADMIN_LIST_LIMIT], null);
    if (!is_array($data) || !isset($data['items'])) {
        return ['items' => [], 'pagination' => ['page' => 1, 'limit' => ADMIN_LIST_LIMIT, 'total_items' => 0, 'total_pages' => 0]];
    }
    $total = (int) ($data['pagination']['total_items'] ?? count($data['items']));
    if ($total > count($data['items'])) {
        $GLOBALS['apiNotices'][] = 'Showing the latest ' . count($data['items']) . ' of ' . number_format($total) . ' ' . $what . '. Use the filters to narrow the list.';
    }
    return $data;
}
