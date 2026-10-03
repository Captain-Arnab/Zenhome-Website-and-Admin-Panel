<?php
include 'header.php';
?>


<!-- ======================================================
     CART PAGE BANNER
====================================================== -->

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
                Your Service Cart
            </h1>

            <p>
                Review your selected Zen Home Experts home services
                before proceeding to checkout and booking.
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
                    Cart
                </strong>

            </div>

        </div>

    </div>

</section>


<!-- ======================================================
     CART MAIN SECTION
====================================================== -->

<section class="zcs-cart-section">

    <div class="zcs-cart-container">


        <!-- CART PAGE HEADING -->

        <div class="zcs-cart-page-heading">

            <div>

                <span>
                    YOUR SELECTED SERVICES
                </span>

                <h2>
                    Review Your
                    <strong>Service Cart.</strong>
                </h2>

            </div>


            <p id="zcsCartCountText">
                Your selected services will appear below.
            </p>

        </div>



        <div class="zcs-cart-layout">


            <!-- ==================================================
                 LEFT - CART ITEMS
            =================================================== -->

            <div class="zcs-cart-left">


                <!-- EMPTY CART -->

                <div
                    class="zcs-cart-empty"
                    id="zcsCartEmpty"
                    style="display:none;"
                >

                    <div class="zcs-cart-empty-icon">

                        <i class="fa-solid fa-cart-shopping"></i>

                    </div>


                    <h3>
                        Your Cart is Empty
                    </h3>

                    <p>
                        You haven't added any Zen Home Experts services yet.
                        Browse our services and add the ones you need.
                    </p>


                    <a
                        href="index.php"
                        class="zcs-cart-browse-btn"
                    >

                        Browse Services

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>



                <!-- ==================================================
                     CART ITEMS WILL BE INSERTED HERE BY JS
                =================================================== -->

                <div
                    class="zcs-cart-items"
                    id="zcsCartItems"
                >


                    <!-- Cart items are rendered here by zen-pages.js -->


                </div>



                <!-- CART BOTTOM ACTION -->

                <div class="zcs-cart-left-actions">


                    <a
                        href="index.php"
                        class="zcs-cart-continue"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        Continue Browsing Services

                    </a>


                    <button
                        type="button"
                        class="zcs-cart-clear-btn"
                        id="zcsClearCart"
                    >

                        <i class="fa-solid fa-trash-can"></i>

                        Clear Cart

                    </button>


                </div>


            </div>



            <!-- ==================================================
                 RIGHT - ORDER SUMMARY
            =================================================== -->

            <aside class="zcs-cart-summary">


                <div class="zcs-cart-summary-card">


                    <div class="zcs-cart-summary-heading">

                        <span>
                            BOOKING SUMMARY
                        </span>

                        <h3>
                            Order Summary
                        </h3>

                        <p>
                            Review your service total before checkout.
                        </p>

                    </div>



                    <div class="zcs-cart-summary-list">


                        <div class="zcs-cart-summary-row">

                            <span>
                                Service Subtotal
                            </span>

                            <strong id="zcsCartSubtotal">
                                ₹0
                            </strong>

                        </div>


                        <div class="zcs-cart-summary-row">

                            <span>
                                Visiting Charges
                            </span>

                            <strong class="zcs-cart-free">
                                Included
                            </strong>

                        </div>


                        <div class="zcs-cart-summary-row">

                            <span>
                                Taxes
                            </span>

                            <strong>
                                Calculated at checkout
                            </strong>

                        </div>


                    </div>



                    <div class="zcs-cart-summary-total">

                        <div>

                            <span>
                                Total
                            </span>

                            <small>
                                Service total
                            </small>

                        </div>


                        <strong id="zcsCartTotal">
                            ₹0
                        </strong>

                    </div>



                    <a
                        href="checkout.php"
                        class="zcs-cart-checkout-btn"
                        id="zcsCartCheckout"
                    >

                        Proceed to Checkout

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>



                    <div class="zcs-cart-secure">

                        <i class="fa-solid fa-shield-halved"></i>

                        <span>
                            Secure & convenient service booking
                        </span>

                    </div>


                </div>



                <!-- ==================================================
                     WHY BOOK WITH ZEN HOME EXPERTS
                =================================================== -->

                <div class="zcs-cart-trust-card">


                    <h3>
                        Why Book with Zen Home Experts?
                    </h3>


                    <div class="zcs-cart-trust-item">

                        <span>

                            <i class="fa-solid fa-user-gear"></i>

                        </span>

                        <div>

                            <strong>
                                Skilled Professionals
                            </strong>

                            <small>
                                Reliable home service technicians.
                            </small>

                        </div>

                    </div>


                    <div class="zcs-cart-trust-item">

                        <span>

                            <i class="fa-solid fa-calendar-check"></i>

                        </span>

                        <div>

                            <strong>
                                Convenient Booking
                            </strong>

                            <small>
                                Choose your preferred service time.
                            </small>

                        </div>

                    </div>


                    <div class="zcs-cart-trust-item">

                        <span>

                            <i class="fa-solid fa-house"></i>

                        </span>

                        <div>

                            <strong>
                                Doorstep Service
                            </strong>

                            <small>
                                Professional assistance at your home.
                            </small>

                        </div>

                    </div>


                    <div class="zcs-cart-trust-item">

                        <span>

                            <i class="fa-solid fa-headset"></i>

                        </span>

                        <div>

                            <strong>
                                Customer Support
                            </strong>

                            <small>
                                Assistance whenever you need it.
                            </small>

                        </div>

                    </div>


                </div>


            </aside>


        </div>


    </div>

</section>



<!-- ======================================================
     CART SUPPORT CTA
====================================================== -->

<section class="zcs-cart-support">

    <div class="zcs-cart-container">

        <div class="zcs-cart-support-box">


            <div>

                <span>
                    NEED HELP WITH YOUR BOOKING?
                </span>

                <h2>
                    We're Here to Help.
                </h2>

                <p>
                    Contact Zen Home Experts for help choosing
                    a service or completing your booking.
                </p>

            </div>


            <div class="zcs-cart-support-actions">


                <a
                    href="<?= site_e(site_tel('phone')) ?>"
                    class="zcs-cart-support-call"
                >

                    <i class="fa-solid fa-phone"></i>

                    <?= site_e(site_phone_display(site_setting('phone'))) ?>

                </a>


                <a
                    href="contact.php"
                    class="zcs-cart-support-contact"
                >

                    Contact Support

                    <i class="fa-solid fa-arrow-right"></i>

                </a>


            </div>


        </div>

    </div>

</section>



<?php
include 'footer.php';
?>