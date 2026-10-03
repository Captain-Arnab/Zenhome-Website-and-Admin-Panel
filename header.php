<?php
require_once __DIR__ . '/api/catalog_helper.php';
require_once __DIR__ . '/api/site_settings_helper.php';
$siteCategories = site_catalog_categories();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php if (!empty($siteMeta['title'])): ?>
    <title><?= htmlspecialchars($siteMeta['title'], ENT_QUOTES, 'UTF-8') ?></title>
<?php else: ?>
    <title>Zen Home Experts | Professional Home Services</title>
<?php endif; ?>

<?php if (!empty($siteMeta['description'])): ?>
    <meta name="description"
          content="<?= htmlspecialchars($siteMeta['description'], ENT_QUOTES, 'UTF-8') ?>">
<?php else: ?>
    <meta name="description"
          content="Zen Home Experts provides AC service, carpenter service, refrigerator repair, home cleaning, salon, pest control, washing machine repair, chimney repair and water purifier services.">
<?php endif; ?>

    <link rel="icon" href="<?= site_e(site_setting('favicon') ?: site_setting('logo')) ?>">

    <script>window.ZCS_LOGO = <?= json_encode(site_setting('logo') ?: 'images/logo.png') ?>;</script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- Header CSS -->
    <link rel="stylesheet" href="style.css?v=9">
</head>

<body>

<header class="zcs-header">

    <!-- TOP BAR -->
    <div class="zcs-topbar">
        <div class="zcs-container zcs-topbar-inner">

            <div class="zcs-topbar-left">
                <span>
                    <i class="fa-solid fa-location-dot"></i>
                    Professional Home Services at Your Doorstep
                </span>
            </div>

            <div class="zcs-topbar-right">

                <a href="<?= site_e(site_tel('phone')) ?>">
                    <i class="fa-solid fa-phone"></i>
                    <?= site_e(site_phone_intl(site_setting('phone'))) ?>
                </a>

                <span class="zcs-top-divider"></span>

                <span>
                    <i class="fa-solid fa-shield-heart"></i>
                    Trusted Professionals
                </span>

            </div>

        </div>
    </div>


    <!-- MAIN HEADER -->
    <div class="zcs-main-header">

        <div class="zcs-container zcs-header-inner">

            <!-- LOGO -->
            <a href="index.php" class="zcs-logo">
                <img src="<?= site_e(site_setting('logo')) ?>" alt="<?= site_e(site_setting('company_name')) ?>">
            </a>


            <!-- DESKTOP NAV -->
            <nav class="zcs-desktop-nav">

                <a href="index.php" class="zcs-nav-link active">
                    Home
                </a>

                <a href="about-us.php" class="zcs-nav-link">
                    About Us
                </a>


                <!-- SERVICES DROPDOWN -->
                <div class="zcs-services-dropdown">

                    <button class="zcs-services-btn">
                        Services
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>

                    <div class="zcs-services-menu">

                        <div class="zcs-services-heading">
                            <span>Our Services</span>
                            <strong>How can we help you?</strong>
                        </div>

                        <div class="zcs-services-grid">
<?php foreach ($siteCategories as $navCategory): ?>
                            <a href="<?= site_e($navCategory['url']) ?>">
                                <span class="zcs-service-icon">
                                    <i class="<?= site_e($navCategory['icon']) ?>"></i>
                                </span>
                                <span>
                                    <strong><?= site_e($navCategory['name']) ?></strong>
                                    <?php if ($navCategory['tagline'] !== ''): ?><small><?= site_e($navCategory['tagline']) ?></small><?php endif; ?>
                                </span>
                            </a>
<?php endforeach; ?>
<?php if (!$siteCategories): ?>
                            <a href="services.php">
                                <span class="zcs-service-icon">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                </span>
                                <span>
                                    <strong>All Services</strong>
                                    <small>Browse our home services</small>
                                </span>
                            </a>
<?php endif; ?>
                        </div>

                    </div>

                </div>


                <a href="services.php" class="zcs-nav-link">
                    Book a Service
                </a>

                <a href="contact.php" class="zcs-nav-link">
                    Contact
                </a>

            </nav>


            <!-- RIGHT ACTIONS -->
            <div class="zcs-header-actions">

                <!-- PHONE -->
                <a href="<?= site_e(site_tel('support_phone')) ?>" class="zcs-phone-action">

                    <span class="zcs-phone-icon">
                        <i class="fa-solid fa-phone"></i>
                    </span>

                    <span class="zcs-phone-content">
                        <small>Need Help?</small>
                        <strong><?= site_e(site_phone_display(site_setting('support_phone'))) ?></strong>
                    </span>

                </a>


                <!-- CART -->
                <div class="zcs-header-cart-wrap">

                    <a href="cart.php" class="zcs-cart-btn">

                        <i class="fa-solid fa-cart-shopping"></i>

                        <span class="zcs-cart-count">0</span>

                    </a>


                    <!-- MINI CART -->
                    <div class="zcs-mini-cart">

                        <div class="zcs-mini-cart-head">

                            <div>
                                <span>Your Cart</span>
                                <strong>Selected Services</strong>
                            </div>

                            <span class="zcs-mini-cart-total-count">
                                0 Items
                            </span>

                        </div>


                        <div class="zcs-mini-cart-items">
                            <!-- JS will insert services here -->
                        </div>


                        <div class="zcs-mini-cart-empty">

                            <span>
                                <i class="fa-solid fa-cart-shopping"></i>
                            </span>

                            <strong>Your cart is empty</strong>

                            <small>
                                Add a service to see it here.
                            </small>

                        </div>


                        <div class="zcs-mini-cart-footer">

                            <div class="zcs-mini-cart-subtotal">

                                <span>Subtotal</span>

                                <strong class="zcs-mini-cart-subtotal-price">
                                    ₹0
                                </strong>

                            </div>


                            <a href="cart.php"
                               class="zcs-mini-cart-view-btn">

                                View Cart

                                <i class="fa-solid fa-arrow-right"></i>

                            </a>

                        </div>

                    </div>

                </div>


                <!-- SIGN IN -->
                <a href="login.php" class="zcs-signin-btn">
                    <i class="fa-regular fa-user"></i>
                    <span>Sign In</span>
                </a>


                <!-- SIGN UP -->
                <a href="register.php" class="zcs-signup-btn">
                    Sign Up
                    <i class="fa-solid fa-arrow-right"></i>
                </a>


                <!-- MOBILE BUTTON -->
                <button class="zcs-mobile-toggle"
                        id="zcsMobileToggle"
                        aria-label="Open Menu">

                    <span></span>
                    <span></span>
                    <span></span>

                </button>

            </div>

        </div>
    </div>


    <!-- MOBILE MENU -->
    <div class="zcs-mobile-menu" id="zcsMobileMenu">

        <div class="zcs-mobile-menu-inner">

            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                Home
            </a>

            <a href="about-us.php">
                <i class="fa-regular fa-building"></i>
                About Us
            </a>


            <!-- MOBILE SERVICES -->
            <div class="zcs-mobile-services">

                <button class="zcs-mobile-services-btn"
                        id="zcsMobileServicesBtn">

                    <span>
                        <i class="fa-solid fa-layer-group"></i>
                        Services
                    </span>

                    <i class="fa-solid fa-chevron-down zcs-mobile-arrow"></i>

                </button>


                <div class="zcs-mobile-submenu"
                     id="zcsMobileSubmenu">
<?php foreach ($siteCategories as $navCategory): ?>
                    <a href="<?= site_e($navCategory['url']) ?>"><?= site_e($navCategory['name']) ?></a>
<?php endforeach; ?>
                    <a href="services.php">All Services</a>
                </div>

            </div>


            <a href="services.php">
                <i class="fa-regular fa-calendar-check"></i>
                Book a Service
            </a>

            <a href="contact.php">
                <i class="fa-regular fa-envelope"></i>
                Contact Us
            </a>


            <!-- MOBILE ACCOUNT -->
            <div class="zcs-mobile-account">

                <a href="cart.php">
                    <i class="fa-solid fa-cart-shopping"></i>
                    Cart
                    <span class="zcs-mobile-cart-count">0</span>
                </a>

                <a href="login.php">
                    <i class="fa-regular fa-user"></i>
                    Sign In
                </a>

            </div>


            <a href="register.php"
               class="zcs-mobile-register">
                Create Account
                <i class="fa-solid fa-arrow-right"></i>
            </a>


            <a href="<?= site_e(site_tel('support_phone')) ?>"
               class="zcs-mobile-call">

                <span>
                    <i class="fa-solid fa-phone"></i>
                </span>

                <div>
                    <small>Call for Assistance</small>
                    <strong><?= site_e(site_phone_intl(site_setting('support_phone'))) ?></strong>
                </div>

            </a>

        </div>

    </div>

</header>


