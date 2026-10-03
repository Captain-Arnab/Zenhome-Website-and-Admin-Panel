<?php
/**
 * Public: homepage "Smart Packages" cards from Admin > Smart Packages (no login).
 *
 * GET api/home_packages.php?page=1&limit=8
 * data.items[]: {id, title, label, icon, description, highlights[], url, url_full,
 *                button_text, featured, category_id, pack_id, price, order}
 * Only active cards whose linked category / service is live, in display order.
 * Revalidated on every request (ETag), so admin changes show up immediately.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/catalog_helper.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

[$page, $limit, $offset] = public_page_params(public_input(), 8, 50);

try {
    [$items, $total] = catalog_home_packages(public_db(), $limit, $offset);
} catch (PDOException $e) {
    error_log('[api/home_packages] ' . $e->getMessage());
    public_json(500, 'Could not load packages. Please try again.');
}

$base = public_site_url();
foreach ($items as &$item) {
    $item['url_full'] = preg_match('#^https?://#i', $item['url']) ? $item['url'] : $base . ltrim($item['url'], '/');
}
unset($item);

public_json_revalidate('OK', ['items' => $items, 'pagination' => public_pagination($page, $limit, $total)]);
