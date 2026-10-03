<?php
/**
 * Support tickets (support_tickets + support_ticket_messages).
 * sender_type: customer | admin (reply) | internal (remark, admins only).
 */

const TICKET_PRIORITIES = ['High', 'Medium', 'Low'];
const TICKET_CATEGORIES = ['Booking', 'Payment', 'Service', 'Account', 'General'];

function ticket_row(array $r): array
{
    $code = trim((string) ($r['booking_code'] ?? ''), ' "');
    return [
        'id'          => (int) $r['id'],
        'code'        => $r['ticket_no'] ?: 'TK-' . (1000 + (int) $r['id']),
        'customer_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
        'customer'    => full_name($r['first_name'] ?? '', $r['last_name'] ?? '') ?: ($r['user_id'] ? 'Customer #' . $r['user_id'] : 'Guest'),
        'mobile'      => format_mobile($r['phone'] ?? ''),
        'subject'     => $r['subject'],
        'message'     => (string) $r['message'],
        'booking_id'  => $r['booking_id'] !== null ? (int) $r['booking_id'] : null,
        'booking'     => $r['booking_id'] !== null ? ($code !== '' ? $code : '#' . $r['booking_id']) : '',
        'category'    => $r['category'],
        'priority'    => $r['priority'],
        'status'      => $r['status'],
        'created'     => $r['created_at'],
        'updated'     => $r['updated_at'] ?: $r['created_at'],
        'closed_at'   => $r['closed_at'],
    ];
}

function tickets_from_sql(): string
{
    return 'FROM support_tickets t
        LEFT JOIN users u ON u.ID = t.user_id
        LEFT JOIN service_booking b ON b.ID = t.booking_id';
}

function tickets_select_sql(): string
{
    return 'SELECT t.*, u.first_name, u.last_name, u.phone, b.unique_booking_id AS booking_code ' . tickets_from_sql();
}

function tickets_list(array $in, ?array $admin): array
{
    [$page, $limit, $offset] = page_params($in, 100);
    $where = ['1 = 1'];
    $params = [];
    if (!empty($in['search'])) {
        $where[] = like_clause(['t.ticket_no', 't.subject', 'u.first_name', 'u.last_name', 'u.phone', 'b.unique_booking_id'], trim((string) $in['search']), $params);
    }
    foreach (['status' => 't.status', 'priority' => 't.priority', 'category' => 't.category'] as $key => $col) {
        if (!empty($in[$key])) {
            $where[] = "$col = ?";
            $params[] = ucfirst(strtolower((string) $in[$key]));
        }
    }
    if (!empty($in['customer_id'])) {
        $where[] = 't.user_id = ?';
        $params[] = (int) $in['customer_id'];
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) q_value('SELECT COUNT(*) ' . tickets_from_sql() . " WHERE $sqlWhere", $params);
    $rows = q_all(tickets_select_sql() . " WHERE $sqlWhere ORDER BY (t.status = 'Open') DESC, FIELD(t.priority, 'High', 'Medium', 'Low'), COALESCE(t.updated_at, t.created_at) DESC LIMIT $limit OFFSET $offset", $params);
    $extra = [];
    if (!empty($in['with_summary'])) {
        $extra['summary'] = tickets_summary();
    }
    return ok(paginated(array_map('ticket_row', $rows), $total, $page, $limit, $extra));
}

function tickets_summary(): array
{
    $agg = q_one("SELECT SUM(status = 'Open') AS open_count, SUM(status = 'Closed') AS closed_count,
                         SUM(status = 'Open' AND priority = 'High') AS high_count,
                         AVG(CASE WHEN status = 'Closed' AND closed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, closed_at) END) AS avg_minutes
                  FROM support_tickets");
    return [
        'open'                   => (int) $agg['open_count'],
        'closed'                 => (int) $agg['closed_count'],
        'high_priority'          => (int) $agg['high_count'],
        'avg_resolution_minutes' => $agg['avg_minutes'] !== null ? (int) round((float) $agg['avg_minutes']) : null,
    ];
}

function ticket_find(int $id): array
{
    $row = $id ? q_one(tickets_select_sql() . ' WHERE t.id = ?', [$id]) : null;
    if (!$row) {
        throw not_found('Ticket');
    }
    return $row;
}

function tickets_get(array $in, ?array $admin): array
{
    $row = ticket_find((int) ($in['id'] ?? 0));
    $ticket = ticket_row($row);
    $messages = q_all('SELECT id, sender_type, sender_name, message, created_at FROM support_ticket_messages WHERE ticket_id = ? ORDER BY created_at, id', [$row['id']]);

    $conversation = [];
    if ($ticket['message'] !== '') {
        $conversation[] = ['from' => 'customer', 'name' => $ticket['customer'], 'time' => $ticket['created'], 'text' => $ticket['message']];
    }
    $remarks = [];
    foreach ($messages as $m) {
        $item = ['from' => $m['sender_type'], 'name' => $m['sender_name'] ?: ($m['sender_type'] === 'customer' ? $ticket['customer'] : 'Support'), 'time' => $m['created_at'], 'text' => $m['message']];
        if ($m['sender_type'] === 'internal') {
            $remarks[] = $item;
        } else {
            $conversation[] = $item;
        }
    }
    return ok([
        'ticket'       => $ticket,
        'messages'     => $conversation,
        'remarks'      => array_reverse($remarks),
        'booking'      => $ticket['booking_id'] ? booking_find($ticket['booking_id']) : null,
    ]);
}

function ticket_touch(int $id, ?string $status = null): void
{
    if ($status === 'Closed') {
        q("UPDATE support_tickets SET status = 'Closed', closed_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $id]);
    } elseif ($status === 'Open') {
        q("UPDATE support_tickets SET status = 'Open', closed_at = NULL, updated_at = ? WHERE id = ?", [now(), $id]);
    } else {
        q('UPDATE support_tickets SET updated_at = ? WHERE id = ?', [now(), $id]);
    }
}

/**
 * Admin reply. Stored in the conversation, which the customer reads through
 * api/support_tickets.php; the push hook notification is logged as well.
 */
function tickets_reply(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Ticket', ['required' => true, 'min' => 1]);
    $message = $v->str('message', 'Reply', ['required' => true, 'max' => 2000]);
    $close = $v->bool('close');
    $v->check();
    $row = ticket_find($id);

    q('INSERT INTO support_ticket_messages (ticket_id, sender_type, admin_id, sender_name, message, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'admin', $admin['id'], $admin['name'], $message, now()]);
    ticket_touch($id, $close ? 'Closed' : ($row['status'] === 'Closed' ? 'Open' : null));

    $ticket = ticket_row($row);
    $push = push_send($ticket['customer_id'], 'Reply on ticket ' . $ticket['code'], mb_strimwidth($message, 0, 120, '...'), ['ticket_id' => $id]);
    q(
        'INSERT INTO customer_notifications (title, message, type, audience, audience_label, user_id, booking_id, channels, recipients, status, status_detail, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)',
        ['Reply on ticket ' . $ticket['code'], $message, 'support', 'single', $ticket['customer'], $ticket['customer_id'], $ticket['booking_id'], 'push', $push['status'], 'PUSH: ' . $push['status'] . ' - ' . $push['detail'], $admin['id'], now()]
    );

    return ok(['id' => $id, 'closed' => $close, 'notification' => $push], 'Reply saved' . ($close ? ' and ticket closed' : '') . '. Customer notification: ' . $push['status'] . '.');
}

function tickets_update(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Ticket', ['required' => true, 'min' => 1]);
    $status = $v->enum('status', 'Status', ['Open', 'Closed'], ['required' => true]);
    $priority = $v->enum('priority', 'Priority', TICKET_PRIORITIES, ['required' => true]);
    $v->check();
    $row = ticket_find($id);
    q('UPDATE support_tickets SET priority = ? WHERE id = ?', [$priority, $id]);
    ticket_touch($id, $status !== $row['status'] ? $status : null);
    $changes = [];
    if ($status !== $row['status']) {
        $changes[] = 'status ' . $row['status'] . ' -> ' . $status;
    }
    if ($priority !== $row['priority']) {
        $changes[] = 'priority ' . $row['priority'] . ' -> ' . $priority;
    }
    if ($changes) {
        q('INSERT INTO support_ticket_messages (ticket_id, sender_type, admin_id, sender_name, message, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'internal', $admin['id'], $admin['name'], 'Changed ' . implode(', ', $changes) . '.', now()]);
    }
    return ok(['id' => $id, 'status' => $status, 'priority' => $priority], 'Ticket updated.');
}

function tickets_add_remark(array $in, ?array $admin): array
{
    $v = new Validator($in);
    $id = $v->int('id', 'Ticket', ['required' => true, 'min' => 1]);
    $remark = $v->str('remark', 'Remark', ['required' => true, 'max' => 1000]);
    $v->check();
    ticket_find($id);
    q('INSERT INTO support_ticket_messages (ticket_id, sender_type, admin_id, sender_name, message, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, 'internal', $admin['id'], $admin['name'], $remark, now()]);
    ticket_touch($id);
    return ok(['remark' => ['from' => 'internal', 'name' => $admin['name'], 'time' => now(), 'text' => $remark]], 'Remark saved.', 201);
}

return [
    'list'       => ['GET',  'tickets_list'],
    'get'        => ['GET',  'tickets_get'],
    'reply'      => ['POST', 'tickets_reply'],
    'update'     => ['POST', 'tickets_update'],
    'add_remark' => ['POST', 'tickets_add_remark'],
];
