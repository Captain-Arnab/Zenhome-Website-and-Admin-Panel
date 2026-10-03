<?php
$siteMeta = ['title' => 'My Bookings | Zen Home Experts'];
include 'header.php';
?>


<!-- ======================================================
     PAGE BANNER
====================================================== -->

<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN HOME EXPERTS HOME SERVICES
            </span>

            <h1>
                My Bookings
            </h1>

            <p>
                Track your Zen Home Experts service bookings, technician
                details and payment status.
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
                    My Bookings
                </strong>

            </div>

        </div>

    </div>

</section>


<!-- ======================================================
     BOOKINGS
====================================================== -->

<section class="zcs-cart-section">

    <div class="zcs-cart-container">

        <div class="zcs-cart-page-heading">

            <div>

                <span>
                    YOUR ACCOUNT
                </span>

                <h2>
                    Your Service
                    <strong>Bookings.</strong>
                </h2>

            </div>

            <div class="zcs-bookings-filter" id="zcsBookingsFilter" role="tablist">
                <button type="button" class="is-active" data-status="">All</button>
                <button type="button" data-status="active">Upcoming</button>
                <button type="button" data-status="past">Past</button>
            </div>

        </div>


        <div id="zcsBookingsAlert"></div>


        <!-- Bookings are rendered here by zen-pages.js -->

        <div class="zcs-bookings-list" id="zcsBookingsList" aria-live="polite">

            <p class="zcs-bookings-loading">
                <i class="fa-solid fa-spinner fa-spin"></i>
                Loading your bookings...
            </p>

        </div>


        <div class="zcs-cart-empty" id="zcsBookingsEmpty" hidden>

            <div class="zcs-cart-empty-icon">
                <i class="fa-solid fa-calendar-xmark"></i>
            </div>

            <h3>
                No Bookings Yet
            </h3>

            <p>
                Your service bookings will appear here once you book.
            </p>

            <a href="services.php" class="zcs-cart-browse-btn">
                Browse Services
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>


        <div class="zcs-bookings-more">
            <button type="button" class="zcs-cart-browse-btn" id="zcsBookingsMore" hidden>
                Show More
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>

    </div>

</section>


<?php
include 'footer.php';
?>
