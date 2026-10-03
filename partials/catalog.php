<?php
/**
 * Website catalog templates (server-side, data from the admin panel via
 * api/catalog_helper.php). Markup and classes are the ones the category
 * pages always used, so style.css applies unchanged.
 */
require_once dirname(__DIR__) . '/api/catalog_helper.php';
require_once dirname(__DIR__) . '/api/site_settings_helper.php';

/**
 * Category for a category page. Sets the page <title>/description and a 404
 * status when the category is missing or disabled in the admin panel.
 */
function catalog_page_category($key): ?array
{
    $category = site_catalog_category($key);
    if (!$category) {
        http_response_code(404);
        $GLOBALS['siteMeta'] = ['title' => 'Service unavailable | Zen Home Experts'];
        return null;
    }
    $GLOBALS['siteMeta'] = [
        'title'       => $category['title'] . ' | Zen Home Experts',
        'description' => $category['description'],
    ];
    return $category;
}

function catalog_render_unavailable(): void
{
    ?>
<section class="zcs-catalog-unavailable">
    <div class="zcs-ac-container">
        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
        <h1>This service is not available right now</h1>
        <p>It may have been paused or moved. Please browse our other home services or call us and we will help you.</p>
        <div class="zcs-catalog-unavailable-actions">
            <a href="services.php" class="zcs-ac-primary-btn">View All Services <i class="fa-solid fa-arrow-right"></i></a>
            <a href="<?= site_e(site_tel('phone')) ?>" class="zcs-catalog-call-link"><i class="fa-solid fa-phone"></i> <?= site_e(site_phone_display(site_setting('phone'))) ?></a>
        </div>
    </div>
</section>
    <?php
}

function catalog_render_banner(array $category): void
{
    ?>
<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN HOME EXPERTS HOME SERVICES
            </span>

            <h1><?= site_e($category['title']) ?></h1>

            <?php if ($category['description'] !== ''): ?>
                <p><?= site_e($category['description']) ?></p>
            <?php endif; ?>

            <div class="zcs-ac-breadcrumb">
                <a href="index.php">
                    <i class="fa-solid fa-house"></i>
                    Home
                </a>
                <span>
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
                <strong><?= site_e($category['name']) ?></strong>
            </div>

        </div>

    </div>

</section>
    <?php
}

/** Intro image of a category page (cover image, falling back to the card image). */
function catalog_render_intro_image(array $category): void
{
    $src = $category['cover_image'] ?? $category['image'];
    if ($src !== null) {
        echo '<img src="' . site_e($src) . '" alt="' . site_e($category['title']) . '">';
    }
}

/** The package grid: one card per enabled service, grouped by subcategory when any are set. */
function catalog_render_services(array $category, array $services): void
{
    if (!$services) {
        ?>
        <div class="zcs-catalog-empty" role="status">
            <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
            <h3>No services are available to book online right now</h3>
            <p>Call us on <a href="<?= site_e(site_tel('phone')) ?>"><?= site_e(site_phone_display(site_setting('phone'))) ?></a> and we will arrange a visit.</p>
        </div>
        <?php
        return;
    }
    $groups = [];
    foreach ($services as $service) {
        $groups[(string) ($service['subcategory'] ?? '')][] = $service;
    }
    $showHeadings = count($groups) > 1 || array_key_first($groups) !== '';
    echo '<div class="zcs-ac-package-grid">';
    foreach ($groups as $heading => $items) {
        if ($showHeadings) {
            echo '<h3 class="zcs-catalog-subheading">' . site_e($heading !== '' ? $heading : 'More ' . $category['name'] . ' services') . '</h3>';
        }
        foreach ($items as $service) {
            catalog_render_service_card($service, $category);
        }
    }
    echo '</div>';
}

function catalog_render_service_card(array $s, array $category): void
{
    $image = $s['image'] ?? $category['image'] ?? 'images/logo.png';
    $detailsId = 'service-' . $s['slug'] . '-details';
    $more = array_values(array_diff($s['included'], $s['highlights']));
    $hasDetails = $more || $s['duration'] !== '' || $s['ideal_for'] !== '';
    ?>
            <article class="zcs-ac-package-card" id="service-<?= site_e($s['slug']) ?>">

                <div class="zcs-ac-package-image">
                    <img src="<?= site_e($image) ?>" alt="<?= site_e($s['name']) ?>" loading="lazy">
                </div>

                <div class="zcs-ac-package-body">

                    <?php if ($s['tag'] !== ''): ?>
                        <span class="zcs-ac-package-tag"><?= site_e($s['tag']) ?></span>
                    <?php endif; ?>

                    <h3><?= site_e($s['name']) ?></h3>

                    <?php if ($s['description'] !== ''): ?>
                        <p><?= site_e($s['description']) ?></p>
                    <?php endif; ?>

                    <?php if ($s['highlights']): ?>
                        <ul class="zcs-ac-package-features">
                            <?php foreach ($s['highlights'] as $point): ?>
                                <li>
                                    <i class="fa-solid fa-circle-check"></i>
                                    <?= site_e($point) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($hasDetails): ?>
                        <div class="zcs-service-more" id="<?= site_e($detailsId) ?>" hidden>
                            <?php if ($more): ?>
                                <strong>Also included</strong>
                                <ul>
                                    <?php foreach ($more as $point): ?>
                                        <li><?= site_e($point) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($s['duration'] !== ''): ?>
                                <p><i class="fa-regular fa-clock"></i> <?= site_e($s['duration']) ?></p>
                            <?php endif; ?>
                            <?php if ($s['ideal_for'] !== ''): ?>
                                <p><i class="fa-solid fa-house-circle-check"></i> Ideal for: <?= site_e($s['ideal_for']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="zcs-ac-package-price">
                        <small>Service Price</small>
                        <strong>
                            <?php if ($s['mrp'] !== null): ?><del><?= site_e(site_price($s['mrp'])) ?></del><?php endif; ?>
                            <?= site_e(site_price($s['price'])) ?>
                        </strong>
                    </div>

                    <div class="zcs-ac-package-actions">

                        <button
                            type="button"
                            class="zcs-add-cart-btn"
                            data-id="<?= site_e($s['slug']) ?>"
                            data-pack-id="<?= (int) $s['pack_id'] ?>"
                            data-name="<?= site_e($s['name']) ?>"
                            data-price="<?= site_e((string) (float) $s['price']) ?>"
                            data-image="<?= site_e($image) ?>"
                            data-url="<?= site_e($category['url']) ?>"
                            data-category="<?= site_e($category['name']) ?>"
                        >
                            <i class="fa-solid fa-cart-plus"></i>
                            <span>Add to Cart</span>
                        </button>

                        <?php if ($hasDetails): ?>
                            <a href="#<?= site_e($detailsId) ?>" class="zcs-service-details-btn" role="button"
                               aria-expanded="false" aria-controls="<?= site_e($detailsId) ?>" data-zcs-details>
                                <span>More Details</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>

                    </div>

                </div>

            </article>
    <?php
}

/**
 * Intro paragraphs from the category's long description (admin panel).
 * Returns false when none is set, so the page keeps its built-in text.
 */
function catalog_render_long_description(array $category): bool
{
    $paragraphs = $category['long_description'] ?? [];
    foreach ($paragraphs as $paragraph) {
        echo "<p>\n                    " . site_e($paragraph) . "\n                </p>\n";
    }
    return (bool) $paragraphs;
}

/**
 * Admin override for the description paragraph of a "why choose us" /
 * "how it works" / CTA section heading (Admin > Categories). Same idea as
 * catalog_render_long_description for the intro: $field is 'why_html',
 * 'process_html' or 'cta_html' (plain text, not HTML, despite the column
 * name); empty/unset keeps the page's own hardcoded paragraph. Returns
 * true when the built-in paragraph should be skipped.
 */
function catalog_render_section(array $category, string $field): bool
{
    $value = trim((string) ($category[$field] ?? ''));
    if ($value === '') {
        return false;
    }
    echo "<p>\n                " . site_e($value) . "\n            </p>\n";
    return true;
}

/**
 * Full "why choose us" / "how it works" / CTA section for category pages
 * with no bespoke built-in copy (service-category.php, the shared template
 * for admin-added categories without their own page). Admin content only -
 * nothing is shown when the field is empty, since there is no hardcoded
 * fallback text to fall back to.
 */
function catalog_render_generic_section(array $category, string $field, string $sectionClass, string $label, string $heading): void
{
    $value = trim((string) ($category[$field] ?? ''));
    if ($value === '') {
        return;
    }
    if ($sectionClass === 'cta') {
        ?>
<section class="zcs-ac-cta">
    <div class="zcs-ac-container">
        <div class="zcs-ac-cta-box">
            <div>
                <span><?= site_e($label) ?></span>
                <h2><?= site_e($heading) ?></h2>
                <p><?= site_e($value) ?></p>
            </div>
            <div class="zcs-ac-cta-actions">
                <a href="#zcs-ac-packages" class="zcs-ac-cta-main">
                    Choose Service
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
                <a href="<?= site_e(site_tel('phone')) ?>" class="zcs-ac-cta-call">
                    <i class="fa-solid fa-phone"></i>
                    <?= site_e(site_phone_display(site_setting('phone'))) ?>
                </a>
            </div>
        </div>
    </div>
</section>
        <?php
        return;
    }
    ?>
<section class="zcs-ac-<?= site_e($sectionClass) ?>">
    <div class="zcs-ac-container">
        <div class="zcs-ac-heading">
            <span><?= site_e($label) ?></span>
            <h2><?= site_e($heading) ?></h2>
            <p><?= site_e($value) ?></p>
        </div>
    </div>
</section>
    <?php
}

/** FAQ section of a category page; nothing when the category has no FAQs. */
function catalog_render_faqs(array $category): void
{
    if (empty($category['faqs'])) {
        return;
    }
    ?>
<section class="zcs-catalog-faq">
    <div class="zcs-ac-container">
        <div class="zcs-ac-heading">
            <span>FAQS</span>
            <h2>
                Frequently Asked
                <strong>Questions.</strong>
            </h2>
        </div>
        <div class="zcs-catalog-faq-list">
            <?php foreach ($category['faqs'] as $i => $faq): ?>
                <details class="zcs-catalog-faq-item"<?= $i === 0 ? ' open' : '' ?>>
                    <summary><?= site_e($faq['question']) ?> <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                    <p><?= nl2br(site_e($faq['answer'])) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
    <?php
}
