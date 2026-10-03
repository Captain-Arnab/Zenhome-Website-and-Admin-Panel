<?php
/**
 * Seeds the Step 4 website content with the values that were hardcoded on
 * the website (database/seed_data/site_content.php and SITE_SETTING_DEFS):
 *  - site_settings: one row per setting key (existing rows are kept)
 *  - home_packages: the 4 homepage "Smart Packages" cards (only when the table is empty)
 *  - service_category.long_description: category page intro text (only where empty)
 *
 *   php database/seed_site_content.php            apply
 *   php database/seed_site_content.php --dry-run  show what would change
 *
 * Safe to re-run: it only fills empty values, so admin edits are never overwritten.
 * Requires migration 2026_10_03_005_site_settings_packages_legacy_log.sql.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../api/admin/core/bootstrap.php';
require_once ZC_ROOT . '/api/site_settings_helper.php';

$dryRun = in_array('--dry-run', $argv, true);
$data = require __DIR__ . '/seed_data/site_content.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}
if (!q_one("SHOW TABLES LIKE 'site_settings'") || !q_one("SHOW TABLES LIKE 'home_packages'") || !q_one("SHOW COLUMNS FROM service_category LIKE 'long_description'")) {
    fwrite(STDERR, "Run the migrations first: php database/migrate.php\n");
    exit(1);
}

$report = ['settings' => 0, 'packages' => 0, 'descriptions' => 0];

// Site settings
$stored = q_all('SELECT setting_key FROM site_settings');
$have = array_flip(array_column($stored, 'setting_key'));
foreach (SITE_SETTING_DEFS as $key => [$label, $type, $group, $default]) {
    if (isset($have[$key])) {
        continue;
    }
    $report['settings']++;
    echo "  setting  $key = " . str_replace("\n", ' / ', $default) . "\n";
    if (!$dryRun) {
        q('INSERT IGNORE INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)', [$key, $default, now()]);
    }
}

// Smart Packages
if ((int) q_value('SELECT COUNT(*) FROM home_packages') === 0) {
    foreach ($data['home_packages'] as $i => $p) {
        $categoryId = q_value('SELECT CATEGORY_ID FROM service_category WHERE slug = ?', [$p['category']]);
        $report['packages']++;
        echo "  package  {$p['title']} -> " . ($categoryId ? "category #$categoryId" : $p['link']) . "\n";
        if (!$dryRun) {
            q(
                'INSERT INTO home_packages (title, label, icon, description, highlights, category_id, pack_id, link, button_text, is_featured, sort_order, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, 1, ?, ?)',
                [$p['title'], $p['label'], $p['icon'], $p['description'], implode("\n", $p['highlights']), $categoryId ?: null,
                 $categoryId ? null : $p['link'], 'Book Package', $p['featured'], $i + 1, now(), now()]
            );
        }
    }
} else {
    echo "  packages already present, skipped\n";
}

// Category page intro text
foreach ($data['category_long_descriptions'] as $slug => $paragraphs) {
    $row = q_one('SELECT CATEGORY_ID, long_description FROM service_category WHERE slug = ?', [$slug]);
    if (!$row || trim((string) $row['long_description']) !== '') {
        continue;
    }
    $report['descriptions']++;
    echo "  intro    $slug\n";
    if (!$dryRun) {
        q('UPDATE service_category SET long_description = ? WHERE CATEGORY_ID = ?', [implode("\n\n", $paragraphs), $row['CATEGORY_ID']]);
    }
}

printf("%s%d settings, %d packages, %d category descriptions.\n", $dryRun ? '[dry run] would add ' : 'Added ', $report['settings'], $report['packages'], $report['descriptions']);
