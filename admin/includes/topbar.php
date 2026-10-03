<?php
/**
 * Top bar: sidebar toggle, page title + breadcrumb, notifications, profile menu.
 */
$breadcrumbs  = $breadcrumbs ?? [['label' => $pageTitle]];
$alertData    = api('dashboard.alerts', [], ['items' => [], 'unread' => 0]);
$adminAlerts  = $alertData['items'];
$unreadAlerts = (int) $alertData['unread'];
$roleLabel    = $currentAdmin['role_label'] ?? (isSuperAdmin() ? 'Super Admin' : 'Admin');
?>
<header class="topbar">
    <button type="button" class="topbar-toggle" data-sidebar-toggle aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-title">
        <h1><?= e($pageTitle) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <?php if ($activeMenu !== 'dashboard'): ?>
                    <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door"></i> Home</a></li>
                <?php endif; ?>
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php $isLast = $i === array_key_last($breadcrumbs); ?>
                    <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>" <?= $isLast ? 'aria-current="page"' : '' ?>>
                        <?php if (!$isLast && !empty($crumb['url'])): ?>
                            <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                        <?php else: ?>
                            <?= e($crumb['label']) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>

    <div class="topbar-actions">
        <a href="../index.php" target="_blank" class="topbar-icon-btn d-none d-md-inline-flex" data-bs-toggle="tooltip" data-bs-placement="bottom" title="View website">
            <i class="bi bi-globe2"></i>
        </a>

        <!-- Notifications -->
        <div class="dropdown">
            <button type="button" class="topbar-icon-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($unreadAlerts): ?>
                    <span class="dot-count"><?= $unreadAlerts ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-menu">
                <div class="menu-head">
                    <strong class="text-heading">Notifications</strong>
                    <?php if ($unreadAlerts): ?>
                        <a href="#" class="fs-12 fw-semibold" data-api-click="dashboard.alerts_read" data-mark-read>Mark all read</a>
                    <?php endif; ?>
                </div>
                <div class="menu-body">
                    <?php if (!$adminAlerts): ?>
                        <?= emptyState('No new notifications', 'bi-bell-slash') ?>
                    <?php endif; ?>
                    <?php foreach ($adminAlerts as $alert): ?>
                        <a href="<?= e($alert['url']) ?>" class="notification-item <?= $alert['unread'] ? 'unread' : '' ?>">
                            <span class="n-icon icon-soft-<?= e($alert['color']) ?>"><i class="bi <?= e($alert['icon']) ?>"></i></span>
                            <span>
                                <p><?= $alert['text'] /* trusted markup from server */ ?></p>
                                <small><?= e($alert['time']) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <a href="notifications.php" class="d-block text-center py-2 fs-13 fw-semibold border-top">View all</a>
            </div>
        </div>

        <!-- Profile -->
        <div class="dropdown">
            <button type="button" class="profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <?= avatar($currentAdmin['name'], 'avatar-sm') ?>
                <span class="profile-meta d-none d-md-block">
                    <strong><?= e($currentAdmin['name']) ?></strong>
                    <small><?= e($roleLabel) ?></small>
                </span>
                <i class="bi bi-chevron-down fs-12 d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2">
                    <strong class="d-block text-heading"><?= e($currentAdmin['name']) ?></strong>
                    <small class="text-muted"><?= e($currentAdmin['email']) ?></small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> My Profile</a></li>
                <li><a class="dropdown-item" href="profile.php#password"><i class="bi bi-key"></i> Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="logout.php" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<main class="page-content">
<?php if (!empty($_GET['denied'])): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-shield-lock"></i> That page is available to Super Admins only.
    </div>
<?php endif; ?>
<?php foreach (array_unique($GLOBALS['apiErrors'] ?? []) as $apiError): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-octagon"></i> <?= e($apiError) ?>
    </div>
<?php endforeach; ?>
<?php foreach (array_unique($GLOBALS['apiNotices'] ?? []) as $apiNotice): ?>
    <div class="alert alert-info d-flex align-items-center gap-2 py-2 fs-13" role="status">
        <i class="bi bi-info-circle"></i> <?= e($apiNotice) ?>
    </div>
<?php endforeach; ?>
