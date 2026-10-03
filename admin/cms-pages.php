<?php
require_once __DIR__ . '/includes/functions.php';

$cmsPages = api_list('cms.list', [], 'pages')['items'];
$grouped = [];
foreach ($cmsPages as $p) {
    $grouped[$p['group']][] = $p;
}

$slug    = (string) ($_GET['page'] ?? ($cmsPages[0]['slug'] ?? ''));
$current = $slug !== '' ? api('cms.get', ['slug' => $slug], null, true) : null;
if ($cmsPages && !$current) {
    renderNotFound('Page not found', 'This CMS page does not exist.', 'cms-pages.php', 'Back to CMS pages');
}
$content = $current ? ($current['content'] !== '' ? $current['content'] : '<h2>' . e($current['title']) . '</h2><p>Write the content for this page here.</p>') : '';

$pageTitle   = 'CMS Pages';
$activeMenu  = 'cms';
$plugins     = ['editor'];
$breadcrumbs = [['label' => 'Banners & CMS'], ['label' => 'CMS Pages']];
if ($current) {
    $breadcrumbs[] = ['label' => $current['title']];
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>CMS Pages</h2>
        <p>Edit static website pages and service page content.</p>
    </div>
    <div class="actions">
        <a href="banners.php" class="btn btn-outline-secondary"><i class="bi bi-images me-1"></i> Banners</a>
    </div>
</div>

<?php if (!$current): ?>
    <div class="card"><div class="card-body"><?= emptyState('No CMS pages found. Run the admin migration to create the page list.', 'bi-file-earmark-text') ?></div></div>
<?php else: ?>
<div class="alert alert-light border fs-13 py-2"><i class="bi bi-info-circle me-1"></i> Published pages replace the built-in text on the website's About Us, Contact, FAQ, Terms, Privacy and Refund pages, and are available to the apps via <code>api/cms_pages.php</code>. Drafts are never shown; until a page is published the site keeps its built-in text. Service content pages are stored for the apps only.</div>
<div class="row g-3">
    <!-- Page list -->
    <div class="col-lg-4 col-xl-3">
        <div class="card">
            <?php foreach ($grouped as $group => $pages): ?>
                <div class="px-3 pt-3 pb-1 fs-12 text-uppercase fw-bold text-muted"><?= e($group) ?></div>
                <div class="list-group list-group-flush px-2 pb-2">
                    <?php foreach ($pages as $p): ?>
                        <a href="cms-pages.php?page=<?= e($p['slug']) ?>"
                           class="list-group-item list-group-item-action border-0 rounded-3 d-flex justify-content-between align-items-center <?= $p['slug'] === $current['slug'] ? 'active' : '' ?>">
                            <span><i class="bi bi-file-earmark-text me-2"></i><?= e($p['title']) ?></span>
                            <?php if ($p['status'] === 'Draft'): ?><span class="badge <?= $p['slug'] === $current['slug'] ? 'bg-light text-dark' : 'badge-soft-muted' ?>">Draft</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Editor -->
    <div class="col-lg-8 col-xl-9">
        <form class="needs-validation" novalidate id="cmsForm" data-api="cms.save" data-reload>
            <input type="hidden" name="slug" value="<?= e($current['slug']) ?>">
            <input type="hidden" name="content" id="cmsContent">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Editing: <?= e($current['title']) ?></h3>
                        <p class="card-subtitle">Last updated <?= fdate($current['updated']) ?></p>
                    </div>
                    <?= statusBadge($current['status']) ?>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label required" for="cmsTitle">Page title</label>
                            <input type="text" class="form-control" id="cmsTitle" name="title" value="<?= e($current['title']) ?>" required maxlength="150">
                            <div class="invalid-feedback">Title is required.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="cmsSlug">URL slug</label>
                            <div class="input-group">
                                <span class="input-group-text">/</span>
                                <input type="text" class="form-control" id="cmsSlug" value="<?= e($current['slug']) ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <label class="form-label required">Content</label>
                    <div id="cmsEditor"><?= $content /* sanitized by the API on save */ ?></div>
                    <div class="invalid-feedback d-none" id="cmsContentError">Content is required.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">SEO</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cmsMetaTitle">Meta title</label>
                            <input type="text" class="form-control" id="cmsMetaTitle" name="meta_title" maxlength="70" value="<?= e($current['meta_title']) ?>" placeholder="<?= e($current['title']) ?> | Zen Care Services" data-char-count="#metaTitleCount">
                            <small class="form-text" id="metaTitleCount"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cmsMetaDesc">Meta description</label>
                            <textarea class="form-control" id="cmsMetaDesc" name="meta_description" rows="2" maxlength="160" data-char-count="#metaDescCount"><?= e($current['meta_description']) ?></textarea>
                            <small class="form-text" id="metaDescCount"></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-end gap-2">
                <?php if ($current['url'] !== ''): ?>
                    <a href="../<?= e($current['url']) ?>" target="_blank" rel="noopener" class="btn btn-light"><i class="bi bi-box-arrow-up-right me-1"></i> View on site</a>
                <?php endif; ?>
                <button type="submit" name="status" value="Draft" class="btn btn-outline-secondary" data-busy-text="Saving...">Save as Draft</button>
                <button type="submit" name="status" value="Published" class="btn btn-primary" data-busy-text="Publishing..."><i class="bi bi-check2 me-1"></i> Publish</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php ob_start(); ?>
<script>
    (function () {
        const form = document.getElementById('cmsForm');
        if (!window.Quill || !form) return;
        const quill = new Quill('#cmsEditor', {
            theme: 'snow',
            placeholder: 'Write page content...',
            modules: {
                toolbar: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'link'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['blockquote', 'image'],
                    ['clean']
                ]
            }
        });
        const error = document.getElementById('cmsContentError');
        form.addEventListener('api:before', function (e) {
            const empty = quill.getText().trim() === '' && !quill.root.querySelector('img');
            error.classList.toggle('d-none', !empty);
            error.classList.toggle('d-block', empty);
            if (empty) {
                e.preventDefault();
                window.AdminUI.toast('Content cannot be empty.', 'danger');
                return;
            }
            document.getElementById('cmsContent').value = quill.root.innerHTML;
        });
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
