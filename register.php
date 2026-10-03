<?php
include 'header.php';
?>

<!-- ======================================================
     SIGN UP PAGE
====================================================== -->

<section class="zcs-signup-section">

    <div class="zcs-signup-container">

        <div class="zcs-signup-box">


            <!-- ==================================================
                 LEFT CONTENT
            =================================================== -->

            <div class="zcs-signup-info">

                <span class="zcs-signup-label">
                    ZEN HOME EXPERTS
                </span>

                <h1>
                    Create Your
                    <strong>Zen Home Experts Account.</strong>
                </h1>

                <p>
                    Register with Zen Home Experts to book trusted home
                    services, manage your appointments and keep track
                    of your service history from one convenient account.
                </p>


                <div class="zcs-signup-benefits">


                    <div class="zcs-signup-benefit">

                        <span>
                            <i class="fa-solid fa-calendar-check"></i>
                        </span>

                        <div>

                            <h3>
                                Easy Service Booking
                            </h3>

                            <p>
                                Book AC, appliance, cleaning and other
                                home services in just a few steps.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-signup-benefit">

                        <span>
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </span>

                        <div>

                            <h3>
                                Booking History
                            </h3>

                            <p>
                                Access your previous and upcoming
                                service appointments anytime.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-signup-benefit">

                        <span>
                            <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <div>

                            <h3>
                                Save Your Details
                            </h3>

                            <p>
                                Keep your contact and service information
                                ready for faster future bookings.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-signup-benefit">

                        <span>
                            <i class="fa-solid fa-headset"></i>
                        </span>

                        <div>

                            <h3>
                                Easy Support
                            </h3>

                            <p>
                                Get convenient support for your
                                bookings and service enquiries.
                            </p>

                        </div>

                    </div>


                </div>

            </div>



            <!-- ==================================================
                 SIGN UP FORM
            =================================================== -->

            <div class="zcs-signup-form-area">


                <div class="zcs-signup-form-header">

                    <span>
                        CUSTOMER REGISTRATION
                    </span>

                    <h2>
                        Create Your Account
                    </h2>

                    <p>
                        Enter your details below to register
                        with Zen Home Experts.
                    </p>

                </div>



                <!-- MESSAGES (filled by zen-pages.js) -->

                <div id="zcsSignupAlert"></div>



                <form
                    action="#"
                    method="POST"
                    class="zcs-signup-form"
                    id="zcsSignupForm"
                >


                    <!-- NAME -->

                    <div class="zcs-signup-field">

                        <label for="zcs-signup-name">
                            Full Name
                        </label>


                        <div class="zcs-signup-input-wrap">

                            <span>
                                <i class="fa-regular fa-user"></i>
                            </span>

                            <input
                                type="text"
                                id="zcs-signup-name"
                                name="name"
                                placeholder="Enter your full name"
                                autocomplete="name"
                                required
                            >

                        </div>

                    </div>



                    <!-- PHONE + EMAIL -->

                    <div class="zcs-signup-row">


                        <div class="zcs-signup-field">

                            <label for="zcs-signup-phone">
                                Mobile Number
                            </label>


                            <div class="zcs-signup-input-wrap">

                                <span>
                                    <i class="fa-solid fa-mobile-screen-button"></i>
                                </span>

                                <input
                                    type="tel"
                                    id="zcs-signup-phone"
                                    name="phone"
                                    placeholder="Enter mobile number"
                                    maxlength="10"
                                    autocomplete="tel"
                                    required
                                >

                            </div>

                        </div>



                        <div class="zcs-signup-field">

                            <label for="zcs-signup-email">
                                Email Address
                            </label>


                            <div class="zcs-signup-input-wrap">

                                <span>
                                    <i class="fa-regular fa-envelope"></i>
                                </span>

                                <input
                                    type="email"
                                    id="zcs-signup-email"
                                    name="email"
                                    placeholder="Enter email address"
                                    autocomplete="email"
                                    required
                                >

                            </div>

                        </div>


                    </div>



                    <!-- PASSWORD + CONFIRM PASSWORD -->

                    <div class="zcs-signup-row">


                        <div class="zcs-signup-field">

                            <label for="zcs-signup-password">
                                Password
                            </label>


                            <div class="zcs-signup-input-wrap">

                                <span>
                                    <i class="fa-solid fa-lock"></i>
                                </span>

                                <input
                                    type="password"
                                    id="zcs-signup-password"
                                    name="password"
                                    placeholder="Create password"
                                    autocomplete="new-password"
                                    minlength="6"
                                    required
                                >

                            </div>

                        </div>



                        <div class="zcs-signup-field">

                            <label for="zcs-signup-confirm-password">
                                Confirm Password
                            </label>


                            <div class="zcs-signup-input-wrap">

                                <span>
                                    <i class="fa-solid fa-lock"></i>
                                </span>

                                <input
                                    type="password"
                                    id="zcs-signup-confirm-password"
                                    name="confirm_password"
                                    placeholder="Confirm password"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>

                        </div>


                    </div>



                    <!-- ADDRESS -->

                    <div class="zcs-signup-field">

                        <label for="zcs-signup-address">
                            Address
                        </label>


                        <div class="zcs-signup-input-wrap zcs-signup-textarea-wrap">

                            <span>
                                <i class="fa-solid fa-location-dot"></i>
                            </span>

                            <textarea
                                id="zcs-signup-address"
                                name="address"
                                placeholder="Enter your complete address"
                                rows="4"
                                required
                            ></textarea>

                        </div>

                    </div>



                    <!-- CITY + PINCODE -->

                    <div class="zcs-signup-row">


                        <div class="zcs-signup-field">

                            <label for="zcs-signup-city">
                                City
                            </label>


                            <div class="zcs-signup-input-wrap">

                                <span>
                                    <i class="fa-solid fa-city"></i>
                                </span>

                                <input
                                    type="text"
                                    id="zcs-signup-city"
                                    name="city"
                                    placeholder="Enter your city"
                                    required
                                >

                            </div>

                        </div>



                        <div class="zcs-signup-field">

                            <label for="zcs-signup-pincode">
                                Pincode
                            </label>


                            <div class="zcs-signup-input-wrap">

                                <span>
                                    <i class="fa-solid fa-map-pin"></i>
                                </span>

                                <input
                                    type="text"
                                    id="zcs-signup-pincode"
                                    name="pincode"
                                    placeholder="Enter pincode"
                                    maxlength="6"
                                    required
                                >

                            </div>

                        </div>


                    </div>



                    <!-- TERMS -->

                    <div class="zcs-signup-options">

                        <label class="zcs-signup-terms">

                            <input
                                type="checkbox"
                                name="terms"
                                value="1"
                                required
                            >

                            <span>
                                I agree to the
                                <a href="terms.php">Terms & Conditions</a>
                                and
                                <a href="privacy-policy.php">Privacy Policy</a>.
                            </span>

                        </label>

                    </div>



                    <!-- REGISTER BUTTON -->

                    <button
                        type="submit"
                        name="register"
                        class="zcs-signup-submit"
                    >

                        <span>
                            Create Account
                        </span>

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </form>



                <!-- LOGIN -->

                <div class="zcs-signup-login">

                    <p>
                        Already have a Zen Home Experts account?

                        <a href="login.php">
                            Sign In
                        </a>

                    </p>

                </div>



                <!-- SECURITY -->

                <div class="zcs-signup-security">

                    <i class="fa-solid fa-shield-halved"></i>

                    <span>
                        Your personal information is securely protected.
                    </span>

                </div>


            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     HELP CTA
====================================================== -->

<section class="zcs-signup-help">

    <div class="zcs-signup-container">

        <div class="zcs-signup-help-box">


            <div>

                <span>
                    NEED HELP?
                </span>

                <h2>
                    Need Help Creating Your Account?
                </h2>

                <p>
                    Contact the Zen Home Experts support team for assistance
                    with registration, bookings or services.
                </p>

            </div>


            <div class="zcs-signup-help-actions">


                <a
                    href="<?= site_e(site_tel('phone')) ?>"
                    class="zcs-signup-help-call"
                >

                    <i class="fa-solid fa-phone"></i>

                    <?= site_e(site_phone_display(site_setting('phone'))) ?>

                </a>


                <a
                    href="contact.php"
                    class="zcs-signup-help-contact"
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