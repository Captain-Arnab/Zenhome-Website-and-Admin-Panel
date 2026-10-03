<?php
include 'header.php';
?>

<!-- ======================================================
     SIGN IN PAGE
====================================================== -->

<section class="zcs-login-section">

    <div class="zcs-login-container">

        <div class="zcs-login-box">


            <!-- ==================================================
                 LEFT CONTENT
            =================================================== -->

            <div class="zcs-login-info">

                <span class="zcs-login-label">
                    ZEN HOME EXPERTS
                </span>

                <h1>
                    Welcome Back to
                    <strong>Zen Home Experts.</strong>
                </h1>

                <p>
                    Sign in to manage your bookings, view your service
                    history, track appointments and book trusted home
                    services quickly and conveniently.
                </p>


                <div class="zcs-login-benefits">


                    <div class="zcs-login-benefit">

                        <span>
                            <i class="fa-solid fa-calendar-check"></i>
                        </span>

                        <div>

                            <h3>
                                Manage Your Bookings
                            </h3>

                            <p>
                                View your current and upcoming
                                Zen Home Experts service appointments.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-login-benefit">

                        <span>
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </span>

                        <div>

                            <h3>
                                Service History
                            </h3>

                            <p>
                                Easily access your previous service
                                bookings and details.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-login-benefit">

                        <span>
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                        </span>

                        <div>

                            <h3>
                                Book Services Faster
                            </h3>

                            <p>
                                Quickly book AC, appliance, cleaning
                                and other home services.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-login-benefit">

                        <span>
                            <i class="fa-solid fa-headset"></i>
                        </span>

                        <div>

                            <h3>
                                Easy Support
                            </h3>

                            <p>
                                Get assistance with your bookings
                                and service-related enquiries.
                            </p>

                        </div>

                    </div>


                </div>

            </div>



            <!-- ==================================================
                 LOGIN FORM
            =================================================== -->

            <div class="zcs-login-form-area">


                <div class="zcs-login-form-header">

                    <span>
                        CUSTOMER LOGIN
                    </span>

                    <h2>
                        Sign In to Your Account
                    </h2>

                    <p>
                        Enter your registered mobile number
                        and password. We'll send you an OTP to confirm.
                    </p>

                </div>



                <!-- MESSAGES (filled by zen-pages.js) -->

                <div id="zcsLoginAlert"></div>


                <?php if(isset($_GET['success'])) { ?>

                    <div class="zcs-login-alert zcs-login-alert-success">

                        <i class="fa-solid fa-circle-check"></i>

                        <span>
                            Your account has been created successfully.
                            Please sign in.
                        </span>

                    </div>

                <?php } elseif (isset($_GET['reset'])) { ?>

                    <div class="zcs-login-alert zcs-login-alert-success">

                        <i class="fa-solid fa-circle-check"></i>

                        <span>
                            Your password has been changed.
                            Please sign in with your new password.
                        </span>

                    </div>

                <?php } ?>



                <form
                    action="#"
                    method="POST"
                    class="zcs-login-form"
                    id="zcsLoginForm"
                    novalidate
                >


                    <!-- MOBILE NUMBER -->

                    <div class="zcs-login-field">

                        <label for="zcs-login-phone">

                            Mobile Number

                        </label>


                        <div class="zcs-login-input-wrap">

                            <span>

                                <i class="fa-solid fa-mobile-screen-button"></i>

                            </span>

                            <input
                                type="tel"
                                id="zcs-login-phone"
                                name="phone"
                                placeholder="Enter your mobile number"
                                autocomplete="tel"
                                inputmode="numeric"
                                maxlength="13"
                                required
                            >

                        </div>

                    </div>



                    <!-- PASSWORD -->

                    <div class="zcs-login-field" id="zcsLoginPasswordField">

                        <div class="zcs-login-label-row">

                            <label for="zcs-login-password">

                                Password

                            </label>


                            <a href="forgot-password.php">

                                Forgot Password?

                            </a>

                        </div>


                        <div class="zcs-login-input-wrap">

                            <span>

                                <i class="fa-solid fa-lock"></i>

                            </span>

                            <input
                                type="password"
                                id="zcs-login-password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                        </div>

                    </div>



                    <!-- OTP (shown after password is accepted) -->

                    <div class="zcs-login-field" id="zcsLoginOtpField" hidden>

                        <div class="zcs-login-label-row">

                            <label for="zcs-login-otp">

                                OTP

                            </label>


                            <a href="#" id="zcsLoginChangeNumber">

                                Change Number

                            </a>

                        </div>


                        <div class="zcs-login-input-wrap">

                            <span>

                                <i class="fa-solid fa-key"></i>

                            </span>

                            <input
                                type="text"
                                id="zcs-login-otp"
                                name="otp"
                                placeholder="Enter the 6-digit OTP"
                                autocomplete="one-time-code"
                                inputmode="numeric"
                                maxlength="6"
                            >

                        </div>

                    </div>



                    <!-- REMEMBER -->

                    <div class="zcs-login-options">

                        <label class="zcs-login-remember">

                            <input
                                type="checkbox"
                                name="remember"
                                id="zcs-login-remember"
                                value="1"
                                checked
                            >

                            <span>
                                Remember Me
                            </span>

                        </label>

                    </div>



                    <!-- BUTTON -->

                    <button
                        type="submit"
                        name="login"
                        class="zcs-login-submit"
                        id="zcsLoginSubmit"
                    >

                        <span>
                            Sign In
                        </span>

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>



                </form>



                <!-- REGISTER -->

                <div class="zcs-login-register">

                    <p>
                        Don't have a Zen Home Experts account?

                        <a href="register.php">
                            Create Account
                        </a>

                    </p>

                </div>



                <!-- SECURITY -->

                <div class="zcs-login-security">

                    <i class="fa-solid fa-shield-halved"></i>

                    <span>
                        Your account information is securely protected.
                    </span>

                </div>


            </div>

        </div>

    </div>

</section>



<!-- ======================================================
     HELP CTA
====================================================== -->

<section class="zcs-login-help">

    <div class="zcs-login-container">

        <div class="zcs-login-help-box">


            <div>

                <span>
                    NEED HELP?
                </span>

                <h2>
                    Having Trouble Signing In?
                </h2>

                <p>
                    Our Zen Home Experts support team can assist you
                    with account and booking-related enquiries.
                </p>

            </div>


            <div class="zcs-login-help-actions">


                <a
                    href="<?= site_e(site_tel('phone')) ?>"
                    class="zcs-login-help-call"
                >

                    <i class="fa-solid fa-phone"></i>

                    <?= site_e(site_phone_display(site_setting('phone'))) ?>

                </a>


                <a
                    href="contact.php"
                    class="zcs-login-help-contact"
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