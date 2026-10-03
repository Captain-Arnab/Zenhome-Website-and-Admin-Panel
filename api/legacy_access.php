<?php
/**
 * Guard for the legacy app endpoints (user_orders, order_details,
 * serviceBooking, serviceComplete, checkSlot, save_transaction,
 * fetch-transaction, user_transaction, paymentConfirmation,
 * payementInitiate, fetchCategory, book_appointment).
 *
 * - Every call is recorded in legacy_access_log (endpoint, token state,
 *   user agent, IP), to see which endpoints the apps really use.
 * - LEGACY_STRICT_AUTH=true in .env requires a valid customer login token
 *   (auth_helper.php) and limits each customer to their own bookings and
 *   transactions. Default false: the endpoints behave exactly as before.
 *
 * Usage, at the top of an endpoint (before any output):
 *   require_once __DIR__ . '/legacy_access.php';
 *   $legacy = legacy_access('user_orders');
 *   legacy_require_user($legacy, $_GET['user_id'] ?? null);
 *
 * Logging problems never break an endpoint.
 */
require_once __DIR__ . '/auth_helper.php';

/** Shared PDO (api/db.php, which also loads .env); null when unavailable. */
function legacy_db(): ?PDO
{
    static $pdo = false;
    if ($pdo === false) {
        $pdo = null;
        ob_start();
        try {
            include __DIR__ . '/db.php';
        } catch (Throwable $e) {
            error_log('[ZenHomeExperts legacy] db: ' . $e->getMessage());
        }
        ob_end_clean();
        if (isset($conn) && $conn instanceof PDO) {
            $pdo = $conn;
        }
    }
    return $pdo;
}

function legacy_env_flag(string $key): bool
{
    legacy_db();
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function legacy_strict(): bool
{
    return legacy_env_flag('LEGACY_STRICT_AUTH');
}

function legacy_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Inserts a legacy_access_log row; returns its id or null. */
function legacy_log(string $endpoint, string $tokenState, ?int $userId, string $outcome = 'allowed'): ?int
{
    $pdo = legacy_db();
    if (!$pdo) {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO legacy_access_log (endpoint, method, has_token, token_state, user_id, outcome, user_agent, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            substr($endpoint, 0, 60),
            substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 0, 8),
            $tokenState === 'none' ? 0 : 1,
            $tokenState,
            $userId,
            $outcome,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            legacy_client_ip(),
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts legacy] log: ' . $e->getMessage());
        return null;
    }
}

function legacy_log_outcome(array $ctx, string $outcome): void
{
    $pdo = legacy_db();
    if (!$pdo || !$ctx['log_id']) {
        return;
    }
    try {
        $pdo->prepare('UPDATE legacy_access_log SET outcome = ? WHERE id = ?')->execute([$outcome, $ctx['log_id']]);
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts legacy] log: ' . $e->getMessage());
    }
}

/**
 * Logs the call and, in strict mode, rejects calls without a valid token.
 * Returns the context used by the ownership checks below.
 */
function legacy_access(string $endpoint): array
{
    $tokenState = 'none';
    $userId = null;
    if (getBearerToken() !== null) {
        $tokenState = 'invalid';
        try {
            $pdo = legacy_db();
            $auth = $pdo ? getUserIdFromRequest($pdo) : null;
            if ($auth) {
                $tokenState = 'valid';
                $userId = (int) $auth['user_id'];
            }
        } catch (Throwable $e) {
            error_log('[ZenHomeExperts legacy] auth: ' . $e->getMessage());
        }
    }
    $ctx = [
        'endpoint' => $endpoint,
        'strict'   => legacy_strict(),
        'token'    => $tokenState,
        'user_id'  => $userId,
        'log_id'   => legacy_log($endpoint, $tokenState, $userId),
    ];
    if ($ctx['strict'] && $tokenState !== 'valid') {
        legacy_deny($ctx, 401, 'Unauthorized or session expired. Please log in again.');
    }
    return $ctx;
}

/**
 * Strict-mode error. Carries the keys of both legacy response styles
 * ("status"/"message" and "success"/"error") so every app can read it.
 */
function legacy_deny(array $ctx, int $code, string $message): void
{
    legacy_log_outcome($ctx, 'denied_' . $code);
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['statusCode' => $code, 'status' => 'error', 'success' => false, 'message' => $message, 'error' => $message]);
    exit;
}

/** Strict mode: the user_id sent by the app must be the logged-in customer. */
function legacy_require_user(array $ctx, $userId): void
{
    if (!$ctx['strict']) {
        return;
    }
    if (!is_scalar($userId) || (string) $userId === '' || (string) (int) $userId !== trim((string) $userId) || (int) $userId !== $ctx['user_id']) {
        legacy_deny($ctx, 403, 'You can only access your own account.');
    }
}

/**
 * Strict mode: the row found by $sql (selecting user_id) must belong to the
 * logged-in customer. A row of another customer answers 404 (not revealed);
 * a missing row is allowed through unless $mustExist.
 */
function legacy_require_owner(array $ctx, string $sql, array $params, string $what, bool $mustExist = false): void
{
    if (!$ctx['strict']) {
        return;
    }
    $pdo = legacy_db();
    if (!$pdo) {
        legacy_deny($ctx, 503, 'Service temporarily unavailable. Please try again.');
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $owner = $stmt->fetchColumn();
    if ($owner === false ? $mustExist : (int) $owner !== $ctx['user_id']) {
        legacy_deny($ctx, 404, $what . ' not found.');
    }
}
