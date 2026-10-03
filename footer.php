<?php require_once __DIR__ . '/api/site_settings_helper.php'; ?>
<!-- =========================
     ZEN CARE PREMIUM FOOTER
========================= -->

<footer class="zcs-footer">

    <!-- FOOTER CTA -->
    <div class="zcs-footer-cta">
        <div class="zcs-footer-container">

            <div class="zcs-footer-cta-inner">

                <div class="zcs-footer-cta-content">

                    <span class="zcs-footer-cta-icon">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </span>

                    <div>
                        <span class="zcs-footer-small-title">
                            <?= site_e(site_setting('footer_cta_tag')) ?>
                        </span>

                        <h2>
                            <?= site_e(site_setting('footer_cta_heading')) ?>
                        </h2>

                        <p>
                            <?= nl2br(site_e(site_setting('footer_cta_text'))) ?>
                        </p>
                    </div>

                </div>

                <div class="zcs-footer-cta-actions">

                    <a href="services.php" class="zcs-footer-book-btn">
                        Book a Service
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <a href="<?= site_e(site_tel('phone')) ?>" class="zcs-footer-call-btn">
                        <i class="fa-solid fa-phone"></i>

                        <span>
                            <small>Call Us Now</small>
                            <strong><?= site_e(site_phone_display(site_setting('phone'))) ?></strong>
                        </span>
                    </a>

                </div>

            </div>

        </div>
    </div>


    <!-- MAIN FOOTER -->
    <div class="zcs-footer-main">

        <div class="zcs-footer-container">

            <div class="zcs-footer-grid">


                <!-- ABOUT -->
                <div class="zcs-footer-column zcs-footer-about">

                    <h3>
                        <?= site_e(site_setting('company_name')) ?>
                    </h3>

                    <p>
                        <?= nl2br(site_e(site_setting('footer_text'))) ?>
                    </p>


                    <?php if ($footerSocial = site_social_links()): ?>
                    <div class="zcs-footer-social">
                        <?php foreach ($footerSocial as [$socialLabel, $socialIcon, $socialUrl]): ?>
                        <a href="<?= site_e($socialUrl) ?>"
                           target="_blank" rel="noopener"
                           aria-label="<?= site_e($socialLabel) ?>">
                            <i class="<?= site_e($socialIcon) ?>"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div>


                <!-- QUICK LINKS -->
                <div class="zcs-footer-column">

                    <h4>
                        Quick Links
                    </h4>

                    <ul class="zcs-footer-links">

                        <li>
                            <a href="index.php">
                                Home
                            </a>
                        </li>

                        <li>
                            <a href="about-us.php">
                                About Us
                            </a>
                        </li>

                        <li>
                            <a href="services.php">
                                Book a Service
                            </a>
                        </li>

                        <li>
                            <a href="contact.php">
                                Contact Us
                            </a>
                        </li>

                        <li>
                            <a href="login.php">
                                Sign In
                            </a>
                        </li>

                        <li>
                            <a href="register.php">
                                Create Account
                            </a>
                        </li>

                    </ul>

                </div>


                <?php
require_once __DIR__ . '/api/catalog_helper.php';
$footerCategories = $siteCategories ?? site_catalog_categories();
$footerColumns = array_chunk($footerCategories, max(1, (int) ceil(count($footerCategories) / 2)));
$footerHeadings = ['Popular Services', 'Home Services'];
?>
                <?php for ($col = 0; $col < 2; $col++): ?>
                <div class="zcs-footer-column">

                    <h4>
                        <?= $footerHeadings[$col] ?>
                    </h4>

                    <ul class="zcs-footer-links">
                        <?php foreach ($footerColumns[$col] ?? [] as $footerCategory): ?>
                        <li>
                            <a href="<?= site_e($footerCategory['url']) ?>">
                                <?= site_e($footerCategory['name']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php if ($col === 1): ?>
                        <li>
                            <a href="services.php">
                                View All Services
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                </div>
                <?php endfor; ?>


                <!-- CONTACT -->
                <div class="zcs-footer-column zcs-footer-contact">

                    <h4>
                        Get In Touch
                    </h4>


                    <a href="<?= site_e(site_tel('phone')) ?>"
                       class="zcs-footer-contact-item">

                        <span class="zcs-footer-contact-icon">
                            <i class="fa-solid fa-phone"></i>
                        </span>

                        <div>
                            <small>Call Us</small>
                            <strong><?= site_e(site_phone_intl(site_setting('phone'))) ?></strong>
                        </div>

                    </a>


                    <a href="mailto:<?= site_e(site_setting('email')) ?>"
                       class="zcs-footer-contact-item">

                        <span class="zcs-footer-contact-icon">
                            <i class="fa-regular fa-envelope"></i>
                        </span>

                        <div>
                            <small>Email Us</small>
                            <strong><?= site_e(site_setting('email')) ?></strong>
                        </div>

                    </a>


                    <?php if (site_setting('working_hours') !== ''): ?>
                    <div class="zcs-footer-contact-item">

                        <span class="zcs-footer-contact-icon">
                            <i class="fa-regular fa-clock"></i>
                        </span>

                        <div>
                            <small>Working Hours</small>
                            <strong><?= site_e(site_setting('working_hours')) ?></strong>
                        </div>

                    </div>
                    <?php endif; ?>


                    <div class="zcs-footer-contact-item">

                        <span class="zcs-footer-contact-icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <div>
                            <small>Service Area</small>
                            <strong><?= site_e(site_setting('footer_service_area_text')) ?></strong>
                        </div>

                    </div>

                </div>


            </div>

        </div>

    </div>


    <!-- TRUST STRIP -->
    <div class="zcs-footer-trust">

        <div class="zcs-footer-container">

            <div class="zcs-footer-trust-grid">


                <?php
                $zcsTrust1 = site_trust_item('trust_item_1');
                $zcsTrust2 = site_trust_item('trust_item_2');
                $zcsTrust3 = site_trust_item('trust_item_3');
                $zcsTrust4 = site_trust_item('trust_item_4');
                ?>

                <div class="zcs-footer-trust-item">

                    <span>
                        <i class="fa-solid fa-user-check"></i>
                    </span>

                    <div>
                        <strong><?= site_e($zcsTrust1['title']) ?></strong>
                        <small><?= site_e($zcsTrust1['text']) ?></small>
                    </div>

                </div>


                <div class="zcs-footer-trust-item">

                    <span>
                        <i class="fa-solid fa-calendar-check"></i>
                    </span>

                    <div>
                        <strong><?= site_e($zcsTrust2['title']) ?></strong>
                        <small><?= site_e($zcsTrust2['text']) ?></small>
                    </div>

                </div>


                <div class="zcs-footer-trust-item">

                    <span>
                        <i class="fa-solid fa-house"></i>
                    </span>

                    <div>
                        <strong><?= site_e($zcsTrust3['title']) ?></strong>
                        <small><?= site_e($zcsTrust3['text']) ?></small>
                    </div>

                </div>


                <div class="zcs-footer-trust-item">

                    <span>
                        <i class="fa-solid fa-headset"></i>
                    </span>

                    <div>
                        <strong><?= site_e($zcsTrust4['title']) ?></strong>
                        <small><?= site_e($zcsTrust4['text']) ?></small>
                    </div>

                </div>


            </div>

        </div>

    </div>


    <!-- COPYRIGHT -->
    <div class="zcs-footer-bottom">

        <div class="zcs-footer-container">

            <div class="zcs-footer-bottom-inner">

                <p>
                    © <span id="zcsCurrentYear"></span>
                    <?= site_e(site_setting('company_name')) ?>. All Rights Reserved.
                </p>

                <div class="zcs-footer-bottom-links">

                    <a href="privacy-policy.php">
                        Privacy Policy
                    </a>

                    <span></span>

                    <a href="terms.php">
                        Terms & Conditions
                    </a>

                    <span></span>

                    <a href="refund-policy.php">
                        Refund Policy
                    </a>

                    <span></span>

                    <a href="faq.php">
                        FAQs
                    </a>

                </div>

            </div>

        </div>

    </div>

</footer>



<div class="zcs-footer-bottom">

    <div class="zcs-footer-bottom-container">

        <div class="zcs-footer-developer">

            <span>
                Designed & Developed by
            </span>

            <a
                href="https://www.vgswebservices.com/"
                target="_blank"
                rel="noopener noreferrer"
            >
                Virtuous Global Solutions
            </a>

        </div>

    </div>

</div>



<script src="zen-api.js?v=3"></script>
<script src="common.js?v=5"></script>
<script src="zen-pages.js?v=6"></script>

</body>
</html>