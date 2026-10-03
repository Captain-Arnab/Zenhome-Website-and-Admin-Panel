<?php
// Published text from Admin > CMS Pages replaces the built-in intro text below.
require_once __DIR__ . '/api/catalog_helper.php';
$cms = site_page_content('about-us');
$siteMeta = $cms ? ['title' => $cms['meta_title'] ?: $cms['title'] . ' | Zen Home Experts', 'description' => $cms['meta_description']] : [];
include('header.php');
?>


<!-- ======================================================
     ABOUT PAGE BANNER
====================================================== -->

<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN HOME EXPERTS HOME SERVICES
            </span>

            <h1>
                About Zen Home Experts
            </h1>

            <p>
                Your trusted destination for reliable,
                professional and convenient home services.
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
                    About Us
                </strong>

            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     ABOUT INTRO SECTION
====================================================== -->

<section class="zcs-ac-intro">

    <div class="zcs-ac-container">

        <div class="zcs-ac-intro-grid">


            <!-- IMAGE -->

            <div class="zcs-ac-intro-image">

                <img
                    src="<?= site_e(site_setting('about_image_1')) ?>"
                    alt="About Zen Home Experts Home Services"
                >

                <div class="zcs-ac-image-badge">

                    <span>
                        <i class="fa-solid fa-house-circle-check"></i>
                    </span>

                    <div>

                        <strong>
                            One Trusted Platform
                        </strong>

                        <small>
                            Home • Lifestyle • Professional Services
                        </small>

                    </div>

                </div>

            </div>



            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    ABOUT ZEN HOME EXPERTS
                </span>

                <?php if ($cms): ?>

                <h2><?= site_e($cms['title']) ?></h2>

                <div class="zcs-cms-content">
                    <?= $cms['content'] ?>
                </div>

                <?php else: ?>

                <h2>
                    We Connect You to the
                    <strong>Right Service.</strong>
                </h2>


                <p>
                    Welcome to Zen Home Experts, your one-stop solution for all home
                    and lifestyle services. We are dedicated to making your life
                    easier with our professional and reliable services.
                </p>


                <p>
                    Whether it’s keeping your home cool with AC servicing,
                    ensuring your appliances run smoothly with refrigerator
                    repair, or giving your space a fresh and hygienic look
                    with home cleaning — we’ve got you covered.
                </p>


                <p>
                    Looking for self-care? Our expert salon services bring
                    the best grooming experience right to your doorstep.
                    Worried about pests? Our pest control solutions ensure
                    a safe and healthy living environment.
                </p>


                <p>
                    Need a carpenter for home improvements? We provide
                    skilled professionals for every repair, fitting
                    and renovation requirement.
                </p>

                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Quality & Reliability

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Clear Service Information

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Time-Saving Convenience

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Smooth Service Experience

                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="services.php"
                       class="zcs-ac-primary-btn">

                        Explore Our Services

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>


                    <a href="<?= site_e(site_tel('phone')) ?>"
                       class="zcs-ac-call-btn">

                        <span>
                            <i class="fa-solid fa-phone"></i>
                        </span>

                        <div>

                            <small>
                                Call for Assistance
                            </small>

                            <strong>
                                <?= site_e(site_phone_display(site_setting('phone'))) ?>
                            </strong>

                        </div>

                    </a>

                </div>

            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     OUR PURPOSE
====================================================== -->

<section class="zcs-ac-why">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR PURPOSE
            </span>

            <h2>
                Making Everyday Services
                <strong>Simple & Reliable.</strong>
            </h2>

            <p>
                Zen Home Experts is built around convenience, quality
                and a customer-first service experience.
            </p>

        </div>


        <div class="zcs-ac-why-grid">


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-bullseye"></i>
                </span>

                <h3>
                    Our Mission
                </h3>

                <p>
                    To make trusted home and lifestyle services
                    easily accessible through professional support,
                    convenient booking and dependable service.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-eye"></i>
                </span>

                <h3>
                    Our Vision
                </h3>

                <p>
                    To become a trusted service platform that
                    customers can rely on for everyday home,
                    appliance and lifestyle requirements.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-heart"></i>
                </span>

                <h3>
                    Customer First
                </h3>

                <p>
                    Every service is designed around customer
                    convenience, transparent information and
                    a smooth end-to-end experience.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-shield-halved"></i>
                </span>

                <h3>
                    Reliable Service
                </h3>

                <p>
                    We focus on connecting customers with
                    skilled professionals for dependable
                    home-service assistance.
                </p>

            </div>


        </div>

    </div>

</section>



<!-- ======================================================
     WHAT WE HELP WITH
====================================================== -->

<section class="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                WHAT WE HELP WITH
            </span>

            <h2>
                One Platform.
                <strong>Multiple Home Services.</strong>
            </h2>

            <p>
                From appliance repair to home cleaning and
                everyday maintenance, Zen Home Experts brings multiple
                services together in one convenient place.
            </p>

        </div>


        <?php $aboutCategories = site_catalog_categories(); ?>
        <?php if ($aboutCategories): ?>
        <div class="zcs-ac-package-grid">

            <?php foreach ($aboutCategories as $aboutCategory): ?>
            <article class="zcs-ac-package-card">

                <div class="zcs-ac-package-image">
                    <?php if ($aboutCategory['cover_image']): ?>
                    <img
                        src="<?= site_e($aboutCategory['cover_image']) ?>"
                        alt="<?= site_e($aboutCategory['name']) ?>"
                        loading="lazy"
                    >
                    <?php endif; ?>
                </div>

                <div class="zcs-ac-package-body">

                    <?php if ($aboutCategory['label'] !== ''): ?>
                    <span class="zcs-ac-package-tag">
                        <?= site_e($aboutCategory['label']) ?>
                    </span>
                    <?php endif; ?>

                    <h3>
                        <?= site_e($aboutCategory['title']) ?>
                    </h3>

                    <?php if ($aboutCategory['description'] !== ''): ?>
                    <p>
                        <?= site_e($aboutCategory['description']) ?>
                    </p>
                    <?php endif; ?>

                    <div class="zcs-ac-package-actions">

                        <a href="<?= site_e($aboutCategory['url']) ?>"
                           class="zcs-service-details-btn">

                            Explore Service

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>

                    </div>

                </div>

            </article>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>

    </div>

</section>



<!-- ======================================================
     WHY CUSTOMERS CHOOSE US
====================================================== -->

<section class="zcs-ac-process">

    <div class="zcs-ac-container">

        <div class="zcs-ac-process-layout">


            <div class="zcs-ac-process-heading">

                <span class="zcs-ac-section-label">
                    WHY ZEN HOME EXPERTS
                </span>

                <h2>
                    Built Around
                    <strong>Your Convenience.</strong>
                </h2>

                <p>
                    At Zen Home Experts, we believe in quality,
                    convenience and customer satisfaction.
                    Your comfort is our priority.
                </p>

            </div>


            <div class="zcs-ac-process-list">


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-star"></i>
                    </span>

                    <div>

                        <h3>
                            Quality & Reliability
                        </h3>

                        <p>
                            We prioritize professional service
                            and dependable support.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-list-check"></i>
                    </span>

                    <div>

                        <h3>
                            Clear Service Information
                        </h3>

                        <p>
                            Detailed service listings help you
                            choose the right option confidently.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-clock"></i>
                    </span>

                    <div>

                        <h3>
                            Save Time & Effort
                        </h3>

                        <p>
                            Find different home and lifestyle
                            services from one convenient platform.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-face-smile"></i>
                    </span>

                    <div>

                        <h3>
                            Smooth Experience
                        </h3>

                        <p>
                            Simple service selection and convenient
                            booking for an easier customer journey.
                        </p>

                    </div>

                </div>


            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     HOW ZEN HOME EXPERTS WORKS
====================================================== -->

<section class="zcs-ac-why">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                SIMPLE SERVICE EXPERIENCE
            </span>

            <h2>
                From Requirement to Service.
                <strong>Made Simple.</strong>
            </h2>

            <p>
                Getting professional help for your home
                should never feel complicated.
            </p>

        </div>


        <div class="zcs-ac-why-grid">


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>

                <h3>
                    Explore Services
                </h3>

                <p>
                    Browse available home and lifestyle
                    services based on your requirement.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-list-check"></i>
                </span>

                <h3>
                    Choose What You Need
                </h3>

                <p>
                    Review service details and select
                    the option that works for you.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-regular fa-calendar-check"></i>
                </span>

                <h3>
                    Book Conveniently
                </h3>

                <p>
                    Schedule your required service
                    according to your convenience.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-house-circle-check"></i>
                </span>

                <h3>
                    Get Doorstep Service
                </h3>

                <p>
                    A professional visits your location
                    and completes the selected service.
                </p>

            </div>


        </div>

    </div>

</section>



<!-- ======================================================
     OUR PROMISE
====================================================== -->

<section class="zcs-ac-intro">

    <div class="zcs-ac-container">

        <div class="zcs-ac-intro-grid">


            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    OUR PROMISE
                </span>

                <h2>
                    Service That Fits
                    <strong>Your Everyday Life.</strong>
                </h2>

                <p>
                    Home maintenance and lifestyle needs are part of
                    everyday life. Zen Home Experts aims to make accessing
                    professional service simpler and more convenient.
                </p>

                <p>
                    Whether it is a small repair, appliance problem,
                    home cleaning requirement or regular maintenance,
                    our goal is to help you find the right service
                    without unnecessary hassle.
                </p>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Professional Service Options

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Convenient Doorstep Support

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Multiple Services in One Place

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Customer-Focused Experience

                    </div>

                </div>

            </div>



            <div class="zcs-ac-intro-image">

                <img
                    src="<?= site_e(site_setting('about_image_2')) ?>"
                    loading="lazy"
                    alt="Zen Home Experts Professional Home Services"
                >

                <div class="zcs-ac-image-badge">

                    <span>
                        <i class="fa-solid fa-handshake"></i>
                    </span>

                    <div>

                        <strong>
                            Here When You Need Us
                        </strong>

                        <small>
                            Convenient • Professional • Reliable
                        </small>

                    </div>

                </div>

            </div>


        </div>

    </div>

</section>



<!-- ======================================================
     FINAL CTA
====================================================== -->

<section class="zcs-ac-cta">

    <div class="zcs-ac-container">

        <div class="zcs-ac-cta-box">


            <div>

                <span>
                    NEED A HOME SERVICE?
                </span>

                <h2>
                    Find the Right Service
                    with Zen Home Experts.
                </h2>

                <p>
                    Explore our home and lifestyle services
                    and book professional assistance at your doorstep.
                </p>

            </div>


            <div class="zcs-ac-cta-actions">


                <a href="services.php"
                   class="zcs-ac-cta-main">

                    Explore Services

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