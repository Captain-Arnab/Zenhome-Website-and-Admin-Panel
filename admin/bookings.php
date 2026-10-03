<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle       = 'Bookings';
$activeMenu      = 'bookings';
$plugins         = ['datatables'];
$withAssignModal = true;
$breadcrumbs     = [['label' => 'Bookings']];

// Service-date range is filtered server-side; everything else client-side.
$dateFrom = (string) ($_GET['date_from'] ?? '');
$dateTo   = (string) ($_GET['date_to'] ?? '');
if (($_GET['date'] ?? '') === 'today') {
    // Dashboard "Today's Bookings" card: bookings received today
    $createdToday = true;
}
$isDate = fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$dateFrom = $isDate($dateFrom) ? $dateFrom : '';
$dateTo   = $isDate($dateTo) ? $dateTo : '';

$query = ['with_counts' => 1];
if ($dateFrom !== '') $query['date_from'] = $dateFrom;
if ($dateTo !== '')   $query['date_to'] = $dateTo;
if (!empty($createdToday)) {
    $query['created_from'] = date('Y-m-d');
    $query['created_to']   = date('Y-m-d');
}
$filtered = count($query) > 1;

$result   = api_list('bookings.list', $query, 'bookings');
$bookings = $result['items'];

$statusList = ['New', 'Pending', 'Assigned', 'Ongoing', 'Completed', 'Cancelled'];
if ($filtered || empty($result['counts'])) {
    $countBy    = array_count_values(array_column($bookings, 'status'));
    $unassigned = count(array_filter($bookings, 'needsAssignment'));
    $totalCount = count($bookings);
} else {
    $countBy    = $result['counts'];
    $unassigned = (int) $result['counts']['unassigned'];
    $totalCount = (int) $result['counts']['total'];
}
$selected = strtolower($_GET['status'] ?? '');

$categoryOptions = api('categories.options', [], ['items' => []])['items'];
$professionalOptions = array_unique(array_filter(array_column($bookings, 'professional')));
sort($professionalOptions);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="alert alert-flow d-flex flex-wrap align-items-center gap-2 mb-3">
    <i class="bi bi-diagram-3-fill fs-5 text-primary"></i>
    <span class="fw-semibold">Booking flow:</span>
    <span>Customer books</span><i class="bi bi-arrow-right"></i>
    <span><strong>Admin assigns a professional</strong></span><i class="bi bi-arrow-right"></i>
    <span>Customer gets SMS/push</span><i class="bi bi-arrow-right"></i>
    <span>Admin updates status until <strong>Completed</strong></span>
</div>

<div class="page-header">
    <div>
        <h2>Bookings</h2>
        <p>Highlighted rows are waiting for a professional. Use <strong>Assign</strong> to dispatch them.</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-outline-secondary" data-export="#bookingsTable" data-filename="bookings-<?= date('Y-m-d') ?>.csv"><i class="bi bi-download me-1"></i> Export</button>
    </div>
</div>

<!-- Filters -->
<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6 col-xl-3">
                <label class="form-label">Search</label>
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Booking ID, customer, mobile" data-dt-search="#bookingsTable">
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <label class="form-label">Service</label>
                <select class="form-select" data-dt-filter="#bookingsTable" data-column="2">
                    <option value="">All services</option>
                    <?php foreach ($categoryOptions as $cat): ?>
                        <option><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <label class="form-label">Payment</label>
                <select class="form-select" data-dt-filter="#bookingsTable" data-column="5" data-exact>
                    <option value="">All payments</option>
                    <option>Paid</option>
                    <option>Pending</option>
                    <option>Refunded</option>
                    <option>Failed</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label class="form-label">Professional</label>
                <select class="form-select" data-dt-filter="#bookingsTable" data-column="6">
                    <option value="">All professionals</option>
                    <option value="Not assigned">Not assigned</option>
                    <?php foreach ($professionalOptions as $name): ?>
                        <option><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-6 col-xl-3">
                <label class="form-label">Service date</label>
                <form method="get" class="input-group" id="dateFilter">
                    <?php if ($selected !== ''): ?><input type="hidden" name="status" value="<?= e($selected) ?>"><?php endif; ?>
                    <input type="date" class="form-control" name="date_from" aria-label="From date" value="<?= e($dateFrom) ?>" onchange="this.form.submit()">
                    <input type="date" class="form-control" name="date_to" aria-label="To date" value="<?= e($dateTo) ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="col-md-2 col-xl-auto d-grid">
                <?php if ($filtered): ?>
                    <a href="bookings.php" class="btn btn-light" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i><span class="d-xl-none"> Reset</span></a>
                <?php else: ?>
                    <button type="button" class="btn btn-light" data-dt-reset="#bookingsTable" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i><span class="d-xl-none"> Reset</span></button>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($createdToday)): ?>
            <p class="fs-12 text-muted mb-0 mt-2"><i class="bi bi-funnel"></i> Showing bookings received today. <a href="bookings.php">Show all</a></p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <!-- Status tabs -->
    <ul class="nav nav-tabs-line px-3" role="tablist">
        <li class="nav-item">
            <button type="button" class="nav-link <?= $selected === '' ? 'active' : '' ?>" data-dt-tab="#bookingsTable" data-column="7" data-value="">
                All <span class="badge badge-soft-secondary"><?= $totalCount ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link <?= $selected === 'unassigned' ? 'active' : '' ?>" data-dt-tab="#bookingsTable" data-column="8" data-value="Unassigned">
                <i class="bi bi-person-exclamation text-warning"></i> Needs Assignment <span class="badge bg-warning"><?= $unassigned ?></span>
            </button>
        </li>
        <?php foreach ($statusList as $st): ?>
            <li class="nav-item">
                <button type="button" class="nav-link <?= $selected === strtolower($st) ? 'active' : '' ?>" data-dt-tab="#bookingsTable" data-column="7" data-value="<?= e($st) ?>">
                    <?= e($st) ?> <span class="badge badge-soft-secondary"><?= (int) ($countBy[$st] ?? 0) ?></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <table class="table table-hover js-datatable" id="bookingsTable" data-empty="<?= $filtered ? 'No bookings match these dates' : 'No bookings yet' ?>">
        <thead>
        <tr>
            <th>Booking</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Schedule</th>
            <th>Amount</th>
            <th>Payment</th>
            <th>Professional</th>
            <th>Status</th>
            <th data-visible="false" class="no-export">Queue</th>
            <th class="text-end no-export" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
            <?php $pending = needsAssignment($b); $closed = in_array($b['status'], ['Completed', 'Cancelled'], true); ?>
            <tr class="<?= $pending ? 'row-highlight' : '' ?>">
                <td class="nowrap" data-order="<?= e($b['created']) ?>">
                    <a href="booking-view.php?id=<?= e($b['id']) ?>" class="cell-title"><?= e(bookingCode($b)) ?></a>
                    <span class="cell-sub"><?= fdate($b['created'], 'd M, h:i A') ?></span>
                </td>
                <td class="nowrap">
                    <?php if ($b['customer_id']): ?>
                        <a href="customer-view.php?id=<?= e($b['customer_id']) ?>" class="fw-semibold text-heading"><?= e($b['customer'] ?: 'Customer #' . $b['customer_id']) ?></a>
                    <?php else: ?>
                        <span class="fw-semibold text-heading"><?= e($b['customer'] ?: 'Guest') ?></span>
                    <?php endif; ?>
                    <div class="cell-sub"><?= e($b['mobile']) ?></div>
                </td>
                <td style="min-width:180px"><?= e($b['service'] ?: '-') ?><div class="cell-sub"><?= e($b['category']) ?></div></td>
                <td class="nowrap" data-order="<?= e($b['date']) ?>"><?= fdate($b['date']) ?><div class="cell-sub"><?= e($b['slot']) ?></div></td>
                <td class="nowrap" data-order="<?= (float) $b['amount'] ?>"><strong><?= money($b['amount']) ?></strong><div class="cell-sub"><?= e($b['payment_method']) ?></div></td>
                <td><?= statusBadge($b['payment_status'], false) ?></td>
                <td class="nowrap">
                    <?php if ($b['professional']): ?>
                        <span class="d-inline-flex align-items-center gap-2"><?= avatar($b['professional'], 'avatar-sm') ?><?= e($b['professional']) ?></span>
                    <?php else: ?>
                        <span class="text-warning fw-semibold fs-13"><i class="bi bi-exclamation-circle"></i> Not assigned</span>
                    <?php endif; ?>
                </td>
                <td><?= statusBadge($b['status']) ?></td>
                <td><?= $pending ? 'Unassigned' : 'Assigned' ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <?php if (!$closed): ?>
                            <?= assignButton($b) ?>
                        <?php endif; ?>
                        <a href="booking-view.php?id=<?= e($b['id']) ?>" class="btn btn-icon btn-soft-info" title="View / manage"><i class="bi bi-eye"></i></a>
                        <?php if (!$closed): ?>
                            <a href="booking-view.php?id=<?= e($b['id']) ?>#cancel" class="btn btn-icon btn-soft-danger" title="Cancel booking">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
