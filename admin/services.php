<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Services';
$activeMenu  = 'services';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Services']];

$categories     = api('categories.options', [], ['items' => []])['items'];
$subcategories  = api_list('subcategories.list', [], 'subcategories')['items'];
$filterCategory = (int) ($_GET['category'] ?? 0);
$filterName     = $filterCategory ? (findBy($categories, 'id', $filterCategory)['name'] ?? '') : '';
$services       = api_list('services.list', $filterCategory ? ['category_id' => $filterCategory] : [], 'services')['items'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Services</h2>
        <p>Bookable services with price and duration. Disabled services are hidden from the website.</p>
    </div>
    <div class="actions">
        <a href="categories.php" class="btn btn-outline-secondary"><i class="bi bi-folder2 me-1"></i> Categories</a>
        <a href="service-form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Service</a>
    </div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Search services" data-dt-search="#servicesTable">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" id="filterCategory" data-child="#filterSubcategory" data-dt-filter="#servicesTable" data-column="1" aria-label="Category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" id="filterSubcategory" data-dt-filter="#servicesTable" data-column="1" aria-label="Subcategory">
                    <option value="">All subcategories</option>
                    <?php foreach ($subcategories as $sub): ?>
                        <option data-parent="<?= e($sub['category']) ?>"><?= e($sub['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select" data-dt-filter="#servicesTable" data-column="5" data-exact aria-label="Status">
                    <option value="">All statuses</option>
                    <option>Enabled</option>
                    <option>Disabled</option>
                </select>
            </div>
        </div>
        <?php if ($filterName !== ''): ?>
            <p class="fs-12 text-muted mb-0 mt-2"><i class="bi bi-funnel"></i> Showing services in <strong><?= e($filterName) ?></strong>. <a href="services.php">Show all</a></p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="servicesTable" data-empty="No services yet. Add the first one.">
        <thead>
        <tr>
            <th>Service</th>
            <th>Category / Subcategory</th>
            <th>Price</th>
            <th>Duration</th>
            <th class="text-center">Bookings</th>
            <th>Status</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($services as $s): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3" style="min-width:240px">
                        <?php if ($s['image']): ?>
                            <img src="<?= e(assetUrl($s['image'])) ?>" class="thumb" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="thumb d-inline-flex align-items-center justify-content-center icon-soft-secondary"><i class="bi bi-image"></i></span>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <a href="service-form.php?id=<?= (int) $s['id'] ?>" class="cell-title"><?= e($s['name']) ?></a>
                            <span class="cell-sub">ID #<?= (int) $s['id'] ?> &middot; <?= (int) $s['items'] ?> included item<?= $s['items'] === 1 ? '' : 's' ?></span>
                        </div>
                    </div>
                </td>
                <td class="nowrap"><?= e($s['category'] ?: '-') ?><div class="cell-sub"><?= e($s['subcategory']) ?></div></td>
                <td class="nowrap fw-bold" data-order="<?= (float) $s['price'] ?>"><?= money($s['price']) ?><?php if ($s['mrp'] && $s['mrp'] > $s['price']): ?><div class="cell-sub text-decoration-line-through"><?= money($s['mrp']) ?></div><?php endif; ?></td>
                <td class="nowrap"><i class="bi bi-clock text-muted me-1"></i><?= e($s['duration'] ?: '-') ?></td>
                <td class="text-center"><?= (int) $s['bookings'] ?></td>
                <td data-search="<?= $s['status'] ? 'Enabled' : 'Disabled' ?>"><?= statusSwitch($s['status'], $s['name'], 'Enabled', 'Disabled', (string) $s['id'], 'services.set_status') ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <a href="service-form.php?id=<?= (int) $s['id'] ?>" class="btn btn-icon btn-soft-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this service', (string) $s['id'], true, 'services.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
