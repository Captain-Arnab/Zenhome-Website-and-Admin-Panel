<?php
include 'header.php';
?>


<!-- ======================================================
     PAGE BANNER - SAME AS SERVICE PAGES
====================================================== -->

<section class="zcs-ac-banner">

    <div class="zcs-ac-banner-overlay"></div>

    <div class="zcs-ac-banner-container">

        <div class="zcs-ac-banner-content">

            <span class="zcs-ac-banner-label">
                ZEN HOME EXPERTS HOME SERVICES
            </span>

            <h1>
                Checkout
            </h1>

            <p>
                Complete your details, choose your preferred service
                schedule and confirm your Zen Home Experts booking.
            </p>


            <div class="zcs-ac-breadcrumb">

                <a href="index.php">

                    <i class="fa-solid fa-house"></i>

                    Home

                </a>

                <span>
                    <i class="fa-solid fa-chevron-right"></i>
                </span>

                <a href="cart.php">
                    Cart
                </a>

                <span>
                    <i class="fa-solid fa-chevron-right"></i>
                </span>

                <strong>
                    Checkout
                </strong>

            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     CHECKOUT
====================================================== -->

<section class="zcs-checkout-section">

    <div class="zcs-checkout-container">


        <!-- HEADING -->

        <div class="zcs-checkout-heading">

            <span>
                COMPLETE YOUR BOOKING
            </span>

            <h2>
                Just a Few Details
                <strong>Before We Arrive.</strong>
            </h2>

            <p>
                Enter your service address and choose your preferred
                appointment date and time.
            </p>

        </div>



        <form
            action="#"
            method="POST"
            id="zcsCheckoutForm"
        >

            <div class="zcs-checkout-layout">


                <!-- ==================================================
                     LEFT SIDE
                =================================================== -->

                <div class="zcs-checkout-main">


                    <!-- ==============================================
                         CUSTOMER DETAILS
                    =============================================== -->

                    <div class="zcs-checkout-card">


                        <div class="zcs-checkout-card-title">

                            <div class="zcs-checkout-title-icon">

                                <i class="fa-regular fa-user"></i>

                            </div>

                            <div>

                                <span>
                                    CUSTOMER DETAILS
                                </span>

                                <h3>
                                    Your Information
                                </h3>

                                <p>
                                    Tell us who we're providing the service for.
                                </p>

                            </div>

                        </div>



                        <div class="zcs-checkout-form-grid">


                            <!-- FULL NAME -->

                            <div class="zcs-checkout-field">

                                <label for="checkout_name">
                                    Full Name <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-regular fa-user"></i>

                                    <input
                                        type="text"
                                        id="checkout_name"
                                        name="name"
                                        placeholder="Enter your full name"
                                        required
                                    >

                                </div>

                            </div>



                            <!-- MOBILE -->

                            <div class="zcs-checkout-field">

                                <label for="checkout_phone">
                                    Mobile Number <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-solid fa-mobile-screen-button"></i>

                                    <input
                                        type="tel"
                                        id="checkout_phone"
                                        name="phone"
                                        placeholder="Enter mobile number"
                                        maxlength="10"
                                        required
                                    >

                                </div>

                            </div>



                            <!-- EMAIL -->

                            <div class="zcs-checkout-field zcs-checkout-full">

                                <label for="checkout_email">
                                    Email Address
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-regular fa-envelope"></i>

                                    <input
                                        type="email"
                                        id="checkout_email"
                                        name="email"
                                        placeholder="Enter your email address"
                                    >

                                </div>

                            </div>


                        </div>

                    </div>



                    <!-- ==============================================
                         SERVICE ADDRESS
                    =============================================== -->

                    <div class="zcs-checkout-card">


                        <div class="zcs-checkout-card-title">

                            <div class="zcs-checkout-title-icon">

                                <i class="fa-solid fa-location-dot"></i>

                            </div>

                            <div>

                                <span>
                                    SERVICE LOCATION
                                </span>

                                <h3>
                                    Service Address
                                </h3>

                                <p>
                                    Where should our professional visit?
                                </p>

                            </div>

                        </div>



                        <div class="zcs-checkout-form-grid">


                            <!-- ADDRESS -->

                            <div class="zcs-checkout-field zcs-checkout-full">

                                <label for="checkout_address">
                                    House / Flat / Address <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-solid fa-house"></i>

                                    <input
                                        type="text"
                                        id="checkout_address"
                                        name="address"
                                        placeholder="House no., flat no., building name"
                                        required
                                    >

                                </div>

                            </div>



                            <!-- AREA -->

                            <div class="zcs-checkout-field zcs-checkout-full">

                                <label for="checkout_area">
                                    Area / Locality <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-solid fa-location-crosshairs"></i>

                                    <input
                                        type="text"
                                        id="checkout_area"
                                        name="area"
                                        placeholder="Enter area or locality"
                                        required
                                    >

                                </div>

                            </div>



                            <!-- CITY -->

                            <div class="zcs-checkout-field">

                                <label for="checkout_city">
                                    City <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-solid fa-city"></i>

                                    <input
                                        type="text"
                                        id="checkout_city"
                                        name="city"
                                        placeholder="Enter city"
                                        required
                                    >

                                </div>

                            </div>



                            <!-- PINCODE -->

                            <div class="zcs-checkout-field">

                                <label for="checkout_pincode">
                                    Pincode <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-solid fa-map-pin"></i>

                                    <input
                                        type="text"
                                        id="checkout_pincode"
                                        name="pincode"
                                        placeholder="Enter pincode"
                                        maxlength="6"
                                        required
                                    >

                                </div>

                            </div>



                            <!-- LANDMARK -->

                            <div class="zcs-checkout-field zcs-checkout-full">

                                <label for="checkout_landmark">
                                    Landmark
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-solid fa-location-arrow"></i>

                                    <input
                                        type="text"
                                        id="checkout_landmark"
                                        name="landmark"
                                        placeholder="Nearby landmark (optional)"
                                    >

                                </div>

                            </div>


                        </div>

                    </div>



                    <!-- ==============================================
                         SERVICE SCHEDULE
                    =============================================== -->

                    <div class="zcs-checkout-card">


                        <div class="zcs-checkout-card-title">

                            <div class="zcs-checkout-title-icon">

                                <i class="fa-regular fa-calendar-check"></i>

                            </div>

                            <div>

                                <span>
                                    APPOINTMENT
                                </span>

                                <h3>
                                    Choose Service Schedule
                                </h3>

                                <p>
                                    Select your preferred service date and time.
                                </p>

                            </div>

                        </div>



                        <div class="zcs-checkout-form-grid">


                            <!-- DATE -->

                            <div class="zcs-checkout-field">

                                <label for="checkout_date">
                                    Preferred Date <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-regular fa-calendar"></i>

                                    <input
                                        type="date"
                                        id="checkout_date"
                                        name="service_date"
                                        required
                                    >

                                </div>

                            </div>



                            <!-- TIME -->

                            <div class="zcs-checkout-field">

                                <label for="checkout_time">
                                    Preferred Time <em>*</em>
                                </label>

                                <div class="zcs-checkout-input">

                                    <i class="fa-regular fa-clock"></i>

                                    <select
                                        id="checkout_time"
                                        name="service_time"
                                        required
                                    >

                                        <option value="">
                                            Select Time
                                        </option>

                                        <option value="09:00 AM - 11:00 AM">
                                            09:00 AM - 11:00 AM
                                        </option>

                                        <option value="11:00 AM - 01:00 PM">
                                            11:00 AM - 01:00 PM
                                        </option>

                                        <option value="01:00 PM - 03:00 PM">
                                            01:00 PM - 03:00 PM
                                        </option>

                                        <option value="03:00 PM - 05:00 PM">
                                            03:00 PM - 05:00 PM
                                        </option>

                                        <option value="05:00 PM - 07:00 PM">
                                            05:00 PM - 07:00 PM
                                        </option>

                                    </select>

                                </div>

                            </div>


                        </div>

                    </div>



                    <!-- ==============================================
                         ADDITIONAL INFORMATION
                    =============================================== -->

                    <div class="zcs-checkout-card">


                        <div class="zcs-checkout-card-title">

                            <div class="zcs-checkout-title-icon">

                                <i class="fa-regular fa-message"></i>

                            </div>

                            <div>

                                <span>
                                    ADDITIONAL DETAILS
                                </span>

                                <h3>
                                    Service Instructions
                                </h3>

                                <p>
                                    Tell us anything our technician should know.
                                </p>

                            </div>

                        </div>


                        <div class="zcs-checkout-field">

                            <label for="checkout_notes">
                                Notes / Instructions
                            </label>

                            <textarea
                                id="checkout_notes"
                                name="notes"
                                rows="5"
                                placeholder="Example: AC is not cooling properly, please call before arriving..."
                            ></textarea>

                        </div>


                    </div>


                </div>



                <!-- ==================================================
                     RIGHT SIDE
                =================================================== -->

                <aside class="zcs-checkout-sidebar">


                    <!-- ORDER SUMMARY -->

                    <div class="zcs-checkout-summary">


                        <div class="zcs-checkout-summary-head">

                            <span>
                                YOUR BOOKING
                            </span>

                            <h3>
                                Service Summary
                            </h3>

                            <p>
                                Review your selected services.
                            </p>

                        </div>



                        <!-- ==========================================
                             CART ITEMS INSERTED BY JS
                        =========================================== -->

                        <div
                            class="zcs-checkout-services"
                            id="zcsCheckoutItems"
                        >


                            <!-- Cart items are rendered here by zen-pages.js -->


                        </div>



                        <!-- COUPON (checked by api/validate_coupon.php, applied by book_appointment.php) -->

                        <div class="zcs-checkout-coupon" id="zcsCouponBox">

                            <label for="zcsCouponCode">
                                <i class="fa-solid fa-tag"></i>
                                Have a coupon?
                            </label>

                            <div class="zcs-checkout-coupon-row">

                                <input
                                    type="text"
                                    id="zcsCouponCode"
                                    maxlength="20"
                                    autocomplete="off"
                                    placeholder="Enter coupon code"
                                >

                                <button type="button" id="zcsCouponApply">
                                    Apply
                                </button>

                            </div>

                            <div class="zcs-checkout-coupon-msg" id="zcsCouponMsg" aria-live="polite"></div>

                        </div>



                        <!-- TOTALS -->

                        <div class="zcs-checkout-price-list">


                            <div>

                                <span>
                                    Service Subtotal
                                </span>

                                <strong id="zcsCheckoutSubtotal">
                                    ₹0
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Visiting Charges
                                </span>

                                <strong class="zcs-checkout-green">
                                    Included
                                </strong>

                            </div>


                            <div id="zcsCheckoutDiscountRow" hidden>

                                <span id="zcsCheckoutDiscountLabel">
                                    Coupon Discount
                                </span>

                                <strong class="zcs-checkout-green" id="zcsCheckoutDiscount">
                                    -₹0
                                </strong>

                            </div>


                        </div>



                        <div class="zcs-checkout-total">

                            <div>

                                <span>
                                    Total Amount
                                </span>

                                <small>
                                    Service booking total
                                </small>

                            </div>


                            <strong id="zcsCheckoutTotal">
                                ₹0
                            </strong>

                        </div>



                        <!-- PAYMENT METHOD -->

                        <label class="zcs-checkout-terms">

                            <input
                                type="radio"
                                name="payment_method"
                                value="online"
                                checked
                            >

                            <span>
                                <strong>Pay Online</strong> –
                                UPI, cards & netbanking via PhonePe
                            </span>

                        </label>


                        <label class="zcs-checkout-terms">

                            <input
                                type="radio"
                                name="payment_method"
                                value="after_service"
                            >

                            <span>
                                <strong>Pay After Service</strong> –
                                pay the technician once the job is done
                            </span>

                        </label>



                        <!-- MESSAGES (filled by zen-pages.js) -->

                        <div id="zcsCheckoutAlert"></div>



                        <!-- HIDDEN CART DATA -->

                        <input
                            type="hidden"
                            name="cart_data"
                            id="zcsCheckoutCartData"
                        >



                        <!-- TERMS -->

                        <label class="zcs-checkout-terms">

                            <input
                                type="checkbox"
                                name="terms"
                                value="1"
                                required
                            >

                            <span>
                                I agree to the
                                <a href="terms.php">
                                    Terms & Conditions
                                </a>
                                and service booking policy.
                            </span>

                        </label>



                        <!-- PLACE ORDER -->

                        <button
                            type="submit"
                            class="zcs-checkout-submit"
                            id="zcsCheckoutSubmit"
                        >

                            Confirm Booking

                            <i class="fa-solid fa-arrow-right"></i>

                        </button>



                        <div class="zcs-checkout-secure">

                            <i class="fa-solid fa-shield-halved"></i>

                            <span>
                                Safe & secure service booking
                            </span>

                        </div>


                    </div>



                    <!-- SUPPORT -->

                    <div class="zcs-checkout-support">

                        <div class="zcs-checkout-support-icon">

                            <i class="fa-solid fa-headset"></i>

                        </div>


                        <div>

                            <span>
                                NEED HELP?
                            </span>

                            <h4>
                                Booking Assistance
                            </h4>

                            <p>
                                Our support team can help you
                                complete your booking.
                            </p>


                            <a href="<?= site_e(site_tel('phone')) ?>">

                                <i class="fa-solid fa-phone"></i>

                                <?= site_e(site_phone_display(site_setting('phone'))) ?>

                            </a>

                        </div>

                    </div>


                </aside>


            </div>

        </form>


    </div>

</section>



<!-- ======================================================
     TRUST SECTION
====================================================== -->

<section class="zcs-checkout-trust">

    <div class="zcs-checkout-container">

        <div class="zcs-checkout-trust-grid">


            <div class="zcs-checkout-trust-item">

                <span>
                    <i class="fa-solid fa-user-gear"></i>
                </span>

                <div>

                    <h3>
                        Skilled Professionals
                    </h3>

                    <p>
                        Reliable technicians for your home services.
                    </p>

                </div>

            </div>



            <div class="zcs-checkout-trust-item">

                <span>
                    <i class="fa-regular fa-calendar-check"></i>
                </span>

                <div>

                    <h3>
                        Convenient Scheduling
                    </h3>

                    <p>
                        Choose a suitable date and service time.
                    </p>

                </div>

            </div>



            <div class="zcs-checkout-trust-item">

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                <div>

                    <h3>
                        Doorstep Service
                    </h3>

                    <p>
                        Professional service delivered at your home.
                    </p>

                </div>

            </div>



            <div class="zcs-checkout-trust-item">

                <span>
                    <i class="fa-solid fa-headset"></i>
                </span>

                <div>

                    <h3>
                        Customer Support
                    </h3>

                    <p>
                        Assistance throughout your service booking.
                    </p>

                </div>

            </div>


        </div>

    </div>

</section>


<?php
include 'footer.php';
?>