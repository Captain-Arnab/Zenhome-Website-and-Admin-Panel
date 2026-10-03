<?php
/**
 * Public: active service categories for the website and apps (no login).
 *
 * GET api/catalog_categories.php?page=1&limit=50
 *     api/catalog_categories.php?category=<id|slug>   one category
 * data.items[]: {id, slug, name, title, description, tagline, label, icon,
 *   highlights[], image, image_url, cover_image, cover_image_url, url,
 *   subcategory_count, service_count}
 * Disabled categories are never returned. Responses carry an ETag and
 * "Cache-Control: no-cache", so admin edits show up immediately.
 * (api/category.php is unchanged and still serves the apps' list.)
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/catalog_helper.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

$in = public_input();
$key = public_str($in, 'category') ?: public_str($in, 'slug') ?: public_str($in, 'id');
[$page, $limit, $offset] = public_page_params($in, 50, 100);
$base = public_site_url();

function catalog_category_public(array $c, string $base): array
{
    $c['image_url'] = $c['image'] !== null ? $base . $c['image'] : null;
    $c['cover_image_url'] = $c['cover_image'] !== null ? $base . $c['cover_image'] : null;
    $c['url'] = $base . $c['url'];
    return $c;
}

try {
    $pdo = public_db();
    if ($key !== '') {
        $category = catalog_category($pdo, $key);
        if (!$category) {
            public_json(404, 'Category not found.');
        }
        public_json_revalidate('OK', ['item' => catalog_category_public($category, $base)]);
    }
    [$items, $total] = catalog_categories($pdo, $limit, $offset);
} catch (PDOException $e) {
    error_log('[api/catalog_categories] ' . $e->getMessage());
    public_json(500, 'Could not load categories. Please try again.');
}

public_json_revalidate('OK', [
    'items'      => array_map(fn($c) => catalog_category_public($c, $base), $items),
    'pagination' => public_pagination($page, $limit, $total),
]);
