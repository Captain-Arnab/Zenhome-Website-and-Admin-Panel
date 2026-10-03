<?php
require_once __DIR__ . '/includes/functions.php';
requireSuperAdminPage();

$pageTitle   = 'Admin Management';
$activeMenu  = 'admins';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Admin Management']];

$admins      = api_list('admins.list', [], 'admins')['items'];
$roles       = adminRoles();
$permissions = adminPermissions();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Admin Management</h2>
        <p>Admin users who can sign in to this panel.</p>
    </div>
    <div class="actions">
        <a href="admin-form.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add Admin</a>
    </div>
</div>

<div class="card mb-4">
    <table class="table table-hover js-datatable" id="adminsTable" data-empty="No admin users found.">
        <thead>
        <tr>
            <th>Admin</th>
            <th>Mobile</th>
            <th>Role</th>
            <th>Last login</th>
            <th>Status</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $a): ?>
            <?php $isSelf = $a['id'] === $currentAdmin['id']; $r = $roles[$a['role']] ?? $roles['admin']; ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <?= avatar($a['name'] ?: $a['email']) ?>
                        <div>
                            <span class="cell-title"><?= e($a['name'] ?: '-') ?><?= $isSelf ? ' <span class="badge badge-soft-muted">You</span>' : '' ?></span>
                            <span class="cell-sub"><?= e($a['email']) ?></span>
                        </div>
                    </div>
                </td>
                <td class="nowrap"><?= e($a['mobile'] ? format_mobile($a['mobile']) : '-') ?></td>
                <td><span class="badge badge-soft-<?= e($r['color']) ?>"><i class="bi bi-shield<?= $a['role'] === 'super_admin' ? '-fill-check' : '' ?> me-1"></i><?= e($r['label']) ?></span></td>
                <td class="nowrap" data-order="<?= e($a['last_login'] ?? '') ?>"><?= $a['last_login'] ? fdate($a['last_login'], 'd M Y, h:i A') : '<span class="text-muted">Never</span>' ?></td>
                <td>
                    <?php if ($isSelf): ?>
                        <?= statusBadge('Active') ?>
                    <?php else: ?>
                        <?= statusSwitch($a['status'], $a['name'] ?: $a['email'], 'Active', 'Inactive', (string) $a['id'], 'admins.set_status') ?>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="table-actions justify-content-end">
                        <a href="admin-form.php?id=<?= (int) $a['id'] ?>" class="btn btn-icon btn-soft-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if (!$isSelf): ?>
                            <?= deleteButton('this admin user', (string) $a['id'], true, 'admins.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Role permission summary -->
<h3 class="h6 mb-3">Role permissions</h3>
<div class="row g-3">
    <?php foreach ($roles as $roleKey => $r): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><span class="badge badge-soft-<?= e($r['color']) ?> me-1"><?= e($r['label']) ?></span></h3>
                        <p class="card-subtitle"><?= e($r['description']) ?></p>
                    </div>
                    <?php $n = count(array_filter($admins, fn($a) => $a['role'] === $roleKey)); ?>
                    <span class="text-muted fs-12"><?= $n ?> user<?= $n === 1 ? '' : 's' ?></span>
                </div>
                <div class="card-body">
                    <ul class="perm-list">
                        <?php foreach ($permissions as $perm => $allowed): ?>
                            <li class="<?= $allowed[$roleKey] ? '' : 'denied' ?>">
                                <i class="bi <?= $allowed[$roleKey] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                <?= e($perm) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
