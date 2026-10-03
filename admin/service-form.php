<?php
require_once __DIR__ . '/includes/functions.php';

$activeMenu = 'services';
$isEdit     = isset($_GET['id']);

$service = null;
if ($isEdit) {
    $service = api('services.get', ['id' => (int) $_GET['id']], null, true);
    if (!$service) {
        $pageTitle   = 'Service not found';
        $breadcrumbs = [['label' => 'Services', 'url' => 'services.php'], ['label' => 'Not found']];
        renderNotFound('Service not found', 'This service does not exist or was deleted.', 'services.php', 'Back to services');
    }
}
$service = $service ?? [
    'id' => '', 'name' => '', 'category_id' => (int) ($_GET['category'] ?? 0), 'subcategory_id' => null, 'price' => '', 'mrp' => null,
    'duration' => '', 'image' => null, 'status' => true, 'description' => '', 'ideal_for' => '', 'included' => [],
    'slug' => '', 'tag' => '', 'highlights' => '', 'order' => 0,
];
// "What's included" items, one per line: "Title | description"
$includedText = implode("\n", array_map(
    fn($i) => $i['title'] . ($i['description'] !== '' ? ' | ' . $i['description'] : ''),
    $service['included']
));

$categories    = api('categories.options', [], ['items' => []])['items'];
$subcategories = api_list('subcategories.list', [], 'subcategories')['items'];

$pageTitle   = $isEdit ? 'Edit Service' : 'Add Service';
$breadcrumbs = [['label' => 'Services', 'url' => 'services.php'], ['label' => $pageTitle]];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2><?= e($pageTitle) ?></h2>
        <p><?= $isEdit ? 'Update service details shown on the website and apps.' : 'Create a new bookable service.' ?></p>
    </div>
    <div class="actions">
        <a href="services.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back to services</a>
    </div>
</div>

<form class="needs-validation" novalidate method="post" enctype="multipart/form-data"
      data-api="services.save" data-redirect="services.php">
    <input type="hidden" name="id" value="<?= e($service['id']) ?>">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Service details</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="svcName">Service name</label>
                        <input type="text" class="form-control" id="svcName" name="name" value="<?= e($service['name']) ?>" required maxlength="120" placeholder="e.g. Foam Jet AC Service">
                        <div class="invalid-feedback">Service name is required.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="svcCategory">Category</label>
                            <select class="form-select" id="svcCategory" name="category" data-child="#svcSubcategory" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['id']) ?>" <?= (int) $service['category_id'] === $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?><?= $cat['status'] ? '' : ' (inactive)' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please choose a category.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="svcSubcategory">Subcategory</label>
                            <select class="form-select" id="svcSubcategory" name="subcategory">
                                <option value="">None</option>
                                <?php foreach ($subcategories as $sub): ?>
                                    <option value="<?= e($sub['id']) ?>" data-parent="<?= e($sub['category_id']) ?>" <?= (int) $service['subcategory_id'] === $sub['id'] ? 'selected' : '' ?>><?= e($sub['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional. Filtered by the selected category.</div>
                            <div class="invalid-feedback">Choose a subcategory of the selected category.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="svcDesc">Description</label>
                        <textarea class="form-control" id="svcDesc" name="description" rows="4" required maxlength="1000"
                                  data-char-count="#svcDescCount"
                                  placeholder="Why customers should choose this service, warranty..."><?= e($service['description']) ?></textarea>
                        <div class="invalid-feedback">Description is required.</div>
                        <small class="form-text d-block text-end" id="svcDescCount"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="svcIncluded">What's included</label>
                        <textarea class="form-control" id="svcIncluded" name="included" rows="5" maxlength="5000"
                                  placeholder="One item per line, e.g.&#10;Indoor unit cleaning | Foam jet wash of filters and coils&#10;Gas pressure check"><?= e($includedText) ?></textarea>
                        <div class="form-text">One item per line. Add a description after a <code>|</code>. Shown as the service's checklist in the apps.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="svcIdeal">Ideal for</label>
                        <input type="text" class="form-control" id="svcIdeal" name="ideal_for" value="<?= e($service['ideal_for']) ?>" maxlength="255" placeholder="e.g. Split and window ACs up to 2 ton">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Website card</h3></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="svcSlug">URL slug</label>
                            <input type="text" class="form-control" id="svcSlug" name="slug" value="<?= e($service['slug']) ?>" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="auto from name">
                            <div class="form-text">Used in website links and carts. Avoid changing it once live.</div>
                            <div class="invalid-feedback">Lowercase letters, numbers and hyphens only.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="svcTag">Badge</label>
                            <input type="text" class="form-control" id="svcTag" name="tag" value="<?= e($service['tag']) ?>" maxlength="40" placeholder="e.g. Popular">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="svcOrder">Display order</label>
                            <input type="number" class="form-control" id="svcOrder" name="order" value="<?= e($service['order']) ?>" min="0" max="9999">
                            <div class="form-text">0 = after ordered items.</div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="svcHighlights">Card highlights</label>
                        <textarea class="form-control" id="svcHighlights" name="highlights" rows="3" maxlength="500"
                                  placeholder="One per line, e.g.&#10;Indoor &amp; outdoor unit cleaning&#10;Gas pressure check"><?= e($service['highlights']) ?></textarea>
                        <div class="form-text">Bullet points on the website card. Leave empty to show the first 3 "What's included" items.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Pricing &amp; duration</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required" for="svcPrice">Price</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="svcPrice" name="price" value="<?= e($service['price']) ?>" min="0" step="1" required>
                                <div class="invalid-feedback">Enter a valid price.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="svcMrp">MRP (strike-through)</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="svcMrp" name="mrp" value="<?= e($service['mrp'] ?? '') ?>" min="0" step="1" placeholder="Optional">
                                <div class="invalid-feedback">MRP must be at least the price.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="svcDuration">Duration</label>
                            <input type="text" class="form-control" id="svcDuration" name="duration" value="<?= e($service['duration']) ?>" maxlength="100" placeholder="e.g. 60 mins">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Service image</h3></div>
                <div class="card-body">
                    <label class="upload-box <?= $service['image'] ? 'has-image' : '' ?>">
                        <input type="file" name="image" accept="image/png,image/jpeg,image/webp">
                        <img class="preview" alt="Preview" <?= $service['image'] ? 'src="' . e(assetUrl($service['image'])) . '"' : '' ?>>
                        <div class="upload-placeholder">
                            <i class="bi bi-image"></i>
                            <p>Click to upload an image<br><small>JPG/PNG/WebP, max 2 MB, 800x600</small></p>
                        </div>
                    </label>
                    <div class="form-text mt-2">Click the image to replace it. Large images are resized automatically.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Visibility</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="d-block fs-13 text-heading">Enable service</strong>
                            <small class="text-muted">Customers can book enabled services.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input js-status-toggle" type="checkbox" id="svcStatus" name="status" data-on="Enabled" data-off="Disabled" data-silent <?= $service['status'] ? 'checked' : '' ?>>
                            <label class="form-check-label fs-12 fw-semibold text-muted" for="svcStatus"><?= $service['status'] ? 'Enabled' : 'Disabled' ?></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i> <?= $isEdit ? 'Update Service' : 'Save Service' ?></button>
                <a href="services.php" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
