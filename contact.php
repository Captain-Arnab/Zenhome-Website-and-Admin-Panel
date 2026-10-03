<?php
// Published text from Admin > CMS Pages replaces the built-in intro text below.
require_once __DIR__ . '/api/site_content.php';
$cms = site_page_content('contact-us');
$siteMeta = $cms ? ['title' => $cms['meta_title'] ?: $cms['title'] . ' | Zen Care Services', 'description' => $cms['meta_description']] : [];
include('header.php');
?>


<!-- ======================================================
     CONTACT PAGE BANNER
====================================================== -->

<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN CARE HOME SERVICES
            </span>

            <h1>Contact Us</h1>

            <p>
                Have a question or need help choosing a service?
                Get in touch with the Zen Care team.
            </p>

            <div class="zcs-ac-breadcrumb">

                <a href="index.php">
                    <i class="fa-solid fa-house"></i>
                    Home
                </a>

                <span>
                    <i class="fa-solid fa-chevron-right"></i>
                </span>

                <strong>Contact Us</strong>

            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     CONTACT INTRO
====================================================== -->

<section class="zcs-ac-intro">

    <div class="zcs-ac-container">

        <div class="zcs-ac-intro-grid">


            <!-- IMAGE -->

            <div class="zcs-ac-intro-image">

                <img
                    src="<?= site_e(site_setting('contact_image')) ?>"
                    alt="Contact Zen Care Home Services"
                >

                <div class="zcs-ac-image-badge">

                    <span>
                        <i class="fa-solid fa-headset"></i>
                    </span>

                    <div>

                        <strong>
                            We're Here to Help
                        </strong>

                        <small>
                            Service • Booking • Support
                        </small>

                    </div>

                </div>

            </div>



            <!-- CONTENT -->

            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    GET IN TOUCH
                </span>

                <?php if ($cms): ?>

                <h2><?= site_e($cms['title']) ?></h2>

                <div class="zcs-cms-content">
                    <?= $cms['content'] ?>
                </div>

                <?php else: ?>

                <h2>
                    Need Help?
                    <strong>Talk to Zen Care.</strong>
                </h2>

                <p>
                    Whether you need help selecting a service,
                    have a question about your booking or simply
                    want to know more about Zen Care, our team
                    is ready to assist you.
                </p>

                <p>
                    From AC and refrigerator repair to home cleaning,
                    carpenter services and other home-service requirements,
                    reach out to us and we'll help you find the
                    right service.
                </p>

                <?php endif; ?>


                <div class="zcs-ac-intro-points">

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Service Enquiries
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Booking Assistance
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        Customer Support
                    </div>

                    <div>
                        <i class="fa-solid fa-circle-check"></i>
                        General Enquiries
                    </div>

                </div>


                <div class="zcs-ac-intro-actions">

                    <a href="<?= site_e(site_tel('phone')) ?>"
                       class="zcs-ac-primary-btn">

                        <i class="fa-solid fa-phone"></i>

                        Call Us Now

                    </a>


                    <a href="mailto:<?= site_e(site_setting('email')) ?>"
                       class="zcs-ac-call-btn">

                        <span>
                            <i class="fa-regular fa-envelope"></i>
                        </span>

                        <div>

                            <small>
                                Email Us
                            </small>

                            <strong>
                                <?= site_e(site_setting('email')) ?>
                            </strong>

                        </div>

                    </a>

                </div>

            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     CONTACT INFORMATION
====================================================== -->

<section class="zcs-ac-why">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                CONTACT INFORMATION
            </span>

            <h2>
                Connect With
                <strong>Zen Care.</strong>
            </h2>

            <p>
                Reach us by phone, email or visit our location
                for service-related assistance.
            </p>

        </div>


        <div class="zcs-ac-why-grid">


            <!-- ADDRESS -->

            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-location-dot"></i>
                </span>

                <h3>
                    Address
                </h3>

                <p>
                    <?= implode("<br>\n                    ", array_map('site_e', site_address_lines())) ?>
                </p>
                <?php if (site_setting('working_hours') !== ''): ?>
                <p><i class="fa-regular fa-clock"></i> <?= site_e(site_setting('working_hours')) ?></p>
                <?php endif; ?>

            </div>



            <!-- EMAIL -->

            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-regular fa-envelope"></i>
                </span>

                <h3>
                    Mail
                </h3>

                <p>
                    Have a question? Send us an email
                    and our team will assist you.
                </p>

                <a href="mailto:<?= site_e(site_setting('email')) ?>">
                    <?= site_e(site_setting('email')) ?>
                </a>

            </div>



            <!-- PHONE -->

            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-phone"></i>
                </span>

                <h3>
                    Phone Number
                </h3>

                <p>
                    Call us for service booking,
                    enquiries and customer assistance.
                </p>

                <a href="<?= site_e(site_tel('phone')) ?>">
                    <?= site_e(site_setting('phone')) ?>
                </a>

            </div>



            <!-- SERVICE SUPPORT -->

            <div class="zcs-ac-why-card">

                <span>
                    <i class="fa-solid fa-headset"></i>
                </span>

                <h3>
                    Service Support
                </h3>

                <p>
                    Need help with a home service?
                    Contact our team for booking and
                    service-related assistance.
                </p>

            </div>


        </div>

    </div>

</section>



<!-- ======================================================
     CONTACT FORM
====================================================== -->

<section class="zcs-ac-packages">

    <div class="zcs-ac-container">


        <div class="zcs-ac-heading">

            <span>
                SEND AN ENQUIRY
            </span>

            <h2>
                Tell Us How We Can
                <strong>Help You.</strong>
            </h2>

            <p>
                Fill in your details and let us know which
                service you are interested in.
            </p>

        </div>


        <div class="zcs-contact-form-wrap">

            <div id="zcsContactAlert"></div>

            <form
                action="#"
                method="post"
                class="zcs-contact-form"
                id="zcsContactForm"
                novalidate
            >

                <input type="text" name="website" class="zcs-contact-hp" tabindex="-1" autocomplete="off" aria-hidden="true">


                <div class="zcs-contact-form-grid">


                    <!-- NAME -->

                    <div class="zcs-contact-field">

                        <label for="contact-name">
                            Your Name
                        </label>

                        <div class="zcs-contact-input">

                            <i class="fa-regular fa-user"></i>

                            <input
                                type="text"
                                id="contact-name"
                                name="name"
                                placeholder="Enter your name"
                                required
                            >

                        </div>

                    </div>



                    <!-- PHONE -->

                    <div class="zcs-contact-field">

                        <label for="contact-phone">
                            Phone Number
                        </label>

                        <div class="zcs-contact-input">

                            <i class="fa-solid fa-phone"></i>

                            <input
                                type="tel"
                                id="contact-phone"
                                name="phone"
                                placeholder="Enter phone number"
                                required
                            >

                        </div>

                    </div>



                    <!-- EMAIL -->

                    <div class="zcs-contact-field">

                        <label for="contact-email">
                            Email Address
                        </label>

                        <div class="zcs-contact-input">

                            <i class="fa-regular fa-envelope"></i>

                            <input
                                type="email"
                                id="contact-email"
                                name="email"
                                placeholder="Enter email address"
                            >

                        </div>

                    </div>



                    <!-- SERVICE -->

                    <div class="zcs-contact-field">

                        <label for="contact-service">
                            Select Service
                        </label>

                        <div class="zcs-contact-input">

                            <i class="fa-solid fa-screwdriver-wrench"></i>

                            <select
                                id="contact-service"
                                name="service"
                                required
                            >

                                <option value="">
                                    Choose a service
                                </option>

<?php foreach ($siteCategories as $contactCategory): ?>
                                <option value="<?= site_e($contactCategory['name']) ?>">
                                    <?= site_e($contactCategory['name']) ?>
                                </option>
<?php endforeach; ?>

                                <option value="Other Service">
                                    Other Service
                                </option>

                            </select>

                        </div>

                    </div>



                    <!-- ADDRESS -->

                    <div class="zcs-contact-field zcs-contact-full">

                        <label for="contact-address">
                            Service Address
                        </label>

                        <div class="zcs-contact-input">

                            <i class="fa-solid fa-location-dot"></i>

                            <input
                                type="text"
                                id="contact-address"
                                name="address"
                                placeholder="Enter your service location"
                            >

                        </div>

                    </div>



                    <!-- MESSAGE -->

                    <div class="zcs-contact-field zcs-contact-full">

                        <label for="contact-message">
                            Your Message
                        </label>

                        <div class="zcs-contact-input zcs-contact-textarea">

                            <i class="fa-regular fa-message"></i>

                            <textarea
                                id="contact-message"
                                name="message"
                                rows="5"
                                placeholder="Tell us about your service requirement..."
                                required
                            ></textarea>

                        </div>

                    </div>


                </div>



                <div class="zcs-contact-submit">

                    <button
                        type="submit"
                        class="zcs-ac-primary-btn"
                        id="zcsContactSubmit"
                    >

                        <span>Send Enquiry</span>

                        <i class="fa-solid fa-paper-plane"></i>

                    </button>

                </div>


            </form>

        </div>

    </div>

</section>



<!-- ======================================================
     HOW WE CAN HELP
====================================================== -->

<section class="zcs-ac-process">

    <div class="zcs-ac-container">

        <div class="zcs-ac-process-layout">


            <div class="zcs-ac-process-heading">

                <span class="zcs-ac-section-label">
                    NEED A SERVICE?
                </span>

                <h2>
                    Getting Help Is
                    <strong>Simple.</strong>
                </h2>

                <p>
                    Contact Zen Care and tell us what you need.
                    We'll help you connect with the appropriate
                    home service.
                </p>

            </div>


            <div class="zcs-ac-process-list">


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-phone-volume"></i>
                    </span>

                    <div>

                        <h3>
                            Contact Us
                        </h3>

                        <p>
                            Call, email or submit the enquiry
                            form with your requirement.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-list-check"></i>
                    </span>

                    <div>

                        <h3>
                            Tell Us What You Need
                        </h3>

                        <p>
                            Let us know the service you require
                            and provide the necessary details.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-regular fa-calendar-check"></i>
                    </span>

                    <div>

                        <h3>
                            Schedule Service
                        </h3>

                        <p>
                            Choose a convenient service
                            date and time.
                        </p>

                    </div>

                </div>


                <div class="zcs-ac-process-item">

                    <span>
                        <i class="fa-solid fa-house-circle-check"></i>
                    </span>

                    <div>

                        <h3>
                            Get Doorstep Assistance
                        </h3>

                        <p>
                            Get professional assistance
                            for your selected home service.
                        </p>

                    </div>

                </div>


            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     LOCATION SECTION
====================================================== -->

<section class="zcs-ac-intro">

    <div class="zcs-ac-container">

        <div class="zcs-ac-intro-grid">


            <div class="zcs-ac-intro-content">

                <span class="zcs-ac-section-label">
                    OUR LOCATION
                </span>

                <h2>
                    Find
                    <strong>Zen Care.</strong>
                </h2>

                <?php if (site_setting('address') === SITE_SETTING_DEFS['address'][3]): ?>
                <p>
                    Visit us at our location near the RTO Office
                    in YSR Nagar, Andhra Pradesh.
                </p>


                <div class="zcs-ac-intro-points">

                    <div>
                        <i class="fa-solid fa-location-dot"></i>
                        Near RTO Office
                    </div>

                    <div>
                        <i class="fa-solid fa-map-location-dot"></i>
                        YSR Nagar
                    </div>

                    <div>
                        <i class="fa-solid fa-location-arrow"></i>
                        Andhra Pradesh
                    </div>

                    <div>
                        <i class="fa-solid fa-phone"></i>
                        <?= site_e(site_setting('phone')) ?>
                    </div>

                </div>
                <?php else: ?>
                <p>Visit us at our location:</p>

                <div class="zcs-ac-intro-points">
                    <?php foreach (site_address_lines() as $addressLine): ?>
                    <div>
                        <i class="fa-solid fa-location-dot"></i>
                        <?= site_e(rtrim($addressLine, ', ')) ?>
                    </div>
                    <?php endforeach; ?>
                    <div>
                        <i class="fa-solid fa-phone"></i>
                        <?= site_e(site_setting('phone')) ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>



            <div class="zcs-ac-intro-image">

                <?php if (site_setting('map_query') !== ''): ?>
                <iframe
                    class="zcs-contact-map"
                    src="https://maps.google.com/maps?q=<?= site_e(rawurlencode(site_setting('map_query'))) ?>&amp;z=13&amp;output=embed"
                    title="Zen Care location map - <?= site_e(site_setting('map_query')) ?>"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
                <?php else: ?>
                <img src="<?= site_e(site_setting('contact_image')) ?>" alt="Zen Care location">
                <?php endif; ?>

                <div class="zcs-ac-image-badge">

                    <span>
                        <i class="fa-solid fa-location-dot"></i>
                    </span>

                    <div>

                        <strong>
                            Zen Care
                        </strong>

                        <small>
                            <?= site_e(str_replace(', ', ' • ', site_setting('map_query') ?: 'YSR Nagar, Andhra Pradesh')) ?>
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
                    WE'RE READY TO HELP
                </span>

                <h2>
                    Need a Home Service?
                    Contact Zen Care Today.
                </h2>

                <p>
                    Get in touch for AC repair, refrigerator service,
                    home cleaning, carpenter services and more.
                </p>

            </div>


            <div class="zcs-ac-cta-actions">


                <a href="<?= site_e(site_tel('phone')) ?>"
                   class="zcs-ac-cta-main">

                    <i class="fa-solid fa-phone"></i>

                    Call Now

                </a>


                <a href="mailto:<?= site_e(site_setting('email')) ?>"
                   class="zcs-ac-cta-call">

                    <i class="fa-regular fa-envelope"></i>

                    Email Us

                </a>

            </div>


        </div>

    </div>

</section>

<?php include('footer.php'); ?>