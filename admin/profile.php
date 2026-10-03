<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'My Profile';
$activeMenu  = '';
$breadcrumbs = [['label' => 'My Profile']];
$me = $currentAdmin;
$myRole = adminRoles()[$me['role']] ?? adminRoles()['admin'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="row g-3">
    <div class="col-lg-4 col-xl-3">
        <div class="card profile-card">
            <div class="card-body p-4">
                <?= avatar($me['name'] ?: $me['email'], 'avatar-xl') ?>
                <h5><?= e($me['name'] ?: 'Admin') ?></h5>
                <p class="text-muted fs-13 mb-2"><?= e($me['email']) ?></p>
                <span class="badge badge-soft-<?= e($myRole['color']) ?>"><?= e($myRole['label']) ?></span>
                <p class="fs-12 text-muted mt-3 mb-0">Last login: <?= $me['last_login'] ? fdate($me['last_login'], 'd M Y, h:i A') : '-' ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-8 col-xl-9">
        <div class="card">
            <ul class="nav nav-tabs-line px-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile" type="button"><i class="bi bi-person me-1"></i> Profile</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#password" type="button"><i class="bi bi-key me-1"></i> Change Password</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active p-4" id="profile" role="tabpanel">
                    <form class="needs-validation" novalidate data-api="auth.profile_update" data-reload>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="pfName">Full name</label>
                                <input type="text" class="form-control" id="pfName" name="name" value="<?= e($me['name']) ?>" required maxlength="100">
                                <div class="invalid-feedback">Name is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="pfMobile">Mobile</label>
                                <input type="tel" class="form-control" id="pfMobile" name="mobile" value="<?= e($me['mobile'] ? (normalize_mobile($me['mobile']) ?: $me['mobile']) : '') ?>" required pattern="(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}" placeholder="98480 12345">
                                <div class="invalid-feedback">Enter a valid 10-digit mobile number.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label required" for="pfEmail">Email</label>
                                <input type="email" class="form-control" id="pfEmail" name="email" value="<?= e($me['email']) ?>" required>
                                <div class="invalid-feedback">Enter a valid email.</div>
                            </div>
                        </div>
                        <div class="text-end mt-4"><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button></div>
                    </form>
                </div>

                <div class="tab-pane fade p-4" id="password" role="tabpanel">
                    <form class="needs-validation" novalidate data-api="auth.change_password" data-reset style="max-width:460px">
                        <div class="mb-3">
                            <label class="form-label required" for="pwCurrent">Current password</label>
                            <div class="input-group has-validation">
                                <input type="password" class="form-control" id="pwCurrent" name="current_password" required autocomplete="current-password">
                                <button class="btn btn-light border toggle-pass" type="button" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                <div class="invalid-feedback">Enter your current password.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="pwNew">New password</label>
                            <div class="input-group has-validation">
                                <input type="password" class="form-control" id="pwNew" name="new_password" minlength="8" maxlength="72" required autocomplete="new-password">
                                <button class="btn btn-light border toggle-pass" type="button" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                <div class="invalid-feedback">Minimum 8 characters.</div>
                            </div>
                            <div class="form-text">At least 8 characters, including at least one letter and one number.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label required" for="pwConfirm">Confirm new password</label>
                            <input type="password" class="form-control" id="pwConfirm" name="confirm_password" required autocomplete="new-password">
                            <div class="invalid-feedback">Passwords must match.</div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i> Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    (function () {
        const pass = document.getElementById('pwNew');
        const confirm = document.getElementById('pwConfirm');
        const check = () => confirm.setCustomValidity(pass.value !== confirm.value ? 'mismatch' : '');
        pass.addEventListener('input', check);
        confirm.addEventListener('input', check);
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
