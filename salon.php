<?php
require_once __DIR__ . '/partials/catalog.php';
$catalogCategory = catalog_page_category('salon');
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
                        <i class="fa-solid fa-spa"></i>
                    </span>

                    <div>

                        <strong>
                            Professional Beauty Care
                        </strong>

                        <small>
                            Facial • Skin Care • Beauty
                        </small>

                    </div>

                </div>

            </div>


            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    PROFESSIONAL FACIAL SERVICES
                </span>

                <h2>
                    Refresh Your Skin.
                    <strong>Restore Your Glow.</strong>
                </h2>

                <?php if (!catalog_render_long_description($catalogCategory)): ?>

                <p>
                    Zen Home Experts brings professional facial and beauty
                    treatments directly to your home for a convenient and
                    relaxing beauty-care experience.
                </p>

                <p>
                    Choose from brightening facials, anti-tanning treatments,
                    youthful skin care, gold facials, instant glow treatments
                    and professional threading services.
                </p>
                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Brightening Facial
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Anti-Tanning Care
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Glow & Youthful Facials
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Professional Threading
                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="#zcs-ac-packages"
                       class="zcs-ac-primary-btn">

                        View Beauty Services

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
     FACIAL SERVICE PACKAGES
====================================================== -->

<section class="zcs-ac-packages"
         id="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                OUR BEAUTY SERVICES
            </span>

            <h2>
                Choose the Treatment
                <strong>Your Skin Needs.</strong>
            </h2>

            <p>
                Explore professional facial and beauty treatments designed
                for brightening, tanning care, hydration, youthful-looking
                skin and everyday grooming.
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
                Professional Beauty Care.
                <strong>At Your Doorstep.</strong>
            </h2>

            <?php if (!catalog_render_section($catalogCategory, 'why_html')): ?>

            <p>
                Enjoy convenient facial and beauty treatments with
                professional care, easy booking and services designed
                around your comfort.
            </p>

            <?php endif; ?>

        </div>


        <div class="zcs-ac-why-grid">


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-user-check"></i>
                </span>

                <h3>
                    Beauty Professionals
                </h3>

                <p>
                    Professional assistance for facial treatments,
                    skin-care services and everyday grooming needs.
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
                    Select the facial or beauty service you need
                    and book your preferred service slot.
                </p>

            </div>


            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-spa"></i>
                </span>

                <h3>
                    Complete Skin Care
                </h3>

                <p>
                    Brightening, de-tanning, glow, hydration and
                    grooming treatments from one service provider.
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
                    Easy assistance for beauty service bookings,
                    treatment selection and service-related enquiries.
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
                    Beauty Services Made
                    <strong>Simple.</strong>
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'process_html')): ?>

                <p>
                    Choose your preferred facial or beauty treatment,
                    select a convenient booking slot and enjoy professional
                    beauty care at your doorstep.
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
                            Choose the facial or beauty treatment
                            that matches your requirement.
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
                            Select your preferred date and convenient
                            service time.
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
                            A beauty service professional visits
                            your location for the selected treatment.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-face-smile-beam"></i>
                    </span>

                    <div>

                        <h3>
                            Feel Fresh & Beautiful
                        </h3>

                        <p>
                            Relax and enjoy professional beauty care
                            from the comfort of your home.
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
                    READY FOR A FRESH GLOW?
                </span>

                <h2>
                    Book Professional Facial & Beauty
                    Services at Your Doorstep.
                </h2>

                <?php if (!catalog_render_section($catalogCategory, 'cta_html')): ?>

                <p>
                    Schedule your Zen Home Experts beauty service today and enjoy
                    convenient skin care, facial treatments and grooming
                    from the comfort of your home.
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