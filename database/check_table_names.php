<?php
/**
 * Guard: verifies every table name the code references exists in the
 * connected DB with that EXACT case (catches the Linux/lower_case_table_names=0
 * mismatch this script was added for). Read-only - runs no destructive SQL.
 *
 * Run:  php database/check_table_names.php
 */
chdir(__DIR__ . '/..');
require __DIR__ . '/../api/db.php';
if (!isset($conn) || !$conn instanceof PDO) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

// Tables referenced anywhere in code (api/, admin/, database/, cron/), kept in
// sync by hand - this is the full set as of the lowercase-rename fix.
$expected = [
    'admin', 'admin_sessions', 'area_services', 'banners', 'booking_notes',
    'booking_status_history', 'cart', 'catalog_seed_log', 'cities', 'cms_pages',
    'coupons', 'customer_notifications', 'home_packages', 'legacy_access_log',
    'orders', 'otp_verification', 'password_resets', 'pincodes', 'ratings_feedback',
    'saverpacks', 'schema_migrations', 'service_booking', 'service_category',
    'service_partners', 'service_schedule', 'service_subcategories',
    'serviceable_areas', 'site_settings', 'sms_log', 'sms_templates',
    'support_ticket_messages', 'support_tickets', 'transactions', 'user_sessions',
    'users',
];

$actual = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$actualSet = array_flip($actual); // exact-case lookup; PHP array keys are case-sensitive

$missing = [];
foreach ($expected as $table) {
    if (!isset($actualSet[$table])) {
        $missing[] = $table;
    }
}

echo count($expected) . " expected tables, " . count($actual) . " tables in the database.\n\n";

if (!$missing) {
    echo "OK - every expected table exists with the exact case code uses.\n";
    exit(0);
}

echo "MISSING or wrong case (" . count($missing) . "):\n";
foreach ($missing as $table) {
    // Case-insensitive match against the real table list, to tell "missing
    // entirely" apart from "exists but under a different case".
    $realCase = null;
    foreach ($actual as $a) {
        if (strcasecmp($a, $table) === 0) {
            $realCase = $a;
            break;
        }
    }
    echo $realCase !== null
        ? "  $table  <-  exists as `$realCase` in the DB (case mismatch)\n"
        : "  $table  <-  no table by this name at all\n";
}
exit(1);
