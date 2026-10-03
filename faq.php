<?php
// Content comes from Admin > CMS Pages (slug "faq"); a contact prompt is shown until it is published.
require_once __DIR__ . '/api/site_content.php';
$cms = site_page_content('faq');
$siteMeta = [
    'title' => $cms ? ($cms['meta_title'] ?: $cms['title'] . ' | Zen Care Services') : 'FAQs | Zen Care Services',
    'description' => $cms['meta_description'] ?? 'Answers to common questions about booking, payments, rescheduling and Zen Care home services.',
];
include('header.php');
?>


<!-- ======================================================
     PAGE BANNER
====================================================== -->

<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN CARE SERVICES
            </span>

            <h1>
                Frequently Asked Questions
            </h1>

            <p>
                Quick answers about booking, payments, rescheduling
                and our home services.
            </p>

            <div class="zcs-ac-breadcrumb">

                <a href="index.php">
                    <i class="fa-solid fa-house"></i>
                    Home
                </a>

                <span>
                    <i class="fa-solid fa-chevron-right"></i>
                </span>

                <strong>
                    FAQs
                </strong>

            </div>

        </div>

    </div>

</section>


<!-- ======================================================
     FAQ CONTENT
====================================================== -->

<section class="zcs-terms-section">

    <div class="zcs-ac-container">

        <?php if ($cms): ?>

        <div class="zcs-terms-intro">
            <span class="zcs-ac-section-label">
                HELP CENTRE
            </span>
            <h2><?= site_e($cms['title']) ?></h2>
            <?php if ($cms['updated']): ?>
                <p>Last updated: <?= site_e(date('d M Y', strtotime($cms['updated']))) ?></p>
            <?php endif; ?>
        </div>

        <div class="zcs-terms-content zcs-cms-content">
            <div class="zcs-terms-block">
                <?= $cms['content'] ?>
            </div>
        </div>

        <?php else: ?>

        <div class="zcs-terms-intro">
            <span class="zcs-ac-section-label">
                HELP CENTRE
            </span>
            <h2>
                Have a
                <strong>Question?</strong>
            </h2>
            <p>
                Our FAQ section is being updated. In the meantime, our
                support team is happy to help with bookings, payments,
                refunds or anything else.
            </p>
        </div>

        <div class="zcs-terms-content">
            <div class="zcs-terms-block">
                <div class="zcs-terms-contact">
                    <p>
                        <strong>Email:</strong>
                        <a href="mailto:<?= site_e(site_setting('email')) ?>">
                            <?= site_e(site_setting('email')) ?>
                        </a>
                    </p>
                    <p>
                        <strong>Customer Support:</strong>
                        <a href="<?= site_e(site_tel('phone')) ?>">
                            <?= site_e(site_setting('phone')) ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </div>

</section>


<!-- ======================================================
     CTA
====================================================== -->

<section class="zcs-ac-cta">

    <div class="zcs-ac-container">

        <div class="zcs-ac-cta-box">

            <div>

                <span>
                    NEED HELP?
                </span>

                <h2>
                    Still Have Questions?
                </h2>

                <p>
                    Contact Zen Care Services for help with bookings,
                    payments, refunds, service policies or your account.
                </p>

            </div>


            <div class="zcs-ac-cta-actions">

                <a href="contact.php"
                   class="zcs-ac-cta-main">

                    Contact Us

                    <i class="fa-solid fa-arrow-right"></i>

                </a>


                <a href="<?= site_e(site_tel('phone')) ?>"
                   class="zcs-ac-cta-call">

                    <i class="fa-solid fa-phone"></i>

                    <?= site_e(site_phone_display(site_setting('phone'))) ?>

                </a>

            </div>

        </div>

    </div>

</section>


<?php include('footer.php'); ?>
