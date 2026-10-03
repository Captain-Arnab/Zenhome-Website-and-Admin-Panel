<?php
/**
 * Sidebar navigation.
 * Menu is config-driven: add/rename items here only.
 * 'super_admin' => true hides the item for normal admins (uses $role).
 */

$sidebarBadges          = adminBadges();
$pendingAssignmentCount = (int) $sidebarBadges['unassigned'];
$openTicketCount        = (int) $sidebarBadges['open_tickets'];

$menu = [
    ['label' => 'Main'],
    ['key' => 'dashboard',     'label' => 'Dashboard',             'icon' => 'bi-grid-1x2',        'url' => 'index.php'],

    ['label' => 'Operations'],
    ['key' => 'customers',     'label' => 'Customers',             'icon' => 'bi-people',          'url' => 'customers.php'],
    ['key' => 'catalog',       'label' => 'Services',              'icon' => 'bi-tools',           'children' => [
        ['key' => 'categories',    'label' => 'Categories',    'url' => 'categories.php'],
        ['key' => 'subcategories', 'label' => 'Subcategories', 'url' => 'subcategories.php'],
        ['key' => 'services',      'label' => 'Services',      'url' => 'services.php'],
    ]],
    ['key' => 'professionals', 'label' => 'Service Professionals', 'icon' => 'bi-person-badge',    'url' => 'professionals.php'],
    ['key' => 'bookings',      'label' => 'Bookings',              'icon' => 'bi-calendar-check',  'url' => 'bookings.php', 'badge' => [$pendingAssignmentCount, 'warning', 'Awaiting assignment']],
    ['key' => 'payments',      'label' => 'Payments',              'icon' => 'bi-credit-card',     'url' => 'payments.php'],
    ['key' => 'locations',     'label' => 'Locations',             'icon' => 'bi-geo-alt',         'url' => 'locations.php'],

    ['label' => 'Engagement'],
    ['key' => 'coupons',       'label' => 'Offers & Coupons',      'icon' => 'bi-ticket-perforated','url' => 'coupons.php'],
    ['key' => 'reviews',       'label' => 'Ratings & Reviews',     'icon' => 'bi-star',            'url' => 'reviews.php'],
    ['key' => 'tickets',       'label' => 'Support Tickets',       'icon' => 'bi-headset',         'url' => 'support-tickets.php', 'badge' => [$openTicketCount, 'danger', 'Open tickets']],
    ['key' => 'notifications', 'label' => 'Notifications',         'icon' => 'bi-bell',            'url' => 'notifications.php'],
    ['key' => 'sms',           'label' => 'SMS Management',        'icon' => 'bi-chat-left-text',  'url' => 'sms.php'],
    ['key' => 'content',       'label' => 'Banners & CMS',         'icon' => 'bi-images',          'children' => [
        ['key' => 'banners',   'label' => 'Banners',   'url' => 'banners.php'],
        ['key' => 'packages',  'label' => 'Smart Packages', 'url' => 'home-packages.php'],
        ['key' => 'cms',       'label' => 'CMS Pages', 'url' => 'cms-pages.php'],
        ['key' => 'settings',  'label' => 'Site Settings', 'url' => 'settings.php'],
    ]],

    ['label' => 'Insights & System'],
    ['key' => 'reports',       'label' => 'Reports',               'icon' => 'bi-bar-chart-line',  'url' => 'reports.php'],
    ['key' => 'admins',        'label' => 'Admin Management',      'icon' => 'bi-shield-lock',     'url' => 'admins.php', 'super_admin' => true],
];
?>
<aside class="sidebar" id="sidebar" aria-label="Admin navigation">
    <div class="sidebar-brand">
        <a href="index.php" class="brand-logo" title="Zen Home Experts">
            <img src="assets/img/logo.png" alt="Zen Home Experts">
        </a>
        <div class="brand-text">
            <strong>Zen Home Experts</strong>
            <small>Admin Panel</small>
        </div>
        <button type="button" class="btn btn-sm text-white ms-auto d-lg-none" data-sidebar-close aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $item): ?>
            <?php
            if (!empty($item['super_admin']) && !isSuperAdmin()) {
                continue;
            }
            if (!isset($item['key'])) {
                echo '<div class="sidebar-label">' . e($item['label']) . '</div>';
                continue;
            }
            $badge = '';
            if (!empty($item['badge'][0])) {
                $badge = '<span class="badge bg-' . e($item['badge'][1]) . '" title="' . e($item['badge'][2]) . '">' . e($item['badge'][0]) . '</span>';
            }
            ?>

            <?php if (!empty($item['children'])): ?>
                <?php
                $childKeys = array_column($item['children'], 'key');
                $isOpen    = in_array($activeMenu, $childKeys, true);
                $collapseId = 'menu-' . $item['key'];
                ?>
                <button type="button"
                        class="sidebar-link <?= $isOpen ? 'parent-active' : '' ?>"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= e($collapseId) ?>"
                        aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                        title="<?= e($item['label']) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span class="link-text"><?= e($item['label']) ?></span>
                    <i class="bi bi-chevron-right chevron"></i>
                </button>
                <div class="collapse <?= $isOpen ? 'show' : '' ?>" id="<?= e($collapseId) ?>">
                    <ul class="sidebar-submenu">
                        <?php foreach ($item['children'] as $child): ?>
                            <li>
                                <a href="<?= e($child['url']) ?>" class="<?= $activeMenu === $child['key'] ? 'active' : '' ?>">
                                    <?= e($child['label']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= e($item['url']) ?>"
                   class="sidebar-link <?= $activeMenu === $item['key'] ? 'active' : '' ?>"
                   title="<?= e($item['label']) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span class="link-text"><?= e($item['label']) ?></span>
                    <?= $badge ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="help-box">
            <strong><i class="bi bi-life-preserver me-1"></i> Need help?</strong>
            Support line: <?= e(site_phone_intl(site_setting('support_phone'))) ?>
            <a href="../index.php" target="_blank" class="d-block mt-2 text-white fw-semibold">
                <i class="bi bi-box-arrow-up-right me-1"></i> View website
            </a>
        </div>
    </div>
</aside>

<div class="main-wrapper">
