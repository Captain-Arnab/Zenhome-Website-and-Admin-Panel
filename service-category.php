<?php
/**
 * Category page for categories added in the admin panel that have no
 * dedicated page of their own: service-category.php?slug=<slug> (or ?id=).
 */
require_once __DIR__ . '/partials/catalog.php';
$catalogKey = (string) ($_GET['slug'] ?? $_GET['id'] ?? '');
$catalogCategory = catalog_page_category($catalogKey);

// Categories with their own page keep a single URL.
if ($catalogCategory && in_array($catalogCategory['slug'], CATALOG_PAGES, true)) {
    header('Location: ' . $catalogCategory['url'], true, 301);
    exit;
}

include 'header.php';

if (!$catalogCategory) {
    catalog_render_unavailable();
    include 'footer.php';
    return;
}
$catalogServices = site_catalog_services(['category_id' => $catalogCategory['id']]);
?>

<?php catalog_render_banner($catalogCategory); ?>

<section class="zcs-ac-intro">
    <div class="zcs-ac-container">
        <div class="zcs-ac-intro-grid">

            <div class="zcs-ac-intro-image">
                <?php catalog_render_intro_image($catalogCategory); ?>
                <div class="zcs-ac-image-badge">
                    <span><i class="<?= site_e($catalogCategory['icon']) ?>"></i></span>
                    <div>
                        <strong><?= site_e($catalogCategory['name']) ?></strong>
                        <small>Fast • Reliable • Professional</small>
                    </div>
                </div>
            </div>

            <div class="zcs-ac-intro-content">
                <span class="zcs-ac-section-label"><?= site_e($catalogCategory['label'] !== '' ? $catalogCategory['label'] : 'ZEN HOME EXPERTS') ?></span>
                <h2>
                    <?= site_e($catalogCategory['name']) ?>
                    <strong>at Your Doorstep.</strong>
                </h2>
                <?php if (!catalog_render_long_description($catalogCategory)): ?>
                    <?php if ($catalogCategory['description'] !== ''): ?>
                        <p><?= site_e($catalogCategory['description']) ?></p>
                    <?php endif; ?>
                    <p>Book online and a verified Zen Home Experts professional will visit at your chosen time slot.</p>
                <?php endif; ?>

                <?php if ($catalogCategory['highlights']): ?>
                    <div class="zcs-ac-intro-points">
                        <?php foreach ($catalogCategory['highlights'] as $point): ?>
                            <div><i class="fa-solid fa-circle-check"></i> <?= site_e($point) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="zcs-ac-intro-actions">
                    <a href="#zcs-ac-packages" class="zcs-ac-primary-btn">
                        View Services
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="<?= site_e(site_tel('phone')) ?>" class="zcs-ac-call-btn">
                        <span><i class="fa-solid fa-phone"></i></span>
                        <div>
                            <small>Call for Booking</small>
                            <strong><?= site_e(site_phone_display(site_setting('phone'))) ?></strong>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<section class="zcs-ac-packages" id="zcs-ac-packages">
    <div class="zcs-ac-container">
        <div class="zcs-ac-heading">
            <span>OUR SERVICES</span>
            <h2>
                Choose the Service
                <strong>You Need.</strong>
            </h2>
            <p>Select the service that fits your requirement and add it to your cart.</p>
        </div>

        <?php catalog_render_services($catalogCategory, $catalogServices); ?>
    </div>
</section>

<?php catalog_render_generic_section($catalogCategory, 'why_html', 'why', 'WHY CHOOSE ZEN HOME EXPERTS', 'Reliable Service. Better Experience.'); ?>
<?php catalog_render_generic_section($catalogCategory, 'process_html', 'process', 'HOW ZEN HOME EXPERTS WORKS', 'Booking Made Simple.'); ?>
<?php catalog_render_generic_section($catalogCategory, 'cta_html', 'cta', 'NEED THIS SERVICE?', 'Book at Your Doorstep.'); ?>

<?php catalog_render_faqs($catalogCategory); ?>

<?php include 'footer.php'; ?>
