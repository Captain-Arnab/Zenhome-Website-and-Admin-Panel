<?php
/**
 * Response, pagination and formatting helpers shared by all admin modules.
 */

/** Error that becomes a JSON error response (or a page alert in-process). */
class ApiException extends RuntimeException
{
    /** @var array<string,string> field => message */
    public array $errors;

    public function __construct(string $message, int $httpCode = 400, array $errors = [])
    {
        parent::__construct($message, $httpCode);
        $this->errors = $errors;
    }
}

/** Successful handler result. */
function ok($data = null, string $message = 'OK', int $httpCode = 200): array
{
    return ['code' => $httpCode, 'message' => $message, 'data' => $data];
}

function not_found(string $what = 'Record'): ApiException
{
    return new ApiException($what . ' not found.', 404);
}

/** Current time in the app timezone (DB session uses the same offset). */
function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

/* --------------------------------------------------------------------------
 * Pagination: ?page=1&limit=20 (limit capped)
 * ------------------------------------------------------------------------ */

const API_DEFAULT_LIMIT = 20;
const API_MAX_LIMIT     = 500;

/** @return array{0:int,1:int,2:int} [page, limit, offset] */
function page_params(array $in, int $defaultLimit = API_DEFAULT_LIMIT): array
{
    $page  = max(1, (int) ($in['page'] ?? 1));
    $limit = (int) ($in['limit'] ?? $defaultLimit);
    $limit = min(API_MAX_LIMIT, max(1, $limit));
    return [$page, $limit, ($page - 1) * $limit];
}

function paginated(array $items, int $total, int $page, int $limit, array $extra = []): array
{
    return array_merge([
        'items'      => $items,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total_items' => $total,
            'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
        ],
    ], $extra);
}

/* --------------------------------------------------------------------------
 * Small query helpers (always prepared statements)
 * ------------------------------------------------------------------------ */

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
    return $stmt;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_value(string $sql, array $params = [])
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/** Builds "col LIKE ? OR col2 LIKE ?" for a search term. */
function like_clause(array $columns, string $term, array &$params): string
{
    $parts = [];
    foreach ($columns as $col) {
        $parts[] = "$col LIKE ?";
        $params[] = '%' . $term . '%';
    }
    return '(' . implode(' OR ', $parts) . ')';
}

/** Order-by from a whitelist: ?sort=key&dir=asc|desc */
function order_by(array $in, array $allowed, string $default): string
{
    $key = (string) ($in['sort'] ?? '');
    if (!isset($allowed[$key])) {
        return $default;
    }
    $dir = strtolower((string) ($in['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    return $allowed[$key] . ' ' . $dir;
}

/* --------------------------------------------------------------------------
 * Formatting
 * ------------------------------------------------------------------------ */

function full_name(?string $first, ?string $last): string
{
    return trim(trim((string) $first) . ' ' . trim((string) $last));
}

/** "9848012345" -> "+91 98480 12345" (display only). */
function format_mobile(?string $mobile): string
{
    $digits = preg_replace('/\D/', '', (string) $mobile);
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    return strlen($digits) === 10 ? '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5) : (string) $mobile;
}

/** Normalises an Indian mobile number to 10 digits, or null if invalid. */
function normalize_mobile(?string $mobile): ?string
{
    $digits = preg_replace('/\D/', '', (string) $mobile);
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
}

/** Splits "Ravi Kumar Reddy" into ['Ravi', 'Kumar Reddy']. */
function split_name(string $name): array
{
    $parts = preg_split('/\s+/', trim($name), 2);
    return [$parts[0] ?? '', $parts[1] ?? ''];
}

/** Website-relative path of an uploaded/legacy image, or null. */
function image_path(?string $stored, string $legacyDir = 'images'): ?string
{
    $stored = trim((string) $stored);
    if ($stored === '' || str_starts_with($stored, 'data:')) {
        return null;
    }
    if (preg_match('#^https?://#i', $stored)) {
        return $stored;
    }
    $stored = ltrim($stored, '/');
    if (str_contains($stored, '/')) {
        return is_file(ZC_ROOT . '/' . $stored) ? $stored : null;
    }
    return is_file(ZC_ROOT . '/' . $legacyDir . '/' . $stored) ? $legacyDir . '/' . $stored : null;
}

/** URL slugs (website addresses): lowercase words joined by hyphens. */
const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

function slugify(string $text, int $max = 80): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    return trim(substr($slug, 0, $max), '-');
}

/** Newline-separated list input (website highlights): trimmed, non-empty lines, max $maxLines. */
function clean_lines(?string $text, int $maxLines, int $maxLength = 80): string
{
    $lines = array_filter(array_map(fn($l) => mb_substr(trim($l), 0, $maxLength), preg_split('/\R/', (string) $text)), 'strlen');
    return implode("\n", array_slice(array_values($lines), 0, $maxLines));
}

/** Decodes a JSON-array or comma separated text column into a list. */
function text_list(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map(fn($v) => is_scalar($v) ? trim((string) $v) : '', $decoded)));
    }
    return array_values(array_filter(array_map('trim', explode(',', $value))));
}

require_once dirname(__DIR__, 2) . '/html_sanitizer.php';   // sanitize_html()

function rel_time(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        $h = floor($diff / 3600);
        return $h . ' hr' . ($h > 1 ? 's' : '') . ' ago';
    }
    $d = floor($diff / 86400);
    return $d < 30 ? $d . ' day' . ($d > 1 ? 's' : '') . ' ago' : date('d M Y', strtotime($datetime));
}
