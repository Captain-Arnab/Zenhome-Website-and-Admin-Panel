<?php
/**
 * SMS: templates (used by the booking notification hooks), promotional
 * campaigns and the send log. Delivery goes through sms_send() in
 * core/notify.php and is only reported as sent when the gateway accepts it.
 */

function sms_template_row(array $r): array
{
    return [
        'id'      => (int) $r['id'],
        'key'     => $r['template_key'],
        'name'    => $r['name'],
        'dlt_id'  => (string) ($r['dlt_template_id'] ?? ''),
        'body'    => $r['body'],
        'status'  => (int) $r['status'] === 1,
        'updated' => $r['updated_at'],
    ];
}

function sms_templates(array $in, ?array $admin): array
{
    $rows = q_all('SELECT * FROM sms_templates ORDER BY id');
    return ok(['items' => array_map('sms_template_row', $rows), 'variables' => SMS_VARIABLES]);
}

/** Gateway readiness for the page header (no credentials exposed). */
function sms_gateway_status(array $in, ?array $admin): array
{
    $c = sms_config();
    $reason = !$c['enabled'] ? 'SMS sending is disabled (SMS_ENABLED is not true in .env).'
        : (!$c['user'] || !$c['password'] || !$c['sender'] || !$c['peid'] ? 'SMS gateway credentials are missing in .env.' : null);
    return ok(['ready' => $reason === null, 'message' => $reason ?? 'SMS gateway configured.']);
}

function sms_template_save(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id     = $v->int('id', 'Template', ['required' => true, 'min' => 1]);
    $name   = $v->str('name', 'Template name', ['required' => true, 'max' => 100]);
    $dlt    = $v->str('dlt_id', 'DLT template ID', ['pattern' => '/^\d{19}$/', 'pattern_message' => 'DLT ID must be 19 digits.', 'default' => '']);
    $body   = $v->str('body', 'Message', ['required' => true, 'max' => 480]);
    $status = $v->bool('status');
    $v->check();
    if (!q_value('SELECT 1 FROM sms_templates WHERE id = ?', [$id])) {
        throw not_found('Template');
    }
    preg_match_all('/\{[a-z_]+\}/', $body, $m);
    $unknown = array_diff(array_unique($m[0]), SMS_VARIABLES);
    if ($unknown) {
        throw new ApiException('Unknown variable(s): ' . implode(', ', $unknown), 422, ['body' => 'Unknown variable(s): ' . implode(', ', $unknown)]);
    }
    q('UPDATE sms_templates SET name = ?, dlt_template_id = ?, body = ?, status = ?, updated_at = ? WHERE id = ?', [$name, $dlt ?: null, $body, $status ? 1 : 0, now(), $id]);
    return ok(sms_template_row(q_one('SELECT * FROM sms_templates WHERE id = ?', [$id])), 'SMS template updated.');
}

function sms_template_status(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Template', ['required' => true, 'min' => 1]);
    $status = $v->bool('status');
    $v->check();
    $row = q_one('SELECT name FROM sms_templates WHERE id = ?', [$id]);
    if (!$row) {
        throw not_found('Template');
    }
    q('UPDATE sms_templates SET status = ?, updated_at = ? WHERE id = ?', [$status ? 1 : 0, now(), $id]);
    return ok(['id' => $id, 'status' => $status], $row['name'] . ($status ? ' activated.' : ' deactivated.'));
}

/** Promotional campaign using the "promotional" template's DLT id. */
function sms_send_promo(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $audience = $v->enum('audience', 'Audience', ['all', 'active', 'inactive_30', 'custom'], ['required' => true]);
    $message  = $v->str('message', 'Message', ['required' => true, 'max' => 480]);
    $couponCode = $v->str('coupon', 'Coupon', ['max' => 20, 'default' => '']);
    $schedule = $v->datetime('schedule_at', 'Schedule');
    if ($schedule && $schedule <= now()) {
        $v->error('schedule_at', 'Schedule time must be in the future.');
    }
    $v->check();

    $vars = [];
    if ($couponCode !== '') {
        $coupon = q_one('SELECT code, type, value FROM coupons WHERE code = ? AND status = 1', [$couponCode]);
        if (!$coupon) {
            throw new ApiException('Coupon not found or inactive.', 422, ['coupon' => 'Choose an active coupon.']);
        }
        $vars['coupon_code'] = $coupon['code'];
        $vars['offer_value'] = $coupon['type'] === 'percentage' ? rtrim(rtrim($coupon['value'], '0'), '.') . '%' : 'Rs ' . number_format((float) $coupon['value'], 0);
    }
    $message = sms_render($message, $vars);
    [$recipients, $label] = customer_audience($audience, $in);
    if (!$recipients) {
        throw new ApiException('No customers match this audience.', 422, ['audience' => 'No matching customers.']);
    }

    if ($schedule) {
        // The cron job re-resolves the audience at send time from these inputs.
        q(
            'INSERT INTO sms_log (template_key, message, recipients, status, provider_response, scheduled_at, audience, audience_params, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'promotional', $message, count($recipients), 'Scheduled', SCHEDULED_PENDING_DETAIL, $schedule,
                $audience, json_encode(array_intersect_key($in, array_flip(['mobiles']))), $admin['id'], now(),
            ]
        );
        $note = scheduler_active()
            ? ' The scheduler will send it; the result will show in the SMS log.'
            : ' Warning: the scheduler (cron/send_scheduled.php) has not run recently, so it will only be sent once the cron job is set up.';
        return ok(['recipients' => count($recipients), 'status' => 'Scheduled'], 'Campaign scheduled for ' . date('d M Y, h:i A', strtotime($schedule)) . '.' . $note, 201);
    }

    $template = q_one("SELECT dlt_template_id, status FROM sms_templates WHERE template_key = 'promotional'");
    if (!$template || (int) $template['status'] !== 1) {
        q(
            'INSERT INTO sms_log (template_key, message, recipients, status, provider_response, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['promotional', $message, count($recipients), 'Not sent', 'The "Promotional" SMS template is inactive.', $admin['id'], now()]
        );
        throw new ApiException('Activate the "Promotional" SMS template (with its DLT id) before sending campaigns.', 409);
    }
    $result = bulk_send($recipients, ['sms'], 'Promotional SMS', $message, 'promotional', $template['dlt_template_id'], $admin);
    return ok(['recipients' => count($recipients), 'audience' => $label] + $result, 'Campaign to ' . count($recipients) . ' number(s): ' . $result['status'] . '.', 201);
}

/** Sends the given text to the logged-in admin's own mobile. */
function sms_send_test(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $message = $v->str('message', 'Message', ['required' => true, 'max' => 480]);
    $v->check();
    $template = q_one("SELECT dlt_template_id FROM sms_templates WHERE template_key = 'promotional'");
    $result = sms_send($admin['mobile'] ?? null, sms_render($message, ['customer_name' => $admin['first_name'] ?? 'Admin']), 'promotional', $template['dlt_template_id'] ?? null, ['admin_id' => $admin['id']]);
    if ($result['status'] !== 'Sent') {
        throw new ApiException('Test SMS not sent: ' . $result['detail'], 409);
    }
    return ok($result, 'Test SMS sent to your mobile.');
}

function sms_log(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['l.mobile', 'l.message'], trim((string) $in['search']), $params);
    }
    if (!empty($in['status'])) {
        $where[] = 'l.status = ?';
        $params[] = (string) $in['status'];
    }
    if (!empty($in['booking_id'])) {
        $where[] = 'l.booking_id = ?';
        $params[] = (int) $in['booking_id'];
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM sms_log l WHERE $sqlWhere", $params);
    $rows = q_all("SELECT l.*, t.name AS template_name FROM sms_log l LEFT JOIN sms_templates t ON t.template_key = l.template_key WHERE $sqlWhere ORDER BY l.created_at DESC, l.id DESC LIMIT $limit OFFSET $offset", $params);
    $items = array_map(fn($r) => [
        'id'         => (int) $r['id'],
        'mobile'     => $r['mobile'] ? format_mobile($r['mobile']) : ((int) $r['recipients'] . ' recipient(s)'),
        'template'   => $r['template_name'] ?: ($r['template_key'] ?: 'Custom'),
        'message'    => $r['message'],
        'recipients' => (int) $r['recipients'],
        'status'     => $r['status'],
        'detail'     => (string) $r['provider_response'],
        'sent_at'    => $r['scheduled_at'] ?: $r['created_at'],
        'booking_id' => $r['booking_id'] !== null ? (int) $r['booking_id'] : null,
    ], $rows);
    return ok(paginated($items, $total, $page, $limit));
}

return [
    'templates'       => ['GET',  'sms_templates'],
    'gateway'         => ['GET',  'sms_gateway_status'],
    'template_save'   => ['POST', 'sms_template_save'],
    'template_status' => ['POST', 'sms_template_status'],
    'send_promo'      => ['POST', 'sms_send_promo'],
    'send_test'       => ['POST', 'sms_send_test'],
    'log'             => ['GET',  'sms_log'],
];
