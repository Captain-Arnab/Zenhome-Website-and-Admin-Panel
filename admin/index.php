<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle       = 'Dashboard';
$activeMenu      = 'dashboard';
$plugins         = ['charts'];
$withAssignModal = true;

$overview = api('dashboard.overview', [], null);
$stats = $overview['stats'] ?? [
    'customers' => 0, 'bookings' => 0, 'today' => 0, 'unassigned' => 0, 'open_tickets' => 0, 'high_priority_tickets' => 0,
    'new' => 0, 'pending' => 0, 'assigned' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0,
    'customers_trend' => 0, 'bookings_trend' => 0,
];
$paymentSummary = $overview['payment_summary'] ?? ['received' => 0, 'pending' => 0, 'refunds' => 0, 'online_share' => 0, 'received_this_month' => 0];
$charts = $overview['charts'] ?? ['status' => ['labels' => [], 'data' => []], 'week' => ['labels' => [], 'data' => []]];

$unassignedBookings = $overview['unassigned']['items'] ?? [];
$unassignedOverdue  = (int) ($overview['unassigned']['overdue'] ?? 0);
$unassignedUpcoming = (int) ($overview['unassigned']['upcoming'] ?? $stats['unassigned']);
$recentBookings     = $overview['recent'] ?? [];

/** "+8.2% this month" trend markup; null = no data for last month. */
$trend = function ($value): string {
    if ($value === null) {
        return '<span class="text-muted">New this month</span>';
    }
    $up = $value >= 0;
    return '<span class="' . ($up ? 'text-success' : 'text-danger') . ' fw-semibold"><i class="bi bi-arrow-' . ($up ? 'up' : 'down') . '-short"></i>'
        . e(abs($value)) . '%</span> <span class="text-muted">vs last month</span>';
};

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2><?= greeting() ?>, <?= e(strtok($currentAdmin['name'], ' ')) ?></h2>
        <p>Here is what is happening with Zen Home Experts bookings today, <?= date('l, d M Y') ?>.</p>
    </div>
    <div class="actions">
        <a href="bookings.php?status=unassigned" class="btn btn-warning"><i class="bi bi-person-plus me-1"></i> Assign Pending (<?= $stats['unassigned'] ?>)</a>
        <a href="reports.php" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i> Reports</a>
    </div>
</div>

<!-- Core workflow reminder -->
<div class="card mb-4">
    <div class="workflow">
        <div class="workflow-step">
            <span class="step-no icon-soft-info"><i class="bi bi-cart-check"></i></span>
            <div><strong>1. Customer books</strong><small>Booking arrives as <em>New</em></small></div>
        </div>
        <div class="workflow-step">
            <span class="step-no icon-soft-warning"><i class="bi bi-inbox"></i></span>
            <div><strong>2. Admin reviews</strong><small><?= $stats['unassigned'] ?> waiting now</small></div>
        </div>
        <div class="workflow-step">
            <span class="step-no icon-soft-primary"><i class="bi bi-person-check"></i></span>
            <div><strong>3. Assign professional</strong><small>Manual, internal team</small></div>
        </div>
        <div class="workflow-step">
            <span class="step-no icon-soft-purple"><i class="bi bi-bell"></i></span>
            <div><strong>4. Customer notified</strong><small>SMS + push update</small></div>
        </div>
        <div class="workflow-step">
            <span class="step-no icon-soft-success"><i class="bi bi-check2-circle"></i></span>
            <div><strong>5. Track to completion</strong><small>Ongoing &rarr; Completed</small></div>
        </div>
    </div>
</div>

<!-- Primary KPIs -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl">
        <?= statCard('Total Customers', number_format($stats['customers']), 'bi-people', 'secondary', $trend($stats['customers_trend']), 'customers.php') ?>
    </div>
    <div class="col-sm-6 col-xl">
        <?= statCard('Total Bookings', number_format($stats['bookings']), 'bi-calendar-check', 'primary', $trend($stats['bookings_trend']), 'bookings.php') ?>
    </div>
    <div class="col-sm-6 col-xl">
        <?= statCard("Today's Bookings", (string) $stats['today'], 'bi-calendar-day', 'info', '<span class="text-muted">Received ' . date('d M Y') . '</span>', 'bookings.php?date=today') ?>
    </div>
    <div class="col-sm-6 col-xl">
        <?= statCard('Pending Assignments', (string) $stats['unassigned'], 'bi-person-exclamation', 'warning', '<span class="text-warning fw-semibold">Needs action</span>', 'bookings.php?status=unassigned', 'stat-alert') ?>
    </div>
    <div class="col-sm-6 col-xl">
        <?= statCard('Open Support Tickets', (string) $stats['open_tickets'], 'bi-headset', 'danger', '<span class="text-muted">' . (int) $stats['high_priority_tickets'] . ' high priority</span>', 'support-tickets.php') ?>
    </div>
</div>

<!-- Booking status breakdown -->
<div class="row g-3 mb-4">
    <?php
    $statusCards = [
        ['New',       $stats['new'],       'bi-stars',            'info'],
        ['Pending',   $stats['pending'],   'bi-hourglass-split',  'warning'],
        ['Assigned',  $stats['assigned'],  'bi-person-check',     'primary'],
        ['Ongoing',   $stats['ongoing'],   'bi-gear-wide-connected', 'purple'],
        ['Completed', $stats['completed'], 'bi-check2-circle',    'success'],
        ['Cancelled', $stats['cancelled'], 'bi-x-circle',         'danger'],
    ];
    foreach ($statusCards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <?= statCard($label, number_format($value), $icon, $color, '', 'bookings.php?status=' . strtolower($label), 'stat-sm') ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <!-- Pending assignment queue -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><i class="bi bi-person-exclamation text-warning me-1"></i> Waiting for Assignment</h3>
                    <p class="card-subtitle">
                        Upcoming service dates first &middot; <?= $unassignedUpcoming ?> upcoming<?php if ($unassignedOverdue): ?>, <span class="text-danger fw-semibold"><?= $unassignedOverdue ?> overdue</span><?php endif; ?>
                    </p>
                </div>
                <a href="bookings.php?status=unassigned" class="btn btn-sm btn-light">View all</a>
            </div>
            <div class="list-group list-group-flush">
                <?php $overdueShown = false; ?>
                <?php foreach ($unassignedBookings as $b): ?>
                    <?php if (!empty($b['overdue']) && !$overdueShown): $overdueShown = true; ?>
                        <div class="list-group-item bg-light py-2 px-3 fs-12 fw-semibold text-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i> Overdue &middot; service date has passed; confirm or reschedule with the customer
                        </div>
                    <?php endif; ?>
                    <div class="list-group-item d-flex align-items-center gap-3 py-3 px-3">
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <a href="booking-view.php?id=<?= e($b['id']) ?>" class="fw-bold"><?= e(bookingCode($b)) ?></a>
                                <?= statusBadge($b['status']) ?>
                                <?php if (!empty($b['overdue'])): ?><span class="badge bg-danger-subtle text-danger">Overdue</span><?php endif; ?>
                            </div>
                            <div class="fs-13 fw-semibold text-heading text-truncate"><?= e($b['service']) ?></div>
                            <div class="fs-12 text-muted text-truncate">
                                <i class="bi bi-person"></i> <?= e($b['customer']) ?>
                                <?php if ($b['area']): ?>&middot; <i class="bi bi-geo-alt"></i> <?= e($b['area']) ?><?php endif; ?>
                                &middot; <i class="bi bi-clock"></i> <?= e(fdate($b['date'], 'd M')) ?><?= $b['slot'] ? ', ' . e(trim(strtok($b['slot'], '-'))) : '' ?>
                            </div>
                        </div>
                        <?= assignButton($b) ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$unassignedBookings): ?>
                    <div class="empty-state"><i class="bi bi-check2-all"></i><p class="mt-2 mb-0">All bookings are assigned.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bookings by status chart -->
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">Bookings by Status</h3>
            </div>
            <div class="card-body">
                <div class="chart-box"><canvas id="statusChart" aria-label="Bookings by status chart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Payment summary -->
    <div class="col-md-6 col-xl-4">
        <div class="card payment-summary h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3 position-relative" style="z-index:1">
                    <div>
                        <p class="mb-1 text-white-50 fs-13">Total received (all time)</p>
                        <h3 class="mb-0 fw-800" style="font-size:30px"><?= money($paymentSummary['received']) ?></h3>
                    </div>
                    <span class="stat-icon" style="background:rgba(255,255,255,.12);color:#a7e77c"><i class="bi bi-wallet2"></i></span>
                </div>
                <div class="pay-row"><span><i class="bi bi-check-circle me-1"></i> Received this month</span><strong><?= money($paymentSummary['received_this_month'] ?? 0) ?></strong></div>
                <div class="pay-row"><span><i class="bi bi-hourglass me-1"></i> Pending (cash on service)</span><strong><?= money($paymentSummary['pending']) ?></strong></div>
                <div class="pay-row"><span><i class="bi bi-arrow-counterclockwise me-1"></i> Refunds</span><strong><?= money($paymentSummary['refunds']) ?></strong></div>
                <div class="pay-row d-block">
                    <div class="d-flex justify-content-between mb-2"><span>Online vs cash</span><strong><?= $paymentSummary['online_share'] ?>% / <?= 100 - $paymentSummary['online_share'] ?>%</strong></div>
                    <div class="progress" style="height:6px;background:rgba(255,255,255,.15)">
                        <div class="progress-bar" style="width:<?= $paymentSummary['online_share'] ?>%"></div>
                    </div>
                </div>
                <a href="payments.php" class="btn btn-primary w-100 mt-3 position-relative" style="z-index:1">View Payments <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Last 7 days chart -->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Last 7 Days</h3>
                    <p class="card-subtitle">Bookings received per day</p>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-box"><canvas id="weekChart" aria-label="Last 7 days bookings chart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Recent bookings -->
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">Recent Bookings</h3>
                <a href="bookings.php" class="btn btn-sm btn-light">View all bookings</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Schedule</th>
                        <th>Professional</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentBookings): ?>
                        <tr><td colspan="7"><?= emptyState('No bookings yet.', 'bi-calendar-x') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentBookings as $b): ?>
                        <tr class="<?= needsAssignment($b) ? 'row-highlight' : '' ?>">
                            <td class="nowrap"><a href="booking-view.php?id=<?= e($b['id']) ?>" class="fw-bold"><?= e(bookingCode($b)) ?></a></td>
                            <td class="nowrap"><?= e($b['customer']) ?></td>
                            <td><span class="d-inline-block text-truncate" style="max-width:180px"><?= e($b['service']) ?></span></td>
                            <td class="nowrap"><?= e(fdate($b['date'], 'd M')) ?><div class="cell-sub"><?= e($b['slot'] ?: '-') ?></div></td>
                            <td class="nowrap">
                                <?= $b['professional'] ? e($b['professional']) : '<span class="text-muted fst-italic">Not assigned</span>' ?>
                            </td>
                            <td><?= statusBadge($b['status']) ?></td>
                            <td class="text-end nowrap">
                                <?php if (needsAssignment($b)): ?>
                                    <?= assignButton($b) ?>
                                <?php else: ?>
                                    <a href="booking-view.php?id=<?= e($b['id']) ?>" class="btn btn-icon btn-soft-info" title="View"><i class="bi bi-eye"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$statusChart = $charts['status'];
$weekChart   = $charts['week'];

ob_start(); ?>
<script>
    (function () {
        if (!window.Chart) return;
        const css = getComputedStyle(document.documentElement);
        const c = name => css.getPropertyValue(name).trim();
        Chart.defaults.font.family = 'Manrope, sans-serif';
        Chart.defaults.color = c('--text-muted');
        Chart.defaults.maintainAspectRatio = false;

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusChart['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($statusChart['data']) ?>,
                    backgroundColor: [c('--info'), c('--warning'), c('--primary'), c('--purple'), c('--success'), c('--danger')],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 12 } }
                }
            }
        });

        const ctx = document.getElementById('weekChart');
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(101,179,46,.35)');
        gradient.addColorStop(1, 'rgba(101,179,46,0)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($weekChart['labels']) ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?= json_encode($weekChart['data']) ?>,
                    borderColor: c('--primary'),
                    backgroundColor: gradient,
                    fill: true,
                    tension: .35,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: c('--primary'),
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#eef1f4' }, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
