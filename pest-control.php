<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('pest-control');
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
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>

                    <div>

                        <strong>
                            Professional Pest Care
                        </strong>

                        <small>
                            Inspection • Treatment • Prevention
                        </small>

                    </div>

                </div>

            </div>


            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    PROFESSIONAL PEST CONTROL SERVICES
                </span>

                <h2>
                    Protect Your Home From
                    <strong>Unwanted Pests.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>

                <p>
                    Zen Care Services provides professional pest control
                    solutions for homes, apartments and commercial spaces.
                </p>

                <p>
                    Our pest control services are designed to treat
                    cockroaches, ants, bed bugs and other common household
                    pests while covering cracks, drains and difficult-to-reach
                    areas.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Cockroach Treatment
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Bed Bug Control
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Apartment Pest Control
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Commercial Pest Control
                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View Pest Control Services

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
     PEST CONTROL PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR PEST CONTROL SERVICES
            </span>

            <h2>
                Choose the Pest Control
                <strong>Service You Need.</strong>
            </h2>

            <p>
                Select from professional pest control packages for kitchens,
                bathrooms, apartments, independent houses and commercial
                properties.
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
                Professional Pest Control.
                <strong>Safer Living Spaces.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>

            <p>
                Convenient pest control services designed to protect
                homes and businesses from common pest problems.
            </p>

            <?php endif; ?>

        </div>


        <div class="zcs-ac-why-grid">


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-user-shield"></i>
                </span>

                <h3>
                    Trained Professionals
                </h3>

                <p>
                    Professional assistance for household and
                    commercial pest control requirements.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-shield-halved"></i>
                </span>

                <h3>
                    Targeted Treatment
                </h3>

                <p>
                    Treatment focuses on cracks, drains, hidden areas
                    and other locations where pests commonly hide.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-house-circle-check"></i>
                </span>

                <h3>
                    Home & Business Care
                </h3>

                <p>
                    Pest control solutions for apartments,
                    independent houses, offices and shops.
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
                    Easy assistance for pest control bookings,
                    service selection and related enquiries.
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
                    Pest Control Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>

                <p>
                    Select your required pest control service,
                    choose a convenient booking time and let our
                    professionals take care of your property.
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
                            Choose the pest control package
                            suitable for your property.
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
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>

                    <div>

                        <h3>
                            Property Inspection
                        </h3>

                        <p>
                            The service professional inspects
                            pest-prone and affected areas.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>

                    <div>

                        <h3>
                            Pest Treatment
                        </h3>

                        <p>
                            Professional treatment is carried out
                            across the required areas.
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
                    NEED PEST CONTROL?
                </span>

                <h2>
                    Book Professional Pest Control
                    at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>

                <p>
                    Schedule your Zen Care pest control service today
                    for professional treatment of your home, apartment,
                    office or commercial space.
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