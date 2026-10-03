<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('ac-service');
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
                            Expert AC Care
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
                    PROFESSIONAL AC SERVICES
                </span>

                <h2>
                    Keep Your Home Cool with
                    <strong>Expert AC Care.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>


                <p>
                    Zen Home Experts provides professional air conditioner
                    servicing, repair and maintenance for homes and businesses.
                    Our technicians help keep your AC working efficiently,
                    cooling properly and running smoothly.
                </p>


                <p>
                    Whether you need routine servicing, gas refill,
                    installation or troubleshooting, our team delivers
                    convenient doorstep service with a customer-first approach.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Split & Window AC Service

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        AC Gas Refill

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        AC Installation

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        AC Repair & Maintenance

                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View AC Services

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
     AC SERVICE PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR AC SERVICES
            </span>

            <h2>
                Choose the Service
                <strong>You Need.</strong>
            </h2>

            <p>
                From routine AC cleaning to installation,
                gas refill and maintenance, select the service
                that fits your requirement.
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
                Reliable AC Service.
                <strong>Better Comfort.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>
            <p>
                Professional home service designed around
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
                    Professional technicians for AC servicing,
                    installation and repair requirements.
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
                    Choose your AC service and book a convenient
                    appointment at your preferred time.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-screwdriver-wrench"></i>

                </span>

                <h3>
                    Complete AC Care
                </h3>

                <p>
                    Servicing, gas refill, installation,
                    cleaning and repair from one service provider.
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
                    service-related enquiries.
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


            <!-- HEADING -->

            <div class="zcs-ac-process-heading">

                <span class="zcs-ac-section-label">
                    HOW ZEN HOME EXPERTS WORKS
                </span>

                <h2>
                    AC Service Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>
                <p>
                    Book your AC service without complicated steps.
                    Select the service you need and let Zen Home Experts
                    handle the rest.
                </p>
                <?php endif; ?>

            </div>


            <!-- STEPS -->

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
                            Choose the AC service or package
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
                            Professional Visit
                        </h3>

                        <p>
                            A service professional visits
                            your location.
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
                            Your AC service is completed
                            professionally.
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
                    NEED AC SERVICE?
                </span>

                <h2>
                    Book Professional AC Service
                    at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>
                <p>
                    Schedule your Zen Home Experts AC service today
                    and keep your home cool and comfortable.
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


<!-- ======================================================
     INCLUDE FOOTER
====================================================== -->

<?php catalog_render_faqs($catalogCategory); ?>

<?php
 include 'footer.php';
?>

 