<?php
/**
 * Services. The apps read services from SaverPacks, where one service is a
 * group of rows sharing (category_Id, subcategory); each row is one
 * "what's included" item and repeats price / description / time.
 * The service id exposed here is the group's first packId (MIN(packId)),
 * which stays stable across edits.
 */

function services_group_sql(): string
{
    return 'SELECT MIN(sp.packId) AS id, sp.category_Id AS category_id, sp.subcategory AS name,
                MAX(sp.price) AS price, MAX(sp.mrp) AS mrp, MAX(sp.serviceTime) AS duration,
                MAX(sp.image) AS image, MAX(sp.status) AS status, MAX(sp.subcategory_id) AS subcategory_id,
                MAX(sp.whyChooseThisPack) AS description, MAX(sp.idealFor) AS ideal_for, COUNT(*) AS item_count,
                MAX(sp.slug) AS slug, MAX(sp.web_tag) AS tag, MAX(sp.highlights) AS highlights, MAX(sp.sort_order) AS sort_order
            FROM SaverPacks sp
            GROUP BY sp.category_Id, sp.subcategory';
}

function services_select_sql(): string
{
    return 'SELECT g.*, c.NAME AS category_name, s.name AS subcategory_name,
            (SELECT COUNT(*) FROM Service_booking b WHERE b.subcategories LIKE CONCAT(\'%\', g.name, \'%\')) AS booking_count
        FROM (' . services_group_sql() . ') g
        LEFT JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = g.category_id
        LEFT JOIN service_subcategories s ON s.id = g.subcategory_id';
}

function service_row(array $r): array
{
    return [
        'id'             => (int) $r['id'],
        'name'           => (string) $r['name'],
        'category_id'    => (int) $r['category_id'],
        'category'       => (string) ($r['category_name'] ?? ''),
        'subcategory_id' => $r['subcategory_id'] !== null ? (int) $r['subcategory_id'] : null,
        'subcategory'    => (string) ($r['subcategory_name'] ?? ''),
        'price'          => (float) $r['price'],
        'mrp'            => $r['mrp'] !== null ? (float) $r['mrp'] : null,
        'duration'       => (string) ($r['duration'] ?? ''),
        'image'          => image_path($r['image'] ?? ''),
        'status'         => (int) $r['status'] === 1,
        'description'    => (string) ($r['description'] ?? ''),
        'ideal_for'      => (string) ($r['ideal_for'] ?? ''),
        'slug'           => (string) ($r['slug'] ?? ''),
        'tag'            => (string) ($r['tag'] ?? ''),
        'highlights'     => (string) ($r['highlights'] ?? ''),
        'order'          => (int) ($r['sort_order'] ?? 0),
        'items'          => (int) $r['item_count'],
        'bookings'       => (int) ($r['booking_count'] ?? 0),
    ];
}

function services_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['g.name', 'c.NAME', 's.name'], trim((string) $in['search']), $params);
    }
    foreach (['category_id' => 'g.category_id', 'subcategory_id' => 'g.subcategory_id'] as $key => $col) {
        if (!empty($in[$key])) {
            $where[] = "$col = ?";
            $params[] = (int) $in[$key];
        }
    }
    $status = strtolower((string) ($in['status'] ?? ''));
    if ($status === 'enabled' || $status === 'disabled') {
        $where[] = 'g.status = ?';
        $params[] = $status === 'enabled' ? 1 : 0;
    }
    $sqlWhere = implode(' AND ', $where);
    $from = '(' . services_group_sql() . ') g
        LEFT JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = g.category_id
        LEFT JOIN service_subcategories s ON s.id = g.subcategory_id';
    $total = (int) q_value("SELECT COUNT(*) FROM $from WHERE $sqlWhere", $params);
    $rows = q_all(services_select_sql() . " WHERE $sqlWhere ORDER BY c.sort_order, g.sort_order = 0, g.sort_order, g.name LIMIT $limit OFFSET $offset", $params);
    return ok(paginated(array_map('service_row', $rows), $total, $page, $limit));
}

/** id/name/category for pickers (e.g. location service map). */
function services_options(array $in, ?array $admin): array
{
    $rows = q_all('SELECT g.id, g.name, g.status, g.category_id, c.NAME AS category FROM (' . services_group_sql() . ') g LEFT JOIN SERVICE_CATEGORY c ON c.CATEGORY_ID = g.category_id ORDER BY c.sort_order, g.name');
    return ok(['items' => array_map(fn($r) => [
        'id' => (int) $r['id'], 'name' => $r['name'], 'category_id' => (int) $r['category_id'],
        'category' => (string) $r['category'], 'status' => (int) $r['status'] === 1,
    ], $rows)]);
}

/** @return array{0:array,1:array} [service row, SaverPacks rows of the group] */
function service_find(int $id): array
{
    $first = $id ? q_one('SELECT category_Id, subcategory FROM SaverPacks WHERE packId = ?', [$id]) : null;
    if (!$first) {
        throw not_found('Service');
    }
    $row = q_one(services_select_sql() . ' WHERE g.category_id = ? AND g.name = ?', [$first['category_Id'], $first['subcategory']]);
    if (!$row || (int) $row['id'] !== $id) {
        throw not_found('Service');
    }
    $packs = q_all('SELECT * FROM SaverPacks WHERE category_Id = ? AND subcategory = ? ORDER BY packId', [$first['category_Id'], $first['subcategory']]);
    return [$row, $packs];
}

function services_get(array $in, ?array $admin): array
{
    [$row, $packs] = service_find((int) ($in['id'] ?? 0));
    $service = service_row($row);
    $service['included'] = array_map(fn($p) => [
        'id'          => (int) $p['packId'],
        'title'       => (string) $p['whatsIncluded'],
        'description' => (string) $p['includedDescription'],
    ], $packs);
    return ok($service);
}

/**
 * "What's included" textarea: one item per line, "Title | description".
 * @return array<int,array{0:string,1:string}>
 */
function services_parse_included(string $text, string $fallback): array
{
    $items = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        [$title, $desc] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
        $items[] = [mb_substr($title, 0, 255), $desc];
    }
    return $items ?: [[mb_substr($fallback, 0, 255), '']];
}

function services_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id       = $v->int('id', 'Service', ['min' => 1]);
    $name     = $v->str('name', 'Service name', ['required' => true, 'max' => 120]);
    $desc     = $v->str('description', 'Description', ['required' => true, 'max' => 1000]);
    $price    = $v->num('price', 'Price', ['required' => true, 'min' => 0, 'max' => 1000000]);
    $mrp      = $v->num('mrp', 'MRP', ['min' => 0, 'max' => 1000000]);
    $duration = $v->str('duration', 'Duration', ['max' => 100, 'default' => '']);
    $idealFor = $v->str('ideal_for', 'Ideal for', ['max' => 255, 'default' => '']);
    $included = $v->str('included', "What's included", ['max' => 5000, 'default' => '']);
    $slug     = $v->str('slug', 'URL slug', ['max' => 100, 'pattern' => SLUG_PATTERN, 'pattern_message' => 'Use lowercase letters, numbers and hyphens, e.g. ac-gas-refill.', 'default' => '']);
    $tag      = $v->str('tag', 'Card badge', ['max' => 40, 'default' => '']);
    $highlights = clean_lines($v->str('highlights', 'Card highlights', ['max' => 1000, 'default' => '']), 6, 80);
    $order    = $v->int('order', 'Website order', ['min' => 0, 'max' => 9999, 'default' => 0]);
    $status   = $v->bool('status', true);
    if (!$v->has('category')) {
        $v->error('category', 'Please choose a category.');
    }
    $v->check();

    $categoryId = resolve_service_category($in['category']);
    $subId = null;
    if (!empty($in['subcategory'])) {
        $subId = (int) q_value('SELECT id FROM service_subcategories WHERE category_id = ? AND (id = ? OR name = ?)', [$categoryId, ctype_digit((string) $in['subcategory']) ? (int) $in['subcategory'] : 0, (string) $in['subcategory']]);
        if (!$subId) {
            throw new ApiException('Subcategory does not belong to this category.', 422, ['subcategory' => 'Choose a subcategory of the selected category.']);
        }
    }
    if ($mrp !== null && $mrp > 0 && $mrp < $price) {
        throw new ApiException('MRP must be greater than or equal to the price.', 422, ['mrp' => 'MRP must be at least the price.']);
    }

    $existing = null;
    $packs = [];
    if ($id) {
        [$existing, $packs] = service_find($id);
    }
    $clash = q_value('SELECT MIN(packId) FROM SaverPacks WHERE category_Id = ? AND subcategory = ?', [$categoryId, $name]);
    if ($clash !== null && (int) $clash !== (int) $id) {
        throw new ApiException('A service with this name already exists in this category.', 422, ['name' => 'Name already used in this category.']);
    }

    // Slug: website address / cart id of the service, unique across all services.
    $slug = $slug !== '' ? $slug : ((string) ($existing['slug'] ?? '') ?: slugify($name, 100));
    $ownIds = array_map(fn($p) => (int) $p['packId'], $packs) ?: [0];
    if ($slug === '' || q_value('SELECT 1 FROM SaverPacks WHERE slug = ? AND packId NOT IN (' . implode(',', $ownIds) . ')', [$slug])) {
        throw new ApiException('This URL slug is already used by another service.', 422, ['slug' => 'Choose a different slug.']);
    }
    if (strlen($highlights) > 500) {
        throw new ApiException('Card highlights are too long.', 422, ['highlights' => 'Keep the highlights short (500 characters in total).']);
    }

    $items = services_parse_included((string) $included, $name);
    $image = save_uploaded_image('image', 'services');
    $imageValue = $image ?? ($existing['image'] ?? null);
    $common = [$categoryId, $name, $price, $desc, $idealFor ?: null, $duration, $subId, $imageValue, $mrp ?: null, $status ? 1 : 0, now(), $slug, $tag ?: null, $highlights ?: null, $order];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($items as $i => [$title, $itemDesc]) {
            if (isset($packs[$i])) {
                q(
                    'UPDATE SaverPacks SET category_Id = ?, subcategory = ?, price = ?, whyChooseThisPack = ?, idealFor = ?, serviceTime = ?, subcategory_id = ?, image = ?, mrp = ?, status = ?, updated_at = ?,
                        slug = ?, web_tag = ?, highlights = ?, sort_order = ?, whatsIncluded = ?, includedDescription = ? WHERE packId = ?',
                    array_merge($common, [$title, $itemDesc, $packs[$i]['packId']])
                );
            } else {
                q(
                    'INSERT INTO SaverPacks (category_Id, subcategory, price, whyChooseThisPack, idealFor, serviceTime, subcategory_id, image, mrp, status, updated_at, slug, web_tag, highlights, sort_order, whatsIncluded, includedDescription)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($common, [$title, $itemDesc])
                );
                if (!$id) {
                    $id = (int) $pdo->lastInsertId();
                }
            }
        }
        foreach (array_slice($packs, count($items)) as $surplus) {
            q('DELETE FROM SaverPacks WHERE packId = ?', [$surplus['packId']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($image) {
            delete_uploaded_image($image);
        }
        throw $e;
    }
    if ($image && $existing && $existing['image'] !== $image) {
        delete_uploaded_image($existing['image']);
    }

    return ok(services_get(['id' => $id], $admin)['data'], $existing ? 'Service updated.' : 'Service created.', $existing ? 200 : 201);
}

function resolve_service_category($value): int
{
    $id = resolve_category_id($value);
    if (!$id) {
        throw new ApiException('Category not found.', 422, ['category' => 'Please choose a valid category.']);
    }
    return $id;
}

function services_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Service', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    [$row] = service_find($id);
    q('UPDATE SaverPacks SET status = ?, updated_at = ? WHERE category_Id = ? AND subcategory = ?', [$status ? 1 : 0, now(), $row['category_id'], $row['name']]);
    return ok(['id' => $id, 'status' => $status], $row['name'] . ($status ? ' enabled.' : ' disabled.'));
}

function services_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Service', ['required' => true, 'min' => 1]);
    $v->check();
    [$row] = service_find($id);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('DELETE FROM SaverPacks WHERE category_Id = ? AND subcategory = ?', [$row['category_id'], $row['name']]);
        q('DELETE FROM area_services WHERE service_id = ?', [$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    delete_uploaded_image($row['image']);
    return ok(['id' => $id], 'Service deleted. Past bookings keep their service name.');
}

return [
    'list'       => ['GET',  'services_list'],
    'options'    => ['GET',  'services_options'],
    'get'        => ['GET',  'services_get'],
    'save'       => ['POST', 'services_save'],
    'set_status' => ['POST', 'services_set_status'],
    'delete'     => ['POST', 'services_delete', 'super'],
];
