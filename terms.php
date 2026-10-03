<?php
// Published text from Admin > CMS Pages replaces the built-in text below.
require_once __DIR__ . '/api/site_content.php';
$cms = site_page_content('terms');
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
                Terms & Conditions
            </h1>

            <p>
                Please read these Terms and Conditions carefully before
                accessing, booking or using Zen Home Experts.
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
                    Terms & Conditions
                </strong>

            </div>

        </div>

    </div>

</section>


<!-- ======================================================
     TERMS INTRO
====================================================== -->

<section class="zcs-terms-section">

    <div class="zcs-ac-container">

        <?php if ($cms): ?>

        <div class="zcs-terms-intro">
            <span class="zcs-ac-section-label">
                LEGAL INFORMATION
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
                LEGAL INFORMATION
            </span>

            <h2>
                Terms and
                <strong>Conditions</strong>
            </h2>

            <p>
                Welcome to Zen Home Experts! These Terms and Conditions (“Terms”)
                govern the access, use, and services offered by Zen Home Experts
                through its website, mobile application, and offline operations.
            </p>

            <p>
                By accessing, browsing, or using Zen Home Experts’s services,
                you (“User” or “Customer”) agree to comply with these Terms.
                If you do not agree to these Terms, you must discontinue
                the use of Zen Home Experts’s services immediately.
            </p>

        </div>


        <div class="zcs-terms-content">


            <!-- ======================================================
                 1. DEFINITIONS
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    1. Definitions
                </h2>

                <p>
                    For the purposes of this Agreement:
                </p>

                <ol>
                    <li>
                        “Zen Home Experts” refers to the brand and its parent company,
                        affiliates, and subsidiaries.
                    </li>

                    <li>
                        “User” or “Customer” refers to any individual or entity
                        accessing or availing services through Zen Home Experts.
                    </li>

                    <li>
                        “Service Provider” refers to third-party professionals
                        or businesses offering services through Zen Home Experts.
                    </li>

                    <li>
                        “Platform” refers to the Zen Home Experts website, mobile
                        application, and other digital mediums facilitating
                        service bookings.
                    </li>

                    <li>
                        “Services” include, but are not limited to, salon,
                        spa, AC repair, refrigerator repair, home cleaning,
                        pest control, carpentry, interior design, and water
                        purifier installation and maintenance.
                    </li>

                    <li>
                        “Agreement” refers to these Terms and Conditions and
                        any additional policies published by Zen Home Experts.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 2. ELIGIBILITY CRITERIA
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    2. Eligibility Criteria
                </h2>

                <ol>
                    <li>
                        Users must be at least 18 years of age to access
                        and avail of Zen Home Experts’s services.
                    </li>

                    <li>
                        Users must be legally competent under the Indian
                        Contract Act, 1872 to enter into a binding contract.
                    </li>

                    <li>
                        Zen Home Experts reserves the right to refuse service to any
                        individual or entity at its discretion.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 3. REGISTRATION & ACCOUNT MANAGEMENT
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    3. Registration & Account Management
                </h2>

                <h3>
                    3.1 Account Creation
                </h3>

                <ol>
                    <li>
                        Users may need to register and create an account
                        to book services.
                    </li>

                    <li>
                        Users must provide accurate, complete, and
                        up-to-date information during registration.
                    </li>

                    <li>
                        Zen Home Experts reserves the right to suspend or terminate
                        accounts with incorrect or fraudulent information.
                    </li>
                </ol>


                <h3>
                    3.2 User Responsibilities
                </h3>

                <ol>
                    <li>
                        Users must maintain the confidentiality of their
                        login credentials.
                    </li>

                    <li>
                        Users are responsible for all activities conducted
                        through their account.
                    </li>

                    <li>
                        Users must immediately notify Zen Home Experts in case of
                        unauthorized access or security breaches.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 4. SERVICE TERMS
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    4. Service Terms
                </h2>


                <h3>
                    4.1 Booking & Scheduling
                </h3>

                <ol>
                    <li>
                        Services must be booked through the Zen Home Experts
                        platform (website/app).
                    </li>

                    <li>
                        Service availability is subject to location,
                        service provider availability, and operational
                        feasibility.
                    </li>
                </ol>


                <h3>
                    4.2 Pricing & Payment
                </h3>

                <ol>
                    <li>
                        Service charges are displayed on the platform and
                        are inclusive of applicable GST under the CGST Act, 2017.
                    </li>

                    <li>
                        Payment options include:

                        <ul>
                            <li>
                                Online payment
                                (UPI, net banking, debit/credit cards, wallets).
                            </li>

                            <li>
                                Cash-on-delivery
                                (subject to availability).
                            </li>
                        </ul>

                    </li>

                    <li>
                        Zen Home Experts reserves the right to modify pricing at
                        any time without prior notice.
                    </li>
                </ol>


                <h3>
                    4.3 Invoicing & Taxes
                </h3>

                <ol>
                    <li>
                        Users receive invoices for all services booked
                        through the platform.
                    </li>

                    <li>
                        GST invoices are provided where applicable under
                        Indian taxation laws.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 5. CANCELLATIONS, REFUNDS & RESCHEDULING
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    5. Cancellations, Refunds & Rescheduling
                </h2>


                <h3>
                    5.1 Cancellation Policy
                </h3>

                <ol>
                    <li>
                        Cancellations made 24 hours before the scheduled
                        service will be eligible for a full refund.
                    </li>

                    <li>
                        Cancellations within 24 hours of service may be
                        subject to a cancellation fee.
                    </li>

                    <li>
                        In case of no-show by the service provider,
                        a full refund will be processed.
                    </li>
                </ol>


                <h3>
                    5.2 Refund Policy
                </h3>

                <ol>
                    <li>
                        Refunds, if applicable, will be processed within
                        7 business days via the original payment method.
                    </li>

                    <li>
                        Partial refunds may be issued in case of incomplete
                        service delivery.
                    </li>
                </ol>


                <h3>
                    5.3 Rescheduling Policy
                </h3>

                <ol>
                    <li>
                        Users can reschedule services subject to service
                        provider availability.
                    </li>

                    <li>
                        Last-minute rescheduling within 12 hours of service
                        may attract additional charges.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 6. USER RESPONSIBILITIES
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    6. User Responsibilities
                </h2>

                <ol>
                    <li>
                        Users must ensure safe and hygienic premises
                        for service execution.
                    </li>

                    <li>
                        Users shall not abuse, harass, or exploit service
                        providers in any manner.
                    </li>

                    <li>
                        Users shall not engage in fraudulent activities
                        such as multiple refunds or false complaints.
                    </li>

                    <li>
                        Any damages caused by the User’s negligence during
                        service execution shall be the User’s responsibility.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 7. SERVICE PROVIDER OBLIGATIONS
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    7. Service Provider Obligations
                </h2>

                <ol>
                    <li>
                        Service providers operate as independent contractors
                        and are not employees of Zen Home Experts.
                    </li>

                    <li>
                        They must adhere to professional ethics and comply with:

                        <ul>
                            <li>
                                The Shops and Establishments Act
                                (state-wise).
                            </li>

                            <li>
                                The Labour Laws
                                (Minimum Wages Act, 1948 & Employees’
                                Compensation Act, 1923).
                            </li>
                        </ul>

                    </li>

                    <li>
                        Service providers must use approved products and
                        equipment while delivering services.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 8. LIABILITY DISCLAIMER
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    8. Liability Disclaimer
                </h2>

                <ol>
                    <li>
                        Zen Home Experts acts as a facilitator and does not assume
                        direct responsibility for the quality of services
                        rendered by third-party service providers.
                    </li>

                    <li>
                        Zen Home Experts shall not be held liable for:

                        <ul>
                            <li>
                                Personal injuries due to service provider negligence.
                            </li>

                            <li>
                                Property damages caused by service providers
                                unless proven.
                            </li>

                            <li>
                                Delays or service failures due to unforeseen
                                circumstances (force majeure).
                            </li>
                        </ul>

                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 9. PRIVACY & DATA SECURITY
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    9. Privacy & Data Security
                </h2>

                <ol>
                    <li>
                        Zen Home Experts complies with the Information Technology
                        (Reasonable Security Practices and Procedures and
                        Sensitive Personal Data or Information) Rules, 2011.
                    </li>

                    <li>
                        Personal data collected is used solely for service
                        execution and not shared without consent.
                    </li>

                    <li>
                        Users can request data deletion as per the Personal
                        Data Protection Bill, 2019 (once enacted).
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 10. INTELLECTUAL PROPERTY RIGHTS
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    10. Intellectual Property Rights
                </h2>

                <ol>
                    <li>
                        All content, including trademarks, logos, text, and
                        images, is owned by Zen Home Experts and protected under:

                        <ul>
                            <li>
                                The Copyright Act, 1957
                            </li>

                            <li>
                                The Trademarks Act, 1999
                            </li>
                        </ul>

                    </li>

                    <li>
                        Users shall not copy, modify, or redistribute
                        Zen Home Experts’s intellectual property without prior
                        written consent.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 11. CONSUMER RIGHTS & DISPUTE RESOLUTION
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    11. Consumer Rights & Dispute Resolution
                </h2>

                <ol>
                    <li>
                        Users have the right to file complaints under the
                        Consumer Protection Act, 2019 for any unfair trade
                        practices or service deficiencies.
                    </li>

                    <li>
                        Disputes shall be resolved through negotiation and
                        mediation before initiating legal action.
                    </li>

                    <li>
                        If unresolved, disputes shall be subject to the
                        exclusive jurisdiction of courts in India.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 12. TERMINATION & ACCOUNT SUSPENSION
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    12. Termination & Account Suspension
                </h2>

                <ol>
                    <li>
                        Zen Home Experts reserves the right to terminate or suspend
                        user accounts for:

                        <ul>
                            <li>
                                Violation of these Terms.
                            </li>

                            <li>
                                Fraudulent or illegal activities.
                            </li>

                            <li>
                                Misuse of services or harassment of
                                service providers.
                            </li>
                        </ul>

                    </li>

                    <li>
                        Terminated accounts shall forfeit all credits,
                        balances, and bookings unless eligible under
                        the refund policy.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 13. MODIFICATIONS & UPDATES
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    13. Modifications & Updates
                </h2>

                <ol>
                    <li>
                        Zen Home Experts may revise these Terms at any time
                        without prior notice.
                    </li>

                    <li>
                        Continued use of services post-updates implies
                        acceptance of the revised Terms.
                    </li>
                </ol>

            </div>



            <!-- ======================================================
                 14. FORCE MAJEURE
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    14. Force Majeure
                </h2>

                <p>
                    Zen Home Experts shall not be liable for service delays or
                    failures due to unforeseen events including, but not
                    limited to, natural disasters, strikes, or government
                    restrictions.
                </p>

            </div>



            <!-- ======================================================
                 15. CONTACT INFORMATION
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    15. Contact Information
                </h2>

                <p>
                    For queries, complaints, or support, contact us:
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
                    By using Zen Home Experts’s services, you acknowledge and
                    agree to these Terms and Conditions.
                </p>

            </div>


        </div>
        <?php endif; ?>

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
                    NEED HELP?
                </span>

                <h2>
                    Have Questions About Our
                    Terms & Conditions?
                </h2>

                <p>
                    Contact Zen Home Experts for assistance regarding
                    bookings, payments, refunds, service policies or
                    account-related queries.
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