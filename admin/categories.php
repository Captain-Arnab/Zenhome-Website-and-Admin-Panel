<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Service Categories';
$activeMenu  = 'categories';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Services', 'url' => 'services.php'], ['label' => 'Categories']];

$categories = api_list('categories.list', [], 'categories')['items'];
$nextOrder  = $categories ? max(array_column($categories, 'order')) + 1 : 1;

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Categories</h2>
        <p>Top-level service groups shown on the website (e.g. AC Service, Home Cleaning).</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" data-title="Add Category"
                data-fill="<?= jsonAttr(['order' => $nextOrder, 'status' => true]) ?>">
            <i class="bi bi-plus-lg me-1"></i> Add Category
        </button>
    </div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Search categories" data-dt-search="#categoriesTable">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#categoriesTable" data-column="4" data-exact aria-label="Status">
                    <option value="">All statuses</option>
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="categoriesTable" data-empty="No categories yet. Add the first one.">
        <thead>
        <tr>
            <th>#</th>
            <th>Category</th>
            <th class="text-center">Subcategories</th>
            <th class="text-center">Services</th>
            <th>Status</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td class="text-muted"><?= (int) $cat['order'] ?></td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($cat['image']): ?>
                            <img src="<?= e(assetUrl($cat['image'])) ?>" class="thumb" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="thumb d-inline-flex align-items-center justify-content-center icon-soft-secondary"><i class="bi bi-image"></i></span>
                        <?php endif; ?>
                        <div>
                            <span class="cell-title"><?php if ($cat['icon']): ?><i class="bi <?= e($cat['icon']) ?> text-primary me-1"></i><?php endif; ?><?= e($cat['name']) ?></span>
                            <span class="cell-sub">Display order <?= (int) $cat['order'] ?><?php if ($cat['slug'] !== ''): ?> &middot; <a href="<?= e(assetUrl($cat['url'])) ?>" target="_blank" rel="noopener"><?= e($cat['url']) ?> <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></span>
                        </div>
                    </div>
                </td>
                <td class="text-center"><a href="subcategories.php?category=<?= e($cat['id']) ?>" class="fw-bold"><?= (int) $cat['subcategories'] ?></a></td>
                <td class="text-center"><a href="services.php?category=<?= e($cat['id']) ?>" class="fw-bold"><?= (int) $cat['services'] ?></a></td>
                <td data-search="<?= $cat['status'] ? 'Active' : 'Inactive' ?>"><?= statusSwitch($cat['status'], $cat['name'], 'Active', 'Inactive', (string) $cat['id'], 'categories.set_status') ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <button type="button" class="btn btn-icon btn-soft-primary" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#categoryModal" data-title="Edit Category"
                                data-fill="<?= jsonAttr([
                                    'id' => $cat['id'], 'name' => $cat['name'], 'icon' => $cat['icon'], 'order' => $cat['order'] ?: 1, 'status' => $cat['status'],
                                    'description' => $cat['description'], 'slug' => $cat['slug'], 'page_title' => $cat['page_title'], 'tagline' => $cat['tagline'],
                                    'label' => $cat['label'], 'web_icon' => $cat['web_icon'], 'highlights' => $cat['highlights'],
                                    'long_description' => $cat['long_description'], 'why_html' => $cat['why_html'], 'process_html' => $cat['process_html'], 'cta_html' => $cat['cta_html'], 'faqs' => $cat['faqs_text'],
                                    'image' => assetUrl($cat['image']), 'cover_image' => assetUrl($cat['cover_image']),
                                ]) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this category', (string) $cat['id'], true, 'categories.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit category modal -->
<div class="modal fade js-crud-modal" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="categories.save">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" data-modal-title>Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label required" for="catName">Category name</label>
                                <input type="text" class="form-control" id="catName" name="name" required maxlength="80" placeholder="e.g. AC Service">
                                <div class="invalid-feedback">Category name is required.</div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-7">
                                    <label class="form-label" for="catIcon">Icon class</label>
                                    <input type="text" class="form-control" id="catIcon" name="icon" placeholder="bi-snow" pattern="bi-[a-z0-9\-]+" maxlength="50">
                                    <div class="form-text">Bootstrap Icons class name.</div>
                                    <div class="invalid-feedback">Use a class like bi-snow.</div>
                                </div>
                                <div class="col-5">
                                    <label class="form-label required" for="catOrder">Display order</label>
                                    <input type="number" class="form-control" id="catOrder" name="order" min="1" value="1" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="catDesc">Short description</label>
                                <textarea class="form-control" id="catDesc" name="description" rows="3" maxlength="200"></textarea>
                                <div class="form-text">Shown under the page heading and on the Services / About pages.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input js-status-toggle" type="checkbox" id="catStatus" name="status" checked data-silent>
                                <label class="form-check-label" for="catStatus">Active</label>
                                <div class="form-text">Inactive categories and their services disappear from the website immediately.</div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Category image</label>
                            <label class="upload-box">
                                <input type="file" name="image" accept="image/png,image/jpeg,image/webp">
                                <img class="preview" alt="Preview">
                                <div class="upload-placeholder">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <p>Click to upload<br><small>Homepage card &amp; apps &middot; 600x400 &middot; max 2 MB</small></p>
                                </div>
                            </label>
                            <label class="form-label mt-3">Page image</label>
                            <label class="upload-box">
                                <input type="file" name="cover_image" accept="image/png,image/jpeg,image/webp">
                                <img class="preview" alt="Preview">
                                <div class="upload-placeholder">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <p>Click to upload<br><small>Category page &amp; Services page &middot; 1200x900 &middot; max 2 MB</small></p>
                                </div>
                            </label>
                            <div class="form-text">Large images are resized automatically.</div>
                        </div>
                        <div class="col-12">
                            <h6 class="fs-13 text-uppercase text-muted fw-bold mb-2 mt-1">Website</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="catSlug">URL slug</label>
                                    <input type="text" class="form-control" id="catSlug" name="slug" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="auto from name, e.g. ac-service">
                                    <div class="form-text">Categories that have their own page keep their slug.</div>
                                    <div class="invalid-feedback">Lowercase letters, numbers and hyphens only.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="catPageTitle">Page heading</label>
                                    <input type="text" class="form-control" id="catPageTitle" name="page_title" maxlength="120" placeholder="e.g. AC Service &amp; Repair">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="catTagline">Menu subtitle</label>
                                    <input type="text" class="form-control" id="catTagline" name="tagline" maxlength="80" placeholder="e.g. Service &amp; repair">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="catLabel">Card label</label>
                                    <input type="text" class="form-control" id="catLabel" name="label" maxlength="40" placeholder="e.g. COOLING">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="catWebIcon">Website icon</label>
                                    <input type="text" class="form-control" id="catWebIcon" name="web_icon" maxlength="60" pattern="fa-(solid|regular|brands) fa-[a-z0-9\-]+" placeholder="fa-solid fa-snowflake">
                                    <div class="invalid-feedback">Use a Font Awesome class, e.g. fa-solid fa-snowflake.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="catHighlights">Highlights</label>
                                    <textarea class="form-control" id="catHighlights" name="highlights" rows="3" maxlength="400" placeholder="One per line, e.g.&#10;AC Repair&#10;Foam Jet Service&#10;Installation"></textarea>
                                    <div class="form-text">Up to 3 are shown on the Services page card.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="catLongDesc">Long description</label>
                                    <textarea class="form-control" id="catLongDesc" name="long_description" rows="5" maxlength="4000"></textarea>
                                    <div class="form-text">The introduction text on the category page. Leave a blank line between paragraphs. Left empty, the page keeps its built-in text.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="catWhyHtml">"Why choose us" text</label>
                                    <textarea class="form-control" id="catWhyHtml" name="why_html" rows="3" maxlength="2000"></textarea>
                                    <div class="form-text">Left empty, the page keeps its built-in text.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="catProcessHtml">"How it works" text</label>
                                    <textarea class="form-control" id="catProcessHtml" name="process_html" rows="3" maxlength="2000"></textarea>
                                    <div class="form-text">Left empty, the page keeps its built-in text.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="catCtaHtml">CTA section text</label>
                                    <textarea class="form-control" id="catCtaHtml" name="cta_html" rows="3" maxlength="2000"></textarea>
                                    <div class="form-text">Left empty, the page keeps its built-in text.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="catFaqs">FAQs (optional)</label>
                                    <textarea class="form-control" id="catFaqs" name="faqs" rows="6" maxlength="15000" placeholder="How long does an AC service take?&#10;Usually 45 to 60 minutes per unit.&#10;&#10;Do you bring spare parts?&#10;Yes, common parts are carried by the technician."></textarea>
                                    <div class="form-text">Question on the first line, answer on the following line(s). Leave a blank line between FAQs. Shown at the bottom of the category page (up to 20).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
