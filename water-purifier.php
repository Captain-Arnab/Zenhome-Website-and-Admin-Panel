<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('water-purifier');
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

                        <i class="fa-solid fa-droplet"></i>

                    </span>

                    <div>

                        <strong>
                            Expert Purifier Care
                        </strong>

                        <small>
                            Clean • Safe • Professional
                        </small>

                    </div>

                </div>

            </div>


            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    PROFESSIONAL WATER PURIFIER SERVICES
                </span>

                <h2>
                    Pure Water Starts with
                    <strong>Expert Purifier Care.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>


                <p>
                    Zen Care Services provides professional water purifier
                    servicing, filter replacement, repair, installation
                    and maintenance at your doorstep.
                </p>


                <p>
                    Whether your purifier has low water pressure,
                    dispensing issues, pump problems, filter issues
                    or requires regular servicing, our technicians
                    provide reliable and convenient assistance.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Water Purifier Service

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Filter Replacement

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Pump & Pressure Repair

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Installation & Uninstallation

                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View Water Purifier Services

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
     WATER PURIFIER SERVICE PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR WATER PURIFIER SERVICES
            </span>

            <h2>
                Choose the Service
                <strong>You Need.</strong>
            </h2>

            <p>
                From routine servicing and filter replacement
                to pump repair, installation and uninstallation,
                choose the service suitable for your water purifier.
            </p>

        </div>


        <?php catalog_render_services($catalogCategory, $catalogServices); ?>

    </div>

</section>



<!-- ======================================================
     WHY CHOOSE ZEN CARE
====================================================== -->

<section class="zcs-ac-why">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                WHY CHOOSE ZEN CARE
            </span>

            <h2>
                Reliable Purifier Service.
                <strong>Cleaner Water.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>

            <p>
                Professional water purifier service designed around
                convenience, quality and dependable support.
            </p>

            <?php endif; ?>

        </div>


        <div class="zcs-ac-why-grid">


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-user-gear"></i>

                </span>

                <h3>
                    Skilled Technicians
                </h3>

                <p>
                    Professional technicians for purifier servicing,
                    repair, installation and maintenance.
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
                    Choose your purifier service and book
                    a convenient appointment at your preferred time.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-screwdriver-wrench"></i>

                </span>

                <h3>
                    Complete Purifier Care
                </h3>

                <p>
                    Servicing, filter replacement, pump repair,
                    installation and maintenance from one provider.
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
                    water purifier service-related enquiries.
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
                    HOW ZEN CARE WORKS
                </span>

                <h2>
                    Water Purifier Service Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>

                <p>
                    Choose the purifier service you need,
                    book your preferred time and let
                    Zen Care handle the rest.
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
                            Select Your Service
                        </h3>

                        <p>
                            Choose the water purifier service
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
                            Select your preferred date
                            and convenient service time.
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
                            A trained service professional
                            visits your location.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                    </span>

                    <div>

                        <h3>
                            Service Complete
                        </h3>

                        <p>
                            Your water purifier service
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
                    NEED WATER PURIFIER SERVICE?
                </span>

                <h2>
                    Book Professional Water Purifier Service
                    at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>

                <p>
                    Schedule your Zen Care water purifier service today
                    for servicing, repair, filter replacement
                    or installation.
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

<?php
include 'footer.php';
?>