<?php
/**
 * Public: CMS pages managed in Admin > CMS Pages (no login).
 *
 * GET api/cms_pages.php?slug=about-us
 *   -> data: {slug, title, group, url, content (sanitized HTML), meta_title, meta_description, updated}
 *   slugs: about-us, contact-us, faq, terms, privacy-policy, refund-policy, ...
 *   404 when the page does not exist or is not Published.
 * GET api/cms_pages.php?page=1&limit=20
 *   -> data.items[]: published pages without content
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/site_content.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

$in = public_input();
$slug = strtolower(public_str($in, 'slug'));

try {
    if ($slug !== '') {
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            public_json(422, 'Invalid page slug.', null, ['slug' => 'Use lowercase letters, digits and hyphens.']);
        }
        $page = site_cms_page(public_db(), $slug);
        if (!$page) {
            public_json(404, 'Page not found or not published.');
        }
        header('Cache-Control: public, max-age=300');
        public_json(200, 'OK', $page);
    }

    [$pageNo, $limit, $offset] = public_page_params($in, 20, 50);
    [$items, $total] = site_cms_list(public_db(), $limit, $offset);
} catch (PDOException $e) {
    error_log('[api/cms_pages] ' . $e->getMessage());
    public_json(500, 'Could not load pages. Please try again.');
}

header('Cache-Control: public, max-age=300');
public_json(200, 'OK', ['items' => $items, 'pagination' => public_pagination($pageNo, $limit, $total)]);
