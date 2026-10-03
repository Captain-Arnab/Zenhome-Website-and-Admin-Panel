<?php
/**
 * Applies database/migrations/*.sql in name order (CLI only).
 *
 *   php database/migrate.php            apply pending migrations
 *   php database/migrate.php --status   list applied / pending
 *
 * Each statement runs on its own. "Already exists" errors (duplicate
 * column/key/table/constraint) are skipped so a partially applied or
 * re-run migration completes safely. Uses the same connection as the API
 * (api/db.php).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ob_start();
require __DIR__ . '/../api/db.php';
$dbOutput = trim(ob_get_clean());
if (!isset($conn) || !$conn instanceof PDO) {
    fwrite(STDERR, "Database connection failed. $dbOutput\n");
    exit(1);
}
$conn->exec('SET NAMES utf8mb4');

$conn->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `migration` varchar(190) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$applied = $conn->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files   = glob(__DIR__ . '/migrations/*.sql');
sort($files);

if (in_array('--status', $argv, true)) {
    foreach ($files as $file) {
        $name = basename($file);
        echo (in_array($name, $applied, true) ? '[applied] ' : '[pending] ') . $name . PHP_EOL;
    }
    exit(0);
}

// MySQL/MariaDB error codes that mean "this change is already in place".
$alreadyApplied = [1050, 1060, 1061, 1826, 1022];

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    echo "Applying $name\n";

    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)));

    // Columns that existed before this migration must never be MODIFY'd.
    $preexisting = [];
    foreach ($statements as $statement) {
        if (preg_match('/^ALTER TABLE `(\w+)` MODIFY COLUMN `(\w+)`/i', $statement, $m) && isset($preexisting[strtolower($m[1] . '.' . $m[2])])) {
            echo "  skipped (column existed before, left unchanged): " . strtok($statement, "\n") . "\n";
            continue;
        }
        try {
            $conn->exec($statement);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($code, $alreadyApplied, true)) {
                if ($code === 1060 && preg_match('/^ALTER TABLE `(\w+)` ADD COLUMN `(\w+)`/i', $statement, $m)) {
                    $preexisting[strtolower($m[1] . '.' . $m[2])] = true;
                }
                echo "  skipped (already applied): " . strtok($statement, "\n") . "\n";
                continue;
            }
            fwrite(STDERR, "  FAILED: " . strtok($statement, "\n") . "\n  " . $e->getMessage() . "\n");
            exit(1);
        }
    }

    $conn->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$name]);
    echo "  done\n";
}

echo "Migrations up to date.\n";
