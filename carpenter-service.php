<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('carpenter-service');
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

                        <i class="fa-solid fa-hammer"></i>

                    </span>

                    <div>

                        <strong>
                            Expert Carpenter Care
                        </strong>

                        <small>
                            Repair • Installation • Fitting
                        </small>

                    </div>

                </div>

            </div>



            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    PROFESSIONAL CARPENTER SERVICES
                </span>

                <h2>
                    Reliable Carpentry for
                    <strong>Your Home.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>


                <p>
                    Zen Care Services provides professional carpenter
                    services for everyday household repairs, fittings
                    and installations.
                </p>


                <p>
                    From door lock replacement and curtain rod installation
                    to bed support repair and cupboard hinge fitting,
                    our service professionals help keep your home
                    functional and well maintained.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Door Installation & Repair

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Door Lock Replacement

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Furniture Repair

                    </div>

                    <div>

                        <i class="fa-solid fa-circle-check"></i>

                        Cupboard & Home Fittings

                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View Carpenter Services

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
     CARPENTER SERVICE PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR CARPENTER SERVICES
            </span>

            <h2>
                Choose the Service
                <strong>You Need.</strong>
            </h2>

            <p>
                From door installation and lock replacement to
                furniture repairs and cupboard fittings, select
                the carpenter service that fits your requirement.
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
                Professional Carpentry.
                <strong>Better Home Care.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>

            <p>
                Convenient carpenter services designed around
                professional assistance, easy booking and dependable support.
            </p>

            <?php endif; ?>

        </div>


        <div class="zcs-ac-why-grid">


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-user-gear"></i>

                </span>

                <h3>
                    Skilled Professionals
                </h3>

                <p>
                    Professional assistance for common carpenter
                    repairs, installations and household fittings.
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
                    Choose your required carpenter service and
                    book at a convenient time.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>

                    <i class="fa-solid fa-hammer"></i>

                </span>

                <h3>
                    Complete Carpenter Care
                </h3>

                <p>
                    Door repairs, lock replacement, furniture
                    repairs and home fittings from one provider.
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
                    Easy assistance for carpenter service
                    bookings and service-related enquiries.
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
                    HOW ZEN CARE WORKS
                </span>

                <h2>
                    Carpenter Service Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>

                <p>
                    Book your carpenter service without complicated steps.
                    Select the service you need and let Zen Care
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
                            Choose the carpenter service
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
                            A carpenter service professional
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
                            Your carpenter service is
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
                    NEED A CARPENTER?
                </span>

                <h2>
                    Book Professional Carpenter Service
                    at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>

                <p>
                    Schedule your Zen Care carpenter service today
                    for reliable home repairs, installations and fittings.
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