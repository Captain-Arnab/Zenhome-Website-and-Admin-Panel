<?php
/**
 * Logout (POST + CSRF only): destroys the admin session and the bearer
 * token, if one was sent. A plain GET link cannot sign an admin out.
 */
require_once dirname(__DIR__) . '/api/admin/core/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_valid(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
    header('Location: index.php');
    exit;
}

admin_logout();
header('Location: login.php?logged_out=1');
exit;
