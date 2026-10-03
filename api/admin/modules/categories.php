<?php
/**
 * Service categories (service_category, also read by api/category.php).
 * IMAGE keeps legacy bare filenames for the apps; new uploads store a
 * relative path. The website fields (slug, web_image, cover_image,
 * web_icon, tagline, web_label, page_title, highlights) only drive the
 * website (api/catalog_helper.php).
 */
require_once ZC_ROOT . '/api/catalog_helper.php';   // CATALOG_PAGES, catalog_category_url()

function category_row(array $r): array
{
    $slug = (string) ($r['slug'] ?? '');
    return [
        'id'            => (int) $r['CATEGORY_ID'],
        'name'          => (string) $r['NAME'],
        'slug'          => $slug,
        'slug_locked'   => in_array($slug, CATALOG_PAGES, true),
        'url'           => catalog_category_url($slug, (int) $r['CATEGORY_ID']),
        // Website card image (falls back to the app image)
        'image'         => image_path($r['web_image'] ?? '') ?? image_path($r['IMAGE'] ?? ''),
        'image_raw'     => (string) ($r['IMAGE'] ?? ''),
        'app_image'     => image_path($r['IMAGE'] ?? ''),
        'cover_image'   => image_path($r['cover_image'] ?? ''),
        'icon'          => (string) ($r['icon'] ?? ''),
        'web_icon'      => (string) ($r['web_icon'] ?? ''),
        'tagline'       => (string) ($r['tagline'] ?? ''),
        'label'         => (string) ($r['web_label'] ?? ''),
        'page_title'    => (string) ($r['page_title'] ?? ''),
        'highlights'    => (string) ($r['highlights'] ?? ''),
        'long_description' => (string) ($r['long_description'] ?? ''),
        'why_html'      => (string) ($r['why_html'] ?? ''),
        'process_html'  => (string) ($r['process_html'] ?? ''),
        'cta_html'      => (string) ($r['cta_html'] ?? ''),
        'faqs'          => catalog_faqs($r['faqs'] ?? null),
        'faqs_text'     => implode("\n\n", array_map(fn($f) => $f['question'] . "\n" . $f['answer'], catalog_faqs($r['faqs'] ?? null))),
        'order'         => (int) ($r['sort_order'] ?? 0),
        'description'   => (string) ($r['description'] ?? ''),
        'status'        => (int) ($r['status'] ?? 1) === 1,
        'subcategories' => (int) ($r['sub_count'] ?? 0),
        'services'      => (int) ($r['service_count'] ?? 0),
    ];
}

function categories_select_sql(): string
{
    return 'SELECT c.CATEGORY_ID, c.NAME, c.IMAGE, c.icon, c.sort_order, c.description, c.status,
            c.slug, c.web_image, c.cover_image, c.web_icon, c.tagline, c.web_label, c.page_title, c.highlights,
            c.long_description, c.why_html, c.process_html, c.cta_html, c.faqs,
            (SELECT COUNT(*) FROM service_subcategories s WHERE s.category_id = c.CATEGORY_ID) AS sub_count,
            (SELECT COUNT(DISTINCT sp.subcategory) FROM saverpacks sp WHERE sp.category_Id = c.CATEGORY_ID) AS service_count
        FROM service_category c';
}

function categories_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['c.NAME', 'c.description', 'c.slug'], trim((string) $in['search']), $params);
    }
    $status = strtolower((string) ($in['status'] ?? ''));
    if ($status === 'active' || $status === 'inactive') {
        $where[] = 'c.status = ?';
        $params[] = $status === 'active' ? 1 : 0;
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM service_category c WHERE $sqlWhere", $params);
    $rows = q_all(categories_select_sql() . " WHERE $sqlWhere ORDER BY c.sort_order, c.NAME LIMIT $limit OFFSET $offset", $params);
    return ok(paginated(array_map('category_row', $rows), $total, $page, $limit));
}

/** id/name pairs for dropdowns. */
function categories_options(array $in, ?array $admin): array
{
    $rows = q_all('SELECT CATEGORY_ID AS id, NAME AS name, status FROM service_category ORDER BY sort_order, NAME');
    return ok(['items' => array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'status' => (int) $r['status'] === 1], $rows)]);
}

function category_find(int $id): array
{
    $row = $id ? q_one(categories_select_sql() . ' WHERE c.CATEGORY_ID = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Category');
    }
    return $row;
}

function categories_get(array $in, ?array $admin): array
{
    return ok(category_row(category_find((int) ($in['id'] ?? 0))));
}

/**
 * FAQ textarea -> JSON. One FAQ per block, blocks separated by a blank line:
 * first line is the question, the following lines the answer.
 */
function category_faqs_from_text(?string $text, Validator $v): ?string
{
    $faqs = [];
    foreach (preg_split('/\R\s*\R/', trim((string) $text)) as $block) {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $block)), 'strlen'));
        if (!$lines) {
            continue;
        }
        $question = preg_replace('/^Q[:.]\s*/i', '', array_shift($lines));
        $answer = preg_replace('/^A[:.]\s*/i', '', implode(' ', $lines));
        if ($answer === '') {
            $v->error('faqs', 'Add an answer below "' . mb_strimwidth($question, 0, 40, '...') . '" (question on the first line, answer on the next).');
            return null;
        }
        if (mb_strlen($question) > 200 || mb_strlen($answer) > 1000) {
            $v->error('faqs', 'Keep each question under 200 and each answer under 1000 characters.');
            return null;
        }
        $faqs[] = ['q' => $question, 'a' => $answer];
    }
    if (count($faqs) > 20) {
        $v->error('faqs', 'Add at most 20 FAQs.');
        return null;
    }
    return $faqs ? json_encode($faqs, JSON_UNESCAPED_UNICODE) : null;
}

function categories_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id    = $v->int('id', 'Category', ['min' => 1]);
    $name  = $v->str('name', 'Category name', ['required' => true, 'max' => 80]);
    $icon  = $v->str('icon', 'Icon', ['max' => 50, 'pattern' => '/^bi-[a-z0-9-]+$/', 'pattern_message' => 'Use a Bootstrap Icons class, e.g. bi-snow.', 'default' => '']);
    $order = $v->int('order', 'Display order', ['required' => true, 'min' => 1, 'max' => 9999]);
    $desc  = $v->str('description', 'Description', ['max' => 200, 'default' => '']);
    $slug  = $v->str('slug', 'URL slug', ['max' => 80, 'pattern' => SLUG_PATTERN, 'pattern_message' => 'Use lowercase letters, numbers and hyphens, e.g. ac-service.', 'default' => '']);
    $webIcon   = $v->str('web_icon', 'Website icon', ['max' => 60, 'pattern' => '/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/', 'pattern_message' => 'Use a Font Awesome class, e.g. fa-solid fa-snowflake.', 'default' => '']);
    $tagline   = $v->str('tagline', 'Menu subtitle', ['max' => 80, 'default' => '']);
    $label     = $v->str('label', 'Card label', ['max' => 40, 'default' => '']);
    $pageTitle = $v->str('page_title', 'Page heading', ['max' => 120, 'default' => '']);
    $highlights = clean_lines($v->str('highlights', 'Highlights', ['max' => 1000, 'default' => '']), 6, 60);
    $longDesc = $v->str('long_description', 'Long description', ['max' => 4000, 'default' => '']);
    $whyHtml = $v->str('why_html', '"Why choose us" text', ['max' => 2000, 'default' => '']);
    $processHtml = $v->str('process_html', '"How it works" text', ['max' => 2000, 'default' => '']);
    $ctaHtml = $v->str('cta_html', 'CTA text', ['max' => 2000, 'default' => '']);
    $faqs = category_faqs_from_text($v->str('faqs', 'FAQs', ['max' => 15000, 'default' => '']), $v);
    $status = $v->bool('status', true);
    $v->check();

    $existing = $id ? category_find($id) : null;
    if ($existing && !array_key_exists('long_description', $in)) {
        $longDesc = (string) $existing['long_description'];
    }
    if ($existing && !array_key_exists('why_html', $in)) {
        $whyHtml = (string) $existing['why_html'];
    }
    if ($existing && !array_key_exists('process_html', $in)) {
        $processHtml = (string) $existing['process_html'];
    }
    if ($existing && !array_key_exists('cta_html', $in)) {
        $ctaHtml = (string) $existing['cta_html'];
    }
    if ($existing && !array_key_exists('faqs', $in)) {
        $faqs = $existing['faqs'];
    }
    if (q_value('SELECT 1 FROM service_category WHERE LOWER(NAME) = LOWER(?) AND CATEGORY_ID <> ?', [$name, $id ?? 0])) {
        throw new ApiException('A category with this name already exists.', 422, ['name' => 'Name already used.']);
    }
    $slug = $slug !== '' ? $slug : ((string) ($existing['slug'] ?? '') ?: slugify($name));
    if ($existing && in_array((string) $existing['slug'], CATALOG_PAGES, true) && $slug !== $existing['slug']) {
        throw new ApiException('This category has its own website page (' . $existing['slug'] . '.php), so its URL slug cannot be changed.', 422, ['slug' => 'Fixed for this category.']);
    }
    if ($slug === '' || q_value('SELECT 1 FROM service_category WHERE slug = ? AND CATEGORY_ID <> ?', [$slug, $id ?? 0])) {
        throw new ApiException('This URL slug is already used by another category.', 422, ['slug' => 'Choose a different slug.']);
    }
    if (strlen(trim((string) $highlights)) > 255) {
        throw new ApiException('Highlights are too long.', 422, ['highlights' => 'Keep the highlights short (255 characters in total).']);
    }

    $image = save_uploaded_image('image', 'categories');
    $cover = null;
    try {
        $cover = save_uploaded_image('cover_image', 'categories');
    } catch (Throwable $e) {
        if ($image) {
            delete_uploaded_image($image);
        }
        throw $e;
    }

    $web = [$slug, $webIcon ?: null, $tagline ?: null, $label ?: null, $pageTitle ?: null, $highlights ?: null, $longDesc ?: null, $whyHtml ?: null, $processHtml ?: null, $ctaHtml ?: null, $faqs];
    if ($existing) {
        $sets = 'NAME = ?, icon = ?, sort_order = ?, description = ?, status = ?, updated_at = ?, slug = ?, web_icon = ?, tagline = ?, web_label = ?, page_title = ?, highlights = ?, long_description = ?, why_html = ?, process_html = ?, cta_html = ?, faqs = ?';
        $params = array_merge([$name, $icon ?: null, $order, $desc ?: null, $status ? 1 : 0, now()], $web);
        if ($image) {
            // The uploaded image is used by the apps (IMAGE) and the website card (web_image).
            $sets .= ', IMAGE = ?, web_image = ?';
            array_push($params, $image, $image);
        }
        if ($cover) {
            $sets .= ', cover_image = ?';
            $params[] = $cover;
        }
        $params[] = $id;
        q("UPDATE service_category SET $sets WHERE CATEGORY_ID = ?", $params);
        if ($image) {
            delete_uploaded_image($existing['IMAGE']);
            if ($existing['web_image'] !== $existing['IMAGE']) {
                delete_uploaded_image($existing['web_image']);
            }
        }
        if ($cover) {
            delete_uploaded_image($existing['cover_image']);
        }
        $message = 'Category updated.';
    } else {
        q(
            'INSERT INTO service_category (NAME, IMAGE, icon, sort_order, description, status, updated_at, slug, web_icon, tagline, web_label, page_title, highlights, long_description, why_html, process_html, cta_html, faqs, web_image, cover_image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge([$name, $image ?? '', $icon ?: null, $order, $desc ?: null, $status ? 1 : 0, now()], $web, [$image, $cover])
        );
        $id = (int) db()->lastInsertId();
        $message = 'Category created.';
    }
    return ok(category_row(category_find($id)), $message, $existing ? 200 : 201);
}

function categories_set_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Category', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    $row = category_find($id);
    q('UPDATE service_category SET status = ?, updated_at = ? WHERE CATEGORY_ID = ?', [$status ? 1 : 0, now(), $id]);
    return ok(['id' => $id, 'status' => $status], $row['NAME'] . ($status ? ' activated.' : ' deactivated.'));
}

/** saverpacks rows cascade-delete with their category, so refuse when in use. */
function categories_delete(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Category', ['required' => true, 'min' => 1]);
    $v->check();
    $row = category_find($id);
    if ((int) $row['service_count'] > 0 || (int) $row['sub_count'] > 0) {
        throw new ApiException('Move or delete this category\'s services and subcategories first (or deactivate it instead).', 409);
    }
    q('DELETE FROM service_category WHERE CATEGORY_ID = ?', [$id]);
    foreach (array_unique(array_filter([$row['IMAGE'], $row['web_image'], $row['cover_image']])) as $file) {
        delete_uploaded_image($file);
    }
    return ok(['id' => $id], 'Category deleted.');
}

return [
    'list'       => ['GET',  'categories_list'],
    'options'    => ['GET',  'categories_options'],
    'get'        => ['GET',  'categories_get'],
    'save'       => ['POST', 'categories_save'],
    'set_status' => ['POST', 'categories_set_status'],
    'delete'     => ['POST', 'categories_delete', 'super'],
];
