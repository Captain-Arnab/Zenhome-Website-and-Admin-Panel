<?php
/**
 * Dashboard: KPI counts, payment summary, charts, queues and topbar alerts.
 */

/** Percentage change this month vs the same days of last month. */
function dashboard_trend(string $table, string $column): ?float
{
    $monthStart = date('Y-m-01');
    $lastStart  = date('Y-m-01', strtotime('first day of last month'));
    $lastSameDay = date('Y-m-d', min(strtotime('last day of last month'), strtotime('-1 month')));
    $current  = (int) q_value("SELECT COUNT(*) FROM $table WHERE $column >= ?", [$monthStart . ' 00:00:00']);
    $previous = (int) q_value("SELECT COUNT(*) FROM $table WHERE $column >= ? AND $column <= ?", [$lastStart . ' 00:00:00', $lastSameDay . ' 23:59:59']);
    if ($previous === 0) {
        return $current > 0 ? null : 0.0;
    }
    return round(($current - $previous) / $previous * 100, 1);
}

function dashboard_counts(): array
{
    $counts = booking_status_counts();
    return [
        'customers'     => (int) q_value('SELECT COUNT(*) FROM users'),
        'bookings'      => $counts['total'],
        'today'         => (int) q_value('SELECT COUNT(*) FROM Service_booking WHERE created_at >= ? AND created_at <= ?', [today() . ' 00:00:00', today() . ' 23:59:59']),
        'scheduled_today' => (int) q_value('SELECT COUNT(*) FROM Service_booking WHERE date = ?', [today()]),
        'new'           => $counts['New'],
        'pending'       => $counts['Pending'],
        'assigned'      => $counts['Assigned'],
        'ongoing'       => $counts['Ongoing'],
        'completed'     => $counts['Completed'],
        'cancelled'     => $counts['Cancelled'],
        'unassigned'    => $counts['unassigned'],
        'open_tickets'  => (int) q_value("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open'"),
        'high_priority_tickets' => (int) q_value("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open' AND priority = 'High'"),
        'pending_reviews' => (int) q_value("SELECT COUNT(*) FROM ratings_feedback WHERE status = 'Pending'"),
    ];
}

/** Small counts used by the sidebar badges on every page. */
function dashboard_badges(array $in, ?array $admin): array
{
    return ok([
        'unassigned'   => (int) q_value('SELECT COUNT(*) FROM Service_booking b WHERE ' . booking_needs_assignment_sql('b')),
        'open_tickets' => (int) q_value("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open'"),
    ]);
}

function dashboard_stats(array $in, ?array $admin): array
{
    $stats = dashboard_counts();
    $stats['customers_trend'] = dashboard_trend('users', 'created_at');
    $stats['bookings_trend']  = dashboard_trend('Service_booking', 'created_at');
    return ok($stats);
}

function dashboard_payment_summary(array $in, ?array $admin): array
{
    $all = payment_totals();
    $month = payment_totals(date('Y-m-01'), today());
    return ok($all + ['received_this_month' => $month['received']]);
}

function dashboard_charts(array $in, ?array $admin): array
{
    $counts = booking_status_counts();
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i day"))] = 0;
    }
    $rows = q_all(
        'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM Service_booking WHERE created_at >= ? GROUP BY DATE(created_at)',
        [array_key_first($days) . ' 00:00:00']
    );
    foreach ($rows as $r) {
        if (isset($days[$r['d']])) {
            $days[$r['d']] = (int) $r['c'];
        }
    }
    return ok([
        'status' => [
            'labels' => BOOKING_STATUSES,
            'data'   => array_map(fn($s) => $counts[$s], BOOKING_STATUSES),
        ],
        'week' => [
            'labels' => array_map(fn($d) => date('D d', strtotime($d)), array_keys($days)),
            'dates'  => array_keys($days),
            'data'   => array_values($days),
        ],
    ]);
}

/**
 * Assignment queue: today/upcoming service dates first (soonest first),
 * then bookings without a date, then overdue ones (service date already
 * passed, most recent first) flagged with overdue = true.
 */
function dashboard_unassigned(array $in, ?array $admin): array
{
    $limit = min(50, max(1, (int) ($in['limit'] ?? 5)));
    $today = date('Y-m-d');
    $order = "CASE WHEN b.date >= '$today' THEN 0 WHEN b.date IS NULL THEN 1 ELSE 2 END,
              CASE WHEN b.date >= '$today' THEN b.date END ASC,
              CASE WHEN b.date < '$today' THEN b.date END DESC,
              b.created_at DESC";
    [$items, $total] = booking_query(['status' => 'unassigned'], $limit, 0, $order);
    foreach ($items as &$item) {
        $item['overdue'] = !empty($item['date']) && $item['date'] < $today;
    }
    unset($item);
    $overdue = (int) q_value(
        'SELECT COUNT(*) FROM Service_booking b WHERE ' . booking_needs_assignment_sql('b') . ' AND b.date < ?',
        [$today]
    );
    return ok(['items' => $items, 'total' => $total, 'overdue' => $overdue, 'upcoming' => $total - $overdue]);
}

function dashboard_recent(array $in, ?array $admin): array
{
    $limit = min(50, max(1, (int) ($in['limit'] ?? 8)));
    [$items] = booking_query([], $limit, 0);
    return ok(['items' => $items]);
}

/** Everything the dashboard page needs in one call. */
function dashboard_overview(array $in, ?array $admin): array
{
    return ok([
        'stats'           => dashboard_stats($in, $admin)['data'],
        'payment_summary' => dashboard_payment_summary($in, $admin)['data'],
        'charts'          => dashboard_charts($in, $admin)['data'],
        'unassigned'      => dashboard_unassigned(['limit' => 5], $admin)['data'],
        'recent'          => dashboard_recent(['limit' => 8], $admin)['data']['items'],
    ]);
}

/**
 * Topbar alerts, derived from live data (no separate alerts table):
 * new bookings, assignment queue, high-priority tickets, reviews to
 * moderate, recent online payments. Unread = newer than alerts_read_at.
 */
function dashboard_alerts(array $in, ?array $admin): array
{
    $readAt = $admin['alerts_read_at'] ?? null;
    $since  = date('Y-m-d H:i:s', strtotime('-7 days'));
    $alerts = [];

    foreach (q_all('SELECT ID, unique_booking_id, category, subcategories, created_at FROM Service_booking WHERE created_at >= ? ORDER BY created_at DESC LIMIT 5', [$since]) as $b) {
        $items = booking_items($b['subcategories']);
        $code = trim((string) $b['unique_booking_id'], ' "') ?: '#' . $b['ID'];
        $alerts[] = [
            'icon' => 'bi-calendar-plus', 'color' => 'info',
            'text' => 'New booking <strong>' . htmlspecialchars($code) . '</strong> - ' . htmlspecialchars($items ? $items[0]['name'] : (string) $b['category']),
            'url'  => 'booking-view.php?id=' . (int) $b['ID'],
            'at'   => $b['created_at'],
        ];
    }

    $queue = q_one('SELECT COUNT(*) AS c, MAX(b.created_at) AS latest FROM Service_booking b WHERE ' . booking_needs_assignment_sql('b'));
    if ((int) $queue['c'] > 0) {
        $alerts[] = [
            'icon' => 'bi-person-exclamation', 'color' => 'warning',
            'text' => '<strong>' . (int) $queue['c'] . ' booking' . ((int) $queue['c'] > 1 ? 's are' : ' is') . '</strong> waiting for professional assignment',
            'url'  => 'bookings.php?status=unassigned',
            'at'   => $queue['latest'],
        ];
    }

    foreach (q_all("SELECT id, ticket_no, subject, created_at FROM support_tickets WHERE status = 'Open' AND priority = 'High' ORDER BY created_at DESC LIMIT 3") as $t) {
        $alerts[] = [
            'icon' => 'bi-headset', 'color' => 'danger',
            'text' => 'High priority ticket <strong>' . htmlspecialchars($t['ticket_no']) . '</strong> - ' . htmlspecialchars(mb_strimwidth($t['subject'], 0, 50, '...')),
            'url'  => 'ticket-view.php?id=' . (int) $t['id'],
            'at'   => $t['created_at'],
        ];
    }

    $reviews = q_one("SELECT COUNT(*) AS c, MAX(created_at) AS latest FROM ratings_feedback WHERE status = 'Pending'");
    if ((int) $reviews['c'] > 0) {
        $alerts[] = [
            'icon' => 'bi-star', 'color' => 'primary',
            'text' => (int) $reviews['c'] . ' review' . ((int) $reviews['c'] > 1 ? 's need' : ' needs') . ' moderation',
            'url'  => 'reviews.php',
            'at'   => $reviews['latest'],
        ];
    }

    foreach (q_all("SELECT transaction_id, amount, created_at FROM transactions WHERE status = 'success' AND created_at >= ? ORDER BY created_at DESC LIMIT 3", [$since]) as $t) {
        $alerts[] = [
            'icon' => 'bi-credit-card', 'color' => 'success',
            'text' => 'Payment of <strong>Rs ' . number_format((float) $t['amount'], 0) . '</strong> received (' . htmlspecialchars($t['transaction_id']) . ')',
            'url'  => 'payments.php',
            'at'   => $t['created_at'],
        ];
    }

    usort($alerts, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
    $alerts = array_slice($alerts, 0, 8);
    foreach ($alerts as &$a) {
        $a['time'] = rel_time($a['at']);
        $a['unread'] = $a['at'] && (!$readAt || $a['at'] > $readAt);
    }
    unset($a);

    return ok(['items' => $alerts, 'unread' => count(array_filter($alerts, fn($a) => $a['unread']))]);
}

function dashboard_alerts_read(array $in, ?array $admin): array
{
    q('UPDATE admin SET alerts_read_at = ? WHERE id = ?', [now(), $admin['id']]);
    return ok(null, 'All notifications marked as read.');
}

return [
    'overview'        => ['GET',  'dashboard_overview'],
    'stats'           => ['GET',  'dashboard_stats'],
    'badges'          => ['GET',  'dashboard_badges'],
    'payment_summary' => ['GET',  'dashboard_payment_summary'],
    'charts'          => ['GET',  'dashboard_charts'],
    'unassigned'      => ['GET',  'dashboard_unassigned'],
    'recent'          => ['GET',  'dashboard_recent'],
    'alerts'          => ['GET',  'dashboard_alerts'],
    'alerts_read'     => ['POST', 'dashboard_alerts_read'],
];
