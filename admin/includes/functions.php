<?php
/**
 * Admin bootstrap + shared view helpers.
 * Every page requires this file first (header.php also requires it).
 */

require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/../../api/site_settings_helper.php';   // support line / receipt contact details

/* --------------------------------------------------------------------------
 * Auth / role. Every page except login.php requires an admin session.
 * Role checks are enforced server-side by the API; $role only drives the UI.
 * ------------------------------------------------------------------------ */
$currentAdmin = admin_require_login();
$role         = $currentAdmin['role'];

function isSuperAdmin(): bool
{
    global $role;
    return $role === 'super_admin';
}

/** Super-Admin-only page guard (UI side; the API checks again). */
function requireSuperAdminPage(): void
{
    if (!isSuperAdmin()) {
        header('Location: index.php?denied=1');
        exit;
    }
}

/** Role labels + permission summary; must match the 'super' routes in api/admin/modules. */
function adminRoles(): array
{
    return [
        'super_admin' => ['label' => 'Super Admin', 'color' => 'secondary', 'description' => 'Full access, including admin users and deleting records.'],
        'admin'       => ['label' => 'Admin',       'color' => 'primary',   'description' => 'Day-to-day operations. Cannot manage admin users or delete records.'],
    ];
}

function adminPermissions(): array
{
    return [
        'Dashboard & reports'                => ['super_admin' => true, 'admin' => true],
        'Bookings & professional assignment' => ['super_admin' => true, 'admin' => true],
        'Customers & support tickets'        => ['super_admin' => true, 'admin' => true],
        'Services, categories & locations'   => ['super_admin' => true, 'admin' => true],
        'Payments (view, mark cash received)' => ['super_admin' => true, 'admin' => true],
        'Refund status updates'              => ['super_admin' => true, 'admin' => false],
        'Coupons, notifications & SMS'       => ['super_admin' => true, 'admin' => true],
        'Banners & CMS pages'                => ['super_admin' => true, 'admin' => true],
        'Admin users & roles'                => ['super_admin' => true, 'admin' => false],
        'Delete records'                     => ['super_admin' => true, 'admin' => false],
    ];
}

/** Sidebar/topbar counts, loaded once per request. */
function adminBadges(): array
{
    static $badges = null;
    return $badges ??= api('dashboard.badges', [], ['unassigned' => 0, 'open_tickets' => 0, 'alerts' => [], 'unread_alerts' => 0]);
}

/* --------------------------------------------------------------------------
 * Formatting helpers
 * ------------------------------------------------------------------------ */

/** HTML-escape output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Indian number grouping: 1286450 -> 12,86,450 */
function inr($number, int $decimals = 0): string
{
    $formatted = number_format(abs((float) $number), $decimals, '.', '');
    [$int, $dec] = array_pad(explode('.', $formatted), 2, '');
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest  = substr($int, 0, -3);
        $int   = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3;
    }
    return ($number < 0 ? '-' : '') . $int . ($dec !== '' ? '.' . $dec : '');
}

/** Indian Rupee formatting: 1286450 -> ₹12,86,450 */
function money($amount, int $decimals = 0): string
{
    return '₹' . inr($amount, $decimals);
}

/** Time-of-day greeting for the dashboard. */
function greeting(): string
{
    $h = (int) date('G');
    return $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
}

/** 2026-10-03 -> 03 Oct 2026 */
function fdate(?string $date, string $format = 'd M Y'): string
{
    return $date ? date($format, strtotime($date)) : '-';
}

/** Form hint under "Schedule" fields: is cron/send_scheduled.php actually running? */
function schedulerNotice(string $what): string
{
    $run = scheduler_last_run();
    if ($run && scheduler_active()) {
        return '<div class="form-text text-success"><i class="bi bi-clock-history me-1"></i>Scheduler active (last run ' . e(fdate($run['finished_at'], 'd M, h:i A')) . '). Scheduled ' . e($what) . ' are sent within about 5 minutes of the chosen time.</div>';
    }
    $last = $run ? 'last run ' . fdate($run['finished_at'], 'd M Y, h:i A') : 'it has never run';
    return '<div class="form-text text-warning"><i class="bi bi-exclamation-triangle me-1"></i>The scheduler (cron/send_scheduled.php) is not running (' . e($last) . '). Scheduled ' . e($what) . ' are saved and will be sent once the cron job is set up.</div>';
}

/** URL for a stored image path (relative to the site root) as seen from /admin. */
function assetUrl(?string $path): string
{
    $path = (string) $path;
    if ($path === '') {
        return '';
    }
    return preg_match('#^https?://#i', $path) ? $path : '../' . ltrim($path, '/');
}

/** Encode an array for use inside an HTML attribute (data-fill='...'). */
function jsonAttr(array $data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** "Ananya Reddy" -> "AR" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return strtoupper($first . $last);
}

/** Stable avatar color class per name. */
function avatarClass(string $name): string
{
    return 'c' . (crc32($name) % 6 + 1);
}

/** Initials avatar markup. */
function avatar(string $name, string $size = ''): string
{
    return '<span class="avatar ' . $size . ' ' . avatarClass($name) . '">' . e(initials($name)) . '</span>';
}

/** Find the first row in $rows where $row[$key] == $value. */
function findBy(array $rows, string $key, $value): ?array
{
    foreach ($rows as $row) {
        if (isset($row[$key]) && (string) $row[$key] === (string) $value) {
            return $row;
        }
    }
    return null;
}

/** Filter rows where $row[$key] == $value. */
function whereBy(array $rows, string $key, $value): array
{
    return array_values(array_filter($rows, fn($r) => isset($r[$key]) && (string) $r[$key] === (string) $value));
}

/* --------------------------------------------------------------------------
 * UI component helpers
 * ------------------------------------------------------------------------ */

/**
 * Color-coded status badge. One map for every module so colors stay consistent.
 */
function statusBadge(string $status, bool $dot = true, ?string $label = null): string
{
    $map = [
        // Booking
        'new'            => 'info',
        'pending'        => 'warning',
        'assigned'       => 'primary',
        'ongoing'        => 'purple',
        'completed'      => 'success',
        'cancelled'      => 'danger',
        // Payment / refund
        'paid'           => 'success',
        'failed'         => 'danger',
        'refunded'       => 'info',
        'refund pending' => 'warning',
        'processed'      => 'info',
        // Generic
        'active'         => 'success',
        'inactive'       => 'muted',
        'approved'       => 'success',
        'hidden'         => 'muted',
        'open'           => 'warning',
        'closed'         => 'success',
        'in progress'    => 'info',
        'sent'           => 'success',
        'delivered'      => 'success',
        'partially sent' => 'warning',
        'not sent'       => 'muted',
        'scheduled'      => 'info',
        'processing'     => 'primary',
        'expired'        => 'muted',
        'draft'          => 'muted',
        'published'      => 'success',
        // Priority
        'high'           => 'danger',
        'medium'         => 'warning',
        'low'            => 'secondary',
    ];
    $color = $map[strtolower($status)] ?? 'secondary';
    return '<span class="badge badge-soft-' . $color . ($dot ? ' badge-dot' : '') . '">' . e($label ?? $status) . '</span>';
}

/** Read-only star rating (supports halves). */
function stars(float $rating): string
{
    $html = '<span class="stars" title="' . e($rating) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="bi bi-star-fill"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="bi bi-star-half"></i>';
        } else {
            $html .= '<i class="bi bi-star"></i>';
        }
    }
    return $html . '</span>';
}

/**
 * Active/inactive toggle switch. With $api (e.g. 'customers.set_status') admin.js
 * POSTs {id, status: 1|0} and reverts the switch if the request fails.
 */
function statusSwitch(bool $checked, string $itemName = '', string $on = 'Active', string $off = 'Inactive', string $id = '', string $api = ''): string
{
    return '<div class="form-check form-switch m-0 d-inline-flex align-items-center gap-2">'
        . '<input class="form-check-input js-status-toggle" type="checkbox" role="switch"'
        . ($checked ? ' checked' : '')
        . ' data-on="' . e($on) . '" data-off="' . e($off) . '"'
        . ($itemName !== '' ? ' data-name="' . e($itemName) . '"' : '')
        . ($id !== '' ? ' data-id="' . e($id) . '"' : '')
        . ($api !== '' ? ' data-api="' . e($api) . '"' : '')
        . ' aria-label="Toggle status">'
        . '<label class="form-check-label fs-12 fw-semibold text-muted">' . e($checked ? $on : $off) . '</label>'
        . '</div>';
}

/**
 * Stat card.
 * @param string      $color  primary|success|warning|danger|info|secondary|purple|muted
 * @param string|null $href   Makes the whole card a link when set.
 */
function statCard(string $label, string $value, string $icon, string $color = 'primary', string $meta = '', ?string $href = null, string $extraClass = ''): string
{
    $tag   = $href ? 'a' : 'div';
    $attrs = $href ? ' href="' . e($href) . '"' : '';
    return '<' . $tag . $attrs . ' class="card stat-card ' . e($extraClass) . '">'
        . '<span class="stat-icon icon-soft-' . e($color) . '"><i class="bi ' . e($icon) . '"></i></span>'
        . '<div class="min-w-0">'
        . '<p class="stat-label">' . e($label) . '</p>'
        . '<p class="stat-value">' . e($value) . '</p>'
        . ($meta !== '' ? '<div class="stat-meta">' . $meta . '</div>' : '')
        . '</div>'
        . '</' . $tag . '>';
}

/**
 * Delete button wired to the global confirm modal; admin.js POSTs {id} to $api
 * (e.g. 'categories.delete') and removes the row on success.
 */
function deleteButton(string $what, string $id = '', bool $removeRow = true, string $api = ''): string
{
    return '<button type="button" class="btn btn-icon btn-soft-danger" title="Delete"'
        . ' data-confirm="Are you sure you want to delete ' . e($what) . '? This cannot be undone."'
        . ' data-confirm-title="Confirm Delete"'
        . ($api !== '' ? ' data-api="' . e($api) . '"' : '')
        . ($id !== '' ? ' data-id="' . e($id) . '"' : '')
        . ($removeRow ? ' data-remove-row' : '')
        . '><i class="bi bi-trash3"></i></button>';
}

/** True when a booking still needs a professional (core workflow step). */
function needsAssignment(array $booking): bool
{
    return ($booking['professional'] ?? '') === '' && in_array($booking['status'], ['New', 'Pending'], true);
}

/** Booking reference shown to admins (unique_booking_id, falls back to #id). */
function bookingCode(array $booking): string
{
    return (string) ($booking['code'] ?? ('#' . ($booking['id'] ?? '')));
}

/** Renders a friendly "not found" page inside the admin layout and stops. */
function renderNotFound(string $title, string $message, string $backUrl, string $backLabel): void
{
    global $pageTitle, $activeMenu, $breadcrumbs, $currentAdmin, $role, $plugins;
    $pageTitle = $pageTitle ?? $title;
    http_response_code(404);
    include __DIR__ . '/header.php';
    include __DIR__ . '/sidebar.php';
    include __DIR__ . '/topbar.php';
    echo '<div class="card"><div class="card-body"><div class="empty-state py-5">'
        . '<i class="bi bi-search"></i><h5 class="mt-3 mb-1">' . e($title) . '</h5>'
        . '<p class="text-muted mb-3">' . e($message) . '</p>'
        . '<a href="' . e($backUrl) . '" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i>' . e($backLabel) . '</a>'
        . '</div></div></div>';
    include __DIR__ . '/footer.php';
    exit;
}

/** Inline empty state for cards/lists. */
function emptyState(string $message, string $icon = 'bi-inbox'): string
{
    return '<div class="empty-state py-4"><i class="bi ' . e($icon) . '"></i><p class="mt-2 mb-0">' . e($message) . '</p></div>';
}

/** "Assign" / "Reassign" button that opens the shared assign modal. */
function assignButton(array $booking, string $size = 'btn-sm'): string
{
    $current = $booking['professional'] ?? '';
    $label   = $current ? 'Reassign' : 'Assign';
    $class   = $current ? 'btn-outline-primary' : 'btn-warning';
    return '<button type="button" class="btn ' . $class . ' ' . $size . ' text-nowrap" data-bs-toggle="modal" data-bs-target="#assignModal"'
        . ' data-booking="' . e($booking['id']) . '"'
        . ' data-code="' . e(bookingCode($booking)) . '"'
        . ' data-service="' . e($booking['service']) . '"'
        . ' data-category="' . e($booking['category']) . '"'
        . ' data-slot="' . e(fdate($booking['date']) . ($booking['slot'] ? ', ' . $booking['slot'] : '')) . '"'
        . ' data-current="' . e($current) . '"'
        . ' data-current-id="' . e($booking['professional_id'] ?? '') . '">'
        . '<i class="bi bi-person-plus me-1"></i>' . $label . '</button>';
}
