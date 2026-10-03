<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Reports';
$activeMenu  = 'reports';
$breadcrumbs = [['label' => 'Reports']];

const REPORT_PAGE_SIZE = 50;

/*
 * Report definitions. Each column is [heading, html formatter, csv formatter].
 * Data comes from reports.run for the selected type + date range.
 */
$plain = fn(string $key) => [fn($r) => e($r[$key] ?? ''), fn($r) => (string) ($r[$key] ?? '')];
$date  = fn(string $key, string $fmt = 'd M Y') => [fn($r) => fdate($r[$key] ?? null, $fmt), fn($r) => (string) ($r[$key] ?? '')];
$amt   = fn(string $key) => [fn($r) => money($r[$key] ?? 0), fn($r) => (string) round((float) ($r[$key] ?? 0), 2)];
$num   = fn(string $key) => [fn($r) => inr($r[$key] ?? 0), fn($r) => (string) (int) ($r[$key] ?? 0)];
$badge = fn(string $key) => [fn($r) => statusBadge((string) ($r[$key] ?? '')), fn($r) => (string) ($r[$key] ?? '')];

$reports = [
    'booking' => [
        'label'   => 'Booking',
        'icon'    => 'bi-calendar-check',
        'summary' => fn($s) => [['Total bookings', inr($s['total']), 'primary'], ['Completed', inr($s['completed']), 'success'], ['Cancelled', inr($s['cancelled']), 'danger'], ['Completion rate', $s['completion_rate'] . '%', 'info']],
        'columns' => [
            'Booking ID'   => [fn($r) => '<a href="booking-view.php?id=' . (int) $r['id'] . '">' . e($r['code']) . '</a>', fn($r) => $r['code']],
            'Booked on'    => $date('created', 'd M Y, h:i A'),
            'Service date' => $date('date'),
            'Customer'     => $plain('customer'),
            'Service'      => $plain('service'),
            'Professional' => [fn($r) => e($r['professional'] ?: '-'), fn($r) => $r['professional']],
            'Amount'       => $amt('amount'),
            'Payment'      => $badge('payment_status'),
            'Status'       => $badge('status'),
        ],
    ],
    'payment' => [
        'label'   => 'Payment',
        'icon'    => 'bi-credit-card',
        'summary' => fn($s) => [['Received', money($s['received']), 'success'], ['Pending', money($s['pending']), 'warning'], ['Refunds', money($s['refunds']), 'info'], ['Online share', $s['online_share'] . '%', 'primary']],
        'columns' => [
            'Transaction ID' => [fn($r) => '<span class="font-monospace fs-12">' . e($r['txn'] ?: '-') . '</span>', fn($r) => (string) $r['txn']],
            'Date'           => $date('date', 'd M Y, h:i A'),
            'Booking'        => [fn($r) => $r['booking_id'] ? '<a href="booking-view.php?id=' . (int) $r['booking_id'] . '">' . e($r['booking']) . '</a>' : e($r['booking'] ?: '-'), fn($r) => (string) $r['booking']],
            'Customer'       => $plain('customer'),
            'Method'         => $plain('method'),
            'Amount'         => $amt('amount'),
            'Status'         => [fn($r) => statusBadge($r['status']) . ($r['refund'] ? ' ' . statusBadge($r['refund'], false) : ''), fn($r) => $r['status'] . ($r['refund'] ? ' / ' . $r['refund'] : '')],
        ],
    ],
    'customer' => [
        'label'   => 'Customer',
        'icon'    => 'bi-people',
        'summary' => fn($s) => [['Total customers', inr($s['total_customers']), 'secondary'], ['New in period', inr($s['new_in_period']), 'primary'], ['Repeat customers', $s['repeat_percent'] . '%', 'success'], ['Avg. spend', money($s['avg_spend']), 'info']],
        'columns' => [
            'Customer'    => [fn($r) => '<a href="customer-view.php?id=' . (int) $r['id'] . '">' . e($r['name']) . '</a>', fn($r) => $r['name']],
            'Mobile'      => $plain('mobile'),
            'City'        => [fn($r) => e($r['city'] ?: '-'), fn($r) => (string) $r['city']],
            'Bookings'    => $num('bookings'),
            'Total spent' => $amt('spent'),
            'Joined'      => $date('joined'),
            'Status'      => $badge('status'),
        ],
    ],
    'service' => [
        'label'   => 'Service-wise',
        'icon'    => 'bi-tools',
        'summary' => fn($s) => [['Active services', inr($s['active_services']), 'primary'], ['Top service', $s['top_service'], 'success'], ['Categories', inr($s['categories']), 'secondary'], ['Avg. ticket', money($s['avg_ticket']), 'info']],
        'columns' => [
            'Service'  => [fn($r) => $r['id'] ? '<a href="service-form.php?id=' . (int) $r['id'] . '">' . e($r['name']) . '</a>' : e($r['name']), fn($r) => $r['name']],
            'Category' => [fn($r) => e($r['category'] ?: '-'), fn($r) => (string) $r['category']],
            'Price'    => $amt('price'),
            'Bookings' => $num('bookings'),
            'Revenue'  => $amt('revenue'),
            'Status'   => $badge('status'),
        ],
    ],
    'professional' => [
        'label'   => 'Professional-wise',
        'icon'    => 'bi-person-badge',
        'summary' => fn($s) => [['Professionals', inr($s['professionals']), 'secondary'], ['Jobs completed', inr($s['jobs_completed']), 'success'], ['Avg. rating', $s['avg_rating'] !== null ? (string) $s['avg_rating'] : '-', 'warning'], ['Avg. jobs / pro', (string) $s['avg_jobs'], 'info']],
        'columns' => [
            'Professional'    => [fn($r) => '<a href="professional-view.php?id=' . (int) $r['id'] . '">' . e($r['name']) . '</a>', fn($r) => $r['name']],
            'Category'        => $plain('category'),
            'Areas'           => [fn($r) => e($r['areas'] ?: '-'), fn($r) => (string) $r['areas']],
            'Jobs in period'  => $num('jobs'),
            'Completed'       => $num('completed'),
            'Active jobs now' => $num('active_jobs'),
            'Rating'          => [fn($r) => $r['rating'] !== null ? stars((float) $r['rating']) . ' <span class="fs-12 text-muted">' . e($r['rating']) . '</span>' : '<span class="text-muted">-</span>', fn($r) => $r['rating'] !== null ? (string) $r['rating'] : ''],
            'Status'          => $badge('status'),
        ],
    ],
    'date' => [
        'label'   => 'Date-wise',
        'icon'    => 'bi-calendar3',
        'summary' => fn($s) => [['Days in range', inr($s['days']), 'secondary'], ['Bookings', inr($s['bookings']), 'primary'], ['Revenue', money($s['revenue']), 'success'], ['Best day', $s['best_day'] ? fdate($s['best_day'], 'D, d M') : '-', 'info']],
        'columns' => [
            'Date'      => $date('date', 'D, d M Y'),
            'Bookings'  => $num('bookings'),
            'Completed' => $num('completed'),
            'Cancelled' => $num('cancelled'),
            'Revenue'   => $amt('revenue'),
        ],
    ],
];

$type = isset($reports[$_GET['type'] ?? '']) ? $_GET['type'] : 'booking';
$isDate = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
$from = $isDate($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$to   = $isDate($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
$page = max(1, (int) ($_GET['page'] ?? 1));
$def  = $reports[$type];
$query = ['type' => $type, 'from' => $from, 'to' => $to];

// CSV export of the whole range (all pages), streamed before any HTML.
if (($_GET['export'] ?? '') === 'csv') {
    $rows = [];
    $p = 1;
    do {
        $chunk = api('reports.run', $query + ['page' => $p, 'limit' => 500], null);
        if (!$chunk) {
            break;
        }
        array_push($rows, ...$chunk['items']);
        $p++;
    } while ($p <= $chunk['pagination']['total_pages'] && $p <= 40);

    if ($chunk === null && !$rows) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", $GLOBALS['apiErrors'] ?: ['Report export failed.']);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_keys($def['columns']));
    foreach ($rows as $r) {
        $line = [];
        foreach ($def['columns'] as [, $csv]) {
            $value = $csv($r);
            // Neutralise spreadsheet formulas in user-supplied text.
            $line[] = preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value) ? "'" . $value : $value;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$result  = api('reports.run', $query + ['page' => $page, 'limit' => REPORT_PAGE_SIZE], null);
$rows    = $result['items'] ?? [];
$pg      = $result['pagination'] ?? ['page' => 1, 'total_items' => 0, 'total_pages' => 0];
$summary = $result ? ($def['summary'])($result['summary']) : [];
$url     = fn(array $extra = []) => 'reports.php?' . http_build_query($extra + $query);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Reports</h2>
        <p>Pick a report, choose a date range and export.</p>
    </div>
</div>

<div class="card">
    <ul class="nav nav-tabs-line px-3 d-print-none">
        <?php foreach ($reports as $key => $r): ?>
            <li class="nav-item">
                <a class="nav-link <?= $key === $type ? 'active' : '' ?>" href="reports.php?<?= e(http_build_query(['type' => $key, 'from' => $from, 'to' => $to])) ?>">
                    <i class="bi <?= e($r['icon']) ?> me-1"></i> <?= e($r['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div>
        <!-- Filter + export -->
        <form class="d-flex flex-wrap align-items-end gap-2 p-3 border-bottom d-print-none" method="get" id="reportFilter">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <div>
                <label class="form-label" for="rpFrom">From</label>
                <input type="date" class="form-control" id="rpFrom" name="from" value="<?= e($from) ?>" max="<?= date('Y-m-d') ?>" required>
            </div>
            <div>
                <label class="form-label" for="rpTo">To</label>
                <input type="date" class="form-control" id="rpTo" name="to" value="<?= e($to) ?>" required>
            </div>
            <div>
                <label class="form-label" for="rpRange">Quick range</label>
                <select class="form-select" id="rpRange">
                    <option value="">Custom</option>
                    <option value="7d">Last 7 days</option>
                    <option value="month">This month</option>
                    <option value="last_month">Last month</option>
                    <option value="year">This year</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary"><i class="bi bi-funnel me-1"></i> Apply</button>
            <div class="ms-auto d-flex gap-2">
                <a href="<?= e($url(['export' => 'csv'])) ?>" class="btn btn-outline-secondary <?= $pg['total_items'] ? '' : 'disabled' ?>"><i class="bi bi-filetype-csv me-1"></i> Export CSV</a>
                <button type="button" class="btn btn-outline-danger" onclick="window.print()" title="Opens the print dialog - choose &quot;Save as PDF&quot;"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</button>
            </div>
        </form>

        <p class="d-none d-print-block px-3 pt-3 mb-0 fw-semibold"><?= e($def['label']) ?> report &middot; <?= fdate($from) ?> &ndash; <?= fdate($to) ?></p>

        <?php if ($result): ?>
            <!-- Summary row -->
            <div class="row g-3 p-3">
                <?php foreach ($summary as [$label, $value, $color]): ?>
                    <div class="col-6 col-lg-3">
                        <div class="rounded-3 p-3 h-100 icon-soft-<?= e($color) ?>">
                            <small class="d-block fw-semibold opacity-75"><?= e($label) ?></small>
                            <strong class="fs-5"><?= e($value) ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Table -->
            <div class="table-responsive border-top">
                <table class="table table-hover">
                    <thead>
                    <tr><?php foreach (array_keys($def['columns']) as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="<?= count($def['columns']) ?>"><?= emptyState('No data for this date range.', 'bi-bar-chart') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr><?php foreach ($def['columns'] as [$html]): ?><td class="nowrap"><?= $html($row) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-footer d-print-none">
                <span>
                    <?php if ($pg['total_items']): ?>
                        Showing <?= inr(($pg['page'] - 1) * REPORT_PAGE_SIZE + 1) ?>&ndash;<?= inr(($pg['page'] - 1) * REPORT_PAGE_SIZE + count($rows)) ?> of <?= inr($pg['total_items']) ?> rows
                    <?php else: ?>
                        No rows
                    <?php endif; ?>
                </span>
                <?php if ($pg['total_pages'] > 1): ?>
                    <?php $cur = (int) $pg['page']; $last = (int) $pg['total_pages']; ?>
                    <nav><ul class="pagination pagination-sm m-0">
                        <li class="page-item <?= $cur <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($url(['page' => $cur - 1])) ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a></li>
                        <?php for ($i = max(1, $cur - 2); $i <= min($last, $cur + 2); $i++): ?>
                            <li class="page-item <?= $i === $cur ? 'active' : '' ?>"><a class="page-link" href="<?= e($url(['page' => $i])) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $cur >= $last ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($url(['page' => $cur + 1])) ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a></li>
                    </ul></nav>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="p-3"><?= emptyState('The report could not be generated. Check the date range and try again.', 'bi-exclamation-circle') ?></div>
        <?php endif; ?>
    </div>
</div>

<?php ob_start(); ?>
<script>
    (function () {
        const from = document.getElementById('rpFrom');
        const to = document.getElementById('rpTo');
        const iso = d => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        document.getElementById('rpRange').addEventListener('change', function () {
            const now = new Date();
            let start = null, end = now;
            if (this.value === '7d') start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
            if (this.value === 'month') start = new Date(now.getFullYear(), now.getMonth(), 1);
            if (this.value === 'last_month') { start = new Date(now.getFullYear(), now.getMonth() - 1, 1); end = new Date(now.getFullYear(), now.getMonth(), 0); }
            if (this.value === 'year') start = new Date(now.getFullYear(), 0, 1);
            if (!start) return;
            from.value = iso(start);
            to.value = iso(end);
            document.getElementById('reportFilter').submit();
        });
        const sync = () => { to.min = from.value; };
        from.addEventListener('change', sync);
        sync();
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
