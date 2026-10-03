<?php
/**
 * One-time catalog seed: fills Categories, Services (saverpacks) and Banners
 * from the content that used to be hardcoded on the website
 * (database/seed_data/website_catalog.php) and copies the website images,
 * resized to WebP, into uploads/admin/{categories,services,banners}/.
 *
 *   php database/seed_catalog_from_website.php            apply
 *   php database/seed_catalog_from_website.php --dry-run  show what would change
 *
 * Safe to re-run:
 * - Every seeded item is recorded in catalog_seed_log; recorded items are
 *   skipped, so later edits made in the admin panel are never overwritten.
 * - Existing rows are matched by slug, then by their current name, so the
 *   app's existing categories / saverpacks rows are updated in place (the
 *   website is the source of truth for names, prices and descriptions);
 *   nothing is duplicated and no row is deleted.
 * - service_category.IMAGE (the app's image) and status are left as they are.
 *
 * Requires migration 2026_10_03_003_catalog_website.sql (php database/migrate.php).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../api/admin/core/bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);
$data = require __DIR__ . '/seed_data/website_catalog.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}
foreach (['service_category' => 'cover_image', 'saverpacks' => 'highlights'] as $table => $column) {
    if (!q_one("SHOW COLUMNS FROM `$table` LIKE '$column'")) {
        fwrite(STDERR, "Run the migrations first: php database/migrate.php\n");
        exit(1);
    }
}

$stats = ['categories' => [], 'services' => [], 'banners' => [], 'images' => 0, 'bytes_in' => 0, 'bytes_out' => 0];
$warnings = [];

function seed_norm(string $s): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($s));
}

function seed_logged(string $entity, string $key): ?array
{
    return q_one('SELECT ref_id, action FROM catalog_seed_log WHERE entity = ? AND seed_key = ?', [$entity, $key]);
}

function seed_log(string $entity, string $key, int $refId, string $action): void
{
    q('INSERT INTO catalog_seed_log (entity, seed_key, ref_id, action, seeded_at) VALUES (?, ?, ?, ?, ?)', [$entity, $key, $refId, $action, now()]);
}

/** Copies a website image into uploads/admin/<folder>/seed-<name>.webp (resized). Returns the relative path. */
function seed_image(?string $src, string $folder, string $name, int $max): ?string
{
    global $stats, $warnings, $dryRun;
    if (!$src) {
        return null;
    }
    $abs = ZC_ROOT . '/' . ltrim($src, '/');
    $rel = "uploads/admin/$folder/seed-$name.webp";
    $dest = ZC_ROOT . '/' . $rel;
    if (is_file($dest)) {
        return $rel;
    }
    if (!is_file($abs)) {
        $warnings[] = "Image not found, skipped: $src";
        return null;
    }
    if ($dryRun) {
        return $rel;
    }
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }
    upload_protect_dir(ZC_ROOT . '/uploads/admin');
    if (!image_fit_file($abs, $dest, $max, $max, 'webp', 80)) {
        // No GD / unreadable: keep the original file (original extension).
        $rel = "uploads/admin/$folder/seed-$name." . strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!copy($abs, ZC_ROOT . '/' . $rel)) {
            $warnings[] = "Could not copy $src";
            return null;
        }
        $dest = ZC_ROOT . '/' . $rel;
    }
    $stats['images']++;
    $stats['bytes_in'] += filesize($abs);
    $stats['bytes_out'] += filesize($dest);
    return $rel;
}

/** Existing row whose current name matches the website name or one of the known app names. */
function seed_match(array $rows, string $nameCol, array $names): ?array
{
    $wanted = array_map('seed_norm', $names);
    foreach ($rows as $row) {
        if (in_array(seed_norm((string) $row[$nameCol]), $wanted, true)) {
            return $row;
        }
    }
    return null;
}

$pdo->beginTransaction();
try {
    /* ---------------- Categories + their services ---------------- */
    foreach ($data['categories'] as $c) {
        $slug = $c['slug'];
        $logged = seed_logged('category', $slug);
        $catId = $logged ? (int) q_value('SELECT CATEGORY_ID FROM service_category WHERE CATEGORY_ID = ?', [$logged['ref_id']]) : 0;

        if ($logged) {
            $stats['categories'][] = "kept      #{$logged['ref_id']} {$c['name']} (seeded before" . ($catId ? '' : ', since deleted') . ')';
        } else {
            $row = q_one('SELECT * FROM service_category WHERE slug = ?', [$slug])
                ?? seed_match(q_all('SELECT * FROM service_category'), 'NAME', array_merge([$c['name']], $c['match']));
            $card  = seed_image($c['image'], 'categories', $slug . '-card', 600);
            $cover = seed_image($c['cover_image'], 'categories', $slug . '-cover', 1200);
            $fields = [
                'NAME' => $c['name'], 'slug' => $slug, 'page_title' => $c['page_title'], 'description' => $c['description'],
                'tagline' => $c['tagline'], 'web_icon' => $c['icon'], 'web_label' => $c['label'],
                'highlights' => implode("\n", $c['highlights']), 'sort_order' => $c['sort'],
                'web_image' => $card, 'cover_image' => $cover, 'updated_at' => now(),
            ];
            if ($row) {
                $clash = q_value('SELECT CATEGORY_ID FROM service_category WHERE LOWER(NAME) = LOWER(?) AND CATEGORY_ID <> ?', [$c['name'], $row['CATEGORY_ID']]);
                if ($clash) {
                    $warnings[] = "Category name \"{$c['name']}\" is already used by #$clash; kept the name of #{$row['CATEGORY_ID']}.";
                    unset($fields['NAME']);
                }
                $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
                q("UPDATE service_category SET $sets WHERE CATEGORY_ID = ?", array_merge(array_values($fields), [$row['CATEGORY_ID']]));
                $catId = (int) $row['CATEGORY_ID'];
                $action = 'updated';
                $stats['categories'][] = "updated   #$catId {$row['NAME']}" . ($row['NAME'] !== $c['name'] ? " -> {$c['name']}" : '');
            } else {
                $fields['IMAGE'] = $card ?? '';
                $fields['status'] = 1;
                $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
                q("INSERT INTO service_category ($cols) VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ')', array_values($fields));
                $catId = (int) $pdo->lastInsertId();
                $action = 'created';
                $stats['categories'][] = "created   #$catId {$c['name']}";
            }
            seed_log('category', $slug, $catId, $action);
        }

        if (!$catId) {
            continue;
        }

        foreach ($c['services'] as $s) {
            $key = $s['slug'];
            if ($logged = seed_logged('service', $key)) {
                $stats['services'][] = "kept      #{$logged['ref_id']} {$s['name']} (seeded before)";
                continue;
            }
            $groups = q_all('SELECT MIN(packId) AS id, subcategory, MAX(price) AS price, MAX(slug) AS slug FROM saverpacks WHERE category_Id = ? GROUP BY subcategory', [$catId]);
            $group = null;
            foreach ($groups as $g) {
                if ($g['slug'] === $key) {
                    $group = $g;
                }
            }
            $group = $group ?? seed_match($groups, 'subcategory', array_merge([$s['name']], $s['match']));
            $image = seed_image($s['image'], 'services', $key, 1000);
            $highlights = implode("\n", $s['highlights']);

            if ($group) {
                $name = $s['name'];
                $clash = q_value('SELECT MIN(packId) FROM saverpacks WHERE category_Id = ? AND subcategory = ? AND subcategory <> ?', [$catId, $name, $group['subcategory']]);
                if ($clash) {
                    $warnings[] = "Service name \"$name\" is already used by #$clash; kept \"{$group['subcategory']}\".";
                    $name = $group['subcategory'];
                }
                q(
                    'UPDATE saverpacks SET subcategory = ?, price = ?, whyChooseThisPack = ?, slug = ?, web_tag = ?, highlights = ?, sort_order = ?, image = COALESCE(?, image), updated_at = ?
                     WHERE category_Id = ? AND subcategory = ?',
                    [$name, $s['price'], $s['description'], $key, $s['tag'], $highlights, $s['sort'], $image, now(), $catId, $group['subcategory']]
                );
                $changes = [];
                if ($group['subcategory'] !== $name) {
                    $changes[] = "name \"{$group['subcategory']}\" -> \"$name\"";
                }
                if ((float) $group['price'] !== (float) $s['price']) {
                    $changes[] = 'price ' . (float) $group['price'] . ' -> ' . (float) $s['price'];
                }
                seed_log('service', $key, (int) $group['id'], 'updated');
                $stats['services'][] = "updated   #{$group['id']} $name" . ($changes ? ' (' . implode('; ', $changes) . ')' : '');
            } else {
                // New service: one saverpacks row per highlight ("what's included" item).
                $firstId = 0;
                foreach ($s['highlights'] ?: [$s['name']] as $item) {
                    q(
                        'INSERT INTO saverpacks (category_Id, subcategory, price, whatsIncluded, includedDescription, whyChooseThisPack, idealFor, serviceTime, image, status, updated_at, slug, web_tag, highlights, sort_order)
                         VALUES (?, ?, ?, ?, \'\', ?, \'\', \'\', ?, 1, ?, ?, ?, ?, ?)',
                        [$catId, $s['name'], $s['price'], mb_substr($item, 0, 255), $s['description'], $image, now(), $key, $s['tag'], $highlights, $s['sort']]
                    );
                    $firstId = $firstId ?: (int) $pdo->lastInsertId();
                }
                seed_log('service', $key, $firstId, 'created');
                $stats['services'][] = "created   #$firstId {$s['name']} (₹{$s['price']})";
            }
        }
    }

    /* ---------------- Banners ---------------- */
    foreach ($data['banners'] as $b) {
        if ($logged = seed_logged('banner', $b['key'])) {
            $stats['banners'][] = "kept      #{$logged['ref_id']} {$b['title']} (seeded before)";
            continue;
        }
        $existing = q_value('SELECT id FROM banners WHERE placement = ? AND title = ?', [$b['placement'], $b['title']]);
        if ($existing) {
            seed_log('banner', $b['key'], (int) $existing, 'matched');
            $stats['banners'][] = "matched   #$existing {$b['title']} (already in banners)";
            continue;
        }
        $image = seed_image($b['image'], 'banners', $b['key'], 1400);
        if (!$image) {
            continue;
        }
        q('INSERT INTO banners (title, placement, image, link, sort_order, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
            [$b['title'], $b['placement'], $image, $b['link'], $b['sort'], now(), now()]);
        $id = (int) $pdo->lastInsertId();
        seed_log('banner', $b['key'], $id, 'created');
        $stats['banners'][] = "created   #$id {$b['title']} ({$b['placement']})";
    }

    $dryRun ? $pdo->rollBack() : $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed, nothing was changed in the database: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $dryRun ? "DRY RUN - no changes were saved.\n\n" : "Catalog seed complete.\n\n";
foreach (['categories', 'services', 'banners'] as $k) {
    echo strtoupper($k) . ' (' . count($stats[$k]) . ")\n  " . implode("\n  ", $stats[$k] ?: ['none']) . "\n\n";
}
printf("Images copied: %d (%.1f MB -> %.1f MB)\n", $stats['images'], $stats['bytes_in'] / 1048576, $stats['bytes_out'] / 1048576);
if ($warnings) {
    echo "\nWarnings:\n  " . implode("\n  ", $warnings) . "\n";
}
