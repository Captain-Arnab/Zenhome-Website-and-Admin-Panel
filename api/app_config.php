<?php
/**
 * Public: start-up config for the customer apps (no login).
 * Values come from Admin > Site Settings > App (and Contact / Social & Apps).
 *
 * GET api/app_config.php[?platform=android|ios&version=1.2.0]
 * data: {min_version_android, min_version_ios, latest_version, force_update,
 *        maintenance, maintenance_message, support_phone, support_email,
 *        play_store_url, app_store_url}
 * With platform + version the answer also has update_required (force_update
 * on and version below that platform's minimum) and update_available.
 * Revalidated with an ETag on every request.
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/site_settings_helper.php';

public_cors('GET, OPTIONS');
public_require_method('GET');

const APP_VERSION_PATTERN = '/^\d{1,4}(\.\d{1,4}){0,3}$/';

try {
    $s = site_settings_resolve(site_settings_stored(public_db()));
} catch (PDOException $e) {
    error_log('[api/app_config] ' . $e->getMessage());
    public_json(500, 'Could not load app settings. Please try again.');
}

$version = fn(string $key): string => preg_match(APP_VERSION_PATTERN, $s[$key] ?? '') ? $s[$key] : SITE_SETTING_DEFS[$key][3];
$orNull = fn(string $key): ?string => ($s[$key] ?? '') !== '' ? $s[$key] : null;

$config = [
    'min_version_android' => $version('app_min_version_android'),
    'min_version_ios'     => $version('app_min_version_ios'),
    'latest_version'      => $version('app_latest_version'),
    'force_update'        => ($s['app_force_update'] ?? '0') === '1',
    'maintenance'         => ($s['app_maintenance'] ?? '0') === '1',
    'maintenance_message' => (string) ($s['app_maintenance_message'] ?? ''),
    'support_phone'       => $orNull('support_phone'),
    'support_email'       => $orNull('email'),
    'play_store_url'      => $orNull('play_store_url'),
    'app_store_url'       => $orNull('app_store_url'),
];

$in = public_input();
$platform = strtolower(public_str($in, 'platform'));
$current = public_str($in, 'version');
if ($platform !== '' || $current !== '') {
    $errors = [];
    if (!in_array($platform, ['android', 'ios'], true)) {
        $errors['platform'] = 'Use android or ios.';
    }
    if (!preg_match(APP_VERSION_PATTERN, $current)) {
        $errors['version'] = 'Use a version number like 1.2.0.';
    }
    if ($errors) {
        public_json(422, reset($errors), null, $errors);
    }
    $below = version_compare($current, $config['min_version_' . $platform], '<');
    $config['update_required'] = $config['force_update'] && $below;
    $config['update_available'] = version_compare($current, $config['latest_version'], '<');
}

public_json_revalidate('OK', $config);
