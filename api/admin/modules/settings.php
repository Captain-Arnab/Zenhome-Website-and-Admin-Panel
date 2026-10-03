<?php
/**
 * Site settings (site_settings table). Keys, types and defaults are defined
 * in api/site_settings_helper.php (shared with the website and the public
 * endpoint api/site_settings.php).
 */
require_once ZC_ROOT . '/api/site_settings_helper.php';

function settings_get(array $in, ?array $admin): array
{
    $stored = site_settings_stored(db());
    $items = [];
    foreach (SITE_SETTING_DEFS as $key => [$label, $type, $group, $default, $fallback, $help]) {
        $value = array_key_exists($key, $stored) ? $stored[$key] : $default;
        $items[] = [
            'key'      => $key,
            'label'    => $label,
            'type'     => $type,
            'group'    => $group,
            'help'     => $help,
            'value'    => $value,
            'default'  => $default,
            'fallback' => $fallback,
            'image'    => $type === 'image' && $value !== '' ? image_path($value) : null,
        ];
    }
    return ok(['groups' => SITE_SETTING_GROUPS, 'items' => $items]);
}

/** Comma-separated 10-digit Indian mobile numbers, max 5; invalid entries are rejected, not silently dropped. */
function settings_mobile_list(Validator $v, string $key, string $label): ?string
{
    $raw = trim((string) ($v->str($key, $label, ['max' => 150, 'default' => '']) ?? ''));
    if ($raw === '') {
        return '';
    }
    $numbers = [];
    foreach (explode(',', $raw) as $part) {
        $digits = preg_replace('/\D+/', '', $part);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (!preg_match('/^[6-9]\d{9}$/', $digits)) {
            $v->error($key, 'Enter 10-digit mobile numbers separated by commas, e.g. 9876543210,9123456780.');
            return null;
        }
        $numbers[] = $digits;
    }
    if (count($numbers) > 5) {
        $v->error($key, 'Enter at most 5 mobile numbers.');
        return null;
    }
    return implode(',', array_values(array_unique($numbers)));
}

/** Digits of a phone number: 10-digit Indian numbers, or 10-13 digits with country / STD code. */
function settings_phone(Validator $v, string $key, string $label, bool $mobileOnly): ?string
{
    $raw = $v->str($key, $label, ['max' => 20, 'default' => '']);
    if ($raw === null || $raw === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $raw);
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    if ($mobileOnly ? !preg_match('/^[6-9]\d{9}$/', $digits) : !preg_match('/^\d{10,13}$/', $digits)) {
        $v->error($key, $mobileOnly ? 'Enter a valid 10-digit mobile number.' : 'Enter a valid phone number (10 to 13 digits).');
        return null;
    }
    return $digits;
}

function settings_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $values = [];
    foreach (SITE_SETTING_DEFS as $key => [$label, $type]) {
        if ($type === 'image' || !array_key_exists($key, $in)) {
            continue;
        }
        $values[$key] = match ($type) {
            'phone'       => settings_phone($v, $key, $label, $key === 'whatsapp'),
            'email'       => $v->email($key, $label, ['default' => '']),
            'url'         => $v->str($key, $label, ['max' => 255, 'default' => '', 'pattern' => '#^(https?://[^\s<>"]+)?$#i', 'pattern_message' => 'Enter a full link starting with https://']),
            'textarea'    => $v->str($key, $label, ['max' => 600, 'default' => '']),
            'bool'        => $v->bool($key) ? '1' : '0',
            'mobile_list' => settings_mobile_list($v, $key, $label),
            default       => $v->str($key, $label, ['max' => 150, 'default' => '']),
        };
    }
    $v->check();

    $stored = site_settings_stored(db());
    $oldImages = [];
    foreach (SITE_SETTING_DEFS as $key => [$label, $type, $group, $default, $fallback]) {
        if ($type !== 'image') {
            continue;
        }
        $upload = save_uploaded_image($key, 'settings');
        if ($upload !== null) {
            $values[$key] = $upload;
        } elseif (!$fallback && !empty($in['remove_' . $key]) && filter_var($in['remove_' . $key], FILTER_VALIDATE_BOOLEAN)) {
            $values[$key] = '';
        } else {
            continue;
        }
        $oldImages[] = $stored[$key] ?? '';
    }

    if (!$values) {
        return ok(null, 'Nothing to save.');
    }
    $db = db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        foreach ($values as $key => $value) {
            $stmt->execute([$key, (string) $value, $admin['id'] ?? null, now()]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    foreach ($oldImages as $old) {
        delete_uploaded_image($old);
    }
    return ok(['saved' => array_keys($values)], 'Site settings saved.');
}

return [
    'get'  => ['GET',  'settings_get'],
    'save' => ['POST', 'settings_save', 'super'],
];
