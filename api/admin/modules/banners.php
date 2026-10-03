<?php
/**
 * Website banners (new banners table).
 */

const BANNER_PLACEMENTS = ['Homepage Hero', 'Homepage Slider', 'Promotional'];

function banner_row(array $r): array
{
    $today = today();
    $live = (int) $r['status'] === 1 && (!$r['valid_from'] || $r['valid_from'] <= $today) && (!$r['valid_to'] || $r['valid_to'] >= $today);
    return [
        'id'        => (int) $r['id'],
        'title'     => $r['title'],
        'placement' => $r['placement'],
        'image'     => image_path($r['image'] ?? ''),
        'link'      => (string) $r['link'],
        'order'     => (int) $r['sort_order'],
        'from'      => $r['valid_from'],
        'to'        => $r['valid_to'],
        'status'    => (int) $r['status'] === 1,
        'live'      => $live,
    ];
}

function banners_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['placement'])) {
        $where[] = 'placement = ?';
        $params[] = (string) $in['placement'];
    }
    if (!empty($in['search'])) {
        $where[] = like_clause(['title', 'link'], trim((string) $in['search']), $params);
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM banners WHERE $sqlWhere", $params);
    $rows = q_all("SELECT * FROM banners WHERE $sqlWhere ORDER BY placement, sort_order, id LIMIT $limit OFFSET $offset", $params);
    return ok(paginated(array_map('banner_row', $rows), $total, $page, $limit));
}

function banner_find(int $id): array
{
    $row = $id ? q_one('SELECT * FROM banners WHERE id = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Banner');
    }
    return $row;
}

function banners_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id        = $v->int('id', 'Banner', ['min' => 1]);
    $title     = $v->str('title', 'Title', ['required' => true, 'max' => 80]);
    $placement = $v->enum('placement', 'Placement', BANNER_PLACEMENTS, ['required' => true]);
    $link      = $v->str('link', 'Link', ['max' => 255, 'default' => '', 'pattern' => '#^(https?://[^\s]+|/?[A-Za-z0-9_\-./?=&\#]*)$#', 'pattern_message' => 'Enter a page (e.g. ac-service.php) or a full https:// URL.']);
    $order     = $v->int('order', 'Order', ['required' => true, 'min' => 1, 'max' => 999]);
    $from      = $v->date('from', 'Start date');
    $to        = $v->date('to', 'End date');
    $status    = $v->bool('status', true);
    if ($from && $to && $to < $from) {
        $v->error('to', 'End date must be on or after the start date.');
    }
    $v->check();

    $existing = $id ? banner_find($id) : null;
    $image = save_uploaded_image('image', 'banners');
    if (!$existing && !$image) {
        throw new ApiException('Banner image is required.', 422, ['image' => 'Upload an image.']);
    }
    $values = [$title, $placement, ltrim((string) $link, '/') ?: null, $order, $from, $to, $status ? 1 : 0, now()];
    if ($existing) {
        q('UPDATE banners SET title = ?, placement = ?, link = ?, sort_order = ?, valid_from = ?, valid_to = ?, status = ?, updated_at = ?' . ($image ? ', image = ?' : '') . ' WHERE id = ?', array_merge($values, $image ? [$image] : [], [$id]));
        if ($image) {
            delete_uploaded_image($existing['image']);
        }
        return ok(banner_row(banner_find($id)), 'Banner updated.');
    }
    q('INSERT INTO banners (title, placement, link, sort_order, valid_from, valid_to, status, updated_at, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$image]));
    return ok(banner_row(banner_find((int) db()->lastInsertId())), 'Banner added.', 201);
}

function banners_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Banner', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    $row = banner_find($id);
    q('UPDATE banners SET status = ?, updated_at = ? WHERE id = ?', [$status ? 1 : 0, now(), $id]);
    return ok(['id' => $id, 'status' => $status], $row['title'] . ($status ? ' activated.' : ' deactivated.'));
}

function banners_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Banner', ['required' => true, 'min' => 1]);
    $v->check();
    $row = banner_find($id);
    q('DELETE FROM banners WHERE id = ?', [$id]);
    delete_uploaded_image($row['image']);
    return ok(['id' => $id], 'Banner deleted.');
}

return [
    'list'       => ['GET',  'banners_list'],
    'save'       => ['POST', 'banners_save'],
    'set_status' => ['POST', 'banners_set_status'],
    'delete'     => ['POST', 'banners_delete', 'super'],
];
