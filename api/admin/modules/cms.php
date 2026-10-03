<?php
/**
 * CMS pages (cms_pages). The registry rows are seeded by the migration;
 * content is HTML from the editor, sanitized on save.
 */

function cms_row(array $r, bool $withContent = false): array
{
    $row = [
        'id'               => (int) $r['id'],
        'slug'             => $r['slug'],
        'title'            => $r['title'],
        'group'            => $r['page_group'],
        'url'              => (string) $r['url'],
        'meta_title'       => (string) $r['meta_title'],
        'meta_description' => (string) $r['meta_description'],
        'status'           => $r['status'],
        'updated'          => $r['updated_at'] ?: $r['created_at'],
        'has_content'      => trim((string) $r['content']) !== '',
    ];
    if ($withContent) {
        $row['content'] = (string) $r['content'];
    }
    return $row;
}

function cms_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $total = (int) q_value('SELECT COUNT(*) FROM cms_pages');
    $rows = q_all("SELECT * FROM cms_pages ORDER BY FIELD(page_group, 'Pages', 'Legal', 'Service Content'), page_group, title LIMIT $limit OFFSET $offset");
    return ok(paginated(array_map('cms_row', $rows), $total, $page, $limit));
}

function cms_get(array $in, ?array $admin): array
{
    $slug = (string) ($in['slug'] ?? '');
    $row = $slug !== '' ? q_one('SELECT * FROM cms_pages WHERE slug = ?', [$slug]) : null;
    if (!$row) {
        throw not_found('Page');
    }
    return ok(cms_row($row, true));
}

function cms_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $slug     = $v->str('slug', 'Page', ['required' => true, 'max' => 80]);
    $title    = $v->str('title', 'Page title', ['required' => true, 'max' => 150]);
    $content  = $v->str('content', 'Content', ['required' => true, 'html' => true, 'max' => 500000]);
    $metaT    = $v->str('meta_title', 'Meta title', ['max' => 70, 'default' => '']);
    $metaD    = $v->str('meta_description', 'Meta description', ['max' => 170, 'default' => '']);
    $status   = $v->enum('status', 'Status', ['Draft', 'Published'], ['default' => 'Draft']);
    $v->check();

    $row = q_one('SELECT id FROM cms_pages WHERE slug = ?', [$slug]);
    if (!$row) {
        throw not_found('Page');
    }
    $clean = sanitize_html((string) $content);
    if (trim(strip_tags($clean, '<img>')) === '') {
        throw new ApiException('Content cannot be empty.', 422, ['content' => 'Content is required.']);
    }
    q(
        'UPDATE cms_pages SET title = ?, content = ?, meta_title = ?, meta_description = ?, status = ?, updated_by = ?, updated_at = ? WHERE id = ?',
        [$title, $clean, $metaT ?: null, $metaD ?: null, $status ?? 'Draft', $admin['id'], now(), $row['id']]
    );
    return ok(cms_row(q_one('SELECT * FROM cms_pages WHERE id = ?', [$row['id']]), true), $status === 'Published' ? 'Page published.' : 'Draft saved.');
}

return [
    'list' => ['GET',  'cms_list'],
    'get'  => ['GET',  'cms_get'],
    'save' => ['POST', 'cms_save'],
];
