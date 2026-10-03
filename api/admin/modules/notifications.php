<?php
/**
 * Customer notifications: compose/send (push, SMS, email hooks) + history.
 * Automatic booking notifications are written by notify_booking_customer().
 */

const NOTIFICATION_TYPES = [
    'booking_confirmation' => 'Booking Confirmation',
    'assignment'           => 'Professional Assignment',
    'status_update'        => 'Service Status Update',
    'cancellation'         => 'Booking Cancellation',
    'payment'              => 'Payment Confirmation',
    'promotional'          => 'Promotional',
    'support'              => 'Support',
];
function notifications_types(array $in, ?array $admin): array
{
    return ok(['items' => NOTIFICATION_TYPES]);
}

function notifications_send(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $type     = $v->enum('type', 'Type', array_keys(NOTIFICATION_TYPES), ['required' => true]);
    $audience = $v->enum('audience', 'Audience', ['all', 'active', 'no_booking_30', 'city', 'booking', 'single'], ['required' => true]);
    $title    = $v->str('title', 'Title', ['required' => true, 'max' => 65]);
    $message  = $v->str('message', 'Message', ['required' => true, 'max' => 240]);
    $schedule = $v->datetime('schedule_at', 'Schedule');
    $channels = array_values(array_intersect($v->strings('channels', 10), ['push', 'sms', 'email']));
    if (!$channels) {
        $v->error('channels', 'Select at least one channel.');
    }
    if ($schedule && $schedule <= now()) {
        $v->error('schedule_at', 'Schedule time must be in the future.');
    }
    $v->check();

    [$recipients, $label] = customer_audience($audience, $in);
    if (!$recipients) {
        throw new ApiException('No customers match this audience.', 422, ['audience' => 'No matching customers.']);
    }
    $single = count($recipients) === 1 ? $recipients[0] : null;
    $bookingId = null;
    if ($audience === 'booking') {
        $code = ltrim(trim((string) $in['target']), '#');
        $bookingId = q_value('SELECT ID FROM service_booking WHERE unique_booking_id = ? OR ID = ? LIMIT 1', [$code, ctype_digit($code) ? (int) $code : 0]);
    }

    if ($schedule) {
        $result = ['status' => 'Scheduled', 'detail' => SCHEDULED_PENDING_DETAIL, 'channels' => []];
    } else {
        $template = q_one('SELECT dlt_template_id FROM sms_templates WHERE template_key = ? AND status = 1', [$type]);
        $result = bulk_send($recipients, $channels, $title, $message, $type, $template['dlt_template_id'] ?? null, $admin);
    }

    // The cron job re-resolves the audience at send time from these inputs.
    $audienceParams = $schedule ? json_encode(array_intersect_key($in, array_flip(['city', 'target', 'mobiles']))) : null;

    q(
        'INSERT INTO customer_notifications (title, message, type, audience, audience_label, audience_params, user_id, booking_id, channels, recipients, status, status_detail, scheduled_at, sent_at, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $title, $message, $type, $audience, mb_substr($label, 0, 150), $audienceParams, $single['id'] ?? null, $bookingId, implode(',', $channels),
            count($recipients), $result['status'], $result['detail'], $schedule,
            in_array($result['status'], ['Sent', 'Partially sent'], true) ? now() : null, $admin['id'], now(),
        ]
    );

    $messageOut = $schedule
        ? 'Scheduled for ' . date('d M Y, h:i A', strtotime($schedule)) . '.' . (scheduler_active()
            ? ' The scheduler will send it; the result will show in History.'
            : ' Warning: the scheduler (cron/send_scheduled.php) has not run recently, so it will only be sent once the cron job is set up.')
        : 'Notification to ' . count($recipients) . ' customer(s): ' . $result['status'] . '.';
    return ok(['id' => (int) db()->lastInsertId(), 'recipients' => count($recipients)] + $result, $messageOut, 201);
}

function notifications_history(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['n.title', 'n.message', 'n.audience_label'], trim((string) $in['search']), $params);
    }
    if (!empty($in['type'])) {
        $where[] = 'n.type = ?';
        $params[] = (string) $in['type'];
    }
    if (!empty($in['status'])) {
        $where[] = 'n.status = ?';
        $params[] = (string) $in['status'];
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value("SELECT COUNT(*) FROM customer_notifications n WHERE $sqlWhere", $params);
    $rows = q_all("SELECT n.*, a.first_name AS admin_first FROM customer_notifications n LEFT JOIN admin a ON a.id = n.created_by WHERE $sqlWhere ORDER BY n.created_at DESC, n.id DESC LIMIT $limit OFFSET $offset", $params);
    $items = array_map(fn($r) => [
        'id'         => (int) $r['id'],
        'title'      => $r['title'],
        'message'    => $r['message'],
        'type'       => $r['type'],
        'type_label' => NOTIFICATION_TYPES[$r['type']] ?? ucfirst(str_replace('_', ' ', $r['type'])),
        'audience'   => $r['audience_label'] ?: $r['audience'],
        'channel'    => implode(' + ', array_map('ucfirst', array_filter(explode(',', $r['channels'])))),
        'recipients' => (int) $r['recipients'],
        'status'     => $r['status'],
        'detail'     => (string) $r['status_detail'],
        'sent_at'    => $r['sent_at'] ?: ($r['scheduled_at'] ?: $r['created_at']),
        'booking_id' => $r['booking_id'] !== null ? (int) $r['booking_id'] : null,
        'by'         => $r['admin_first'] ?: 'System',
    ], $rows);
    return ok(paginated($items, $total, $page, $limit));
}

return [
    'types'   => ['GET',  'notifications_types'],
    'send'    => ['POST', 'notifications_send'],
    'history' => ['GET',  'notifications_history'],
];
