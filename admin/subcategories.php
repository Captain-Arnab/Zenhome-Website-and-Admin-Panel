<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Subcategories';
$activeMenu  = 'subcategories';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Services', 'url' => 'services.php'], ['label' => 'Subcategories']];

$categories     = api('categories.options', [], ['items' => []])['items'];
$filterCategory = (int) ($_GET['category'] ?? 0);
$filterName     = $filterCategory ? (findBy($categories, 'id', $filterCategory)['name'] ?? '') : '';
$subcategories  = api_list('subcategories.list', $filterCategory ? ['category_id' => $filterCategory] : [], 'subcategories')['items'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Subcategories</h2>
        <p>Group services inside a category (e.g. AC Service &rarr; AC Installation).</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subcategoryModal" data-title="Add Subcategory"
                data-fill="<?= jsonAttr(['status' => true] + ($filterCategory ? ['category' => (string) $filterCategory] : [])) ?>">
            <i class="bi bi-plus-lg me-1"></i> Add Subcategory
        </button>
    </div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-5">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Search subcategories" data-dt-search="#subcategoriesTable">
                </div>
            </div>
            <div class="col-6 col-md-4">
                <select class="form-select" data-dt-filter="#subcategoriesTable" data-column="1" data-exact aria-label="Category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#subcategoriesTable" data-column="3" data-exact aria-label="Status">
                    <option value="">All statuses</option>
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </div>
        </div>
        <?php if ($filterName !== ''): ?>
            <p class="fs-12 text-muted mb-0 mt-2"><i class="bi bi-funnel"></i> Showing subcategories of <strong><?= e($filterName) ?></strong>. <a href="subcategories.php">Show all</a></p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="subcategoriesTable" data-empty="No subcategories yet. Services can also sit directly under a category.">

        <thead>
        <tr>
            <th>Subcategory</th>
            <th>Category</th>
            <th class="text-center">Services</th>
            <th>Status</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($subcategories as $sub): ?>
            <tr>
                <td><span class="cell-title"><?= e($sub['name']) ?></span><?php if ($sub['slug'] !== ''): ?><span class="cell-sub"><?= e($sub['slug']) ?></span><?php endif; ?></td>
                <td><span class="badge badge-soft-secondary"><?= e($sub['category']) ?></span></td>
                <td class="text-center fw-bold"><?= (int) $sub['services'] ?></td>
                <td data-search="<?= $sub['status'] ? 'Active' : 'Inactive' ?>"><?= statusSwitch($sub['status'], $sub['name'], 'Active', 'Inactive', (string) $sub['id'], 'subcategories.set_status') ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <button type="button" class="btn btn-icon btn-soft-primary" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#subcategoryModal" data-title="Edit Subcategory"
                                data-fill="<?= jsonAttr(['id' => $sub['id'], 'name' => $sub['name'], 'slug' => $sub['slug'], 'order' => $sub['order'], 'category' => (string) $sub['category_id'], 'status' => $sub['status']]) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this subcategory', (string) $sub['id'], true, 'subcategories.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit subcategory modal -->
<div class="modal fade js-crud-modal" id="subcategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="subcategories.save">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" data-modal-title>Add Subcategory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required" for="subCategory">Parent category</label>
                        <select class="form-select" id="subCategory" name="category" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['id']) ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please choose a category.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="subName">Subcategory name</label>
                        <input type="text" class="form-control" id="subName" name="name" required maxlength="120" placeholder="e.g. AC Installation">
                        <div class="invalid-feedback">Subcategory name is required.</div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label" for="subSlug">URL slug</label>
                            <input type="text" class="form-control" id="subSlug" name="slug" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="auto from name">
                            <div class="invalid-feedback">Lowercase letters, numbers and hyphens only.</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="subOrder">Order</label>
                            <input type="number" class="form-control" id="subOrder" name="order" min="0" max="9999" value="0">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input js-status-toggle" type="checkbox" id="subStatus" name="status" checked data-silent>
                        <label class="form-check-label" for="subStatus">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
