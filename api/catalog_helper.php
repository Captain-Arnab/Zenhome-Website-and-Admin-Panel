<?php
/**
 * Read-only service catalog managed in the admin panel: categories
 * (SERVICE_CATEGORY), subcategories (service_subcategories) and services
 * (SaverPacks groups). Used by the public endpoints
 * (api/catalog_categories.php, catalog_subcategories.php, catalog_services.php)
 * and rendered server-side by the website pages.
 *
 * Only live items are returned: active categories, active subcategories and
 * enabled services inside active categories. Nothing is cached, so admin
 * changes show up on the next request.
 *
 * A service is a SaverPacks group sharing (category_Id, subcategory); its id
 * (and the pack_id the cart / booking sends) is the group's MIN(packId),
 * the same id the admin panel uses.
 */
require_once __DIR__ . '/site_content.php';

/** Categories that have their own website page (<slug>.php); others use service-category.php?slug=. */
const CATALOG_PAGES = [
    'ac-service', 'refrigerator-repair', 'home-cleaning', 'salon', 'pest-control',
    'washing-machine-repair', 'chimney-repair', 'water-purifier', 'carpenter-service',
];

const CATALOG_SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

/** First stored image that exists on disk (relative path). Bare legacy names are looked up in images/. */
function catalog_image(?string ...$stored): ?string
{
    foreach ($stored as $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $path = site_image_path(str_contains($value, '/') ? $value : 'images/' . $value);
        if ($path !== null) {
            return $path;
        }
    }
    return null;
}

/** Non-empty lines of a newline-separated column. */
function catalog_lines(?string $text, int $max = 10): array
{
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), 'strlen'));
    return array_slice($lines, 0, $max);
}

function catalog_category_url(?string $slug, int $id): string
{
    $slug = (string) $slug;
    if (in_array($slug, CATALOG_PAGES, true)) {
        return $slug . '.php';
    }
    return 'service-category.php?' . ($slug !== '' ? 'slug=' . rawurlencode($slug) : 'id=' . $id);
}

function catalog_category_row(array $r): array
{
    $id = (int) $r['CATEGORY_ID'];
    $slug = $r['slug'] !== null && $r['slug'] !== '' ? (string) $r['slug'] : null;
    return [
        'id'                => $id,
        'slug'              => $slug,
        'name'              => (string) $r['NAME'],
        'title'             => (string) ($r['page_title'] ?: $r['NAME']),
        'description'       => (string) ($r['description'] ?? ''),
        'tagline'           => (string) ($r['tagline'] ?? ''),
        'label'             => (string) ($r['web_label'] ?? ''),
        'icon'              => preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/', (string) $r['web_icon']) ? (string) $r['web_icon'] : 'fa-solid fa-screwdriver-wrench',
        'highlights'        => catalog_lines($r['highlights'], 6),
        'long_description'  => catalog_paragraphs($r['long_description'] ?? ''),
        'faqs'              => catalog_faqs($r['faqs'] ?? null),
        // Plain-text paragraph override; empty keeps the category page's own hardcoded paragraph.
        'why_html'          => (string) ($r['why_html'] ?? ''),
        'process_html'      => (string) ($r['process_html'] ?? ''),
        'cta_html'          => (string) ($r['cta_html'] ?? ''),
        'image'             => catalog_image($r['web_image'], $r['IMAGE'], $r['cover_image']),
        'cover_image'       => catalog_image($r['cover_image'], $r['web_image'], $r['IMAGE']),
        'url'               => catalog_category_url($slug, $id),
        'subcategory_count' => (int) ($r['sub_count'] ?? 0),
        'service_count'     => (int) ($r['service_count'] ?? 0),
    ];
}

function catalog_category_sql(): string
{
    return 'SELECT c.CATEGORY_ID, c.NAME, c.IMAGE, c.slug, c.web_image, c.cover_image, c.web_icon, c.tagline, c.web_label,
            c.page_title, c.highlights, c.description, c.long_description, c.faqs, c.why_html, c.process_html, c.cta_html, c.sort_order,
            (SELECT COUNT(*) FROM service_subcategories s WHERE s.category_id = c.CATEGORY_ID AND s.status = 1) AS sub_count,
            (SELECT COUNT(DISTINCT sp.subcategory) FROM SaverPacks sp WHERE sp.category_Id = c.CATEGORY_ID AND sp.status = 1) AS service_count
        FROM SERVICE_CATEGORY c
        WHERE c.status = 1';
}

/** @return array{0:array,1:int} active categories in display order, [items, total] */
function catalog_categories(PDO $pdo, int $limit = 100, int $offset = 0): array
{
    $total = (int) $pdo->query('SELECT COUNT(*) FROM SERVICE_CATEGORY WHERE status = 1')->fetchColumn();
    $rows = $pdo->query(catalog_category_sql() . ' ORDER BY c.sort_order, c.NAME LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset)->fetchAll(PDO::FETCH_ASSOC);
    return [array_map('catalog_category_row', $rows), $total];
}

/** Active category by id or slug, or null. */
function catalog_category(PDO $pdo, $key): ?array
{
    $key = trim((string) $key);
    if (ctype_digit($key)) {
        $stmt = $pdo->prepare(catalog_category_sql() . ' AND c.CATEGORY_ID = ?');
    } elseif (preg_match(CATALOG_SLUG_PATTERN, $key)) {
        $stmt = $pdo->prepare(catalog_category_sql() . ' AND c.slug = ?');
    } else {
        return null;
    }
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? catalog_category_row($row) : null;
}

/** Active subcategories of a category, with their enabled service counts. */
function catalog_subcategories(PDO $pdo, int $categoryId): array
{
    $stmt = $pdo->prepare('SELECT s.id, s.category_id, s.name, s.slug, s.sort_order,
            (SELECT COUNT(DISTINCT sp.subcategory) FROM SaverPacks sp WHERE sp.subcategory_id = s.id AND sp.status = 1) AS service_count
        FROM service_subcategories s
        WHERE s.category_id = ? AND s.status = 1
        ORDER BY s.sort_order, s.name');
    $stmt->execute([$categoryId]);
    return array_map(fn($r) => [
        'id'            => (int) $r['id'],
        'category_id'   => (int) $r['category_id'],
        'slug'          => $r['slug'] !== null && $r['slug'] !== '' ? (string) $r['slug'] : null,
        'name'          => (string) $r['name'],
        'service_count' => (int) $r['service_count'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Enabled services. Filters: category_id, subcategory_id, slug, ids (int[]), slugs (string[]).
 * @return array{0:array,1:int} [items, total]
 */
function catalog_services(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0): array
{
    $pdo->exec('SET SESSION group_concat_max_len = 65535');
    $where = ['g.status = 1', '(g.subcategory_id IS NULL OR s.status = 1)'];
    $params = [];
    if (!empty($filters['category_id'])) {
        $where[] = 'g.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['subcategory_id'])) {
        $where[] = 'g.subcategory_id = ?';
        $params[] = (int) $filters['subcategory_id'];
    }
    if (!empty($filters['slug'])) {
        $where[] = 'g.slug = ?';
        $params[] = (string) $filters['slug'];
    }
    if (isset($filters['ids']) || isset($filters['slugs'])) {
        // Services without a slug are listed as "service-<id>".
        $ids = array_map('intval', $filters['ids'] ?? []);
        $slugs = [];
        foreach ($filters['slugs'] ?? [] as $slug) {
            $slugs[] = (string) $slug;
            if (preg_match('/^service-(\d+)$/', (string) $slug, $m)) {
                $ids[] = (int) $m[1];
            }
        }
        $ids = array_slice(array_values(array_unique($ids)), 0, 100);
        $slugs = array_slice(array_values(array_unique($slugs)), 0, 100);
        $any = [];
        if ($ids) {
            $any[] = 'g.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
        if ($slugs) {
            $any[] = 'g.slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')';
            array_push($params, ...$slugs);
        }
        if (!$any) {
            return [[], 0];
        }
        $where[] = '(' . implode(' OR ', $any) . ')';
    }
    $from = '(SELECT MIN(sp.packId) AS id, sp.category_Id AS category_id, sp.subcategory AS name,
                MAX(sp.price) AS price, MAX(sp.mrp) AS mrp, MAX(sp.serviceTime) AS duration, MAX(sp.image) AS image,
                MAX(sp.status) AS status, MAX(sp.subcategory_id) AS subcategory_id, MAX(sp.whyChooseThisPack) AS description,
                MAX(sp.idealFor) AS ideal_for, MAX(sp.slug) AS slug, MAX(sp.web_tag) AS tag, MAX(sp.highlights) AS highlights,
                MAX(sp.sort_order) AS sort_order,
                GROUP_CONCAT(REPLACE(REPLACE(sp.whatsIncluded, CHAR(13), \' \'), CHAR(10), \' \') ORDER BY sp.packId SEPARATOR \'\n\') AS included
            FROM SaverPacks sp
            GROUP BY sp.category_Id, sp.subcategory) g
        JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = g.category_id AND c.status = 1
        LEFT JOIN service_subcategories s ON s.id = g.subcategory_id';
    $sqlWhere = implode(' AND ', $where);

    $count = $pdo->prepare("SELECT COUNT(*) FROM $from WHERE $sqlWhere");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare("SELECT g.*, c.NAME AS category_name, c.slug AS category_slug, s.name AS subcategory_name, s.slug AS subcategory_slug
        FROM $from WHERE $sqlWhere
        ORDER BY c.sort_order, c.NAME, g.sort_order = 0, g.sort_order, g.id
        LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $included = catalog_lines($r['included'], 50);
        $highlights = catalog_lines($r['highlights'], 6) ?: array_slice($included, 0, 3);
        $id = (int) $r['id'];
        $items[] = [
            'id'             => $id,
            'pack_id'        => $id,
            'slug'           => $r['slug'] ?: 'service-' . $id,
            'name'           => (string) $r['name'],
            'category_id'    => (int) $r['category_id'],
            'category'       => (string) $r['category_name'],
            'category_slug'  => $r['category_slug'],
            'subcategory_id' => $r['subcategory_id'] !== null ? (int) $r['subcategory_id'] : null,
            'subcategory'    => $r['subcategory_name'],
            'price'          => (float) $r['price'],
            'mrp'            => $r['mrp'] !== null && (float) $r['mrp'] > (float) $r['price'] ? (float) $r['mrp'] : null,
            'duration'       => (string) ($r['duration'] ?? ''),
            'description'    => (string) ($r['description'] ?? ''),
            'tag'            => (string) ($r['tag'] ?? ''),
            'highlights'     => $highlights,
            'included'       => $included,
            'ideal_for'      => (string) ($r['ideal_for'] ?? ''),
            'image'          => catalog_image($r['image']),
            'url'            => catalog_category_url($r['category_slug'], (int) $r['category_id']) . '#service-' . ($r['slug'] ?: $id),
        ];
    }
    return [$items, $total];
}

/**
 * Homepage "Smart Packages" cards (home_packages). A card that points to a
 * disabled category or service is hidden; one that points to a service
 * also carries that service's live price.
 * @return array{0:array,1:int} [items, total]
 */
function catalog_home_packages(PDO $pdo, int $limit = 12, int $offset = 0): array
{
    $rows = $pdo->query('SELECT * FROM home_packages WHERE status = 1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $packIds = array_values(array_filter(array_map(fn($r) => (int) $r['pack_id'], $rows)));
    $services = [];
    if ($packIds) {
        foreach (catalog_services($pdo, ['ids' => $packIds])[0] as $s) {
            $services[$s['id']] = $s;
        }
    }
    $categories = [];
    foreach (catalog_categories($pdo)[0] as $c) {
        $categories[$c['id']] = $c;
    }

    $items = [];
    foreach ($rows as $r) {
        $service = $r['pack_id'] ? ($services[(int) $r['pack_id']] ?? null) : null;
        $category = $r['category_id'] ? ($categories[(int) $r['category_id']] ?? null) : null;
        if (($r['pack_id'] && !$service) || ($r['category_id'] && !$category)) {
            continue;
        }
        $url = $service['url'] ?? $category['url'] ?? (site_safe_link($r['link']) ?: 'services.php');
        $items[] = [
            'id'          => (int) $r['id'],
            'title'       => (string) $r['title'],
            'label'       => (string) ($r['label'] ?? ''),
            'icon'        => preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/', (string) $r['icon']) ? (string) $r['icon'] : 'fa-solid fa-box-open',
            'description' => (string) ($r['description'] ?? ''),
            'highlights'  => catalog_lines($r['highlights'], 5),
            'url'         => $url,
            'button_text' => (string) ($r['button_text'] ?: 'Book Package'),
            'featured'    => (int) $r['is_featured'] === 1,
            'category_id' => $category['id'] ?? ($service['category_id'] ?? null),
            'pack_id'     => $service['pack_id'] ?? null,
            'price'       => $service['price'] ?? null,
            'order'       => (int) $r['sort_order'],
        ];
    }
    return [array_slice($items, $offset, $limit), count($items)];
}

/** Category FAQs stored as JSON [{"q": "...", "a": "..."}]. */
function catalog_faqs(?string $json): array
{
    $list = json_decode((string) $json, true);
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $faq) {
        $q = trim((string) ($faq['q'] ?? ''));
        $a = trim((string) ($faq['a'] ?? ''));
        if ($q !== '' && $a !== '') {
            $out[] = ['question' => $q, 'answer' => $a];
        }
    }
    return array_slice($out, 0, 20);
}

/** Long description paragraphs (separated by a blank line). */
function catalog_paragraphs(?string $text): array
{
    $parts = preg_split('/\R\s*\R/', trim((string) $text));
    return array_values(array_filter(array_map(fn($p) => trim(preg_replace('/\s*\R\s*/', ' ', $p)), $parts), 'strlen'));
}

/* ---------------------------------------------------------------------
 * Website helpers: never throw; an unavailable database renders the
 * pages' empty states instead of a PHP error.
 * ------------------------------------------------------------------- */

function site_home_packages(int $limit = 8): array
{
    try {
        $pdo = site_content_db();
        return $pdo ? catalog_home_packages($pdo, $limit)[0] : [];
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts catalog] home packages: ' . $e->getMessage());
        return [];
    }
}

function site_catalog_categories(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo = site_content_db();
        return $cache = $pdo ? catalog_categories($pdo)[0] : [];
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts catalog] categories: ' . $e->getMessage());
        return $cache = [];
    }
}

/** Active category for a website page, or null when it is disabled / missing. */
function site_catalog_category($key): ?array
{
    try {
        $pdo = site_content_db();
        return $pdo ? catalog_category($pdo, $key) : null;
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts catalog] category: ' . $e->getMessage());
        return null;
    }
}

function site_catalog_services(array $filters, int $limit = 100): array
{
    try {
        $pdo = site_content_db();
        return $pdo ? catalog_services($pdo, $filters, $limit)[0] : [];
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts catalog] services: ' . $e->getMessage());
        return [];
    }
}

function site_catalog_subcategories(int $categoryId): array
{
    try {
        $pdo = site_content_db();
        return $pdo ? catalog_subcategories($pdo, $categoryId) : [];
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts catalog] subcategories: ' . $e->getMessage());
        return [];
    }
}

/** "₹1,499" (Indian digit grouping), or the inspection label for unpriced services. */
function site_price($amount): string
{
    $amount = (float) $amount;
    if ($amount <= 0) {
        return 'Price after inspection';
    }
    $n = (string) (int) round($amount);
    $last3 = substr($n, -3);
    $rest = substr($n, 0, -3);
    if ($rest !== '' && $rest !== false) {
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $last3 = $rest . ',' . $last3;
    }
    return '₹' . $last3;
}
