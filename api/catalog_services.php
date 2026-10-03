<?php
/**
 * Public: enabled services for the website and apps (no login).
 *
 * GET api/catalog_services.php?category=<id|slug>&subcategory=<id|slug>&page=1&limit=50
 *     api/catalog_services.php?slug=<service slug>          one service
 *     api/catalog_services.php?ids=12,35  or  ?slugs=a,b    cart refresh (max 100)
 * data.items[]: {id, pack_id, slug, name, category_id, category, category_slug,
 *   subcategory_id, subcategory, price, mrp, duration, description, tag,
 *   highlights[], included[], ideal_for, image, image_url, url}
 * pack_id is the saverpacks id to send to book_appointment.php /
 * validate_coupon.php as items[{pack_id, quantity}] (priced on the server).
 * Disabled services, or services in a disabled category, are never returned.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/catalog_helper.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

$in = public_input();
[$page, $limit, $offset] = public_page_params($in, 50, 100);
$filters = [];
$errors = [];

try {
    $pdo = public_db();

    $categoryKey = public_str($in, 'category') ?: public_str($in, 'category_id');
    if ($categoryKey !== '') {
        $category = catalog_category($pdo, $categoryKey);
        if (!$category) {
            public_json(404, 'Category not found.');
        }
        $filters['category_id'] = $category['id'];
    }

    $subKey = public_str($in, 'subcategory') ?: public_str($in, 'subcategory_id');
    if ($subKey !== '') {
        $match = null;
        if (isset($category)) {
            foreach (catalog_subcategories($pdo, $category['id']) as $sub) {
                if ((string) $sub['id'] === $subKey || $sub['slug'] === $subKey) {
                    $match = $sub;
                }
            }
        } elseif (ctype_digit($subKey)) {
            $stmt = $pdo->prepare('SELECT s.id FROM service_subcategories s JOIN service_category c ON c.CATEGORY_ID = s.category_id AND c.status = 1 WHERE s.id = ? AND s.status = 1');
            $stmt->execute([(int) $subKey]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $errors['subcategory'] = 'Send the category too when filtering by subcategory slug.';
        }
        if (!$errors && !$match) {
            public_json(404, 'Subcategory not found.');
        }
        $filters['subcategory_id'] = (int) ($match['id'] ?? 0);
    }

    $slug = public_str($in, 'slug');
    if ($slug !== '') {
        if (!preg_match(CATALOG_SLUG_PATTERN, $slug)) {
            $errors['slug'] = 'Invalid service slug.';
        }
        $filters['slug'] = $slug;
    }
    foreach (['ids' => '/^\d{1,10}$/', 'slugs' => CATALOG_SLUG_PATTERN] as $key => $pattern) {
        $raw = $in[$key] ?? null;
        if ($raw === null || $raw === '') {
            continue;
        }
        $values = array_values(array_filter(array_map('trim', is_array($raw) ? $raw : explode(',', (string) $raw)), 'strlen'));
        if (count($values) > 100 || array_filter($values, fn($v) => !is_scalar($v) || !preg_match($pattern, (string) $v))) {
            $errors[$key] = 'Send up to 100 comma-separated ' . ($key === 'ids' ? 'service ids.' : 'service slugs.');
            continue;
        }
        $filters[$key] = $key === 'ids' ? array_map('intval', $values) : $values;
    }
    if ($errors) {
        public_json(422, 'Please check the filters.', null, $errors);
    }

    [$items, $total] = catalog_services($pdo, $filters, $limit, $offset);
} catch (PDOException $e) {
    error_log('[api/catalog_services] ' . $e->getMessage());
    public_json(500, 'Could not load services. Please try again.');
}

if ($slug !== '' && !$items) {
    public_json(404, 'Service not found.');
}

$base = public_site_url();
foreach ($items as &$item) {
    $item['image_url'] = $item['image'] !== null ? $base . $item['image'] : null;
    $item['url'] = $base . $item['url'];
}
unset($item);

public_json_revalidate('OK', ['items' => $items, 'pagination' => public_pagination($page, $limit, $total)]);
