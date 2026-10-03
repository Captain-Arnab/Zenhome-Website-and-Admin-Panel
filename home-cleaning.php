<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('home-cleaning');
include 'header.php';

if (!$catalogCategory) {
    catalog_render_unavailable();
    include 'footer.php';
    return;
}
$catalogServices = site_catalog_services(['category_id' => $catalogCategory['id']]);
?>


<!-- ======================================================
     PAGE BANNER
====================================================== -->

<?php catalog_render_banner($catalogCategory); ?>



<!-- ======================================================
     INTRO SECTION
====================================================== -->

<section class="zcs-ac-intro">

    <div class="zcs-ac-container">

        <div class="zcs-ac-intro-grid">


            <!-- IMAGE -->

            <div class="zcs-ac-intro-image">

                <?php catalog_render_intro_image($catalogCategory); ?>


                <div class="zcs-ac-image-badge">

                    <span>

                        <i class="fa-solid fa-broom"></i>

                    </span>

                    <div>

                        <strong>
                            Expert Home Cleaning
                        </strong>

                        <small>
                            Clean • Fresh • Hygienic
                        </small>

                    </div>

                </div>

            </div>



            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    PROFESSIONAL HOME CLEANING
                </span>

                <h2>
                    A Cleaner Home.
                    <strong>A Fresher Space.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>


                <p>
                    Zen Home Experts provides professional home cleaning
                    for furnished and unfurnished apartments, independent
                    homes and bathrooms.
                </p>


                <p>
                    From routine dusting, vacuuming and mopping to deep
                    cleaning, upholstery care, machine scrubbing and
                    bathroom sanitization, choose the package that
                    matches your home.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Full Home Cleaning

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Deep Cleaning

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Furnished & Unfurnished Homes

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Bathroom Cleaning

                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View Cleaning Services

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>


                    <a href="<?= site_e(site_tel('phone')) ?>"
                       class="zcs-ac-call-btn">

                        <span>

                            <i class="fa-solid fa-phone"></i>

                        </span>

                        <div>

                            <small>
                                Call for Booking
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
     HOME CLEANING PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR HOME CLEANING SERVICES
            </span>

            <h2>
                Choose the Cleaning
                <strong>Package You Need.</strong>
            </h2>

            <p>
                Select from basic, deep, furnished, unfurnished
                and bathroom cleaning services according to
                your home requirements.
            </p>

        </div>


        <?php catalog_render_services($catalogCategory, $catalogServices); ?>

    </div>

</section>



<!-- ======================================================
     WHY CHOOSE ZEN HOME EXPERTS
====================================================== -->

<section class="zcs-ac-why">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                WHY CHOOSE ZEN HOME EXPERTS
            </span>

            <h2>
                Professional Cleaning.
                <strong>A Fresher Home.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>

            <p>
                Convenient home cleaning services designed
                around hygiene, quality and dependable support.
            </p>

            <?php endif; ?>

        </div>


        <div class="zcs-ac-why-grid">


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-user-gear"></i>

                </span>

                <h3>
                    Professional Cleaning
                </h3>

                <p>
                    Professional assistance for full-home
                    and bathroom cleaning requirements.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-regular fa-calendar-check"></i>

                </span>

                <h3>
                    Convenient Booking
                </h3>

                <p>
                    Choose your cleaning package and
                    book a convenient service slot.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-broom"></i>

                </span>

                <h3>
                    Complete Home Care
                </h3>

                <p>
                    Dusting, vacuuming, mopping, scrubbing,
                    deep cleaning and sanitization services.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-headset"></i>

                </span>

                <h3>
                    Customer Support
                </h3>

                <p>
                    Easy assistance for booking and
                    home-cleaning service enquiries.
                </p>

            </div>


        </div>

    </div>

</section>



<!-- ======================================================
     HOW IT WORKS
====================================================== -->

<section class="zcs-ac-process">

    <div class="zcs-ac-container">

        <div class="zcs-ac-process-layout">


            <div class="zcs-ac-process-heading">

                <span class="zcs-ac-section-label">
                    HOW ZEN HOME EXPERTS WORKS
                </span>

                <h2>
                    Home Cleaning Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>

                <p>
                    Choose the cleaning service you need,
                    select your preferred time and let
                    Zen Home Experts handle the rest.
                </p>

                <?php endif; ?>

            </div>


            <div class="zcs-ac-process-list">


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-list-check"></i>
                    </span>

                    <div>

                        <h3>
                            Select Your Package
                        </h3>

                        <p>
                            Choose the home cleaning service
                            that fits your requirement.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-regular fa-calendar-check"></i>
                    </span>

                    <div>

                        <h3>
                            Book Your Slot
                        </h3>

                        <p>
                            Select your preferred date and
                            convenient cleaning time.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-user-check"></i>
                    </span>

                    <div>

                        <h3>
                            Professional Visit
                        </h3>

                        <p>
                            Our service professionals visit
                            your home for the cleaning service.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                    </span>

                    <div>

                        <h3>
                            Cleaning Complete
                        </h3>

                        <p>
                            Your selected home cleaning service
                            is completed professionally.
                        </p>

                    </div>

                </div>


            </div>

        </div>

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
                    NEED HOME CLEANING?
                </span>

                <h2>
                    Book Professional Home Cleaning
                    at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>

                <p>
                    Schedule your Zen Home Experts home cleaning service
                    today and enjoy a cleaner, fresher living space.
                </p>

                <?php endif; ?>

            </div>


            <div class="zcs-ac-cta-actions">


                <a href="#zcs-ac-packages"
                   class="zcs-ac-cta-main">

                    Choose Service

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


<?php catalog_render_faqs($catalogCategory); ?>

<?php include('footer.php'); ?>