<?php
/**
 * Public: website settings from Admin > Site Settings (no login).
 *
 * GET api/site_settings.php
 * data.settings: only keys that have a value, e.g.
 *   {company_name, phone, phone_display, support_phone, support_phone_display,
 *    email, whatsapp, whatsapp_url, address, address_lines[], map_query,
 *    working_hours, facebook_url, instagram_url, youtube_url, play_store_url,
 *    app_store_url, app_qr_link, app_qr_image, footer_text, logo, favicon,
 *    about_image_1, about_image_2, contact_image}
 * Images are absolute URLs. Revalidated on every request (ETag), so admin
 * changes show up immediately.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/site_settings_helper.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

try {
    $resolved = site_settings_resolve(site_settings_stored(public_db()));
} catch (PDOException $e) {
    error_log('[api/site_settings] ' . $e->getMessage());
    public_json(500, 'Could not load settings. Please try again.');
}

public_json_revalidate('OK', ['settings' => site_settings_public($resolved, public_site_url())]);
