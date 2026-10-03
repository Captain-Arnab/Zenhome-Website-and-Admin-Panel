<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Smart Packages';
$activeMenu  = 'packages';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Banners & CMS'], ['label' => 'Smart Packages']];

$packages   = api_list('home_packages.list', [], 'packages')['items'];
$categories = api('categories.options', [], ['items' => []])['items'];
$services   = api('services.options', [], ['items' => []])['items'];
$nextOrder  = $packages ? max(array_column($packages, 'order')) + 1 : 1;

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Smart Packages</h2>
        <p>The "Smart Packages for Everyday Home Care" cards on the website homepage. Lower order numbers show first.</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal" data-title="Add Package"
                data-fill="<?= jsonAttr(['order' => $nextOrder, 'status' => true, 'button_text' => 'Book Package']) ?>"><i class="bi bi-plus-lg me-1"></i> Add Package</button>
    </div>
</div>

<div class="alert alert-light border fs-13 py-2"><i class="bi bi-info-circle me-1"></i> A card links to a category page, to one service (its price is shown) or to a custom link. Cards whose category or service is disabled are hidden automatically. When no card is active, the section is hidden. Also available to the apps via <code>api/home_packages.php</code>.</div>

<div class="card">
    <table class="table table-hover js-datatable" id="packagesTable" data-empty="No packages yet. Add the first one.">
        <thead>
        <tr>
            <th>Package</th>
            <th>Links to</th>
            <th class="text-center">Order</th>
            <th>Active</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($packages as $p): ?>
            <tr>
                <td style="min-width:220px">
                    <span class="cell-title"><?= e($p['title']) ?>
                        <?php if ($p['featured']): ?><span class="badge badge-soft-success ms-1">Highlighted</span><?php endif; ?></span>
                    <span class="cell-sub"><?= e($p['label'] !== '' ? $p['label'] . ' · ' : '') ?><?= e(mb_strimwidth($p['description'], 0, 80, '...')) ?></span>
                </td>
                <td>
                    <?php if ($p['service']): ?>
                        <span class="badge badge-soft-info">Service</span> <?= e($p['service']) ?>
                    <?php elseif ($p['category']): ?>
                        <span class="badge badge-soft-secondary">Category</span> <?= e($p['category']) ?>
                    <?php elseif ($p['link'] !== ''): ?>
                        <span class="badge badge-soft-warning">Link</span> <?= e($p['link']) ?>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold"><?= (int) $p['order'] ?></td>
                <td><?= statusSwitch($p['status'], $p['title'], 'Active', 'Inactive', (string) $p['id'], 'home_packages.set_status') ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <button type="button" class="btn btn-icon btn-soft-primary" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#packageModal" data-title="Edit Package"
                                data-fill="<?= jsonAttr(['id' => $p['id'], 'title' => $p['title'], 'label' => $p['label'], 'icon' => $p['icon'], 'description' => $p['description'], 'highlights' => $p['highlights'], 'category_id' => $p['category_id'] ?? '', 'pack_id' => $p['pack_id'] ?? '', 'link' => $p['link'], 'button_text' => $p['button_text'], 'featured' => $p['featured'], 'order' => $p['order'], 'status' => $p['status']]) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this package', (string) $p['id'], true, 'home_packages.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit package modal -->
<div class="modal fade js-crud-modal" id="packageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="home_packages.save">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" data-modal-title>Add Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label required" for="hpTitle">Title</label>
                            <input type="text" class="form-control" id="hpTitle" name="title" required maxlength="80" placeholder="e.g. AC Care Package">
                            <div class="invalid-feedback">Title is required.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="hpLabel">Label</label>
                            <input type="text" class="form-control" id="hpLabel" name="label" maxlength="30" placeholder="e.g. POPULAR">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="hpDesc">Description</label>
                            <textarea class="form-control" id="hpDesc" name="description" rows="2" maxlength="300"></textarea>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="hpPoints">Points</label>
                            <textarea class="form-control" id="hpPoints" name="highlights" rows="3" maxlength="500" placeholder="One per line, up to 5"></textarea>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="hpIcon">Icon</label>
                            <input type="text" class="form-control" id="hpIcon" name="icon" maxlength="60" pattern="fa-(solid|regular|brands) fa-[a-z0-9-]+" placeholder="fa-solid fa-snowflake">
                            <div class="form-text">Font Awesome class.</div>
                            <div class="invalid-feedback">e.g. fa-solid fa-broom</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="hpCategory">Category</label>
                            <select class="form-select" id="hpCategory" name="category_id" data-child="#hpService">
                                <option value="">None</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['id']) ?>"><?= e($cat['name']) ?><?= $cat['status'] ? '' : ' (inactive)' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="hpService">Service (optional)</label>
                            <select class="form-select" id="hpService" name="pack_id">
                                <option value="">None - link to the category page</option>
                                <?php foreach ($services as $svc): ?>
                                    <option value="<?= e($svc['id']) ?>" data-parent="<?= e($svc['category_id']) ?>"><?= e($svc['name']) ?><?= $svc['status'] ? '' : ' (disabled)' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="hpLink">Custom link</label>
                            <input type="text" class="form-control" id="hpLink" name="link" maxlength="255" placeholder="Only when no category or service is chosen">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="hpButton">Button text</label>
                            <input type="text" class="form-control" id="hpButton" name="button_text" maxlength="30" placeholder="Book Package">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="hpOrder">Display order</label>
                            <input type="number" class="form-control" id="hpOrder" name="order" min="1" max="999" value="1" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="hpFeatured" name="featured" data-silent>
                                <label class="form-check-label" for="hpFeatured">Highlighted card</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input js-status-toggle" type="checkbox" id="hpStatus" name="status" checked data-silent>
                                <label class="form-check-label" for="hpStatus">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
