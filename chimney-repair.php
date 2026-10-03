<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('chimney-repair');
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

                        <i class="fa-solid fa-screwdriver-wrench"></i>

                    </span>

                    <div>

                        <strong>
                            Expert Chimney Care
                        </strong>

                        <small>
                            Fast • Reliable • Professional
                        </small>

                    </div>

                </div>

            </div>


            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    PROFESSIONAL CHIMNEY SERVICES
                </span>

                <h2>
                    Keep Your Kitchen Fresh with
                    <strong>Expert Chimney Care.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>


                <p>
                    Zen Home Experts provides professional chimney
                    inspection, repair, cleaning and maintenance services
                    for modern kitchens. Our technicians help identify
                    suction, motor, fan and control panel problems.
                </p>


                <p>
                    Whether your chimney has poor suction, a faulty motor,
                    blower issue, duct problem or requires installation,
                    our team provides reliable doorstep chimney service.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Chimney Cleaning

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Motor & Fan Repair

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Suction Issue Repair

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Installation & Uninstallation

                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View Chimney Services

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
     CHIMNEY SERVICE PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR CHIMNEY SERVICES
            </span>

            <h2>
                Choose the Service
                <strong>You Need.</strong>
            </h2>

            <p>
                From chimney cleaning and repair to installation
                and uninstallation, choose the service that fits
                your kitchen chimney requirement.
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
                Reliable Chimney Service.
                <strong>Cleaner Kitchen.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>

            <p>
                Professional chimney service designed around
                convenience, safety and dependable support.
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
                    Professional technicians for chimney cleaning,
                    repair and installation requirements.
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
                    Choose your chimney service and book
                    an appointment at your preferred time.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </span>

                <h3>
                    Complete Chimney Care
                </h3>

                <p>
                    Cleaning, motor repair, fan repair,
                    duct service and installation from one provider.
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
                    chimney service-related enquiries.
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
                    Chimney Service Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>

                <p>
                    Select your required chimney service,
                    choose a convenient slot and let
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
                            Select Your Service
                        </h3>

                        <p>
                            Choose the chimney service
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
                            A trained chimney service professional
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
                            Your chimney service is
                            completed professionally.
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
                    NEED CHIMNEY SERVICE?
                </span>

                <h2>
                    Book Professional Chimney Service
                    at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>

                <p>
                    Schedule your Zen Home Experts chimney service today
                    for cleaning, inspection, repair or installation.
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