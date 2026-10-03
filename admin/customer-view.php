<?php
require_once __DIR__ . '/includes/functions.php';

$activeMenu = 'customers';

$data = api('customers.get', ['id' => (int) ($_GET['id'] ?? 0)], null, true);
if (!$data) {
    $pageTitle   = 'Customer not found';
    $breadcrumbs = [['label' => 'Customers', 'url' => 'customers.php'], ['label' => 'Not found']];
    renderNotFound('Customer not found', 'This customer does not exist or was deleted.', 'customers.php', 'Back to customers');
}

$customer         = $data['customer'];
$addresses        = $data['addresses'];
$customerBookings = $data['bookings']['items'];
$bookingTotal     = (int) $data['bookings']['pagination']['total_items'];
$customerPayments = $data['payments']['items'];
$paymentTotal     = (int) $data['payments']['pagination']['total_items'];
$place            = implode(', ', array_filter([$customer['area'], $customer['city']]));

$pageTitle   = 'Customer Details';
$breadcrumbs = [['label' => 'Customers', 'url' => 'customers.php'], ['label' => $customer['name']]];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2><?= e($customer['name']) ?> <span class="text-muted fs-6 fw-semibold">#<?= e($customer['id']) ?></span></h2>
        <p>Customer since <?= fdate($customer['joined']) ?></p>
    </div>
    <div class="actions">
        <a href="customers.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <!-- Profile card -->
    <div class="col-lg-4 col-xl-3">
        <div class="card profile-card">
            <div class="card-body p-4">
                <?= avatar($customer['name'], 'avatar-xl') ?>
                <h5><?= e($customer['name']) ?></h5>
                <p class="text-muted fs-13 mb-2"><?= e($place ?: 'Location not available') ?></p>
                <?= statusBadge($customer['status'] ? 'Active' : 'Inactive') ?>

                <div class="profile-stats">
                    <div><strong><?= (int) $customer['bookings'] ?></strong><small>Bookings</small></div>
                    <div><strong><?= money($customer['spent']) ?></strong><small>Spent</small></div>
                </div>
            </div>
            <div class="card-footer text-start">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="d-block fs-13 text-heading">Account status</strong>
                        <small class="text-muted">Deactivating logs the customer out of all devices and blocks login.</small>
                    </div>
                    <?= statusSwitch($customer['status'], $customer['name'], 'Active', 'Inactive', (string) $customer['id'], 'customers.set_status') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="col-lg-8 col-xl-9">
        <div class="card">
            <ul class="nav nav-tabs-line px-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#details" type="button">Details</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#addresses" type="button">Saved Addresses <span class="badge badge-soft-secondary"><?= count($addresses) ?></span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bookingHistory" type="button">Booking History <span class="badge badge-soft-secondary"><?= $bookingTotal ?></span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paymentHistory" type="button">Payment History <span class="badge badge-soft-secondary"><?= $paymentTotal ?></span></button></li>
            </ul>

            <div class="tab-content">
                <!-- Details -->
                <div class="tab-pane fade show active p-4" id="details" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <dl class="detail-list">
                                <div class="row-item"><dt>Full name</dt><dd><?= e($customer['name']) ?></dd></div>
                                <div class="row-item"><dt>Mobile</dt><dd><?= e($customer['mobile'] ?: '-') ?></dd></div>
                                <div class="row-item"><dt>Email</dt><dd><?= e($customer['email'] ?: '-') ?></dd></div>
                                <div class="row-item"><dt>City</dt><dd><?= e($customer['city'] ?: '-') ?></dd></div>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="detail-list">
                                <div class="row-item"><dt>Customer ID</dt><dd><?= e($customer['id']) ?></dd></div>
                                <div class="row-item"><dt>Registered on</dt><dd><?= fdate($customer['joined']) ?></dd></div>
                                <div class="row-item"><dt>Last booking</dt><dd><?= fdate($customer['last_booking']) ?></dd></div>
                                <div class="row-item"><dt>Status</dt><dd><?= statusBadge($customer['status'] ? 'Active' : 'Inactive') ?></dd></div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Saved addresses (profile address + addresses used on bookings) -->
                <div class="tab-pane fade p-4" id="addresses" role="tabpanel">
                    <div class="row g-3">
                        <?php foreach ($addresses as $a): ?>
                            <div class="col-md-6">
                                <div class="address-card <?= $a['default'] ? 'default' : '' ?>">
                                    <?php if ($a['default']): ?><span class="badge badge-soft-primary">Latest</span><?php endif; ?>
                                    <strong class="text-heading d-block mb-1"><i class="bi bi-<?= $a['label'] === 'Profile address' ? 'person' : 'geo-alt' ?> me-1"></i><?= e($a['label']) ?></strong>
                                    <p class="fs-13 mb-1"><?= e($a['line']) ?></p>
                                    <?php if ($a['landmark'] !== ''): ?><small class="text-muted d-block">Landmark: <?= e($a['landmark']) ?></small><?php endif; ?>
                                    <?php if ($a['used_at']): ?><small class="text-muted">Last used <?= fdate($a['used_at']) ?></small><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$addresses): ?>
                            <div class="col-12"><?= emptyState('No addresses saved yet.', 'bi-geo') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Booking history -->
                <div class="tab-pane fade" id="bookingHistory" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Booking</th><th>Service</th><th>Date</th><th>Amount</th><th>Professional</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($customerBookings as $b): ?>
                                <tr>
                                    <td class="nowrap"><a href="booking-view.php?id=<?= e($b['id']) ?>" class="fw-bold"><?= e(bookingCode($b)) ?></a></td>
                                    <td><?= e($b['service'] ?: '-') ?></td>
                                    <td class="nowrap"><?= fdate($b['date']) ?><div class="cell-sub"><?= e($b['slot']) ?></div></td>
                                    <td class="nowrap"><?= money($b['amount']) ?></td>
                                    <td class="nowrap"><?= $b['professional'] ? e($b['professional']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= statusBadge($b['status']) ?></td>
                                    <td class="text-end"><a href="booking-view.php?id=<?= e($b['id']) ?>" class="btn btn-icon btn-soft-info" title="View"><i class="bi bi-eye"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$customerBookings): ?>
                                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i><p class="mt-2 mb-0">No bookings yet.</p></div></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($bookingTotal > count($customerBookings)): ?>
                        <p class="fs-12 text-muted px-3 pb-3 mb-0">Showing the latest <?= count($customerBookings) ?> of <?= $bookingTotal ?> bookings. <a href="bookings.php">Open Bookings</a> to search all.</p>
                    <?php endif; ?>
                </div>

                <!-- Payment history -->
                <div class="tab-pane fade" id="paymentHistory" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Transaction ID</th><th>Booking</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th><th>Refund</th></tr></thead>
                            <tbody>
                            <?php foreach ($customerPayments as $p): ?>
                                <tr>
                                    <td class="nowrap font-monospace fs-12"><?= e($p['txn']) ?></td>
                                    <td><?php if ($p['booking_id']): ?><a href="booking-view.php?id=<?= e($p['booking_id']) ?>"><?= e($p['booking']) ?></a><?php else: ?>-<?php endif; ?></td>
                                    <td class="nowrap fw-bold"><?= money($p['amount']) ?></td>
                                    <td><?= e($p['method']) ?></td>
                                    <td class="nowrap"><?= fdate($p['date'], 'd M Y, h:i A') ?></td>
                                    <td><?= statusBadge($p['status']) ?></td>
                                    <td><?= $p['refund'] === '-' ? '<span class="text-muted">-</span>' : statusBadge($p['refund']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$customerPayments): ?>
                                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt"></i><p class="mt-2 mb-0">No payments yet.</p></div></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
