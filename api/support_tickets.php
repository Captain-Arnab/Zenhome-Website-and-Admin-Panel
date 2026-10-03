<?php
/**
 * Customer support tickets (login required: the existing customer Bearer
 * token, "Authorization: Bearer <token>" or ?token=).
 * Admins answer in Admin > Support Tickets; their replies appear here.
 * Internal admin remarks are never returned.
 *
 * GET  ?action=list[&status=Open|Closed&page=1&limit=20]  own tickets, newest activity first
 * GET  ?action=get&id=12                                  ticket + conversation
 * POST ?action=create  {subject, message, category?, booking_id?}
 *        category: Booking | Payment | Service | Account | General (default)
 *        booking_id: booking code ("12-38") or numeric ID of the customer's own booking
 * POST ?action=reply   {id, message}   (re-opens a closed ticket)
 */
require __DIR__ . '/public_helper.php';
require __DIR__ . '/auth_helper.php';

const SUPPORT_CATEGORIES = ['Booking', 'Payment', 'Service', 'Account', 'General'];
const SUPPORT_DAILY_LIMIT = 5;

public_cors('GET, POST, OPTIONS');

$in = public_input();
$conn = public_db();
$auth = requireAuth($conn);
$userId = (int) $auth['user_id'];
$customerName = trim(($auth['user']['first_name'] ?? '') . ' ' . ($auth['user']['last_name'] ?? '')) ?: 'Customer';
if (in_array(strtolower(trim((string) ($auth['user']['status'] ?? ''))), ['inactive', 'blocked', 'disabled'], true)) {
    public_json(403, 'Your account is disabled. Please contact support by phone.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = public_str($in, 'action') ?: ($method === 'POST' ? 'create' : (public_str($in, 'id') !== '' ? 'get' : 'list'));
$now = (function (): string {
    $tz = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
    return (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d H:i:s');
})();

function support_ticket_out(array $t): array
{
    $code = trim((string) ($t['booking_code'] ?? ''), ' "');
    return [
        'id'          => (int) $t['id'],
        'ticket_no'   => $t['ticket_no'] ?: 'TK-' . (1000 + (int) $t['id']),
        'subject'     => $t['subject'],
        'category'    => $t['category'],
        'status'      => $t['status'],
        'booking_id'  => $t['booking_id'] !== null ? (int) $t['booking_id'] : null,
        'booking'     => $t['booking_id'] !== null ? ($code !== '' ? $code : '#' . $t['booking_id']) : null,
        'created_at'  => $t['created_at'],
        'updated_at'  => $t['updated_at'] ?: $t['created_at'],
        'closed_at'   => $t['closed_at'],
    ];
}

/** Own ticket or 404 (never reveals other customers' tickets). */
function support_find(PDO $conn, int $id, int $userId): array
{
    $stmt = $conn->prepare('SELECT t.*, b.unique_booking_id AS booking_code FROM support_tickets t LEFT JOIN Service_booking b ON b.ID = t.booking_id WHERE t.id = ? AND t.user_id = ?');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        public_json(404, 'Ticket not found.');
    }
    return $row;
}

function support_conversation(PDO $conn, array $ticket, string $customerName): array
{
    $messages = [];
    if (trim((string) $ticket['message']) !== '') {
        $messages[] = ['id' => null, 'from' => 'customer', 'name' => $customerName, 'message' => $ticket['message'], 'created_at' => $ticket['created_at']];
    }
    $stmt = $conn->prepare("SELECT id, sender_type, sender_name, message, created_at FROM support_ticket_messages WHERE ticket_id = ? AND sender_type IN ('customer', 'admin') ORDER BY created_at, id");
    $stmt->execute([(int) $ticket['id']]);
    foreach ($stmt->fetchAll() as $m) {
        $isAdmin = $m['sender_type'] === 'admin';
        $messages[] = [
            'id'         => (int) $m['id'],
            'from'       => $isAdmin ? 'support' : 'customer',
            'name'       => $isAdmin ? 'Zen Home Experts Support' : ($m['sender_name'] ?: $customerName),
            'message'    => $m['message'],
            'created_at' => $m['created_at'],
        ];
    }
    return $messages;
}

try {
    switch ($action) {
        case 'list':
            public_require_method('GET');
            [$page, $limit, $offset] = public_page_params($in, 20, 50);
            $where = 't.user_id = ?';
            $params = [$userId];
            $status = ucfirst(strtolower(public_str($in, 'status')));
            if (in_array($status, ['Open', 'Closed'], true)) {
                $where .= ' AND t.status = ?';
                $params[] = $status;
            }
            $count = $conn->prepare("SELECT COUNT(*) FROM support_tickets t WHERE $where");
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $stmt = $conn->prepare(
                "SELECT t.*, b.unique_booking_id AS booking_code,
                        (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id AND m.sender_type = 'admin') AS admin_replies,
                        (SELECT MAX(m.created_at) FROM support_ticket_messages m WHERE m.ticket_id = t.id AND m.sender_type = 'admin') AS last_admin_reply
                 FROM support_tickets t LEFT JOIN Service_booking b ON b.ID = t.booking_id
                 WHERE $where ORDER BY COALESCE(t.updated_at, t.created_at) DESC, t.id DESC LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            $items = array_map(fn($t) => support_ticket_out($t) + [
                'admin_replies'    => (int) $t['admin_replies'],
                'last_admin_reply' => $t['last_admin_reply'],
            ], $stmt->fetchAll());
            public_json(200, 'OK', ['items' => $items, 'pagination' => public_pagination($page, $limit, $total)]);

        case 'get':
            public_require_method('GET');
            $ticket = support_find($conn, (int) public_str($in, 'id'), $userId);
            public_json(200, 'OK', ['ticket' => support_ticket_out($ticket), 'messages' => support_conversation($conn, $ticket, $customerName)]);

        case 'create':
            public_require_method('POST');
            $subject = public_str($in, 'subject');
            $message = public_str($in, 'message');
            $category = ucfirst(strtolower(public_str($in, 'category'))) ?: 'General';
            $bookingRef = trim(public_str($in, 'booking_id') ?: public_str($in, 'unique_booking_id'), ' "#');
            $errors = [];
            if ($subject === '' || mb_strlen($subject) > 200) {
                $errors['subject'] = $subject === '' ? 'Subject is required.' : 'Subject can be at most 200 characters.';
            }
            if ($message === '' || mb_strlen($message) > 2000) {
                $errors['message'] = $message === '' ? 'Describe the issue.' : 'Message can be at most 2000 characters.';
            }
            if (!in_array($category, SUPPORT_CATEGORIES, true)) {
                $errors['category'] = 'Use one of: ' . implode(', ', SUPPORT_CATEGORIES) . '.';
            }
            $bookingId = null;
            if ($bookingRef !== '') {
                $stmt = $conn->prepare('SELECT ID FROM Service_booking WHERE user_id = ? AND (unique_booking_id = ? OR ID = ?) LIMIT 1');
                $stmt->execute([$userId, $bookingRef, ctype_digit($bookingRef) ? (int) $bookingRef : 0]);
                $bookingId = $stmt->fetchColumn() ?: null;
                if (!$bookingId) {
                    $errors['booking_id'] = 'This booking was not found in your account.';
                }
            }
            if ($errors) {
                public_json(422, 'Please check the highlighted fields.', null, $errors);
            }
            $recent = $conn->prepare('SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND created_at >= ?');
            $recent->execute([$userId, date('Y-m-d H:i:s', strtotime($now . ' -1 day'))]);
            if ((int) $recent->fetchColumn() >= SUPPORT_DAILY_LIMIT) {
                public_json(429, 'You have raised several tickets today. Please wait for our reply or call support.');
            }

            $conn->beginTransaction();
            $conn->prepare('INSERT INTO support_tickets (user_id, booking_id, subject, category, priority, status, message, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$userId, $bookingId, strip_tags($subject), $category, 'Medium', 'Open', strip_tags($message), $now, $now]);
            $id = (int) $conn->lastInsertId();
            $conn->prepare('UPDATE support_tickets SET ticket_no = ? WHERE id = ?')->execute(['TK-' . (1000 + $id), $id]);
            $conn->commit();

            $ticket = support_find($conn, $id, $userId);
            public_json(201, 'Ticket ' . $ticket['ticket_no'] . ' created. Our team will reply soon.', ['ticket' => support_ticket_out($ticket), 'messages' => support_conversation($conn, $ticket, $customerName)]);

        case 'reply':
            public_require_method('POST');
            $ticket = support_find($conn, (int) public_str($in, 'id'), $userId);
            $message = public_str($in, 'message');
            if ($message === '' || mb_strlen($message) > 2000) {
                public_json(422, 'Please check the highlighted fields.', null, ['message' => $message === '' ? 'Message is required.' : 'Message can be at most 2000 characters.']);
            }
            $conn->beginTransaction();
            $conn->prepare("INSERT INTO support_ticket_messages (ticket_id, sender_type, admin_id, sender_name, message, created_at) VALUES (?, 'customer', NULL, ?, ?, ?)")
                ->execute([(int) $ticket['id'], mb_substr($customerName, 0, 100), strip_tags($message), $now]);
            $conn->prepare("UPDATE support_tickets SET status = 'Open', closed_at = NULL, updated_at = ? WHERE id = ?")
                ->execute([$now, (int) $ticket['id']]);
            $conn->commit();

            $ticket = support_find($conn, (int) $ticket['id'], $userId);
            public_json(201, 'Reply sent.', ['ticket' => support_ticket_out($ticket), 'messages' => support_conversation($conn, $ticket, $customerName)]);

        default:
            public_json(404, 'Unknown action. Use list, get, create or reply.');
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('[api/support_tickets] ' . $e->getMessage());
    public_json(500, 'Could not process the request. Please try again.');
}
