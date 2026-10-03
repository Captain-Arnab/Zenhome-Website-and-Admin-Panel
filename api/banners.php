<?php
/**
 * Public: live website/app banners (no login).
 *
 * GET api/banners.php?placement=Homepage%20Slider&page=1&limit=10
 *   placement: "Homepage Hero" | "Homepage Slider" | "Promotional" (optional, default all)
 * data.items[]: {id, title, placement, image, image_url, link, order, valid_to}
 * Only active banners inside their date range are returned, by placement + order.
 * Revalidated on every request (ETag), so admin changes show up immediately.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/site_content.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

$in = public_input();
$placement = public_str($in, 'placement');
if ($placement !== '' && !in_array($placement, SITE_BANNER_PLACEMENTS, true)) {
    public_json(422, 'Unknown placement.', null, ['placement' => 'Use one of: ' . implode(', ', SITE_BANNER_PLACEMENTS) . '.']);
}
[$page, $limit, $offset] = public_page_params($in, 10, 50);

try {
    [$items, $total] = site_banners(public_db(), $placement !== '' ? $placement : null, $limit, $offset);
} catch (PDOException $e) {
    error_log('[api/banners] ' . $e->getMessage());
    public_json(500, 'Could not load banners. Please try again.');
}

$base = public_site_url();
foreach ($items as &$item) {
    $item['image_url'] = $base . $item['image'];
    if ($item['link'] !== '' && !preg_match('#^https?://#i', $item['link'])) {
        $item['link_url'] = $base . ltrim($item['link'], '/');
    } else {
        $item['link_url'] = $item['link'];
    }
}
unset($item);

public_json_revalidate('OK', ['items' => $items, 'pagination' => public_pagination($page, $limit, $total)]);
