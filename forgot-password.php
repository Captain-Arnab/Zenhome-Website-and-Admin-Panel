<?php
include 'header.php';
?>

<!-- ======================================================
     FORGOT PASSWORD PAGE
====================================================== -->

<section class="zcs-login-section">

    <div class="zcs-login-container">

        <div class="zcs-login-box">


            <!-- ==================================================
                 LEFT CONTENT
            =================================================== -->

            <div class="zcs-login-info">

                <span class="zcs-login-label">
                    ZEN CARE SERVICES
                </span>

                <h1>
                    Forgot Your
                    <strong>Password?</strong>
                </h1>

                <p>
                    No problem. Enter your registered mobile number
                    and we will help you reset your Zen Care account password.
                </p>


                <div class="zcs-login-benefits">


                    <div class="zcs-login-benefit">

                        <span>
                            <i class="fa-solid fa-shield-halved"></i>
                        </span>

                        <div>

                            <h3>
                                Secure Password Reset
                            </h3>

                            <p>
                                Your password reset request is handled
                                securely for your account protection.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-login-benefit">

                        <span>
                            <i class="fa-solid fa-comment-sms"></i>
                        </span>

                        <div>

                            <h3>
                                OTP by SMS
                            </h3>

                            <p>
                                A one-time code is sent
                                to your registered mobile number.
                            </p>

                        </div>

                    </div>


                    <div class="zcs-login-benefit">

                        <span>
                            <i class="fa-solid fa-key"></i>
                        </span>

                        <div>

                            <h3>
                                Create a New Password
                            </h3>

                            <p>
                                Enter the OTP to securely create
                                a new password for your account.
                            </p>

                        </div>

                    </div>


                </div>

            </div>



            <!-- ==================================================
                 FORGOT PASSWORD FORM
            =================================================== -->

            <div class="zcs-login-form-area">


                <div class="zcs-login-form-header">

                    <span>
                        PASSWORD RECOVERY
                    </span>

                    <h2>
                        Reset Your Password
                    </h2>

                    <p>
                        Enter the mobile number registered
                        with your Zen Care account. We will send you an OTP.
                    </p>

                </div>



                <div id="zcsForgotAlert"></div>



                <form
                    class="zcs-login-form"
                    id="zcsForgotForm"
                    novalidate
                >


                    <!-- MOBILE NUMBER -->

                    <div class="zcs-login-field">

                        <label for="zcs-forgot-phone">
                            Registered Mobile Number
                        </label>

                        <div class="zcs-login-input-wrap">

                            <span>
                                <i class="fa-solid fa-mobile-screen-button"></i>
                            </span>

                            <input
                                type="tel"
                                id="zcs-forgot-phone"
                                name="phone"
                                placeholder="Enter your mobile number"
                                autocomplete="tel"
                                inputmode="numeric"
                                maxlength="13"
                                required
                            >

                        </div>

                    </div>



                    <!-- OTP + NEW PASSWORD (shown after the OTP is sent) -->

                    <div id="zcsForgotResetFields" hidden>

                        <div class="zcs-login-field">

                            <div class="zcs-login-label-row">

                                <label for="zcs-forgot-otp">
                                    OTP
                                </label>

                                <a href="#" id="zcsForgotResend">
                                    Resend OTP
                                </a>

                            </div>

                            <div class="zcs-login-input-wrap">

                                <span>
                                    <i class="fa-solid fa-key"></i>
                                </span>

                                <input
                                    type="text"
                                    id="zcs-forgot-otp"
                                    name="otp"
                                    placeholder="Enter the 6-digit OTP"
                                    autocomplete="one-time-code"
                                    inputmode="numeric"
                                    maxlength="6"
                                >

                            </div>

                        </div>


                        <div class="zcs-login-field">

                            <label for="zcs-forgot-password">
                                New Password
                            </label>

                            <div class="zcs-login-input-wrap">

                                <span>
                                    <i class="fa-solid fa-lock"></i>
                                </span>

                                <input
                                    type="password"
                                    id="zcs-forgot-password"
                                    name="password"
                                    placeholder="At least 6 characters"
                                    autocomplete="new-password"
                                    minlength="6"
                                    maxlength="72"
                                >

                            </div>

                        </div>


                        <div class="zcs-login-field">

                            <label for="zcs-forgot-confirm">
                                Confirm New Password
                            </label>

                            <div class="zcs-login-input-wrap">

                                <span>
                                    <i class="fa-solid fa-lock"></i>
                                </span>

                                <input
                                    type="password"
                                    id="zcs-forgot-confirm"
                                    name="confirm_password"
                                    placeholder="Re-enter the new password"
                                    autocomplete="new-password"
                                    maxlength="72"
                                >

                            </div>

                        </div>

                    </div>



                    <!-- SUBMIT BUTTON -->

                    <button
                        type="submit"
                        id="zcsForgotSubmit"
                        class="zcs-login-submit"
                    >

                        <span>
                            Send OTP
                        </span>

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </form>



                <!-- BACK TO LOGIN -->

                <div class="zcs-login-register">

                    <p>

                        Remember your password?

                        <a href="login.php">
                            Back to Sign In
                        </a>

                    </p>

                </div>



                <!-- SECURITY -->

                <div class="zcs-login-security">

                    <i class="fa-solid fa-shield-halved"></i>

                    <span>
                        For security, the OTP expires after 10 minutes
                        and you are signed out on all devices.
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
                    Unable to Reset Your Password?
                </h2>

                <p>
                    Contact the Zen Care support team if you are
                    having trouble accessing your account.
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