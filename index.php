<?php
// Live banners and categories from the admin panel (empty when none / database unavailable).
require_once __DIR__ . '/api/catalog_helper.php';
$mainBanner   = site_live_banners('Homepage Hero', 1)[0] ?? null;
$heroBanners  = site_live_banners('Homepage Slider', 6);
$promoBanners = site_live_banners('Promotional', 6);

include('header.php');
?>


 <section class="zcs-welcome-section">

    <div class="zcs-welcome-container">

        <div class="zcs-welcome-layout">

            <!-- LEFT PANEL -->
            <div class="zcs-welcome-panel">

                <div class="zcs-welcome-head">

                    <span class="zcs-welcome-mini">
                        <?= site_e(site_setting('hero_tag')) ?>
                    </span>

                    <h1>
                        Welcome To
                        <strong><?= site_e(site_setting('company_name')) ?></strong>
                    </h1>

                    <p>
                        <?= site_e(site_setting('hero_subtitle')) ?>
                    </p>

                </div>


                <div class="zcs-welcome-service-grid">

<?php foreach (site_catalog_categories() as $homeCategory): ?>
                    <a href="<?= site_e($homeCategory['url']) ?>"
                       class="zcs-welcome-service-card">

                        <div class="zcs-welcome-service-image">
                            <?php if ($homeCategory['image']): ?>
                                <img src="<?= site_e($homeCategory['image']) ?>" alt="<?= site_e($homeCategory['name']) ?>">
                            <?php else: ?>
                                <i class="<?= site_e($homeCategory['icon']) ?>" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>

                        <span><?= site_e($homeCategory['name']) ?></span>

                    </a>
<?php endforeach; ?>


                </div>

            </div>


            <!-- RIGHT VISUAL -->
            <div class="zcs-welcome-visual">

                <div class="zcs-welcome-bg-circle zcs-circle-one"></div>
                <div class="zcs-welcome-bg-circle zcs-circle-two"></div>


                <!-- RATING -->
                <div class="zcs-welcome-rating">

                    <span class="zcs-welcome-rating-icon">
                        <i class="fa-solid fa-star"></i>
                    </span>

                    <div>
                        <strong><?= site_e(site_setting('hero_rating_value')) ?></strong>
                        <small>Customer Rating</small>
                    </div>

                </div>


                <!-- BOOKING BADGE -->
                <div class="zcs-welcome-booking">

                    <span></span>

                    <strong>
                        <?= site_e(site_setting('hero_bookings_text')) ?>
                    </strong>

                </div>


                <!-- IMAGE -->
                <?php if ($mainBanner): ?>
                <div class="zcs-welcome-main-image">

                    <?php if ($mainBanner['link'] !== ''): ?><a href="<?= site_e($mainBanner['link']) ?>"<?= preg_match('#^https?://#i', $mainBanner['link']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?php endif; ?>
                    <img
                        src="<?= site_e($mainBanner['image']) ?>"
                        alt="<?= site_e($mainBanner['title']) ?>">
                    <?php if ($mainBanner['link'] !== ''): ?></a><?php endif; ?>

                </div>
                <?php endif; ?>


                <!-- BOTTOM MINI CARD -->
                <div class="zcs-welcome-help-card">

                    <span>
                        <i class="fa-solid fa-headset"></i>
                    </span>

                    <div>
                        <small>Need help choosing?</small>
                        <strong>Call <?= site_e(site_phone_intl(site_setting('phone'))) ?></strong>
                    </div>

                    <a href="<?= site_e(site_tel('phone')) ?>">
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                </div>

            </div>

        </div>

    </div>

</section>


<?php if ($heroBanners || $promoBanners): ?>
<!-- =========================================================
     OFFERS & BANNERS (managed in Admin > Banners)
========================================================= -->
<section class="zcs-home-banner-section" aria-label="Offers">

    <div class="zcs-home-container">

        <?php if ($heroBanners): ?>
            <div class="zcs-banner-slider" data-zcs-slider>

                <div class="zcs-banner-track">
                    <?php foreach ($heroBanners as $i => $banner): ?>
                        <?php
                        $external = (bool) preg_match('#^https?://#i', $banner['link']);
                        $open = $banner['link'] !== ''
                            ? '<a class="zcs-banner-slide' . ($i === 0 ? ' is-active' : '') . '" href="' . site_e($banner['link']) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
                            : '<div class="zcs-banner-slide' . ($i === 0 ? ' is-active' : '') . '">';
                        ?>
                        <?= $open ?>
                            <img src="<?= site_e($banner['image']) ?>" alt="<?= site_e($banner['title']) ?>" <?= $i === 0 ? '' : 'loading="lazy"' ?>>
                        <?= $banner['link'] !== '' ? '</a>' : '</div>' ?>
                    <?php endforeach; ?>
                </div>

                <?php if (count($heroBanners) > 1): ?>
                    <button type="button" class="zcs-banner-nav zcs-banner-prev" aria-label="Previous banner">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <button type="button" class="zcs-banner-nav zcs-banner-next" aria-label="Next banner">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <div class="zcs-banner-dots">
                        <?php foreach ($heroBanners as $i => $banner): ?>
                            <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" aria-label="Show banner <?= $i + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <?php if ($promoBanners): ?>
            <div class="zcs-promo-grid">
                <?php foreach ($promoBanners as $banner): ?>
                    <?php $external = (bool) preg_match('#^https?://#i', $banner['link']); ?>
                    <?php if ($banner['link'] !== ''): ?>
                        <a class="zcs-promo-card" href="<?= site_e($banner['link']) ?>"<?= $external ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <img src="<?= site_e($banner['image']) ?>" alt="<?= site_e($banner['title']) ?>" loading="lazy">
                        </a>
                    <?php else: ?>
                        <div class="zcs-promo-card">
                            <img src="<?= site_e($banner['image']) ?>" alt="<?= site_e($banner['title']) ?>" loading="lazy">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</section>
<?php endif; ?>


<!-- =========================================================
     HOW ZEN CARE WORKS
========================================================= -->
<section class="zcs-home-work-section">

    <div class="zcs-home-container">

        <div class="zcs-home-section-head">

            <span class="zcs-home-section-tag">
                HOW ZEN CARE WORKS
            </span>

            <h2>
                Simple Booking.
                <strong>Professional Service.</strong>
            </h2>

            <p>
                Getting reliable home service should be simple.
                Choose what you need, select a convenient time and
                let our professionals take care of the rest.
            </p>

        </div>


        <div class="zcs-home-work-grid">

            <div class="zcs-home-work-card">

                <div class="zcs-home-work-icon">
                    <i class="fa-solid fa-layer-group"></i>
                </div>

                <div class="zcs-home-work-content">
                    <span>Choose a Service</span>

                    <h3>
                        Find What Your Home Needs.
                    </h3>

                    <p>
                        Select from AC service, cleaning, appliance repair,
                        pest control, salon and other professional services.
                    </p>
                </div>

            </div>


            <div class="zcs-home-work-card">

                <div class="zcs-home-work-icon">
                    <i class="fa-regular fa-calendar-check"></i>
                </div>

                <div class="zcs-home-work-content">
                    <span>Book Your Time</span>

                    <h3>
                        Schedule at Your Convenience.
                    </h3>

                    <p>
                        Choose your preferred service date and time
                        without complicated booking steps.
                    </p>
                </div>

            </div>


            <div class="zcs-home-work-card">

                <div class="zcs-home-work-icon">
                    <i class="fa-solid fa-user-check"></i>
                </div>

                <div class="zcs-home-work-content">
                    <span>Professional Arrives</span>

                    <h3>
                        Skilled Help at Your Doorstep.
                    </h3>

                    <p>
                        A service professional visits your location
                        and completes the requested service efficiently.
                    </p>
                </div>

            </div>


            <div class="zcs-home-work-card">

                <div class="zcs-home-work-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div class="zcs-home-work-content">
                    <span>Service Completed</span>

                    <h3>
                        Done. Simple. Hassle-Free.
                    </h3>

                    <p>
                        Enjoy a convenient home service experience
                        from booking to completion.
                    </p>
                </div>

            </div>

        </div>

    </div>

</section>



<?php $homePackages = site_home_packages(8); ?>
<?php if ($homePackages): ?>
<section class="zcs-extra-packages">

    <div class="zcs-extra-container">

        <div class="zcs-extra-heading">

            <span>POPULAR SERVICE PACKAGES</span>

            <h2>
                Smart Packages for
                <strong>Everyday Home Care.</strong>
            </h2>

            <p>
                Choose convenient service combinations designed to save
                time and make home maintenance easier.
            </p>

        </div>


        <div class="zcs-extra-package-grid">

            <?php foreach ($homePackages as $package): ?>
            <article class="zcs-extra-package-card<?= $package['featured'] ? ' zcs-extra-featured-package' : '' ?>">

                <div class="zcs-extra-package-icon">
                    <i class="<?= site_e($package['icon']) ?>"></i>
                </div>

                <?php if ($package['label'] !== ''): ?>
                <span class="zcs-extra-package-label">
                    <?= site_e($package['label']) ?>
                </span>
                <?php endif; ?>

                <h3>
                    <?= site_e($package['title']) ?>
                </h3>

                <?php if ($package['description'] !== ''): ?>
                <p>
                    <?= site_e($package['description']) ?>
                </p>
                <?php endif; ?>

                <?php if ((float) $package['price'] > 0): ?>
                <p class="zcs-extra-package-price"><strong><?= site_e(site_price($package['price'])) ?></strong></p>
                <?php endif; ?>

                <?php if ($package['highlights']): ?>
                <ul>
                    <?php foreach ($package['highlights'] as $point): ?>
                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        <?= site_e($point) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <a href="<?= site_e($package['url']) ?>">
                    <?= site_e($package['button_text']) ?>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </article>
            <?php endforeach; ?>

        </div>

    </div>

</section>
<?php endif; ?>



<!-- =========================================================
     WHY CHOOSE ZEN CARE + COUNTERS
========================================================= -->
<section class="zcs-home-why-section">

    <div class="zcs-home-container">

        <div class="zcs-home-why-layout">

            <!-- LEFT CONTENT -->
            <div class="zcs-home-why-content">

                <span class="zcs-home-section-tag">
                    WHY CHOOSE ZEN CARE
                </span>

                <h2>
                    Home Services You Can
                    <strong>Book With Confidence.</strong>
                </h2>

                <p class="zcs-home-why-intro">
                    Zen Care brings essential home services together in one
                    convenient place, helping customers save time while
                    getting dependable professional support.
                </p>


                <div class="zcs-home-why-points">

                    <div class="zcs-home-why-item">
                        <span>
                            <i class="fa-solid fa-user-shield"></i>
                        </span>

                        <div>
                            <h3>Trusted Professionals</h3>
                            <p>
                                Experienced service professionals for
                                everyday home requirements.
                            </p>
                        </div>
                    </div>


                    <div class="zcs-home-why-item">
                        <span>
                            <i class="fa-solid fa-clock"></i>
                        </span>

                        <div>
                            <h3>Convenient Scheduling</h3>
                            <p>
                                Book services around your preferred time
                                without unnecessary hassle.
                            </p>
                        </div>
                    </div>


                    <div class="zcs-home-why-item">
                        <span>
                            <i class="fa-solid fa-house-circle-check"></i>
                        </span>

                        <div>
                            <h3>Multiple Home Services</h3>
                            <p>
                                Repairs, cleaning, maintenance and personal
                                care available from one platform.
                            </p>
                        </div>
                    </div>


                    <div class="zcs-home-why-item">
                        <span>
                            <i class="fa-solid fa-headset"></i>
                        </span>

                        <div>
                            <h3>Customer Support</h3>
                            <p>
                                Easy assistance when you need help with
                                booking or service enquiries.
                            </p>
                        </div>
                    </div>

                </div>


                <a href="services.php" class="zcs-home-primary-btn">
                    Book a Service
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </div>


            <!-- RIGHT COUNTERS -->
            <div class="zcs-home-counter-panel">

                <div class="zcs-home-counter-top">

                    <span>
                        <i class="fa-solid fa-star"></i>
                    </span>

                    <div>
                        <small>CUSTOMER EXPERIENCE</small>
                        <h3>
                            Service Built Around
                            Your Convenience.
                        </h3>
                    </div>

                </div>


                <div class="zcs-home-counter-grid">

                    <div class="zcs-home-counter-card">

                        <strong
                            class="zcs-home-counter"
                            data-target="300"
                            data-suffix="+">
                            0
                        </strong>

                        <span>
                            Bookings Completed
                        </span>

                    </div>


                    <div class="zcs-home-counter-card">

                        <strong
                            class="zcs-home-counter"
                            data-target="9"
                            data-suffix="+">
                            0
                        </strong>

                        <span>
                            Home Services
                        </span>

                    </div>


                    <div class="zcs-home-counter-card">

                        <strong
                            class="zcs-home-counter"
                            data-target="98"
                            data-suffix="%">
                            0
                        </strong>

                        <span>
                            Customer Satisfaction
                        </span>

                    </div>


                    <div class="zcs-home-counter-card">

                        <strong
                            class="zcs-home-counter"
                            data-target="4.9"
                            data-decimal="1"
                            data-suffix="/5">
                            0
                        </strong>

                        <span>
                            Customer Rating
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>




<section class="zcs-extra-locations">

    <div class="zcs-extra-container">

        <div class="zcs-extra-location-box">

            <!-- LEFT -->
            <div class="zcs-extra-location-visual">

                <div class="zcs-extra-map-card">

                    <div class="zcs-extra-map-grid"></div>

                    <div class="zcs-extra-map-center">

                        <span>
                            <i class="fa-solid fa-location-dot"></i>
                        </span>

                        <strong>Zen Care</strong>

                        <small>
                            Home Services Near You
                        </small>

                    </div>


                    <div class="zcs-extra-map-pin zcs-extra-pin-one">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <div class="zcs-extra-map-pin zcs-extra-pin-two">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <div class="zcs-extra-map-pin zcs-extra-pin-three">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <div class="zcs-extra-map-pin zcs-extra-pin-four">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                </div>

            </div>


            <!-- RIGHT -->
            <div class="zcs-extra-location-content">

                <span class="zcs-extra-location-label">
                    SERVICE AREAS
                </span>

                <h2>
                    Professional Home Services
                    <strong>Closer to You.</strong>
                </h2>

                <p>
                    Zen Care provides convenient doorstep services across
                    multiple locations. Choose your area and book the
                    service you need.
                </p>


                <?php $zcsServiceAreas = site_serviceable_areas(12); ?>
                <?php if ($zcsServiceAreas): ?>
                <div class="zcs-extra-area-list">
                    <?php foreach ($zcsServiceAreas as $zcsArea): ?>
                    <a href="services.php">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= site_e($zcsArea) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>


                <div class="zcs-extra-location-bottom">

                    <div>
                        <span>
                            <i class="fa-solid fa-circle-check"></i>
                        </span>

                        <p>
                            <strong>Can't find your area?</strong>
                            Call us to check service availability.
                        </p>
                    </div>

                    <a href="<?= site_e(site_tel('phone')) ?>">
                        <i class="fa-solid fa-phone"></i>
                        <?= site_e(site_phone_display(site_setting('phone'))) ?>
                    </a>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     TESTIMONIALS
========================================================= -->
<section class="zcs-home-testimonial-section">

    <div class="zcs-home-container">

        <div class="zcs-home-testimonial-head">

            <div>
                <span class="zcs-home-section-tag">
                    CUSTOMER STORIES
                </span>

                <h2>
                    What Customers Say
                    <strong>About Zen Care.</strong>
                </h2>
            </div>

            <p>
                Real service experiences from customers who choose
                Zen Care for everyday home requirements.
            </p>

        </div>


        <?php $zcsTestimonials = site_testimonials(3); ?>
        <?php if ($zcsTestimonials): ?>
        <div class="zcs-home-testimonial-grid">
            <?php foreach ($zcsTestimonials as $zcsT): ?>
            <article class="zcs-home-testimonial-card">

                <div class="zcs-home-testimonial-stars">
                    <?php for ($zcsI = 0; $zcsI < 5; $zcsI++): ?>
                    <i class="fa-<?= $zcsI < round($zcsT['rating']) ? 'solid' : 'regular' ?> fa-star"></i>
                    <?php endfor; ?>
                </div>

                <p>
                    “<?= nl2br(site_e($zcsT['text'])) ?>”
                </p>

                <div class="zcs-home-testimonial-user">

                    <span class="zcs-home-testimonial-avatar">
                        <?= site_e(mb_strtoupper(mb_substr($zcsT['name'], 0, 1))) ?>
                    </span>

                    <div>
                        <strong><?= site_e($zcsT['name']) ?></strong>
                        <small><?= site_e($zcsT['service'] ?: 'Zen Care Customer') ?></small>
                    </div>

                </div>

            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="zcs-home-testimonial-grid">

            <article class="zcs-home-testimonial-card">

                <div class="zcs-home-testimonial-stars">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                </div>

                <p>
                    “Booking was simple and the technician arrived on time.
                    The AC service was completed neatly and the overall
                    experience was very convenient.”
                </p>

                <div class="zcs-home-testimonial-user">

                    <span class="zcs-home-testimonial-avatar">
                        R
                    </span>

                    <div>
                        <strong>Rahul Kumar</strong>
                        <small>AC Service Customer</small>
                    </div>

                </div>

            </article>


            <article class="zcs-home-testimonial-card">

                <div class="zcs-home-testimonial-stars">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                </div>

                <p>
                    “I booked home cleaning and the process was smooth.
                    The team was professional and the service was completed
                    without disturbing our schedule.”
                </p>

                <div class="zcs-home-testimonial-user">

                    <span class="zcs-home-testimonial-avatar">
                        S
                    </span>

                    <div>
                        <strong>Sneha Reddy</strong>
                        <small>Home Cleaning Customer</small>
                    </div>

                </div>

            </article>


            <article class="zcs-home-testimonial-card">

                <div class="zcs-home-testimonial-stars">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                </div>

                <p>
                    “The refrigerator repair was quick and easy to arrange.
                    I liked having the service booking, timing and support
                    all handled from one place.”
                </p>

                <div class="zcs-home-testimonial-user">

                    <span class="zcs-home-testimonial-avatar">
                        A
                    </span>

                    <div>
                        <strong>Arjun Mehta</strong>
                        <small>Appliance Repair Customer</small>
                    </div>

                </div>

            </article>

        </div>
        <?php endif; ?>

    </div>

</section>


<!-- =========================================================
     MOBILE APP + QR SECTION
========================================================= -->
<section class="zcs-home-app-section">

    <div class="zcs-home-container">

        <div class="zcs-home-app-box">

            <!-- LEFT -->
            <div class="zcs-home-app-content">

                <span class="zcs-home-app-tag">
                    ZEN CARE ON YOUR PHONE
                </span>

                <h2>
                    Your Home Services.
                    <strong>One Tap Away.</strong>
                </h2>

                <p>
                    Discover services, book professionals and manage
                    your home service requirements conveniently from
                    the Zen Care mobile experience.
                </p>


                <div class="zcs-home-app-features">

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                        Quick Service Booking
                    </span>

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                        Easy Service Selection
                    </span>

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                        Booking Updates
                    </span>

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                        Customer Support
                    </span>

                </div>


                <div class="zcs-home-app-download">

                    <!-- QR -->
                    <?php if (site_app_qr_src() !== ''): ?>
                    <div class="zcs-home-app-qr">

                        <img
                            src="<?= site_e(site_app_qr_src()) ?>"
                            alt="Zen Care App QR Code"
                        >

                    </div>
                    <?php endif; ?>


                    <div class="zcs-home-app-download-text">

                        <strong>
                            Scan to Get Started
                        </strong>

                        <p>
                            Scan the QR code using your phone
                            to explore Zen Care Services.
                        </p>


                        <?php if (site_setting('play_store_url') !== '' || site_setting('app_store_url') !== ''): ?>
                        <div class="zcs-home-app-store-buttons">

                            <?php if (site_setting('play_store_url') !== ''): ?>
                            <a href="<?= site_e(site_setting('play_store_url')) ?>" class="zcs-home-store-btn" target="_blank" rel="noopener">

                                <i class="fa-brands fa-google-play"></i>

                                <span>
                                    <small>GET IT ON</small>
                                    Google Play
                                </span>

                            </a>
                            <?php endif; ?>


                            <?php if (site_setting('app_store_url') !== ''): ?>
                            <a href="<?= site_e(site_setting('app_store_url')) ?>" class="zcs-home-store-btn" target="_blank" rel="noopener">

                                <i class="fa-brands fa-apple"></i>

                                <span>
                                    <small>DOWNLOAD ON THE</small>
                                    App Store
                                </span>

                            </a>
                            <?php endif; ?>

                        </div>
                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- RIGHT -->
            <div class="zcs-home-app-visual">

                <div class="zcs-home-phone">

                    <div class="zcs-home-phone-top"></div>

                    <div class="zcs-home-phone-screen">

                        <div class="zcs-home-phone-brand">
                            <span>
                                <i class="fa-solid fa-house"></i>
                            </span>

                            <div>
                                <small>WELCOME TO</small>
                                <strong>ZEN CARE</strong>
                            </div>
                        </div>


                        <h3>
                            What do you need today?
                        </h3>


                        <div class="zcs-home-phone-services">

                            <span>
                                <i class="fa-solid fa-snowflake"></i>
                                AC
                            </span>

                            <span>
                                <i class="fa-solid fa-broom"></i>
                                Cleaning
                            </span>

                            <span>
                                <i class="fa-solid fa-bug"></i>
                                Pest
                            </span>

                            <span>
                                <i class="fa-solid fa-hammer"></i>
                                Carpenter
                            </span>

                        </div>


                        <div class="zcs-home-phone-book">

                            <small>
                                NEED A SERVICE?
                            </small>

                            <strong>
                                Book a Professional
                            </strong>

                            <button type="button">
                                Book Now
                            </button>

                        </div>

                    </div>

                </div>


                <span class="zcs-home-app-float zcs-home-app-float-one">
                    <i class="fa-solid fa-calendar-check"></i>
                    Easy Booking
                </span>

                <span class="zcs-home-app-float zcs-home-app-float-two">
                    <i class="fa-solid fa-shield-heart"></i>
                    Trusted Service
                </span>

            </div>

        </div>

    </div>

</section>


<?php include('footer.php'); ?>