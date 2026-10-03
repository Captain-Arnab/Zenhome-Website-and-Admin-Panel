<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('refrigerator-repair');
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

                        <i class="fa-solid fa-snowflake"></i>

                    </span>

                    <div>

                        <strong>
                            Expert Refrigerator Care
                        </strong>

                        <small>
                            Diagnose • Repair • Maintain
                        </small>

                    </div>

                </div>

            </div>



            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    PROFESSIONAL REFRIGERATOR SERVICES
                </span>

                <h2>
                    Complete Refrigerator Care
                    <strong>At Your Doorstep.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>


                <p>
                    Zen Home Experts provides professional refrigerator
                    repair and inspection for single-door, double-door,
                    inverter and side-by-side refrigerators.
                </p>


                <p>
                    From cooling problems and water leakage to ice maker
                    repair, defrosting issues and refrigerator gas refilling,
                    our service professionals provide convenient assistance
                    for common refrigerator problems.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Cooling Problem Inspection

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Water Leakage Repair

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Gas Refilling

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Complete Refrigerator Repair

                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View Refrigerator Services

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
     REFRIGERATOR SERVICE PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR REFRIGERATOR SERVICES
            </span>

            <h2>
                Choose the Service
                <strong>You Need.</strong>
            </h2>

            <p>
                Select the refrigerator repair or inspection
                service that matches your appliance and requirement.
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
                Professional Refrigerator Care.
                <strong>Reliable Home Service.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>

            <p>
                Convenient refrigerator repair and maintenance
                designed around professional diagnosis and support.
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
                    Professional service for different refrigerator
                    types and common appliance issues.
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
                    Select your required refrigerator service
                    and book a convenient appointment.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-screwdriver-wrench"></i>

                </span>

                <h3>
                    Complete Refrigerator Care
                </h3>

                <p>
                    Cooling, leakage, gas refill, defrosting and
                    complete refrigerator repair services.
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
                    refrigerator service-related enquiries.
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
                    Refrigerator Service Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>

                <p>
                    Select the service you need, choose your
                    preferred time and let Zen Home Experts handle the rest.
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
                            Choose the refrigerator repair
                            service that fits your requirement.
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
                            convenient service time.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-user-check"></i>
                    </span>

                    <div>

                        <h3>
                            Technician Visit
                        </h3>

                        <p>
                            A service professional visits
                            your location and inspects your refrigerator.
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
                            Your refrigerator service is
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
                    NEED REFRIGERATOR SERVICE?
                </span>

                <h2>
                    Book Professional Refrigerator Repair
                    at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>

                <p>
                    Schedule your Zen Home Experts refrigerator service
                    today for professional diagnosis and repair.
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