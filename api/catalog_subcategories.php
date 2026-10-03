<?php
/**
 * Public: active subcategories of an active category (no login).
 *
 * GET api/catalog_subcategories.php?category=<id|slug>
 * data: {category: {id, slug, name}, items[]: {id, category_id, slug, name, service_count}}
 * 404 when the category does not exist or is disabled.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/catalog_helper.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

$in = public_input();
$key = public_str($in, 'category') ?: public_str($in, 'category_id');
if ($key === '') {
    public_json(422, 'Choose a category.', null, ['category' => 'Send a category id or slug.']);
}

try {
    $pdo = public_db();
    $category = catalog_category($pdo, $key);
    if (!$category) {
        public_json(404, 'Category not found.');
    }
    $items = catalog_subcategories($pdo, $category['id']);
} catch (PDOException $e) {
    error_log('[api/catalog_subcategories] ' . $e->getMessage());
    public_json(500, 'Could not load subcategories. Please try again.');
}

public_json_revalidate('OK', [
    'category'   => ['id' => $category['id'], 'slug' => $category['slug'], 'name' => $category['name']],
    'items'      => $items,
    'pagination' => public_pagination(1, max(1, count($items)), count($items)),
]);
