<?php
/**
 * Subcategories (service_subcategories): optional grouping between a
 * category and its services (SaverPacks.subcategory_id).
 */

function subcategory_row(array $r): array
{
    return [
        'id'          => (int) $r['id'],
        'category_id' => (int) $r['category_id'],
        'category'    => (string) ($r['category_name'] ?? ''),
        'name'        => (string) $r['name'],
        'slug'        => (string) ($r['slug'] ?? ''),
        'order'       => (int) $r['sort_order'],
        'status'      => (int) $r['status'] === 1,
        'services'    => (int) ($r['service_count'] ?? 0),
    ];
}

function subcategories_select_sql(): string
{
    return 'SELECT s.*, c.NAME AS category_name,
            (SELECT COUNT(DISTINCT sp.subcategory) FROM SaverPacks sp WHERE sp.subcategory_id = s.id) AS service_count
        FROM service_subcategories s
        JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = s.category_id';
}

function subcategories_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['s.name', 'c.NAME'], trim((string) $in['search']), $params);
    }
    if (!empty($in['category_id'])) {
        $where[] = 's.category_id = ?';
        $params[] = (int) $in['category_id'];
    }
    $status = strtolower((string) ($in['status'] ?? ''));
    if ($status === 'active' || $status === 'inactive') {
        $where[] = 's.status = ?';
        $params[] = $status === 'active' ? 1 : 0;
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM service_subcategories s JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = s.category_id WHERE $sqlWhere", $params);
    $rows = q_all(subcategories_select_sql() . " WHERE $sqlWhere ORDER BY c.sort_order, s.sort_order, s.name LIMIT $limit OFFSET $offset", $params);
    return ok(paginated(array_map('subcategory_row', $rows), $total, $page, $limit));
}

function subcategory_find(int $id): array
{
    $row = $id ? q_one(subcategories_select_sql() . ' WHERE s.id = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Subcategory');
    }
    return $row;
}

function subcategories_get(array $in, ?array $admin): array
{
    return ok(subcategory_row(subcategory_find((int) ($in['id'] ?? 0))));
}

function subcategories_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id     = $v->int('id', 'Subcategory', ['min' => 1]);
    $name   = $v->str('name', 'Subcategory name', ['required' => true, 'max' => 120]);
    $order  = $v->int('order', 'Display order', ['min' => 0, 'max' => 9999, 'default' => 0]);
    $slug   = $v->str('slug', 'URL slug', ['max' => 100, 'pattern' => SLUG_PATTERN, 'pattern_message' => 'Use lowercase letters, numbers and hyphens.', 'default' => '']);
    $status = $v->bool('status', true);
    if (!$v->has('category')) {
        $v->error('category', 'Please choose a category.');
    }
    $v->check();
    $categoryId = resolve_category_id($in['category']);
    if (!$categoryId) {
        throw new ApiException('Category not found.', 422, ['category' => 'Please choose a valid category.']);
    }

    $existing = $id ? subcategory_find($id) : null;
    if (q_value('SELECT 1 FROM service_subcategories WHERE category_id = ? AND LOWER(name) = LOWER(?) AND id <> ?', [$categoryId, $name, $id ?? 0])) {
        throw new ApiException('This category already has a subcategory with that name.', 422, ['name' => 'Name already used in this category.']);
    }

    $slug = $slug !== '' ? $slug : ((string) ($existing['slug'] ?? '') ?: slugify($name, 100));
    if ($slug === '' || q_value('SELECT 1 FROM service_subcategories WHERE category_id = ? AND slug = ? AND id <> ?', [$categoryId, $slug, $id ?? 0])) {
        throw new ApiException('This category already has a subcategory with that URL slug.', 422, ['slug' => 'Choose a different slug.']);
    }

    if ($existing) {
        q('UPDATE service_subcategories SET category_id = ?, name = ?, slug = ?, sort_order = ?, status = ?, updated_at = ? WHERE id = ?', [$categoryId, $name, $slug, $order, $status ? 1 : 0, now(), $id]);
        if ((int) $existing['category_id'] !== $categoryId) {
            // Services keep their category; unlink them from a subcategory that moved away.
            q('UPDATE SaverPacks SET subcategory_id = NULL WHERE subcategory_id = ? AND category_Id <> ?', [$id, $categoryId]);
        }
    } else {
        q('INSERT INTO service_subcategories (category_id, name, slug, sort_order, status, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$categoryId, $name, $slug, $order, $status ? 1 : 0, now()]);
        $id = (int) db()->lastInsertId();
    }
    return ok(subcategory_row(subcategory_find($id)), $existing ? 'Subcategory updated.' : 'Subcategory created.', $existing ? 200 : 201);
}

function subcategories_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Subcategory', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    $row = subcategory_find($id);
    q('UPDATE service_subcategories SET status = ?, updated_at = ? WHERE id = ?', [$status ? 1 : 0, now(), $id]);
    return ok(['id' => $id, 'status' => $status], $row['name'] . ($status ? ' activated.' : ' deactivated.'));
}

function subcategories_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Subcategory', ['required' => true, 'min' => 1]);
    $v->check();
    $row = subcategory_find($id);
    if ((int) $row['service_count'] > 0) {
        throw new ApiException('Move this subcategory\'s services to another subcategory first (or deactivate it).', 409);
    }
    q('DELETE FROM service_subcategories WHERE id = ?', [$id]);
    return ok(['id' => $id], 'Subcategory deleted.');
}

return [
    'list'       => ['GET',  'subcategories_list'],
    'get'        => ['GET',  'subcategories_get'],
    'save'       => ['POST', 'subcategories_save'],
    'set_status' => ['POST', 'subcategories_set_status'],
    'delete'     => ['POST', 'subcategories_delete', 'super'],
];
