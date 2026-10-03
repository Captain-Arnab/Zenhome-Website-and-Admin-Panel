<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Banners';
$activeMenu  = 'banners';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Banners & CMS'], ['label' => 'Banners']];

$banners   = api_list('banners.list', [], 'banners')['items'];
$nextOrder = $banners ? max(array_column($banners, 'order')) + 1 : 1;

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Banners</h2>
        <p>Homepage slider and promotional banners. Lower order numbers show first.</p>
    </div>
    <div class="actions">
        <a href="cms-pages.php" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-text me-1"></i> CMS Pages</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bannerModal" data-title="Add Banner"
                data-fill="<?= jsonAttr(['placement' => 'Homepage Slider', 'order' => $nextOrder, 'status' => true]) ?>"><i class="bi bi-plus-lg me-1"></i> Add Banner</button>
    </div>
</div>

<div class="alert alert-light border fs-13 py-2"><i class="bi bi-info-circle me-1"></i> Active banners within their dates go live on the website homepage and in the apps via <code>api/banners.php</code>: <strong>Homepage Hero</strong> is the main picture beside the welcome panel (the first active one is shown), <strong>Homepage Slider</strong> is the offers slider below it and <strong>Promotional</strong> the offer cards. Images are resized automatically; recommended 1200&times;600 px.</div>

<div class="card">
    <ul class="nav nav-tabs-line px-3">
        <li class="nav-item"><button type="button" class="nav-link active" data-dt-tab="#bannersTable" data-column="2" data-value="">All <span class="badge badge-soft-secondary"><?= count($banners) ?></span></button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-dt-tab="#bannersTable" data-column="2" data-value="Homepage Hero">Homepage Hero</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-dt-tab="#bannersTable" data-column="2" data-value="Homepage Slider">Homepage Slider</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-dt-tab="#bannersTable" data-column="2" data-value="Promotional">Promotional</button></li>
    </ul>
    <table class="table table-hover js-datatable" id="bannersTable" data-empty="No banners yet. Add the first one.">
        <thead>
        <tr>
            <th data-orderable="false">Preview</th>
            <th>Title / Link</th>
            <th>Placement</th>
            <th class="text-center">Order</th>
            <th>Active</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($banners as $b): ?>
            <?php $img = assetUrl($b['image']); ?>
            <tr>
                <td><?= $img ? '<img src="' . e($img) . '" class="banner-thumb" alt="" loading="lazy">' : '<span class="text-muted"><i class="bi bi-image fs-4"></i></span>' ?></td>
                <td style="min-width:200px">
                    <span class="cell-title"><?= e($b['title']) ?></span>
                    <span class="cell-sub"><?= $b['link'] !== '' ? '<i class="bi bi-link-45deg"></i> ' . e(preg_match('#^https?://#', $b['link']) ? $b['link'] : '/' . $b['link']) : 'No link' ?></span>
                    <?php if ($b['from'] || $b['to']): ?>
                        <span class="cell-sub"><i class="bi bi-calendar3"></i> <?= $b['from'] ? fdate($b['from'], 'd M') : 'Now' ?> &ndash; <?= $b['to'] ? fdate($b['to']) : 'No end' ?><?= $b['status'] && !$b['live'] ? ' &middot; <span class="text-warning">not live today</span>' : '' ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= ['Promotional' => 'badge-soft-warning', 'Homepage Hero' => 'badge-soft-success'][$b['placement']] ?? 'badge-soft-info' ?>"><?= e($b['placement']) ?></span></td>
                <td class="text-center fw-bold"><?= (int) $b['order'] ?></td>
                <td><?= statusSwitch($b['status'], $b['title'], 'Active', 'Inactive', (string) $b['id'], 'banners.set_status') ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <button type="button" class="btn btn-icon btn-soft-primary" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#bannerModal" data-title="Edit Banner"
                                data-fill="<?= jsonAttr(['id' => $b['id'], 'title' => $b['title'], 'placement' => $b['placement'], 'link' => $b['link'], 'order' => $b['order'], 'from' => $b['from'], 'to' => $b['to'], 'status' => $b['status'], 'image' => $img]) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this banner', (string) $b['id'], true, 'banners.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit banner modal -->
<div class="modal fade js-crud-modal" id="bannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="banners.save">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" data-modal-title>Add Banner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Banner image</label>
                        <label class="upload-box">
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                            <img class="preview" alt="Preview">
                            <div class="upload-placeholder">
                                <i class="bi bi-image"></i>
                                <p>Click to upload<br><small>Hero: 1000x1000 &middot; Slider: 1600x600 &middot; Promotional: 1200x400 &middot; JPG/PNG/WebP, max 2 MB</small></p>
                            </div>
                        </label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label required" for="bnTitle">Title</label>
                            <input type="text" class="form-control" id="bnTitle" name="title" required maxlength="80">
                            <div class="invalid-feedback">Title is required.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="bnPlacement">Placement</label>
                            <select class="form-select" id="bnPlacement" name="placement" required>
                                <option>Homepage Hero</option>
                                <option>Homepage Slider</option>
                                <option>Promotional</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="bnLink">Link (page or URL)</label>
                            <input type="text" class="form-control" id="bnLink" name="link" placeholder="ac-service.php">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="bnOrder">Display order</label>
                            <input type="number" class="form-control" id="bnOrder" name="order" min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bnFrom">Show from</label>
                            <input type="date" class="form-control" id="bnFrom" name="from">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bnTo">Show until</label>
                            <input type="date" class="form-control" id="bnTo" name="to">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input js-status-toggle" type="checkbox" id="bnStatus" name="status" checked data-silent>
                                <label class="form-check-label" for="bnStatus">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
