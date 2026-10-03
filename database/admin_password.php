<?php
/**
 * Sets a new password for an admin account (CLI only) - recovery for a
 * forgotten Super Admin password. Also re-activates the account, clears
 * failed-login lockout and signs out its existing sessions.
 *
 *   php database/admin_password.php admin@gmail.com
 *       prompts for the new password (input hidden where supported)
 *   php database/admin_password.php --list
 *       lists admin accounts (email, role, status)
 *
 * Same password rule as the admin panel (8 to 72 characters), stored with
 * password_hash().
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
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$arg = $argv[1] ?? '';

if ($arg === '' || $arg === '--help') {
    echo "Usage: php database/admin_password.php <admin email>\n       php database/admin_password.php --list\n";
    exit($arg === '' ? 1 : 0);
}

if ($arg === '--list') {
    foreach ($conn->query('SELECT id, email, role, status FROM admin ORDER BY id') as $r) {
        printf("#%d  %-40s %-12s %s\n", $r['id'], $r['email'], $r['role'], (int) $r['status'] === 1 ? 'active' : 'inactive');
    }
    exit(0);
}

$stmt = $conn->prepare('SELECT id, email, role FROM admin WHERE LOWER(email) = LOWER(?)');
$stmt->execute([trim($arg)]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    fwrite(STDERR, "No admin with email \"$arg\". Use --list to see accounts.\n");
    exit(1);
}

function read_secret(string $prompt): string
{
    echo $prompt;
    if (DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        shell_exec('stty -echo 2>/dev/null');
        $value = fgets(STDIN);
        shell_exec('stty echo 2>/dev/null');
        echo PHP_EOL;
    } else {
        $value = fgets(STDIN);   // Windows console: input is visible
    }
    return rtrim((string) $value, "\r\n");
}

$password = read_secret('New password for ' . $admin['email'] . ': ');
if (strlen($password) < 8 || strlen($password) > 72) {
    fwrite(STDERR, "Password must be 8 to 72 characters.\n");
    exit(1);
}
if (read_secret('Repeat the password: ') !== $password) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$conn->beginTransaction();
$conn->prepare('UPDATE admin SET password = ?, status = 1, failed_logins = 0, locked_until = NULL, reset_token = NULL, token_expiry = NULL, updated_at = NOW() WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
$conn->prepare('DELETE FROM admin_sessions WHERE admin_id = ?')->execute([$admin['id']]);
$conn->commit();

echo "Password updated for {$admin['email']} ({$admin['role']}). The account is active and its old sessions were signed out.\n";
