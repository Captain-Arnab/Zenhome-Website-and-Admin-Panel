<?php
/**
 * Reports for a date range: booking, payment, customer, service-wise,
 * professional-wise and date-wise. Values are raw (numbers / ISO dates);
 * the admin page formats them and builds the CSV export.
 */

const REPORT_TYPES = ['booking', 'payment', 'customer', 'service', 'professional', 'date'];

/** @return array{0:string,1:string} validated [from, to] (default: last 7 days) */
function report_range(array $in): array
{
    $v = new Validator($in);
    $from = $v->date('from', 'From date') ?? date('Y-m-d', strtotime('-6 days'));
    $to   = $v->date('to', 'To date') ?? today();
    if ($from > $to) {
        $v->error('to', 'To date must be on or after the From date.');
    }
    $v->check();
    return [$from, $to];
}

function reports_run(array $in, ?array $admin): array
{
    $type = (string) ($in['type'] ?? '');
    if (!in_array($type, REPORT_TYPES, true)) {
        throw new ApiException('Unknown report type.', 422, ['type' => 'Use one of: ' . implode(', ', REPORT_TYPES)]);
    }
    [$from, $to] = report_range($in);
    [$page, $limit, $offset] = page_params($in, 100);
    $fn = 'report_' . $type;
    $report = $fn($from, $to, $limit, $offset);
    return ok(['type' => $type, 'from' => $from, 'to' => $to, 'summary' => $report['summary']]
        + paginated($report['rows'], $report['total'], $page, $limit));
}

function report_booking(string $from, string $to, int $limit, int $offset): array
{
    $range = ['created_from' => $from, 'created_to' => $to];
    [$rows, $total] = booking_query($range, $limit, $offset);
    $counts = booking_status_counts('b.created_at BETWEEN ? AND ?', ["$from 00:00:00", "$to 23:59:59"]);
    return [
        'summary' => [
            'total'           => $counts['total'],
            'completed'       => $counts['Completed'],
            'cancelled'       => $counts['Cancelled'],
            'completion_rate' => $counts['total'] ? round($counts['Completed'] / $counts['total'] * 100, 1) : 0,
        ],
        'rows'  => array_map(fn($b) => [
            'id' => $b['id'], 'code' => $b['code'], 'created' => $b['created'], 'date' => $b['date'], 'customer' => $b['customer'],
            'service' => $b['service'], 'professional' => $b['professional'], 'amount' => $b['amount'],
            'payment_status' => $b['payment_status'], 'status' => $b['status'],
        ], $rows),
        'total' => $total,
    ];
}

function report_payment(string $from, string $to, int $limit, int $offset): array
{
    [$rows, $total] = payment_query(['date_from' => $from, 'date_to' => $to], $limit, $offset);
    $totals = payment_totals($from, $to);
    return [
        'summary' => [
            'received'     => $totals['received'],
            'pending'      => $totals['pending'],
            'refunds'      => $totals['refunds'],
            'online_share' => $totals['online_share'],
        ],
        'rows'  => array_map(fn($p) => [
            'txn' => $p['txn'], 'date' => $p['date'], 'booking_id' => $p['booking_id'], 'booking' => $p['booking'], 'customer' => $p['customer'],
            'method' => $p['method'], 'amount' => $p['amount'], 'status' => $p['status'], 'refund' => $p['refund'],
        ], $rows),
        'total' => $total,
    ];
}

/** Customers who joined or booked in the range. */
function report_customer(string $from, string $to, int $limit, int $offset): array
{
    api_routes('customers');
    $params = ["$from 00:00:00", "$to 23:59:59", "$from 00:00:00", "$to 23:59:59"];
    $where = '(u.created_at BETWEEN ? AND ? OR EXISTS (SELECT 1 FROM Service_booking rb WHERE rb.user_id = u.ID AND rb.created_at BETWEEN ? AND ?))';
    $total = (int) q_value("SELECT COUNT(*) FROM users u WHERE $where", $params);
    $rows = array_map('customer_row', q_all(customers_select_sql() . " WHERE $where ORDER BY booking_count DESC, u.ID DESC LIMIT $limit OFFSET $offset", $params));

    $agg = q_one(
        'SELECT COUNT(*) AS customers, SUM(c > 1) AS repeaters FROM (SELECT user_id, COUNT(*) AS c FROM Service_booking WHERE user_id IS NOT NULL GROUP BY user_id) x'
    );
    $totals = payment_totals();
    $payers = (int) q_value(
        "SELECT COUNT(*) FROM (
            SELECT CAST(user_id AS CHAR) AS uid FROM Service_booking WHERE LOWER(payment_status) = 'paid' AND user_id IS NOT NULL
            UNION SELECT user_id FROM transactions WHERE status = 'success'
        ) x"
    );
    return [
        'summary' => [
            'total_customers' => (int) q_value('SELECT COUNT(*) FROM users'),
            'new_in_period'   => (int) q_value('SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?', ["$from 00:00:00", "$to 23:59:59"]),
            'repeat_percent'  => (int) $agg['customers'] ? round((int) $agg['repeaters'] / (int) $agg['customers'] * 100) : 0,
            'avg_spend'       => $payers ? round($totals['received'] / $payers) : 0,
        ],
        'rows'  => array_map(fn($c) => [
            'id' => $c['id'], 'name' => $c['name'], 'mobile' => $c['mobile'], 'city' => $c['city'], 'bookings' => $c['bookings'],
            'spent' => $c['spent'], 'joined' => $c['joined'], 'status' => $c['status'] ? 'Active' : 'Inactive',
        ], $rows),
        'total' => $total,
    ];
}

/** Item-level counts from bookings created in the range (cancelled excluded). */
function report_service(string $from, string $to, int $limit, int $offset): array
{
    api_routes('services');
    $services = array_map('service_row', q_all(services_select_sql()));
    $byName = [];
    foreach ($services as $s) {
        $byName[strtolower($s['name'])] = $s + ['period_bookings' => 0, 'revenue' => 0.0];
    }
    $amounts = [];
    $bookings = q_all(
        'SELECT b.price, b.subcategories, t.amount AS txn_amount FROM Service_booking b ' . BOOKING_TXN_JOIN . '
         WHERE b.created_at BETWEEN ? AND ? AND ' . booking_status_sql('b.status') . " <> 'Cancelled'",
        ["$from 00:00:00", "$to 23:59:59"]
    );
    foreach ($bookings as $b) {
        $items = booking_items($b['subcategories']);
        $amounts[] = booking_amount($b['price'], $b['txn_amount'], $items);
        foreach ($items as $item) {
            $key = strtolower($item['name']);
            if (!isset($byName[$key])) {
                $byName[$key] = ['id' => null, 'name' => $item['name'], 'category' => '', 'price' => (float) ($item['price'] ?? 0), 'status' => false, 'period_bookings' => 0, 'revenue' => 0.0];
            }
            $byName[$key]['period_bookings']++;
            $byName[$key]['revenue'] += (float) ($item['price'] ?? $byName[$key]['price']);
        }
    }
    $rows = array_values($byName);
    usort($rows, fn($a, $b) => [$b['period_bookings'], $b['revenue']] <=> [$a['period_bookings'], $a['revenue']]);
    $top = $rows && $rows[0]['period_bookings'] > 0 ? $rows[0]['name'] : '-';

    return [
        'summary' => [
            'active_services' => count(array_filter($services, fn($s) => $s['status'])),
            'top_service'     => $top,
            'categories'      => (int) q_value('SELECT COUNT(*) FROM SERVICE_CATEGORY WHERE status = 1'),
            'avg_ticket'      => $amounts ? round(array_sum($amounts) / count($amounts)) : 0,
        ],
        'rows'  => array_map(fn($s) => [
            'id' => $s['id'], 'name' => $s['name'], 'category' => $s['category'], 'price' => $s['price'],
            'bookings' => $s['period_bookings'], 'revenue' => $s['revenue'], 'status' => $s['status'] ? 'Active' : 'Inactive',
        ], array_slice($rows, $offset, $limit)),
        'total' => count($rows),
    ];
}

/** Jobs per professional for bookings with a service date in the range. */
function report_professional(string $from, string $to, int $limit, int $offset): array
{
    $done = "IN ('service complete','completed','complete')";
    $sql = professional_select_sql();
    $pros = array_map(function ($r) use ($from, $to, $done) {
        $p = professional_row($r);
        $p['period_jobs'] = (int) q_value('SELECT COUNT(*) FROM Service_booking WHERE professional_id = ? AND date BETWEEN ? AND ?', [$p['id'], $from, $to]);
        $p['period_completed'] = (int) q_value("SELECT COUNT(*) FROM Service_booking WHERE professional_id = ? AND date BETWEEN ? AND ? AND LOWER(TRIM(status)) $done", [$p['id'], $from, $to]);
        return $p;
    }, q_all("$sql ORDER BY p.full_name"));
    usort($pros, fn($a, $b) => $b['period_jobs'] <=> $a['period_jobs']);
    $rated = array_filter(array_column($pros, 'rating'), fn($r) => $r !== null);
    $completed = array_sum(array_column($pros, 'period_completed'));
    return [
        'summary' => [
            'professionals'  => count($pros),
            'jobs_completed' => $completed,
            'avg_rating'     => $rated ? round(array_sum($rated) / count($rated), 1) : null,
            'avg_jobs'       => $pros ? round(array_sum(array_column($pros, 'period_jobs')) / count($pros), 1) : 0,
        ],
        'rows'  => array_map(fn($p) => [
            'id' => $p['id'], 'name' => $p['name'], 'category' => $p['category'], 'areas' => $p['area'], 'jobs' => $p['period_jobs'],
            'active_jobs' => $p['active_jobs'], 'completed' => $p['period_completed'], 'rating' => $p['rating'], 'status' => $p['status_label'],
        ], array_slice($pros, $offset, $limit)),
        'total' => count($pros),
    ];
}

function report_date(string $from, string $to, int $limit, int $offset): array
{
    $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
    if ($days > 366) {
        throw new ApiException('Date-wise report is limited to 366 days.', 422, ['from' => 'Choose a shorter range.']);
    }
    $rows = [];
    for ($d = strtotime($to); $d >= strtotime($from); $d -= 86400) {
        $rows[date('Y-m-d', $d)] = ['date' => date('Y-m-d', $d), 'bookings' => 0, 'completed' => 0, 'cancelled' => 0, 'revenue' => 0.0];
    }
    $range = ["$from 00:00:00", "$to 23:59:59"];
    foreach (q_all('SELECT DATE(b.created_at) AS d, ' . booking_status_sql('b.status') . ' AS s, COUNT(*) AS c FROM Service_booking b WHERE b.created_at BETWEEN ? AND ? GROUP BY d, s', $range) as $r) {
        if (!isset($rows[$r['d']])) {
            continue;
        }
        $rows[$r['d']]['bookings'] += (int) $r['c'];
        if ($r['s'] === 'Completed') {
            $rows[$r['d']]['completed'] += (int) $r['c'];
        } elseif ($r['s'] === 'Cancelled') {
            $rows[$r['d']]['cancelled'] += (int) $r['c'];
        }
    }
    foreach (q_all("SELECT DATE(created_at) AS d, SUM(amount) AS a FROM transactions WHERE status = 'success' AND created_at BETWEEN ? AND ? GROUP BY d", $range) as $r) {
        if (isset($rows[$r['d']])) {
            $rows[$r['d']]['revenue'] += (float) $r['a'];
        }
    }
    $cash = q_all(
        'SELECT b.price, b.subcategories, COALESCE(DATE(b.completed_at), b.date, DATE(b.created_at)) AS d FROM Service_booking b ' . BOOKING_TXN_JOIN . "
         WHERE t.id IS NULL AND LOWER(b.payment_status) = 'paid'"
    );
    foreach ($cash as $r) {
        if (isset($rows[$r['d']])) {
            $rows[$r['d']]['revenue'] += booking_amount($r['price'], null, booking_items($r['subcategories']));
        }
    }
    $list = array_values($rows);
    $best = $list ? array_reduce($list, fn($carry, $r) => $carry === null || $r['bookings'] > $carry['bookings'] ? $r : $carry) : null;
    return [
        'summary' => [
            'days'     => $days,
            'bookings' => array_sum(array_column($list, 'bookings')),
            'revenue'  => array_sum(array_column($list, 'revenue')),
            'best_day' => $best && $best['bookings'] > 0 ? $best['date'] : null,
        ],
        'rows'  => array_slice($list, $offset, $limit),
        'total' => count($list),
    ];
}

return [
    'run' => ['GET', 'reports_run'],
];
