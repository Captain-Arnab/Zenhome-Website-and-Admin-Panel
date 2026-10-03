<?php
/**
 * Customer notification hooks (SMS + push + email).
 *
 * Every attempt is logged (sms_log / customer_notifications) with its real
 * outcome. Nothing is reported as sent unless a provider accepted it.
 *
 * SMS uses the Bulk SMS Hyderabad gateway already used for OTPs
 * (api/sms_sender.php). Transactional SMS needs, per template, a
 * DLT-approved template id, plus these .env values:
 *   SMS_ENABLED=true
 *   SMS_GATEWAY_USER, SMS_GATEWAY_PASSWORD, SMS_GATEWAY_SENDER, SMS_GATEWAY_PEID
 *   SMS_GATEWAY_URL (optional, defaults to the Bulk SMS Hyderabad endpoint)
 */

const SMS_VARIABLES = [
    '{customer_name}', '{booking_id}', '{service_name}', '{booking_date}', '{time_slot}',
    '{professional_name}', '{professional_mobile}', '{booking_status}', '{reason}',
    '{amount}', '{transaction_id}', '{coupon_code}', '{offer_value}',
    '{payment_mode}', '{customer_mobile}', '{area}',
];

const SCHEDULED_PENDING_DETAIL = 'Waiting for cron/send_scheduled.php.';

/** Summary written by cron/send_scheduled.php after each run, or null if it never ran. */
function scheduler_last_run(): ?array
{
    $file = ZC_ROOT . '/cron/last_run.json';
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) && !empty($data['finished_at']) ? $data : null;
}

/** True when the cron job finished a run in the last 15 minutes. */
function scheduler_active(): bool
{
    $run = scheduler_last_run();
    return $run !== null && strtotime($run['finished_at']) >= time() - 900;
}

function sms_config(): array
{
    return [
        'enabled'  => env_flag('SMS_ENABLED'),
        'url'      => env_value('SMS_GATEWAY_URL', 'http://tra.bulksmshyderabad.co.in/websms/sendsms.aspx'),
        'user'     => env_value('SMS_GATEWAY_USER'),
        'password' => env_value('SMS_GATEWAY_PASSWORD'),
        'sender'   => env_value('SMS_GATEWAY_SENDER'),
        'peid'     => env_value('SMS_GATEWAY_PEID'),
    ];
}

/** Why SMS cannot be sent right now, or null when it can. */
function sms_unavailable_reason(?string $dltTemplateId): ?string
{
    $c = sms_config();
    if (!$c['enabled']) {
        return 'SMS sending is disabled (set SMS_ENABLED=true in .env).';
    }
    if (!$c['user'] || !$c['password'] || !$c['sender'] || !$c['peid']) {
        return 'SMS gateway credentials are not configured in .env.';
    }
    if (!$dltTemplateId) {
        return 'No DLT template id set for this template.';
    }
    return null;
}

function sms_render(string $body, array $vars): string
{
    $map = [];
    foreach ($vars as $key => $value) {
        $map['{' . trim($key, '{}') . '}'] = (string) $value;
    }
    return trim(preg_replace('/\s{2,}/', ' ', strtr($body, $map)));
}

/**
 * Sends one SMS through the gateway and logs it.
 * @return array{status:string,detail:string,log_id:int}
 */
function sms_send(?string $mobile, string $message, ?string $templateKey, ?string $dltTemplateId, array $context = []): array
{
    $mobile = normalize_mobile($mobile);
    $status = 'Not sent';
    $detail = '';

    if ($mobile === null) {
        $detail = 'Customer has no valid mobile number.';
    } elseif ($reason = sms_unavailable_reason($dltTemplateId)) {
        $detail = $reason;
    } else {
        $c = sms_config();
        $url = $c['url'] . '?' . http_build_query([
            'userid'   => $c['user'],
            'password' => $c['password'],
            'sender'   => $c['sender'],
            'mobileno' => $mobile,
            'msg'      => $message,
            'peid'     => $c['peid'],
            'tpid'     => $dltTemplateId,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
        $response = curl_exec($ch);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error || $http >= 400) {
            $status = 'Failed';
            $detail = $error ?: "Gateway HTTP $http";
        } else {
            $status = 'Sent';
            $detail = mb_substr(trim((string) $response), 0, 500);
        }
    }

    if ($status !== 'Sent') {
        error_log("[ZenHomeExperts admin] SMS $status ($templateKey): $detail");
    }

    q(
        'INSERT INTO sms_log (mobile, user_id, booking_id, template_key, message, recipients, status, provider_response, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?)',
        [$mobile, $context['user_id'] ?? null, $context['booking_id'] ?? null, $templateKey, $message, $status, $detail, $context['admin_id'] ?? null, now()]
    );

    return ['status' => $status, 'detail' => $detail, 'log_id' => (int) db()->lastInsertId()];
}

/**
 * Push notification hook.
 * TODO: integrate a push provider (e.g. Firebase Cloud Messaging). The
 * customer apps do not register device tokens yet (no table for them), so
 * there is nothing to deliver to. Logged, never reported as sent.
 */
function push_send(?int $userId, string $title, string $message, array $data = []): array
{
    $detail = 'Push provider not configured (no FCM credentials / device tokens).';
    error_log("[ZenHomeExperts admin] Push not sent to user #" . (int) $userId . ": $title");
    return ['status' => 'Not sent', 'detail' => $detail];
}

/**
 * Email hook.
 * TODO: configure an SMTP/mail provider. Logged, never reported as sent.
 */
function email_send(?string $email, string $subject, string $message): array
{
    error_log("[ZenHomeExperts admin] Email not sent to " . ($email ?: '-') . ": $subject");
    return ['status' => 'Not sent', 'detail' => 'Email provider not configured.'];
}

/** Combined status for several channel results. */
function combine_channel_status(array $results): array
{
    $statuses = array_column($results, 'status');
    if (!$statuses) {
        return ['Not sent', 'No channel selected.'];
    }
    $sent = count(array_filter($statuses, fn($s) => $s === 'Sent'));
    $partial = in_array('Partially sent', $statuses, true);
    $status = $sent === count($statuses) ? 'Sent' : ($sent > 0 || $partial ? 'Partially sent' : (in_array('Failed', $statuses, true) ? 'Failed' : 'Not sent'));
    $details = [];
    foreach ($results as $channel => $r) {
        $details[] = strtoupper($channel) . ': ' . $r['status'] . ($r['status'] !== 'Sent' && $r['detail'] ? ' - ' . $r['detail'] : '');
    }
    return [$status, mb_substr(implode(' | ', $details), 0, 255)];
}

/**
 * Sends one message to many customers over the chosen channels.
 * Per-recipient SMS is only attempted when the gateway is usable; otherwise
 * a single "Not sent" summary row (with the reason) is logged.
 * {customer_name} in the message is replaced per recipient.
 * @return array{status:string,detail:string,channels:array}
 */
function bulk_send(array $recipients, array $channels, string $title, string $message, ?string $templateKey, ?string $dltId, ?array $admin): array
{
    $results = [];
    $count = count($recipients);
    $adminId = $admin['id'] ?? null;

    if (in_array('sms', $channels, true)) {
        $reason = sms_unavailable_reason($dltId);
        if ($reason) {
            error_log("[ZenHomeExperts admin] Bulk SMS not sent to $count recipient(s): $reason");
            q(
                'INSERT INTO sms_log (mobile, template_key, message, recipients, status, provider_response, created_by, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)',
                [$templateKey, $message, $count, 'Not sent', $reason, $adminId, now()]
            );
            $results['sms'] = ['status' => 'Not sent', 'detail' => $reason];
        } else {
            $sent = 0;
            $failed = 0;
            foreach ($recipients as $r) {
                $text = sms_render($message, ['customer_name' => $r['name']]);
                $res = sms_send($r['mobile'], $text, $templateKey, $dltId, ['user_id' => $r['id'], 'admin_id' => $adminId]);
                $res['status'] === 'Sent' ? $sent++ : $failed++;
            }
            $results['sms'] = [
                'status' => $sent === $count ? 'Sent' : ($sent > 0 ? 'Partially sent' : 'Failed'),
                'detail' => "$sent sent, $failed not sent",
            ];
        }
    }
    if (in_array('push', $channels, true)) {
        $results['push'] = push_send(null, $title, $message, ['recipients' => $count]);
    }
    if (in_array('email', $channels, true)) {
        $results['email'] = email_send(null, $title, $message);
    }

    [$status, $detail] = combine_channel_status($results);
    return ['status' => $status, 'detail' => $detail, 'channels' => $results];
}

/**
 * Notifies the customer about a booking event and records it.
 * $event: assignment | status_update | cancellation
 * Called by assign / update_status / cancel (modules/bookings.php).
 */
function notify_booking_customer(array $booking, string $event, array $extraVars = [], ?array $admin = null): array
{
    $template = q_one('SELECT * FROM sms_templates WHERE template_key = ?', [$event]);
    $vars = array_merge([
        'customer_name'  => $booking['customer'] ?: 'Customer',
        'booking_id'     => $booking['code'],
        'service_name'   => mb_strimwidth($booking['service'], 0, 60, '...'),
        'booking_date'   => $booking['date'] ? date('d M Y', strtotime($booking['date'])) : '',
        'time_slot'      => $booking['slot'],
        'booking_status' => $booking['status'],
    ], $extraVars);

    $titles = [
        'assignment'    => 'Professional assigned',
        'status_update' => 'Booking ' . $booking['code'] . ' is now ' . $booking['status'],
        'cancellation'  => 'Booking ' . $booking['code'] . ' cancelled',
    ];
    $title   = $titles[$event] ?? 'Booking update';
    $message = $template ? sms_render($template['body'], $vars) : $title;
    $context = ['user_id' => $booking['customer_id'], 'booking_id' => $booking['id'], 'admin_id' => $admin['id'] ?? null];

    $results = [];
    if ($template && (int) $template['status'] === 1) {
        $results['sms'] = sms_send($booking['raw_mobile'] ?? $booking['mobile'], $message, $event, $template['dlt_template_id'], $context);
    } else {
        $results['sms'] = ['status' => 'Not sent', 'detail' => 'SMS template "' . $event . '" is disabled.'];
    }
    $results['push'] = push_send($booking['customer_id'], $title, $message, ['booking_id' => $booking['id']]);

    [$status, $detail] = combine_channel_status($results);
    q(
        'INSERT INTO customer_notifications (title, message, type, audience, audience_label, user_id, booking_id, channels, recipients, status, status_detail, sent_at, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)',
        [
            $title, $message, $event, 'single', ($booking['customer'] ?: 'Customer') . ' (' . $booking['code'] . ')',
            $booking['customer_id'], $booking['id'], 'push,sms', $status, $detail,
            $status === 'Sent' || $status === 'Partially sent' ? now() : null, $admin['id'] ?? null, now(),
        ]
    );

    return ['status' => $status, 'detail' => $detail, 'channels' => $results];
}

require_once ZC_ROOT . '/api/site_settings_helper.php';

/** One sms_log row recording that nothing was sent to the admin, and why. */
function notify_admin_log_skip(int $bookingId, string $reason, ?int $adminId): void
{
    q(
        'INSERT INTO sms_log (mobile, booking_id, template_key, message, recipients, status, provider_response, created_by, created_at)
         VALUES (NULL, ?, ?, ?, 0, ?, ?, ?, ?)',
        [$bookingId, 'admin_new_booking', '', 'Not sent', $reason, $adminId, now()]
    );
}

/**
 * Alerts the configured admin mobile numbers (Site Settings > Admin Alerts)
 * about a newly confirmed booking. One sms_log row per recipient; a single
 * "Not sent" row with the real reason when alerts are off, unconfigured or
 * the template cannot be used. Never throws.
 */
function notify_admin_new_booking(array $booking, ?array $admin = null): array
{
    $adminId = $admin['id'] ?? null;
    try {
        if (site_setting('admin_alert_enabled') !== '1') {
            notify_admin_log_skip($booking['id'], 'Admin SMS alerts are turned off (Site Settings > Admin Alerts).', $adminId);
            return ['status' => 'Not sent', 'detail' => 'Admin alerts are off.'];
        }
        $mobiles = site_admin_alert_mobiles();
        if (!$mobiles) {
            notify_admin_log_skip($booking['id'], 'No admin alert mobile numbers configured (Site Settings > Admin Alerts).', $adminId);
            return ['status' => 'Not sent', 'detail' => 'No admin alert mobile numbers configured.'];
        }
        $template = q_one('SELECT * FROM sms_templates WHERE template_key = ?', ['admin_new_booking']);
        if (!$template || (int) $template['status'] !== 1) {
            notify_admin_log_skip($booking['id'], 'SMS template "admin_new_booking" is disabled or missing.', $adminId);
            return ['status' => 'Not sent', 'detail' => 'Template disabled.'];
        }

        $paymentMode = $booking['payment_method'] === 'Online' ? 'Paid online' : 'Cash on service';
        $vars = [
            'booking_id'      => $booking['code'],
            'customer_name'   => $booking['customer'] ?: 'Customer',
            'customer_mobile' => $booking['raw_mobile'] ?: '-',
            'service_name'    => mb_strimwidth((string) $booking['service'], 0, 50, '...'),
            'booking_date'    => $booking['date'] ? date('d M Y', strtotime($booking['date'])) : '',
            'time_slot'       => $booking['slot'],
            'area'            => mb_strimwidth((string) ($booking['area'] ?: '-'), 0, 30, '...'),
            'amount'          => (string) (int) round((float) $booking['amount']),
            'payment_mode'    => $paymentMode,
        ];
        $message = sms_render($template['body'], $vars);

        $sent = 0;
        foreach ($mobiles as $mobile) {
            $res = sms_send($mobile, $message, 'admin_new_booking', $template['dlt_template_id'], ['booking_id' => $booking['id'], 'admin_id' => $adminId]);
            if ($res['status'] === 'Sent') {
                $sent++;
            }
        }
        $count = count($mobiles);
        $status = $sent === $count ? 'Sent' : ($sent > 0 ? 'Partially sent' : 'Not sent');
        return ['status' => $status, 'detail' => "$sent of $count sent"];
    } catch (Throwable $e) {
        error_log('[notify] admin_new_booking failed for booking #' . $booking['id'] . ': ' . $e->getMessage());
        return ['status' => 'Not sent', 'detail' => 'Internal error.'];
    }
}

/**
 * Sends the booking-confirmed SMS to the customer (template booking_confirmation)
 * and an alert SMS to the admin numbers (template admin_new_booking), exactly
 * once per booking. Call this from the cash booking path right after the
 * booking is created, and from the online-payment-success paths (never at
 * the pending stage) - paymentConfirmation.php and the webhook handler.
 *
 * service_booking.confirm_sms_sent_at is claimed atomically first, so a
 * second call for the same booking (retry, duplicate webhook delivery, page
 * refresh) is a guaranteed no-op - nothing is sent or logged again.
 *
 * Never throws: an SMS failure must never fail or delay the booking/payment
 * response.
 */
function notify_booking_confirmed(int $bookingId, ?array $admin = null): array
{
    try {
        $claimed = q('UPDATE service_booking SET confirm_sms_sent_at = ? WHERE ID = ? AND confirm_sms_sent_at IS NULL', [now(), $bookingId])->rowCount() > 0;
        if (!$claimed) {
            return ['status' => 'skipped', 'detail' => 'Confirmation SMS already sent for this booking.'];
        }
        $booking = booking_find($bookingId);
        if (!$booking) {
            return ['status' => 'skipped', 'detail' => 'Booking not found.'];
        }
        $paymentMode = $booking['payment_method'] === 'Online' ? 'Paid online' : 'Cash on service';
        $customer = notify_booking_customer($booking, 'booking_confirmation', [
            'amount'       => (string) (int) round((float) $booking['amount']),
            'payment_mode' => $paymentMode,
        ], $admin);
        $adminAlert = notify_admin_new_booking($booking, $admin);
        return ['status' => 'ok', 'customer' => $customer, 'admin' => $adminAlert];
    } catch (Throwable $e) {
        error_log('[notify] booking confirmation failed for booking #' . $bookingId . ': ' . $e->getMessage());
        return ['status' => 'error', 'detail' => $e->getMessage()];
    }
}

/**
 * Website checkout starts online payments with order_id "ZC-<unique_booking_id>"
 * (zen-pages.js, api/payments.php). Called on a successful payment from
 * paymentConfirmation.php and the webhook handler; a no-op for transaction
 * ids not in that format (app/legacy flows with no linked booking). Never
 * throws.
 */
function notify_booking_confirmed_for_transaction(string $transactionId): void
{
    if (!preg_match('/^ZC-(\d+-\d+)$/', $transactionId, $m)) {
        return;
    }
    try {
        $bookingId = q_value('SELECT ID FROM service_booking WHERE unique_booking_id = ?', [$m[1]]);
        if ($bookingId) {
            notify_booking_confirmed((int) $bookingId);
        }
    } catch (Throwable $e) {
        error_log('[notify] booking lookup failed for transaction ' . $transactionId . ': ' . $e->getMessage());
    }
}
