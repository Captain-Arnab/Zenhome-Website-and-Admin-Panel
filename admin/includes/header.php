<?php
/**
 * Shared <head> + opening layout.
 * Page variables (set before including):
 *   $pageTitle   string  Page title (topbar + <title>)
 *   $activeMenu  string  Sidebar key to highlight
 *   $breadcrumbs array   [['label' => 'Bookings', 'url' => 'bookings.php'], ['label' => 'ZC1012']]
 *   $plugins     array   Optional CDN libraries: 'datatables', 'charts', 'editor'
 */
require_once __DIR__ . '/functions.php';

$pageTitle  = $pageTitle  ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
$plugins    = $plugins    ?? [];
$csrfToken  = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($pageTitle) ?> | Zen Home Experts Admin</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Same font as the public website -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php if (in_array('datatables', $plugins, true)): ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<?php endif; ?>
<?php if (in_array('editor', $plugins, true)): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css">
<?php endif; ?>
    <link rel="stylesheet" href="assets/css/admin.css?v=3">
</head>
<body>
<script>
    // Apply the saved collapsed state before paint to avoid a layout flash
    if (localStorage.getItem('zcAdminSidebar') === 'collapsed' && window.innerWidth >= 992) {
        document.body.classList.add('sidebar-collapsed');
    }
</script>
<div class="sidebar-backdrop" data-sidebar-close></div>
