<?php
// Published text from Admin > CMS Pages replaces the built-in text below.
require_once __DIR__ . '/api/site_content.php';
$cms = site_page_content('privacy-policy');
$siteMeta = $cms ? ['title' => $cms['meta_title'] ?: $cms['title'] . ' | Zen Home Experts', 'description' => $cms['meta_description']] : [];
include('header.php');
?>


<!-- ======================================================
     PAGE BANNER
====================================================== -->

<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN HOME EXPERTS
            </span>

            <h1>
                Privacy Policy
            </h1>

            <p>
                Learn how Zen Home Experts collects, uses and safeguards your
                personal information when you use our platform and services.
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
                    Privacy Policy
                </strong>

            </div>

        </div>

    </div>

</section>


<!-- ======================================================
     PRIVACY POLICY
====================================================== -->

<section class="zcs-terms-section">

    <div class="zcs-ac-container">


        <!-- INTRO -->

        <?php if ($cms): ?>

        <div class="zcs-terms-intro">
            <span class="zcs-ac-section-label">
                PRIVACY POLICY
            </span>
            <h2><?= site_e($cms['title']) ?></h2>
            <?php if ($cms['updated']): ?>
                <p>Last updated: <?= site_e(date('d M Y', strtotime($cms['updated']))) ?></p>
            <?php endif; ?>
        </div>

        <div class="zcs-terms-content zcs-cms-content">
            <div class="zcs-terms-block">
                <?= $cms['content'] ?>
            </div>
        </div>

        <?php else: ?>

        <div class="zcs-terms-intro">

            <span class="zcs-ac-section-label">
                YOUR PRIVACY MATTERS
            </span>

            <h2>
                Privacy
                <strong>Policy</strong>
            </h2>

            <p>
                Zen Home Experts ("Company", "we", "us", or "our") values your
                privacy and is committed to protecting your personal data.
                This Privacy Policy outlines how we collect, use, disclose,
                and safeguard your information when you use our website,
                mobile application, and services.
            </p>

            <p>
                By accessing or using Zen Home Experts services, you agree to this
                Privacy Policy. If you do not agree, please discontinue
                using our platform.
            </p>

        </div>


        <div class="zcs-terms-content">


            <!-- ======================================================
                 1. INFORMATION WE COLLECT
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    1. Information We Collect
                </h2>


                <h3>
                    1.1 Personal Information
                </h3>

                <ol>

                    <li>
                        <strong>Identity Information:</strong>
                        Name, age, gender, profile picture, and
                        government-issued ID (if required for verification).
                    </li>

                    <li>
                        <strong>Contact Information:</strong>
                        Email address, phone number, and mailing address.
                    </li>

                    <li>
                        <strong>Payment Information:</strong>
                        UPI ID, bank account details, and debit/credit
                        card information (processed via secure gateways).
                    </li>

                </ol>


                <h3>
                    1.2 Non-Personal Information
                </h3>

                <ol>

                    <li>
                        <strong>Device Data:</strong>
                        IP address, browser type, operating system,
                        and device information.
                    </li>

                    <li>
                        <strong>Usage Data:</strong>
                        Browsing activity, pages visited, service preferences,
                        and interactions with the platform.
                    </li>

                    <li>
                        <strong>Location Data:</strong>
                        GPS or IP-based location tracking for service delivery
                        (only with user consent).
                    </li>

                </ol>

            </div>



            <!-- ======================================================
                 2. HOW WE COLLECT INFORMATION
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    2. How We Collect Information
                </h2>

                <ol>

                    <li>
                        <strong>User Input:</strong>
                        When you create an account, book services,
                        or contact customer support.
                    </li>

                    <li>
                        <strong>Automated Technologies:</strong>
                        Cookies, tracking tools, and analytics software.
                    </li>

                    <li>
                        <strong>Third-Party Sources:</strong>
                        Social media logins, referral programs,
                        or publicly available data.
                    </li>

                </ol>

            </div>



            <!-- ======================================================
                 3. HOW WE USE YOUR INFORMATION
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    3. How We Use Your Information
                </h2>

                <ol>

                    <li>
                        <strong>To Provide Services:</strong>
                        Processing bookings, connecting you with service
                        providers, and facilitating customer support.
                    </li>

                    <li>
                        <strong>To Process Payments:</strong>
                        Secure transactions through encrypted
                        payment gateways.
                    </li>

                    <li>
                        <strong>To Improve User Experience:</strong>
                        Customizing service recommendations based
                        on preferences.
                    </li>

                    <li>
                        <strong>To Send Promotions & Updates:</strong>
                        Special offers, discounts, and service-related
                        notifications (opt-out available).
                    </li>

                    <li>
                        <strong>To Enhance Security & Fraud Prevention:</strong>
                        Monitoring suspicious activities and ensuring
                        compliance with legal regulations.
                    </li>

                    <li>
                        <strong>To Comply with Legal Obligations:</strong>
                        Adhering to the Information Technology Act, 2000
                        and other applicable Indian laws.
                    </li>

                </ol>

            </div>



            <!-- ======================================================
                 4. CONTACT INFORMATION
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    4. Contact Information
                </h2>

                <p>
                    For privacy concerns, complaints, or data requests,
                    please contact us:
                </p>


                <div class="zcs-terms-contact">

                    <p>
                        <strong>Email:</strong>

                        <a href="mailto:<?= site_e(site_setting('email')) ?>">
                            <?= site_e(site_setting('email')) ?>
                        </a>
                    </p>


                    <p>
                        <strong>Customer Support:</strong>

                        <a href="<?= site_e(site_tel('phone')) ?>">
                            <?= site_e(site_setting('phone')) ?>
                        </a>
                    </p>

                </div>


                <p>
                    By using Zen Home Experts, you acknowledge that you have read,
                    understood, and agreed to this Privacy Policy.
                </p>

            </div>


        </div>
        <?php endif; ?>

    </div>

</section>



<!-- ======================================================
     PRIVACY SUPPORT CTA
====================================================== -->

<section class="zcs-ac-cta">

    <div class="zcs-ac-container">

        <div class="zcs-ac-cta-box">


            <div>

                <span>
                    PRIVACY SUPPORT
                </span>

                <h2>
                    Have Questions About
                    Your Privacy?
                </h2>

                <p>
                    Contact Zen Home Experts for assistance with privacy
                    concerns, personal information, data requests or
                    account-related questions.
                </p>

            </div>


            <div class="zcs-ac-cta-actions">

                <a href="contact.php"
                   class="zcs-ac-cta-main">

                    Contact Us

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