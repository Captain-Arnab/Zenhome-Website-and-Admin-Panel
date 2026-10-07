<?php
/**
 * Read-only website content managed in the admin panel (Banners, CMS Pages).
 * Used by the public endpoints (api/banners.php, api/cms_pages.php) and
 * rendered server-side by the website pages (index.php, about-us.php ...).
 * Only live content is returned: active banners within their dates and
 * Published CMS pages with content.
 */
require_once __DIR__ . '/runtime.php';

const SITE_BANNER_PLACEMENTS = ['Homepage Hero', 'Homepage Slider', 'Promotional'];

/**
 * Connection for website pages: never prints errors into the page.
 * Returns null when the database is unavailable (pages fall back to static content).
 */
function site_content_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    try {
        $zcDbSoftFail = true;
        ob_start();
        require __DIR__ . '/db.php';
        ob_end_clean();
        if (isset($conn) && $conn instanceof PDO) {
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->exec('SET NAMES utf8mb4');
            return $pdo = $conn;
        }
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts site content] ' . $e->getMessage());
    }
    return $pdo = null;
}

function site_today(): string
{
    $tz = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
    try {
        return (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
    } catch (Exception $e) {
        return date('Y-m-d');
    }
}

/** HTML escape for website templates. */
function site_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Uploaded image path (relative to the website root) if the file exists. */
function site_image_path(?string $stored): ?string
{
    $stored = ltrim(trim((string) $stored), '/');
    if ($stored === '' || str_contains($stored, '..') || !preg_match('#^[A-Za-z0-9_\-./]+\.(png|jpe?g|webp|gif)$#i', $stored)) {
        return null;
    }
    return is_file(dirname(__DIR__) . '/' . $stored) ? $stored : null;
}

/** Banner link: site page or http(s) URL only. */
function site_safe_link(?string $link): string
{
    $link = trim((string) $link);
    if ($link === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $link)) {
        return '';
    }
    return preg_match('#^(https?://|[A-Za-z0-9_\-./?=&\#]+$)#', $link) ? $link : '';
}

/**
 * Live banners. $placement null = all placements.
 * @return array{0:array,1:int} [items, total]
 */
function site_banners(PDO $pdo, ?string $placement = null, int $limit = 10, int $offset = 0): array
{
    $today = site_today();
    $where = "status = 1 AND (valid_from IS NULL OR valid_from <= ?) AND (valid_to IS NULL OR valid_to >= ?) AND image IS NOT NULL AND image <> ''";
    $params = [$today, $today];
    if ($placement !== null) {
        $where .= ' AND placement = ?';
        $params[] = $placement;
    }
    $count = $pdo->prepare("SELECT COUNT(*) FROM banners WHERE $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, title, placement, image, link, sort_order, valid_to FROM banners WHERE $where ORDER BY placement, sort_order, id LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $image = site_image_path($r['image']);
        if ($image === null) {
            continue;
        }
        $items[] = [
            'id'        => (int) $r['id'],
            'title'     => $r['title'],
            'placement' => $r['placement'],
            'image'     => $image,
            'link'      => site_safe_link($r['link']),
            'order'     => (int) $r['sort_order'],
            'valid_to'  => $r['valid_to'],
        ];
    }
    return [$items, $total];
}

/** Published CMS page with content, or null. Content is sanitized on save and again here. */
function site_cms_page(PDO $pdo, string $slug): ?array
{
    if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT slug, title, page_group, url, content, meta_title, meta_description, updated_at, created_at FROM cms_pages WHERE slug = ? AND status = 'Published'");
    $stmt->execute([$slug]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        require_once __DIR__ . '/html_sanitizer.php';
        $r['content'] = sanitize_html((string) $r['content']);
    }
    if (!$r || trim(strip_tags($r['content'], '<img>')) === '') {
        return null;
    }
    return [
        'slug'             => $r['slug'],
        'title'            => $r['title'],
        'group'            => $r['page_group'],
        'url'              => (string) $r['url'],
        'content'          => (string) $r['content'],
        'meta_title'       => (string) $r['meta_title'],
        'meta_description' => (string) $r['meta_description'],
        'updated'          => $r['updated_at'] ?: $r['created_at'],
    ];
}

/** @return array{0:array,1:int} published pages (no content), [items, total] */
function site_cms_list(PDO $pdo, int $limit = 50, int $offset = 0): array
{
    $where = "status = 'Published' AND content IS NOT NULL AND content <> ''";
    $total = (int) $pdo->query("SELECT COUNT(*) FROM cms_pages WHERE $where")->fetchColumn();
    $stmt = $pdo->query("SELECT slug, title, page_group, url, updated_at, created_at FROM cms_pages WHERE $where ORDER BY FIELD(page_group, 'Pages', 'Legal', 'Service Content'), title LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset);
    $items = array_map(fn($r) => [
        'slug'    => $r['slug'],
        'title'   => $r['title'],
        'group'   => $r['page_group'],
        'url'     => (string) $r['url'],
        'updated' => $r['updated_at'] ?: $r['created_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    return [$items, $total];
}

/**
 * For website pages: published CMS page by slug, or null (keep static content).
 * Never throws.
 */
function site_page_content(string $slug): ?array
{
    try {
        $pdo = site_content_db();
        return $pdo ? site_cms_page($pdo, $slug) : null;
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts site content] cms ' . $slug . ': ' . $e->getMessage());
        return null;
    }
}

/** For website pages: live banners for one placement. Never throws. */
function site_live_banners(string $placement, int $limit = 6): array
{
    try {
        $pdo = site_content_db();
        return $pdo ? site_banners($pdo, $placement, $limit)[0] : [];
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts site content] banners: ' . $e->getMessage());
        return [];
    }
}

/**
 * Active serviceable areas (Admin > Locations) for the homepage "Service Areas"
 * list: active areas whose city (if any) is also active, same filter as
 * api/fetch_serviceable_areas.php. Never throws; empty array hides the section.
 */
function site_serviceable_areas(int $limit = 12): array
{
    try {
        $pdo = site_content_db();
        if (!$pdo) {
            return [];
        }
        $stmt = $pdo->prepare(
            "SELECT a.name FROM serviceable_areas a
             WHERE a.status = 1 AND (a.city_id IS NULL OR EXISTS (SELECT 1 FROM cities c WHERE c.id = a.city_id AND c.status = 1))
             ORDER BY a.sort_order ASC, a.name ASC LIMIT " . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts site content] serviceable areas: ' . $e->getMessage());
        return [];
    }
}

/**
 * Homepage testimonials: Approved + Featured reviews (Admin > Reviews).
 * Returns [] when fewer than $min are featured, so the caller falls back
 * to the website's built-in testimonials. Never throws.
 */
function site_testimonials(int $min = 3, int $limit = 6): array
{
    try {
        $pdo = site_content_db();
        if (!$pdo) {
            return [];
        }
        $stmt = $pdo->prepare(
            "SELECT r.rating, r.feedback, r.created_at, u.first_name, u.last_name, b.category
             FROM ratings_feedback r
             LEFT JOIN users u ON u.ID = r.user_id
             LEFT JOIN service_booking b ON b.ID = r.unique_booking_id
             WHERE r.status = 'Approved' AND r.featured = 1 AND r.feedback IS NOT NULL AND r.feedback <> ''
             ORDER BY r.moderated_at DESC, r.created_at DESC LIMIT " . (int) $limit
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) < $min) {
            return [];
        }
        return array_map(static function (array $r): array {
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            return [
                'rating'  => (float) $r['rating'],
                'text'    => (string) $r['feedback'],
                'name'    => $name !== '' ? $name : 'Zen Home Experts Customer',
                'service' => (string) ($r['category'] ?? ''),
            ];
        }, $rows);
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts site content] testimonials: ' . $e->getMessage());
        return [];
    }
}
