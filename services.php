<?php
require_once __DIR__ . '/api/catalog_helper.php';
$siteMeta = ['title' => 'Our Services | Zen Care Services'];
include 'header.php';
$serviceCategories = site_catalog_categories();
?>


<!-- =========================================================
     ZEN CARE SERVICES - OUR SERVICES PAGE
========================================================= -->


<!-- =========================================================
     PAGE BANNER
========================================================= -->

<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN CARE HOME SERVICES
            </span>

            <h1>Our Services</h1>

            <p>
                Professional home services delivered by skilled
                technicians and service professionals at your doorstep.
            </p>

            <div class="zcs-ac-breadcrumb">

                <a href="index.php">
                    <i class="fa-solid fa-house"></i>
                    Home
                </a>

                <span>
                    <i class="fa-solid fa-chevron-right"></i>
                </span>

                <strong>Our Services</strong>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     SERVICES INTRO
========================================================= -->

<section class="zcs-services-intro">

    <div class="zcs-services-container">

        <div class="zcs-services-intro-grid">

            <div class="zcs-services-intro-content">

                <span class="zcs-services-eyebrow">
                    SERVICES FOR YOUR HOME
                </span>

                <h2>
                    Everything Your Home Needs,
                    <strong>All in One Place.</strong>
                </h2>

                <p>
                    From appliance repairs and installations to cleaning,
                    beauty and pest control, Zen Care Services makes
                    maintaining your home simple and convenient.
                </p>

            </div>


            <div class="zcs-services-intro-note">

                <div class="zcs-services-intro-icon">
                    <i class="fa-solid fa-house-circle-check"></i>
                </div>

                <div>
                    <span>DOORSTEP CONVENIENCE</span>

                    <h3>
                        Professional Services at Your Home
                    </h3>

                    <p>
                        Choose the service you need, select the required
                        package and book a convenient appointment.
                    </p>
                </div>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     ALL SERVICES
========================================================= -->

<section class="zcs-services-main">

    <div class="zcs-services-container">


        <!-- SECTION HEADING -->

        <div class="zcs-services-heading">

            <span>
                EXPLORE OUR SERVICES
            </span>

            <h2>
                How Can We
                <strong>Help You Today?</strong>
            </h2>

            <p>
                Select a service below to explore available packages,
                pricing and service details.
            </p>

        </div>



        <?php if (!$serviceCategories): ?>
        <div class="zcs-catalog-empty" role="status">
            <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
            <h3>Our services could not be loaded right now</h3>
            <p>Please refresh the page or call us on <a href="<?= site_e(site_tel('phone')) ?>"><?= site_e(site_phone_display(site_setting('phone'))) ?></a>.</p>
        </div>
        <?php else: ?>
        <div class="zcs-services-grid">

            <?php foreach ($serviceCategories as $svcCategory): ?>
            <article class="zcs-services-card">

                <div class="zcs-services-card-image">

                    <?php if ($svcCategory['cover_image']): ?>
                    <img
                        src="<?= site_e($svcCategory['cover_image']) ?>"
                        alt="<?= site_e($svcCategory['name']) ?>"
                        loading="lazy"
                    >
                    <?php endif; ?>

                    <?php if ($svcCategory['label'] !== ''): ?>
                    <span class="zcs-services-category">
                        <?= site_e($svcCategory['label']) ?>
                    </span>
                    <?php endif; ?>

                </div>


                <div class="zcs-services-card-body">

                    <div class="zcs-services-card-icon">
                        <i class="<?= site_e($svcCategory['icon']) ?>"></i>
                    </div>

                    <h3>
                        <?= site_e($svcCategory['name']) ?>
                    </h3>

                    <?php if ($svcCategory['description'] !== ''): ?>
                    <p>
                        <?= site_e($svcCategory['description']) ?>
                    </p>
                    <?php endif; ?>

                    <?php if ($svcCategory['highlights']): ?>
                    <div class="zcs-services-features">
                        <?php foreach (array_slice($svcCategory['highlights'], 0, 3) as $point): ?>
                        <span>
                            <i class="fa-solid fa-check"></i>
                            <?= site_e($point) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <a href="<?= site_e($svcCategory['url']) ?>"
                       class="zcs-services-btn">

                        Explore <?= site_e($svcCategory['name']) ?>

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>

            </article>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>

    </div>

</section>



<!-- =========================================================
     WHY BOOK WITH ZEN CARE
========================================================= -->

<section class="zcs-services-why">

    <div class="zcs-services-container">

        <div class="zcs-services-why-head">

            <span>WHY ZEN CARE?</span>

            <h2>
                Home Services Made
                <strong>Simple.</strong>
            </h2>

        </div>


        <div class="zcs-services-why-grid">


            <div class="zcs-services-why-item">

                <i class="fa-solid fa-user-gear"></i>

                <h3>
                    Skilled Professionals
                </h3>

                <p>
                    Get your home service requirements handled
                    by experienced service professionals.
                </p>

            </div>


            <div class="zcs-services-why-item">

                <i class="fa-regular fa-calendar-check"></i>

                <h3>
                    Easy Booking
                </h3>

                <p>
                    Choose the service you need and book an
                    appointment at your convenience.
                </p>

            </div>


            <div class="zcs-services-why-item">

                <i class="fa-solid fa-house"></i>

                <h3>
                    Doorstep Service
                </h3>

                <p>
                    Professional home services without the hassle
                    of taking appliances outside your home.
                </p>

            </div>


            <div class="zcs-services-why-item">

                <i class="fa-solid fa-headset"></i>

                <h3>
                    Customer Support
                </h3>

                <p>
                    Our team is available to assist with service
                    bookings and related enquiries.
                </p>

            </div>


        </div>

    </div>

</section>



<!-- =========================================================
     BOTTOM CTA
========================================================= -->

<section class="zcs-services-cta">

    <div class="zcs-services-container">

        <div class="zcs-services-cta-box">

            <div>

                <span>
                    NEED A HOME SERVICE?
                </span>

                <h2>
                    Your Home Deserves
                    Professional Care.
                </h2>

                <p>
                    Choose from our complete range of home services
                    and book the service you need today.
                </p>

            </div>


            <div class="zcs-services-cta-actions">

                <a href="<?= site_e(site_tel('phone')) ?>"
                   class="zcs-services-call-btn">

                    <i class="fa-solid fa-phone"></i>

                    Call <?= site_e(site_phone_display(site_setting('phone'))) ?>

                </a>

                <a href="contact.php"
                   class="zcs-services-contact-btn">

                    Contact Us

                    <i class="fa-solid fa-arrow-right"></i>

                </a>

            </div>

        </div>

    </div>

</section>



<?php include('footer.php'); ?>