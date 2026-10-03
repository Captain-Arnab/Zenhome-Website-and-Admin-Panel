<?php
/**
 * Site settings managed in Admin > Site Settings (site_settings table):
 * contact details, social and app links, footer text, logo and section
 * images. Read by the website templates, api/site_settings.php and the
 * admin module (api/admin/modules/settings.php).
 *
 * Every key has the website's original value as its default. Keys marked
 * 'fallback' always show something (an empty or missing value uses the
 * default); the others are optional and hidden on the website when empty.
 * Nothing is cached between requests, so admin changes show immediately.
 */
require_once __DIR__ . '/site_content.php';

const SITE_SETTING_GROUPS = ['Homepage', 'Contact', 'Social & Apps', 'Footer', 'Branding & Images', 'Admin Alerts'];

/** key => [label, type, group, default, fallback, help] */
const SITE_SETTING_DEFS = [
    'hero_tag'            => ['Homepage hero label', 'text', 'Homepage', 'HOME SERVICES MADE SIMPLE', true, 'Small label above the homepage welcome heading.'],
    'hero_subtitle'       => ['Homepage hero subtitle', 'text', 'Homepage', 'What are you looking for?', true, 'Line below "Welcome To ' . '{Company name}" on the homepage.'],
    'hero_rating_value'   => ['Homepage rating badge', 'text', 'Homepage', '4.9 / 5', true, 'Rating shown on the homepage hero (e.g. "4.9 / 5").'],
    'hero_bookings_text'  => ['Homepage bookings badge', 'text', 'Homepage', '300+ Bookings Completed', true, ''],
    'footer_cta_tag'      => ['Footer CTA label', 'text', 'Footer', 'NEED A PROFESSIONAL?', true, 'Small label above the footer call-to-action heading.'],
    'footer_cta_heading'  => ['Footer CTA heading', 'text', 'Footer', 'Book a Trusted Home Service Today.', true, ''],
    'footer_cta_text'     => ['Footer CTA text', 'textarea', 'Footer', 'Fast booking, experienced professionals and reliable doorstep services for your home.', true, ''],
    'footer_service_area_text' => ['Footer "Service Area" value', 'text', 'Footer', 'Professional Doorstep Services', true, 'Shown next to the location icon in the footer contact column.'],
    'trust_item_1'        => ['Trust strip 1 (title / text)', 'textarea', 'Footer', "Trusted Experts\nProfessional service team", true, 'First line = title, second line = description.'],
    'trust_item_2'        => ['Trust strip 2 (title / text)', 'textarea', 'Footer', "Easy Booking\nQuick and convenient", true, ''],
    'trust_item_3'        => ['Trust strip 3 (title / text)', 'textarea', 'Footer', "Doorstep Service\nAt your preferred location", true, ''],
    'trust_item_4'        => ['Trust strip 4 (title / text)', 'textarea', 'Footer', "Customer Support\nWe're here to assist you", true, ''],
    'company_name'  => ['Company name', 'text', 'Footer', 'Zen Home Experts', true, 'Shown in the footer and copyright line.'],
    'phone'         => ['Phone number', 'phone', 'Contact', '8179550262', true, 'Main booking number (header, footer, service pages).'],
    'support_phone' => ['Support line', 'phone', 'Contact', '8179550262', true, 'The "Need Help?" and "Call for Assistance" number in the header and mobile menu.'],
    'email'         => ['Email address', 'email', 'Contact', 'zencareservices@gmail.com', true, ''],
    'whatsapp'      => ['WhatsApp number', 'phone', 'Contact', '8179550262', false, '10-digit mobile number. Leave empty to hide the WhatsApp icon.'],
    'address'       => ['Address', 'textarea', 'Contact', "No 4, Near by RTO Office,\nYSR Nagar, Dinone,\nAndhra Pradesh, India - 518222", true, 'One line per row, as it should appear on the contact page.'],
    'map_query'     => ['Map location', 'text', 'Contact', 'YSR Nagar, Andhra Pradesh', false, 'Place or address searched on the contact page map. Leave empty to hide the map.'],
    'working_hours' => ['Working hours', 'text', 'Contact', '', false, 'e.g. Mon - Sun, 9:00 AM - 8:00 PM. Leave empty to hide.'],
    'facebook_url'  => ['Facebook page', 'url', 'Social & Apps', '', false, 'Full https:// link. Empty links are hidden.'],
    'instagram_url' => ['Instagram profile', 'url', 'Social & Apps', '', false, ''],
    'youtube_url'   => ['YouTube channel', 'url', 'Social & Apps', '', false, ''],
    'play_store_url'=> ['Google Play link', 'url', 'Social & Apps', '', false, 'Leave empty to hide the Google Play button.'],
    'app_store_url' => ['App Store link', 'url', 'Social & Apps', '', false, 'Leave empty to hide the App Store button.'],
    'app_qr_link'   => ['QR code link', 'url', 'Social & Apps', 'https://zenhomeexperts.com/', false, 'Used to draw the homepage QR code when no QR image is uploaded.'],
    'app_qr_image'  => ['App QR image', 'image', 'Social & Apps', '', false, 'Optional. Replaces the generated QR code.'],
    'footer_text'   => ['Footer about text', 'textarea', 'Footer', 'Reliable home services delivered by trained professionals. From appliance repair and cleaning to pest control, carpentry and salon services, we make home care simple and convenient.', true, ''],
    'logo'          => ['Logo', 'image', 'Branding & Images', 'images/logo.png', true, 'Header logo. PNG with a transparent background works best.'],
    'favicon'       => ['Favicon', 'image', 'Branding & Images', '', false, 'Browser tab icon (square, 64x64 or larger). Uses the logo when empty.'],
    'about_image_1' => ['About page image (top)', 'image', 'Branding & Images', 'images/site/about-home-services.webp', true, ''],
    'about_image_2' => ['About page image (lower)', 'image', 'Branding & Images', 'images/site/about-professionals.webp', true, ''],
    'contact_image' => ['Contact page image', 'image', 'Branding & Images', 'images/site/contact-support.webp', true, ''],
    'admin_alert_enabled' => ['Send admin SMS alerts', 'bool', 'Admin Alerts', '0', true, 'When on, an SMS is sent to the numbers below each time a booking is confirmed.'],
    'admin_alert_mobiles'  => ['Admin alert mobile numbers', 'mobile_list', 'Admin Alerts', '', true, 'Comma-separated 10-digit mobile numbers (max 5) to notify on new bookings.'],
];

/** Stored values (key => string) or null when the table is unavailable. */
function site_settings_stored(PDO $pdo): array
{
    $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return array_map(fn($v) => trim((string) $v), $rows);
}

/** Effective value of every key for the website: stored value, else the default (see 'fallback'). */
function site_settings_resolve(array $stored): array
{
    $out = [];
    foreach (SITE_SETTING_DEFS as $key => [$label, $type, $group, $default, $fallback]) {
        $value = array_key_exists($key, $stored) ? $stored[$key] : $default;
        if ($type === 'image' && $value !== '' && site_image_path($value) === null) {
            $value = '';
        }
        if ($value === '' && $fallback) {
            $value = $default;
        }
        $out[$key] = $value;
    }
    return $out;
}

/** For website pages. Never throws: an unavailable database gives the defaults. */
function site_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $stored = [];
    try {
        $pdo = site_content_db();
        $stored = $pdo ? site_settings_stored($pdo) : [];
    } catch (Throwable $e) {
        error_log('[ZenHomeExperts site settings] ' . $e->getMessage());
    }
    return $cache = site_settings_resolve($stored);
}

function site_setting(string $key): string
{
    return site_settings()[$key] ?? '';
}

/** "81795 50262" for 10-digit numbers, digits as stored otherwise. */
function site_phone_display(string $digits): string
{
    return preg_match('/^\d{10}$/', $digits) ? substr($digits, 0, 5) . ' ' . substr($digits, 5) : $digits;
}

/** "+91 81795 50262" for 10-digit numbers. */
function site_phone_intl(string $digits): string
{
    return preg_match('/^\d{10}$/', $digits) ? '+91 ' . site_phone_display($digits) : $digits;
}

function site_tel(string $key = 'phone'): string
{
    return 'tel:' . site_setting($key);
}

function site_whatsapp_url(): string
{
    $digits = site_setting('whatsapp');
    if ($digits === '') {
        return '';
    }
    return 'https://wa.me/' . (strlen($digits) === 10 ? '91' . $digits : $digits);
}

/** Address rows (one per line). */
function site_address_lines(): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', site_setting('address'))), 'strlen'));
}

/** Admin alert mobile numbers (10-digit), parsed from the comma-separated setting; [] when alerts are off or none configured. */
function site_admin_alert_mobiles(): array
{
    if (site_setting('admin_alert_enabled') !== '1') {
        return [];
    }
    $out = [];
    foreach (explode(',', site_setting('admin_alert_mobiles')) as $raw) {
        $digits = preg_replace('/\D+/', '', $raw);
        if (preg_match('/^[6-9]\d{9}$/', $digits)) {
            $out[] = $digits;
        }
    }
    return array_slice(array_values(array_unique($out)), 0, 5);
}

/** Footer trust-strip item ('title' / 'text'); first line = title, rest = description. */
function site_trust_item(string $key): array
{
    $lines = preg_split('/\R/', site_setting($key), 2);
    return ['title' => trim($lines[0] ?? ''), 'text' => trim($lines[1] ?? '')];
}

/** Uploaded QR image, else a QR drawn for the QR link, else ''. */
function site_app_qr_src(): string
{
    $image = site_setting('app_qr_image');
    if ($image !== '') {
        return $image;
    }
    $link = site_setting('app_qr_link');
    return $link !== '' ? 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($link) : '';
}

/** Social links that are set: [[label, icon, url], ...]. */
function site_social_links(): array
{
    $links = [];
    foreach ([['Facebook', 'fa-brands fa-facebook-f', site_setting('facebook_url')], ['Instagram', 'fa-brands fa-instagram', site_setting('instagram_url')], ['Youtube', 'fa-brands fa-youtube', site_setting('youtube_url')], ['WhatsApp', 'fa-brands fa-whatsapp', site_whatsapp_url()]] as $link) {
        if ($link[2] !== '') {
            $links[] = $link;
        }
    }
    return $links;
}

/**
 * Public API shape (api/site_settings.php): only keys with a value, images
 * as absolute URLs, plus ready-made phone/WhatsApp formats.
 */
function site_settings_public(array $resolved, string $baseUrl): array
{
    $out = [];
    foreach (SITE_SETTING_DEFS as $key => [$label, $type]) {
        $value = $resolved[$key] ?? '';
        if ($value === '') {
            continue;
        }
        if ($type === 'image') {
            $out[$key] = rtrim($baseUrl, '/') . '/' . $value;
            continue;
        }
        $out[$key] = $value;
        if ($type === 'phone') {
            $out[$key . '_display'] = site_phone_intl($value);
        }
    }
    if (!empty($out['whatsapp'])) {
        $out['whatsapp_url'] = 'https://wa.me/' . (strlen($out['whatsapp']) === 10 ? '91' . $out['whatsapp'] : $out['whatsapp']);
    }
    if (!empty($out['address'])) {
        $out['address_lines'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $out['address'])), 'strlen'));
    }
    return $out;
}
