<?php
/**
 * Locations: cities (new), areas (serviceable_areas, read by the public
 * api/fetch_serviceable_areas.php), PIN codes (new) and the services
 * offered per area (area_services).
 */

function location_city_id($value, bool $required = true): ?int
{
    if ($value === null || $value === '' || !is_scalar($value)) {
        if ($required) {
            throw new ApiException('Please choose a city.', 422, ['city' => 'Select a city.']);
        }
        return null;
    }
    $id = ctype_digit((string) $value)
        ? q_value('SELECT id FROM cities WHERE id = ?', [(int) $value])
        : q_value('SELECT id FROM cities WHERE name = ?', [(string) $value]);
    if ($id === null) {
        throw new ApiException('City not found.', 422, ['city' => 'Select a valid city.']);
    }
    return (int) $id;
}

/* ---------------------------------- Cities ---------------------------------- */

function locations_cities(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 200);
    $where = '1 = 1';
    $params = [];
    if (!empty($in['search'])) {
        $where = like_clause(['c.name', 'c.state'], trim((string) $in['search']), $params);
    }
    $total = (int) q_value("SELECT COUNT(*) FROM cities c WHERE $where", $params);
    $rows = q_all(
        "SELECT c.*, (SELECT COUNT(*) FROM serviceable_areas a WHERE a.city_id = c.id) AS areas,
                (SELECT COUNT(*) FROM pincodes p WHERE p.city_id = c.id) AS pincodes
         FROM cities c WHERE $where ORDER BY c.name LIMIT $limit OFFSET $offset",
        $params
    );
    $items = array_map(fn($r) => [
        'id' => (int) $r['id'], 'name' => $r['name'], 'state' => $r['state'], 'status' => (int) $r['status'] === 1,
        'areas' => (int) $r['areas'], 'pincodes' => (int) $r['pincodes'],
    ], $rows);
    return ok(paginated($items, $total, $page, $limit));
}

function locations_city_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id     = $v->int('id', 'City', ['min' => 1]);
    $name   = $v->str('name', 'City name', ['required' => true, 'max' => 100]);
    $state  = $v->str('state', 'State', ['required' => true, 'max' => 100]);
    $status = $v->bool('status', true);
    $v->check();
    if ($id && !q_value('SELECT 1 FROM cities WHERE id = ?', [$id])) {
        throw not_found('City');
    }
    if (q_value('SELECT 1 FROM cities WHERE LOWER(name) = LOWER(?) AND LOWER(state) = LOWER(?) AND id <> ?', [$name, $state, $id ?? 0])) {
        throw new ApiException('This city already exists.', 422, ['name' => 'City already exists in this state.']);
    }
    if ($id) {
        q('UPDATE cities SET name = ?, state = ?, status = ? WHERE id = ?', [$name, $state, $status ? 1 : 0, $id]);
        return ok(['id' => $id], 'City updated.');
    }
    q('INSERT INTO cities (name, state, status, created_at) VALUES (?, ?, ?, ?)', [$name, $state, $status ? 1 : 0, now()]);
    return ok(['id' => (int) db()->lastInsertId()], 'City added.', 201);
}

function locations_city_status(array $in, ?array $admin): array
{
    return locations_toggle('cities', 'id', $in, 'City');
}

function locations_city_delete(array $in, ?array $admin): array
{
    $id = locations_require_id($in, 'cities', 'City');
    if (q_value('SELECT 1 FROM serviceable_areas WHERE city_id = ? LIMIT 1', [$id]) || q_value('SELECT 1 FROM pincodes WHERE city_id = ? LIMIT 1', [$id])) {
        throw new ApiException('Remove or move this city\'s areas and PIN codes first (or disable the city).', 409);
    }
    q('DELETE FROM cities WHERE id = ?', [$id]);
    return ok(['id' => $id], 'City deleted.');
}

/* ---------------------------------- Areas ----------------------------------- */

function locations_areas(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 200);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['a.name', 'a.pincode', 'c.name'], trim((string) $in['search']), $params);
    }
    if (!empty($in['city_id'])) {
        $where[] = 'a.city_id = ?';
        $params[] = (int) $in['city_id'];
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM serviceable_areas a LEFT JOIN cities c ON c.id = a.city_id WHERE $sqlWhere", $params);
    $rows = q_all(
        "SELECT a.*, c.name AS city_name, (SELECT COUNT(*) FROM area_services s WHERE s.area_id = a.id) AS services
         FROM serviceable_areas a LEFT JOIN cities c ON c.id = a.city_id
         WHERE $sqlWhere ORDER BY a.sort_order, a.name LIMIT $limit OFFSET $offset",
        $params
    );
    $items = array_map(fn($r) => [
        'id' => (int) $r['id'], 'name' => $r['name'], 'pincode' => $r['pincode'], 'city_id' => $r['city_id'] !== null ? (int) $r['city_id'] : null,
        'city' => (string) ($r['city_name'] ?? ''), 'status' => (int) $r['status'] === 1, 'services' => (int) $r['services'], 'order' => (int) $r['sort_order'],
    ], $rows);
    return ok(paginated($items, $total, $page, $limit, ['total_services' => (int) q_value('SELECT COUNT(DISTINCT category_Id, subcategory) FROM SaverPacks')]));
}

function locations_area_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id      = $v->int('id', 'Area', ['min' => 1]);
    $name    = $v->str('name', 'Area name', ['required' => true, 'max' => 100]);
    $pincode = $v->str('pincode', 'PIN code', ['required' => true, 'pattern' => '/^[1-9]\d{5}$/', 'pattern_message' => 'Enter a valid 6-digit PIN code.']);
    $status  = $v->bool('status', true);
    $v->check();
    // Existing areas were created without a city, so the city stays optional.
    $cityId = location_city_id($in['city'] ?? null, false);
    if ($id && !q_value('SELECT 1 FROM serviceable_areas WHERE id = ?', [$id])) {
        throw not_found('Area');
    }
    if (q_value('SELECT 1 FROM serviceable_areas WHERE LOWER(name) = LOWER(?) AND pincode = ? AND id <> ?', [$name, $pincode, $id ?? 0])) {
        throw new ApiException('This area already exists.', 422, ['name' => 'Area with this PIN code already exists.']);
    }
    if ($id) {
        q('UPDATE serviceable_areas SET name = ?, pincode = ?, city_id = ?, status = ? WHERE id = ?', [$name, $pincode, $cityId, $status ? 1 : 0, $id]);
        $message = 'Area updated.';
    } else {
        $order = (int) q_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM serviceable_areas');
        q('INSERT INTO serviceable_areas (name, pincode, sort_order, city_id, status) VALUES (?, ?, ?, ?, ?)', [$name, $pincode, $order, $cityId, $status ? 1 : 0]);
        $id = (int) db()->lastInsertId();
        $message = 'Area added.';
    }
    return ok(['id' => $id], $message, $message === 'Area added.' ? 201 : 200);
}

function locations_area_status(array $in, ?array $admin): array
{
    return locations_toggle('serviceable_areas', 'id', $in, 'Area');
}

function locations_area_delete(array $in, ?array $admin): array
{
    $id = locations_require_id($in, 'serviceable_areas', 'Area');
    q('DELETE FROM serviceable_areas WHERE id = ?', [$id]);
    return ok(['id' => $id], 'Area deleted.');
}

/* -------------------------------- PIN codes --------------------------------- */

function locations_pincodes(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 200);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['p.pincode', 'p.area_name', 'c.name'], trim((string) $in['search']), $params);
    }
    if (!empty($in['city_id'])) {
        $where[] = 'p.city_id = ?';
        $params[] = (int) $in['city_id'];
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM pincodes p LEFT JOIN cities c ON c.id = p.city_id WHERE $sqlWhere", $params);
    $rows = q_all("SELECT p.*, c.name AS city_name FROM pincodes p LEFT JOIN cities c ON c.id = p.city_id WHERE $sqlWhere ORDER BY p.pincode LIMIT $limit OFFSET $offset", $params);
    $items = array_map(fn($r) => [
        'id' => (int) $r['id'], 'code' => $r['pincode'], 'area' => (string) $r['area_name'], 'city_id' => $r['city_id'] !== null ? (int) $r['city_id'] : null,
        'city' => (string) ($r['city_name'] ?? ''), 'status' => (int) $r['status'] === 1,
    ], $rows);
    return ok(paginated($items, $total, $page, $limit));
}

function locations_pincode_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id     = $v->int('id', 'PIN code', ['min' => 1]);
    $code   = $v->str('code', 'PIN code', ['required' => true, 'pattern' => '/^[1-9]\d{5}$/', 'pattern_message' => 'Enter a valid 6-digit PIN code.']);
    $area   = $v->str('area', 'Area label', ['max' => 120, 'default' => '']);
    $status = $v->bool('status', true);
    $v->check();
    $cityId = location_city_id($in['city'] ?? null);
    if ($id && !q_value('SELECT 1 FROM pincodes WHERE id = ?', [$id])) {
        throw not_found('PIN code');
    }
    if (q_value('SELECT 1 FROM pincodes WHERE pincode = ? AND id <> ?', [$code, $id ?? 0])) {
        throw new ApiException('This PIN code already exists.', 422, ['code' => 'PIN code already added.']);
    }
    if ($id) {
        q('UPDATE pincodes SET pincode = ?, area_name = ?, city_id = ?, status = ? WHERE id = ?', [$code, $area ?: null, $cityId, $status ? 1 : 0, $id]);
        return ok(['id' => $id], 'PIN code updated.');
    }
    q('INSERT INTO pincodes (pincode, area_name, city_id, status, created_at) VALUES (?, ?, ?, ?, ?)', [$code, $area ?: null, $cityId, $status ? 1 : 0, now()]);
    return ok(['id' => (int) db()->lastInsertId()], 'PIN code added.', 201);
}

function locations_pincode_status(array $in, ?array $admin): array
{
    return locations_toggle('pincodes', 'id', $in, 'PIN code');
}

function locations_pincode_delete(array $in, ?array $admin): array
{
    $id = locations_require_id($in, 'pincodes', 'PIN code');
    q('DELETE FROM pincodes WHERE id = ?', [$id]);
    return ok(['id' => $id], 'PIN code deleted.');
}

/* ------------------------------ Service mapping ----------------------------- */

function locations_area_services(array $in, ?array $admin): array
{
    $id = (int) ($in['area_id'] ?? 0);
    $area = $id ? q_one('SELECT id, name FROM serviceable_areas WHERE id = ?', [$id]) : null;
    if (!$area) {
        throw not_found('Area');
    }
    $ids = array_map('intval', array_column(q_all('SELECT service_id FROM area_services WHERE area_id = ?', [$id]), 'service_id'));
    return ok(['area_id' => $id, 'area' => $area['name'], 'services' => $ids]);
}

function locations_map_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $areaId = $v->int('area', 'Area', ['required' => true, 'min' => 1]);
    $serviceIds = $v->ids('services');
    $v->check();
    $area = q_one('SELECT id, name FROM serviceable_areas WHERE id = ?', [$areaId]);
    if (!$area) {
        throw new ApiException('Area not found.', 422, ['area' => 'Select an area.']);
    }
    if ($serviceIds) {
        $valid = array_map('intval', array_column(q_all('SELECT MIN(packId) AS id FROM SaverPacks GROUP BY category_Id, subcategory'), 'id'));
        $serviceIds = array_values(array_intersect($serviceIds, $valid));
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('DELETE FROM area_services WHERE area_id = ?', [$areaId]);
        foreach ($serviceIds as $sid) {
            q('INSERT INTO area_services (area_id, service_id, created_at) VALUES (?, ?, ?)', [$areaId, $sid, now()]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ok(['area_id' => $areaId, 'services' => $serviceIds], count($serviceIds) . ' service(s) available in ' . $area['name'] . '.');
}

/* --------------------------------- Helpers ---------------------------------- */

function locations_require_id(array $in, string $table, string $label): int
{
    $v = new Validator($in);
    $id = $v->int('id', $label, ['required' => true, 'min' => 1]);
    $v->check();
    if (!q_value("SELECT 1 FROM $table WHERE id = ?", [$id])) {
        throw not_found($label);
    }
    return $id;
}

/** $table is always one of the fixed names above, never user input. */
function locations_toggle(string $table, string $key, array $in, string $label): array
{
    $id = locations_require_id($in, $table, $label);
    $status = (new Validator($in))->bool('status');
    q("UPDATE $table SET status = ? WHERE $key = ?", [$status ? 1 : 0, $id]);
    return ok(['id' => $id, 'status' => $status], $label . ($status ? ' enabled.' : ' disabled.'));
}

return [
    'cities'          => ['GET',  'locations_cities'],
    'city_save'       => ['POST', 'locations_city_save'],
    'city_status'     => ['POST', 'locations_city_status'],
    'city_delete'     => ['POST', 'locations_city_delete', 'super'],
    'areas'           => ['GET',  'locations_areas'],
    'area_save'       => ['POST', 'locations_area_save'],
    'area_status'     => ['POST', 'locations_area_status'],
    'area_delete'     => ['POST', 'locations_area_delete', 'super'],
    'pincodes'        => ['GET',  'locations_pincodes'],
    'pincode_save'    => ['POST', 'locations_pincode_save'],
    'pincode_status'  => ['POST', 'locations_pincode_status'],
    'pincode_delete'  => ['POST', 'locations_pincode_delete', 'super'],
    'area_services'   => ['GET',  'locations_area_services'],
    'map_save'        => ['POST', 'locations_map_save'],
];
