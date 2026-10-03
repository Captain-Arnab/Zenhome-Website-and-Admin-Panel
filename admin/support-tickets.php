<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Support Tickets';
$activeMenu  = 'tickets';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Support Tickets']];

$result  = api_list('tickets.list', ['with_summary' => 1], 'tickets');
$tickets = $result['items'];
$summary = $result['summary'] ?? ['open' => 0, 'closed' => 0, 'high_priority' => 0, 'avg_resolution_minutes' => null];
$openCount   = (int) $summary['open'];
$closedCount = (int) $summary['closed'];
$highCount   = (int) $summary['high_priority'];
$avgMinutes  = $summary['avg_resolution_minutes'];
$avgLabel    = $avgMinutes === null ? '-' : ($avgMinutes < 60 ? $avgMinutes . ' min' : round($avgMinutes / 60, 1) . ' hrs');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Support Tickets</h2>
        <p>Customer issues raised from the website or by phone.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><?= statCard('Open', (string) $openCount, 'bi-envelope-open', 'warning', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('High Priority', (string) $highCount, 'bi-exclamation-octagon', 'danger', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Closed', (string) $closedCount, 'bi-check2-circle', 'success', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Avg. Resolution', $avgLabel, 'bi-stopwatch', 'info', '', null, 'stat-sm') ?></div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-5">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Ticket ID, customer, subject" data-dt-search="#ticketsTable">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#ticketsTable" data-column="4" data-exact aria-label="Category">
                    <option value="">All categories</option>
                    <option>Booking</option>
                    <option>Payment</option>
                    <option>Service</option>
                    <option>Account</option>
                    <option>General</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#ticketsTable" data-column="5" data-exact aria-label="Priority">
                    <option value="">All priorities</option>
                    <option>High</option>
                    <option>Medium</option>
                    <option>Low</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <ul class="nav nav-tabs-line px-3">
        <li class="nav-item"><button type="button" class="nav-link active" data-dt-tab="#ticketsTable" data-column="7" data-value="Open">Open <span class="badge bg-warning"><?= $openCount ?></span></button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-dt-tab="#ticketsTable" data-column="7" data-value="Closed">Closed <span class="badge badge-soft-secondary"><?= $closedCount ?></span></button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-dt-tab="#ticketsTable" data-column="7" data-value="">All <span class="badge badge-soft-secondary"><?= $openCount + $closedCount ?></span></button></li>
    </ul>
    <table class="table table-hover js-datatable" id="ticketsTable" data-empty="No support tickets yet.">

        <thead>
        <tr>
            <th>Ticket</th>
            <th>Customer</th>
            <th>Subject</th>
            <th>Booking</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Last update</th>
            <th>Status</th>
            <th class="text-end" data-orderable="false">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr>
                <td class="nowrap" data-order="<?= e($t['created']) ?>"><a href="ticket-view.php?id=<?= e($t['id']) ?>" class="cell-title"><?= e($t['code']) ?></a><span class="cell-sub"><?= fdate($t['created'], 'd M, h:i A') ?></span></td>
                <td class="nowrap"><?= e($t['customer']) ?><?php if ($t['mobile']): ?><div class="cell-sub"><?= e($t['mobile']) ?></div><?php endif; ?></td>
                <td style="min-width:220px"><a href="ticket-view.php?id=<?= e($t['id']) ?>" class="text-heading fw-semibold"><?= e($t['subject']) ?></a></td>
                <td><?= $t['booking_id'] ? '<a href="booking-view.php?id=' . e($t['booking_id']) . '">' . e($t['booking']) . '</a>' : '<span class="text-muted">-</span>' ?></td>
                <td><?= e($t['category']) ?></td>
                <td><?= statusBadge($t['priority'], false) ?></td>
                <td class="nowrap" data-order="<?= e($t['updated']) ?>"><?= fdate($t['updated'], 'd M, h:i A') ?></td>
                <td><?= statusBadge($t['status']) ?></td>
                <td class="text-end"><a href="ticket-view.php?id=<?= e($t['id']) ?>" class="btn btn-sm btn-light text-nowrap"><i class="bi bi-reply me-1"></i> Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
