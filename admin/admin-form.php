<?php
require_once __DIR__ . '/includes/functions.php';
requireSuperAdminPage();

$isEdit    = isset($_GET['id']);
$adminUser = $isEdit ? api('admins.get', ['id' => (int) $_GET['id']], null, true) : null;
$activeMenu = 'admins';
if ($isEdit && !$adminUser) {
    $pageTitle   = 'Admin not found';
    $breadcrumbs = [['label' => 'Admin Management', 'url' => 'admins.php'], ['label' => 'Not found']];
    renderNotFound('Admin not found', 'This admin user does not exist or was deleted.', 'admins.php', 'Back to admins');
}
$adminUser = $adminUser ?? ['id' => '', 'name' => '', 'email' => '', 'mobile' => '', 'role' => 'admin', 'status' => true];
$isSelf    = $isEdit && $adminUser['id'] === $currentAdmin['id'];
$roles       = adminRoles();
$permissions = adminPermissions();

$pageTitle   = $isEdit ? 'Edit Admin' : 'Add Admin';
$breadcrumbs = [['label' => 'Admin Management', 'url' => 'admins.php'], ['label' => $pageTitle]];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2><?= e($pageTitle) ?></h2>
        <p>Admins sign in with their email and password.</p>
    </div>
    <div class="actions">
        <a href="admins.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>
</div>

<form class="needs-validation" novalidate method="post" data-api="admins.save" data-redirect="admins.php">
    <input type="hidden" name="id" value="<?= e($adminUser['id']) ?>">
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Account details</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="adName">Full name</label>
                            <input type="text" class="form-control" id="adName" name="name" value="<?= e($adminUser['name']) ?>" required maxlength="100">
                            <div class="invalid-feedback">Name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="adMobile">Mobile</label>
                            <input type="tel" class="form-control" id="adMobile" name="mobile" value="<?= e($adminUser['mobile'] ? (normalize_mobile($adminUser['mobile']) ?: $adminUser['mobile']) : '') ?>" required pattern="(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}" placeholder="98480 12345">
                            <div class="invalid-feedback">Enter a valid 10-digit mobile number.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="adEmail">Email (login)</label>
                            <input type="email" class="form-control" id="adEmail" name="email" value="<?= e($adminUser['email']) ?>" required>
                            <div class="invalid-feedback">Enter a valid email address.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label <?= $isEdit ? '' : 'required' ?>" for="adPass"><?= $isEdit ? 'New password' : 'Password' ?></label>
                            <div class="input-group has-validation">
                                <input type="password" class="form-control" id="adPass" name="password" minlength="8" maxlength="72" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
                                <button class="btn btn-light border toggle-pass" type="button" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                <div class="invalid-feedback">Minimum 8 characters.</div>
                            </div>
                            <?php if ($isEdit): ?><div class="form-text">Leave blank to keep the current password.</div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label <?= $isEdit ? '' : 'required' ?>" for="adPass2">Confirm password</label>
                            <input type="password" class="form-control" id="adPass2" name="password_confirm" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
                            <div class="invalid-feedback">Passwords must match.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Role &amp; access</h3></div>
                <div class="card-body">
                    <?php if ($isSelf): ?>
                        <div class="alert alert-info fs-13 py-2"><i class="bi bi-info-circle me-1"></i> You cannot change your own role or deactivate yourself.</div>
                        <input type="hidden" name="role" value="<?= e($adminUser['role']) ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <?php foreach ($roles as $roleKey => $r): ?>
                            <div class="form-check border rounded-3 p-3 ps-5 mb-2">
                                <input class="form-check-input" type="radio" <?= $isSelf ? 'disabled' : 'name="role"' ?> id="role_<?= e($roleKey) ?>" value="<?= e($roleKey) ?>" <?= $adminUser['role'] === $roleKey ? 'checked' : '' ?> required>
                                <label class="form-check-label w-100" for="role_<?= e($roleKey) ?>">
                                    <strong class="text-heading d-block"><?= e($r['label']) ?></strong>
                                    <small class="text-muted"><?= e($r['description']) ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="fs-12 text-uppercase fw-bold text-muted mb-1">Permission summary</p>
                    <ul class="perm-list" id="permSummary">
                        <?php foreach ($permissions as $perm => $allowed): ?>
                            <li data-super="<?= $allowed['super_admin'] ? 1 : 0 ?>" data-admin="<?= $allowed['admin'] ? 1 : 0 ?>">
                                <i class="bi"></i> <?= e($perm) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="fs-13 text-heading">Account active</strong>
                        <div class="form-check form-switch m-0">
                            <?php if ($isSelf): ?><input type="hidden" name="status" value="1"><?php endif; ?>
                            <input class="form-check-input js-status-toggle" type="checkbox" id="adStatus" <?= $isSelf ? 'disabled' : 'name="status"' ?> data-silent <?= $adminUser['status'] ? 'checked' : '' ?>>
                            <label class="form-check-label fs-12 fw-semibold text-muted" for="adStatus"><?= $adminUser['status'] ? 'Active' : 'Inactive' ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i> <?= $isEdit ? 'Update Admin' : 'Create Admin' ?></button>
                <a href="admins.php" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php ob_start(); ?>
<script>
    (function () {
        // Live permission summary for the selected role
        const items = document.querySelectorAll('#permSummary li');
        const sync = () => {
            const role = document.querySelector('input[type="radio"][id^="role_"]:checked')?.value || 'admin';
            items.forEach(li => {
                const ok = li.dataset[role === 'super_admin' ? 'super' : 'admin'] === '1';
                li.classList.toggle('denied', !ok);
                li.querySelector('i').className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill');
            });
        };
        document.querySelectorAll('input[type="radio"][id^="role_"]').forEach(r => r.addEventListener('change', sync));
        sync();

        // Confirm password must match
        const pass = document.getElementById('adPass');
        const confirm = document.getElementById('adPass2');
        const check = () => confirm.setCustomValidity(pass.value !== confirm.value ? 'mismatch' : '');
        pass.addEventListener('input', check);
        confirm.addEventListener('input', check);
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
