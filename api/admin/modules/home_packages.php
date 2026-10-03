<?php
/**
 * Homepage "Smart Packages" cards (home_packages table).
 * A card links to a category page, one service (SaverPacks id) or a custom link.
 */

function home_package_row(array $r): array
{
    return [
        'id'          => (int) $r['id'],
        'title'       => $r['title'],
        'label'       => (string) $r['label'],
        'icon'        => (string) $r['icon'],
        'description' => (string) $r['description'],
        'highlights'  => (string) $r['highlights'],
        'category_id' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
        'category'    => $r['category_name'] ?? null,
        'pack_id'     => $r['pack_id'] !== null ? (int) $r['pack_id'] : null,
        'service'     => $r['service_name'] ?? null,
        'link'        => (string) $r['link'],
        'button_text' => (string) $r['button_text'],
        'featured'    => (int) $r['is_featured'] === 1,
        'order'       => (int) $r['sort_order'],
        'status'      => (int) $r['status'] === 1,
    ];
}

function home_packages_sql(): string
{
    return 'SELECT p.*, c.NAME AS category_name, (SELECT sp.subcategory FROM SaverPacks sp WHERE sp.packId = p.pack_id) AS service_name
        FROM home_packages p LEFT JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = p.category_id';
}

function home_packages_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $total = (int) q_value('SELECT COUNT(*) FROM home_packages');
    $rows = q_all(home_packages_sql() . " ORDER BY p.sort_order, p.id LIMIT $limit OFFSET $offset");
    return ok(paginated(array_map('home_package_row', $rows), $total, $page, $limit));
}

function home_package_find(int $id): array
{
    $row = $id ? q_one(home_packages_sql() . ' WHERE p.id = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Package');
    }
    return $row;
}

function home_packages_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id          = $v->int('id', 'Package', ['min' => 1]);
    $title       = $v->str('title', 'Title', ['required' => true, 'max' => 80]);
    $label       = $v->str('label', 'Label', ['max' => 30, 'default' => '']);
    $icon        = $v->str('icon', 'Icon', ['max' => 60, 'default' => '', 'pattern' => '/^(fa-(solid|regular|brands) fa-[a-z0-9-]+)?$/', 'pattern_message' => 'Use a Font Awesome class, e.g. fa-solid fa-broom']);
    $description = $v->str('description', 'Description', ['max' => 300, 'default' => '']);
    $highlights  = clean_lines($v->str('highlights', 'Points', ['max' => 500, 'default' => '']), 5, 80);
    $categoryId  = $v->int('category_id', 'Category', ['min' => 1]);
    $packId      = $v->int('pack_id', 'Service', ['min' => 1]);
    $link        = $v->str('link', 'Custom link', ['max' => 255, 'default' => '', 'pattern' => '#^(https?://[^\s]+|/?[A-Za-z0-9_\-./?=&\#]*)$#', 'pattern_message' => 'Enter a page (e.g. ac-service.php) or a full https:// URL.']);
    $buttonText  = $v->str('button_text', 'Button text', ['max' => 30, 'default' => '']);
    $featured    = $v->bool('featured');
    $order       = $v->int('order', 'Order', ['required' => true, 'min' => 1, 'max' => 999]);
    $status      = $v->bool('status', true);
    if ($categoryId && !q_value('SELECT 1 FROM SERVICE_CATEGORY WHERE CATEGORY_ID = ?', [$categoryId])) {
        $v->error('category_id', 'Choose an existing category.');
    }
    if ($packId) {
        $packCategory = q_value('SELECT category_Id FROM SaverPacks WHERE packId = ?', [$packId]);
        if (!$packCategory) {
            $v->error('pack_id', 'Choose an existing service.');
        } elseif ($categoryId && (int) $packCategory !== $categoryId) {
            $v->error('pack_id', 'This service belongs to a different category.');
        }
    }
    if (!$categoryId && !$packId && $link === '') {
        $v->error('category_id', 'Choose where the card links to: a category, a service or a custom link.');
    }
    $v->check();

    $values = [$title, $label ?: null, $icon ?: null, $description ?: null, $highlights ?: null, $categoryId, $packId,
        ltrim((string) $link, '/') ?: null, $buttonText ?: null, $featured ? 1 : 0, $order, $status ? 1 : 0, now()];
    if ($id) {
        home_package_find($id);
        q('UPDATE home_packages SET title = ?, label = ?, icon = ?, description = ?, highlights = ?, category_id = ?, pack_id = ?, link = ?,
            button_text = ?, is_featured = ?, sort_order = ?, status = ?, updated_at = ? WHERE id = ?', array_merge($values, [$id]));
        return ok(home_package_row(home_package_find($id)), 'Package updated.');
    }
    q('INSERT INTO home_packages (title, label, icon, description, highlights, category_id, pack_id, link, button_text, is_featured, sort_order, status, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
    return ok(home_package_row(home_package_find((int) db()->lastInsertId())), 'Package added.', 201);
}

function home_packages_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Package', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    $row = home_package_find($id);
    q('UPDATE home_packages SET status = ?, updated_at = ? WHERE id = ?', [$status ? 1 : 0, now(), $id]);
    return ok(['id' => $id, 'status' => $status], $row['title'] . ($status ? ' activated.' : ' deactivated.'));
}

function home_packages_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Package', ['required' => true, 'min' => 1]);
    $v->check();
    home_package_find($id);
    q('DELETE FROM home_packages WHERE id = ?', [$id]);
    return ok(['id' => $id], 'Package deleted.');
}

return [
    'list'       => ['GET',  'home_packages_list'],
    'save'       => ['POST', 'home_packages_save'],
    'set_status' => ['POST', 'home_packages_set_status'],
    'delete'     => ['POST', 'home_packages_delete', 'super'],
];
