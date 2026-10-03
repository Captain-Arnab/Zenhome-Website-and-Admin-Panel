<?php
require_once __DIR__ . '/includes/functions.php';

$activeMenu = 'professionals';
$isEdit     = isset($_GET['id']);
$pro        = null;
if ($isEdit) {
    $pro = api('professionals.get', ['id' => (int) $_GET['id'], 'limit' => 1], null, true)['professional'] ?? null;
    if (!$pro) {
        $pageTitle   = 'Professional not found';
        $breadcrumbs = [['label' => 'Service Professionals', 'url' => 'professionals.php'], ['label' => 'Not found']];
        renderNotFound('Professional not found', 'This professional does not exist or was deleted.', 'professionals.php', 'Back to professionals');
    }
}
$pro = $pro ?? ['id' => '', 'name' => '', 'raw_mobile' => '', 'categories' => [], 'areas' => [], 'status' => true, 'status_label' => 'Active', 'remarks' => ''];
$proCategory = $pro['categories'][0] ?? '';
$proAreas    = $pro['areas'];

$categories  = api('categories.options', [], ['items' => []])['items'];
$areaNames   = array_column(api('locations.areas', ['limit' => ADMIN_LIST_LIMIT], ['items' => []])['items'], 'name');
// Keep areas saved on the professional even if they are not in the area list
$areaNames   = array_values(array_unique(array_merge($areaNames, $proAreas)));
natcasesort($areaNames);
$categoryNames = array_column($categories, 'name');
if ($proCategory !== '' && !in_array($proCategory, $categoryNames, true)) {
    $categoryNames[] = $proCategory;
}

$pageTitle   = $isEdit ? 'Edit Professional' : 'Add Professional';
$breadcrumbs = [['label' => 'Service Professionals', 'url' => 'professionals.php'], ['label' => $pageTitle]];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="alert alert-internal d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-shield-lock-fill fs-5"></i>
    <div><strong>Internal use only.</strong> No professional login or registration on the website. These details are only used by admins to assign jobs.</div>
</div>

<div class="page-header">
    <div>
        <h2><?= e($pageTitle) ?></h2>
        <p>Basic contact, skill and service-area details.</p>
    </div>
    <div class="actions">
        <a href="professionals.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>
</div>

<?php if ($pro['status_label'] === 'Pending'): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-hourglass-split"></i> This professional registered on the website and is waiting for approval. Save with <strong>Active</strong> on to approve.
    </div>
<?php endif; ?>

<form class="needs-validation" novalidate method="post" data-api="professionals.save" data-redirect="professional-view.php?id={id}">
    <input type="hidden" name="id" value="<?= e($pro['id']) ?>">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Professional details</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="proName">Full name</label>
                            <input type="text" class="form-control" id="proName" name="name" value="<?= e($pro['name']) ?>" required maxlength="80">
                            <div class="invalid-feedback">Name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="proMobile">Mobile number</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">+91</span>
                                <input type="tel" class="form-control" id="proMobile" name="mobile" value="<?= e(normalize_mobile($pro['raw_mobile']) ?? $pro['raw_mobile']) ?>"
                                       required pattern="[6-9][0-9]{4}\s?[0-9]{5}" placeholder="98765 43210">
                                <div class="invalid-feedback">Enter a valid 10-digit mobile number.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="proCategory">Service / category</label>
                            <select class="form-select" id="proCategory" name="category" required>
                                <option value="">Select category</option>
                                <?php foreach ($categoryNames as $catName): ?>
                                    <option <?= strcasecmp($proCategory, $catName) === 0 ? 'selected' : '' ?>><?= e($catName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please choose a category.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="proAreas">Area / location</label>
                            <select class="form-select" id="proAreas" name="areas[]" multiple size="4" required>
                                <?php foreach ($areaNames as $areaName): ?>
                                    <option <?= in_array($areaName, $proAreas, true) ? 'selected' : '' ?>><?= e($areaName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl / Cmd to select multiple areas. Areas are managed under <a href="locations.php#areas">Locations</a>.</div>
                            <div class="invalid-feedback">Select at least one area.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="proRemarks">Remarks (internal)</label>
                            <textarea class="form-control" id="proRemarks" name="remarks" rows="3" maxlength="500" placeholder="Experience, availability, notes for the admin team"><?= e($pro['remarks']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Status</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="d-block fs-13 text-heading">Available for assignment</strong>
                            <small class="text-muted">Inactive professionals are hidden from the assign dropdown.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input js-status-toggle" type="checkbox" id="proStatus" name="status" data-silent <?= $pro['status'] ? 'checked' : '' ?>>
                            <label class="form-check-label fs-12 fw-semibold text-muted" for="proStatus"><?= $pro['status'] ? 'Active' : 'Inactive' ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i> <?= $isEdit ? 'Update Professional' : 'Save Professional' ?></button>
                <a href="professionals.php" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
