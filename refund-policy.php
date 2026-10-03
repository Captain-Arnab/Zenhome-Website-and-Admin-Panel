<?php
// Published text from Admin > CMS Pages replaces the built-in text below.
require_once __DIR__ . '/api/site_content.php';
$cms = site_page_content('refund-policy');
$siteMeta = $cms ? ['title' => $cms['meta_title'] ?: $cms['title'] . ' | Zen Care Services', 'description' => $cms['meta_description']] : [];
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
                ZEN CARE SERVICES
            </span>

            <h1>
                Cancellation & Refund Policy
            </h1>

            <p>
                Clear and transparent information about service cancellations,
                refunds and rescheduling with Zen Care Services.
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
                    Cancellation & Refund Policy
                </strong>

            </div>

        </div>

    </div>

</section>


<!-- ======================================================
     CANCELLATION & REFUND POLICY
====================================================== -->

<section class="zcs-terms-section">

    <div class="zcs-ac-container">


        <!-- INTRO -->

        <?php if ($cms): ?>

        <div class="zcs-terms-intro">
            <span class="zcs-ac-section-label">
                REFUND POLICY
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
                SERVICE POLICY
            </span>

            <h2>
                Cancellation &
                <strong>Refund Policy</strong>
            </h2>

            <p>
                Zen Care ("Company", "we", "us", or "our") strives to provide
                high-quality services to our customers. This Cancellation and
                Refund Policy outlines the terms governing service cancellations,
                refunds, and rescheduling.
            </p>

            <p>
                By booking a service with Zen Care, you agree to the terms
                set forth in this policy.
            </p>

        </div>


        <div class="zcs-terms-content">


            <!-- ======================================================
                 1. CANCELLATION POLICY
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    1. Cancellation Policy
                </h2>


                <h3>
                    1.1 Cancellation by Customers
                </h3>

                <ol>

                    <li>
                        Customers may cancel their service booking through
                        the Zen Care website, mobile app, or by contacting
                        customer support.
                    </li>

                    <li>
                        Cancellation requests must be made at least
                        6 hours before the scheduled service time to be
                        eligible for a full refund.
                    </li>

                    <li>
                        Cancellations made within 6 hours of the service
                        appointment will be subject to a cancellation fee
                        of 30% of the service cost.
                    </li>

                    <li>
                        Cancellations made after the service provider has
                        arrived will not be eligible for a refund.
                    </li>

                </ol>


                <h3>
                    1.2 Cancellation by Zen Care
                </h3>

                <ol>

                    <li>
                        Zen Care reserves the right to cancel bookings due to:

                        <ul>

                            <li>
                                Unavailability of service providers.
                            </li>

                            <li>
                                Unforeseen circumstances
                                (e.g., extreme weather, technical failures).
                            </li>

                            <li>
                                Fraudulent or suspicious transactions.
                            </li>

                        </ul>

                    </li>

                    <li>
                        In such cases, customers will receive a full refund
                        or the option to reschedule the service.
                    </li>

                </ol>

            </div>



            <!-- ======================================================
                 2. REFUND POLICY
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    2. Refund Policy
                </h2>


                <h3>
                    2.1 Eligibility for Refunds
                </h3>

                <p>
                    Refunds will be issued under the following conditions:
                </p>

                <ol>

                    <li>
                        Service was canceled by Zen Care due to provider
                        unavailability.
                    </li>

                    <li>
                        Service was canceled by the customer at least
                        6 hours before the scheduled time.
                    </li>

                    <li>
                        The service provider did not show up or failed
                        to complete the service.
                    </li>

                    <li>
                        The service provided was not as described
                        (subject to investigation and customer complaint
                        resolution).
                    </li>

                </ol>


                <h3>
                    2.2 Non-Refundable Cases
                </h3>

                <p>
                    Refunds will not be issued in the following cases:
                </p>

                <ol>

                    <li>
                        Customer cancels the service within 6 hours
                        of the scheduled time.
                    </li>

                    <li>
                        Service was completed but did not meet customer
                        expectations. Partial compensation may be offered
                        after review.
                    </li>

                    <li>
                        Service provider was denied entry to the premises
                        by the customer.
                    </li>

                    <li>
                        Customer provided incorrect details leading to
                        incomplete or unsatisfactory service.
                    </li>

                </ol>


                <h3>
                    2.3 Refund Processing Time
                </h3>

                <p>
                    Approved refunds will be processed through the applicable
                    payment method. Processing time may depend on the payment
                    provider, bank, or transaction method used for the booking.
                </p>

            </div>



            <!-- ======================================================
                 3. RESCHEDULING POLICY
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    3. Rescheduling Policy
                </h2>


                <h3>
                    3.1 Rescheduling by Customers
                </h3>

                <ol>

                    <li>
                        Customers can request to reschedule their booking
                        at least 6 hours before the scheduled time.
                    </li>

                    <li>
                        Rescheduling requests within 6 hours may incur a
                        rescheduling fee of 10% of the service cost.
                    </li>

                    <li>
                        Rescheduling is subject to service provider
                        availability.
                    </li>

                </ol>

            </div>



            <!-- ======================================================
                 5. CONTACT INFORMATION
            ====================================================== -->

            <div class="zcs-terms-block">

                <h2>
                    5. Contact Information
                </h2>

                <p>
                    For cancellation, refund, or rescheduling requests,
                    please contact us:
                </p>


                <div class="zcs-terms-contact">

                    <p>
                        <strong>
                            Email:
                        </strong>

                        <a href="mailto:<?= site_e(site_setting('email')) ?>">
                            <?= site_e(site_setting('email')) ?>
                        </a>
                    </p>


                    <p>
                        <strong>
                            Customer Support:
                        </strong>

                        <a href="<?= site_e(site_tel('phone')) ?>">
                            <?= site_e(site_setting('phone')) ?>
                        </a>
                    </p>

                </div>


                <p>
                    By booking a service with Zen Care, you acknowledge
                    that you have read, understood, and agreed to this
                    Cancellation and Refund Policy.
                </p>

            </div>


        </div>
        <?php endif; ?>

    </div>

</section>



<!-- ======================================================
     POLICY SUPPORT CTA
====================================================== -->

<section class="zcs-ac-cta">

    <div class="zcs-ac-container">

        <div class="zcs-ac-cta-box">


            <div>

                <span>
                    NEED ASSISTANCE?
                </span>

                <h2>
                    Need Help With a Cancellation,
                    Refund or Rescheduling?
                </h2>

                <p>
                    Contact the Zen Care support team for assistance
                    with your service booking, cancellation, refund,
                    or rescheduling request.
                </p>

            </div>


            <div class="zcs-ac-cta-actions">

                <a href="contact.php"
                   class="zcs-ac-cta-main">

                    Contact Support

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