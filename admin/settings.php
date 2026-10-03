<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Site Settings';
$activeMenu  = 'settings';
$breadcrumbs = [['label' => 'Banners & CMS'], ['label' => 'Site Settings']];

$settings = api('settings.get', [], ['groups' => [], 'items' => []]);
$canEdit  = isSuperAdmin();
$byGroup  = [];
foreach ($settings['items'] as $item) {
    $byGroup[$item['group']][] = $item;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Site Settings</h2>
        <p>Contact details, social and app links, footer text and website images. Changes show on the website immediately.</p>
    </div>
    <div class="actions">
        <a href="../index.php" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> View website</a>
    </div>
</div>

<?php if (!$canEdit): ?>
    <div class="alert alert-light border fs-13 py-2"><i class="bi bi-lock me-1"></i> Only a Super Admin can change site settings.</div>
<?php endif; ?>

<form class="needs-validation" novalidate enctype="multipart/form-data" data-api="settings.save" data-reload>
    <fieldset <?= $canEdit ? '' : 'disabled' ?>>
    <div class="row g-3">
        <?php foreach ($settings['groups'] as $group): ?>
            <?php if (empty($byGroup[$group])) { continue; } ?>
            <div class="<?= $group === 'Branding & Images' ? 'col-12' : 'col-lg-6' ?>">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><?= e($group) ?></h3></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($byGroup[$group] as $s): ?>
                                <?php $id = 'set_' . $s['key']; ?>
                                <?php if ($s['type'] === 'image'): ?>
                                    <div class="col-md-6 col-xl-4">
                                        <label class="form-label"><?= e($s['label']) ?></label>
                                        <label class="upload-box <?= $s['image'] ? 'has-image' : '' ?>">
                                            <input type="file" name="<?= e($s['key']) ?>" accept="image/png,image/jpeg,image/webp">
                                            <img class="preview" alt="Preview" <?= $s['image'] ? 'src="' . e(assetUrl($s['image'])) . '"' : '' ?>>
                                            <div class="upload-placeholder">
                                                <i class="bi bi-image"></i>
                                                <p>Click to upload<br><small>JPG/PNG/WebP, max 2 MB</small></p>
                                            </div>
                                        </label>
                                        <?php if ($s['help'] !== ''): ?><div class="form-text"><?= e($s['help']) ?></div><?php endif; ?>
                                        <?php if (!$s['fallback'] && $s['value'] !== ''): ?>
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" id="<?= e($id) ?>_rm" name="remove_<?= e($s['key']) ?>" value="1">
                                                <label class="form-check-label fs-13" for="<?= e($id) ?>_rm">Remove this image</label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($s['type'] === 'bool'): ?>
                                    <div class="col-md-6">
                                        <label class="form-label d-block"><?= e($s['label']) ?></label>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" id="<?= e($id) ?>" name="<?= e($s['key']) ?>" value="1" <?= $s['value'] === '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label fs-13" for="<?= e($id) ?>"><?= $s['value'] === '1' ? 'On' : 'Off' ?></label>
                                        </div>
                                        <?php if ($s['help'] !== ''): ?><div class="form-text"><?= e($s['help']) ?></div><?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="<?= $s['type'] === 'textarea' ? 'col-12' : 'col-md-6' ?>">
                                        <label class="form-label" for="<?= e($id) ?>"><?= e($s['label']) ?></label>
                                        <?php if ($s['type'] === 'textarea'): ?>
                                            <textarea class="form-control" id="<?= e($id) ?>" name="<?= e($s['key']) ?>" rows="3" maxlength="600"><?= e($s['value']) ?></textarea>
                                        <?php elseif ($s['type'] === 'mobile_list'): ?>
                                            <input class="form-control" id="<?= e($id) ?>" name="<?= e($s['key']) ?>" value="<?= e($s['value']) ?>" type="text" maxlength="150" placeholder="9876543210, 9123456780">
                                        <?php else: ?>
                                            <input class="form-control" id="<?= e($id) ?>" name="<?= e($s['key']) ?>" value="<?= e($s['value']) ?>"
                                                   type="<?= ['email' => 'email', 'url' => 'url', 'phone' => 'tel'][$s['type']] ?? 'text' ?>"
                                                   maxlength="<?= $s['type'] === 'url' ? 255 : ($s['type'] === 'phone' ? 20 : 150) ?>"
                                                   <?= $s['type'] === 'url' ? 'placeholder="https://"' : '' ?>>
                                        <?php endif; ?>
                                        <div class="invalid-feedback">Please check this value.</div>
                                        <?php if ($s['help'] !== '' || $s['fallback']): ?>
                                            <div class="form-text"><?= e($s['help']) ?><?= $s['fallback'] ? ($s['help'] !== '' ? ' ' : '') . 'Left empty, the original website value is used.' : '' ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($canEdit): ?>
        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Settings</button>
        </div>
    <?php endif; ?>
    </fieldset>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
