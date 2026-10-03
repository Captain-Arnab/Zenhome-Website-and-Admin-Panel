<?php
require_once __DIR__ . '/includes/functions.php';

$activeMenu      = 'bookings';
$withAssignModal = true;

$data = api('bookings.get', ['id' => (int) ($_GET['id'] ?? 0)], null, true);
if (!$data) {
    $pageTitle   = 'Booking not found';
    $breadcrumbs = [['label' => 'Bookings', 'url' => 'bookings.php'], ['label' => 'Not found']];
    renderNotFound('Booking not found', 'This booking does not exist or was removed.', 'bookings.php', 'Back to bookings');
}

$booking  = $data['booking'];
$customer = $data['customer'];
$payment  = $data['payment'];
$notes    = $data['notes'];
$code     = bookingCode($booking);

$isCancelled = $booking['status'] === 'Cancelled';
$isClosed    = in_array($booking['status'], ['Completed', 'Cancelled'], true);
$pending     = needsAssignment($booking);

// Progress stepper: Booked -> Assigned -> Ongoing -> Completed
$steps     = ['Booked' => 'bi-cart-check', 'Assigned' => 'bi-person-check', 'Ongoing' => 'bi-tools', 'Completed' => 'bi-check2-circle'];
$stepIndex = ['New' => 0, 'Pending' => 0, 'Assigned' => 1, 'Ongoing' => 2, 'Completed' => 3, 'Cancelled' => 0][$booking['status']] ?? 0;

// Status history (booking_status_history), newest first, plus the booking itself
$eventStyle = [
    'assigned'   => ['bi-person-check', 'primary', 'Professional assigned'],
    'reassigned' => ['bi-arrow-left-right', 'primary', 'Professional reassigned'],
    'status'     => ['bi-arrow-repeat', 'info', 'Status updated'],
    'cancelled'  => ['bi-x-circle', 'danger', 'Booking cancelled'],
    'payment'    => ['bi-cash-coin', 'success', 'Payment received'],
    'refund'     => ['bi-arrow-counterclockwise', 'warning', 'Refund updated'],
];
$statusIcons = ['Ongoing' => ['bi-tools', 'purple'], 'Completed' => ['bi-check2-circle', 'success'], 'Pending' => ['bi-hourglass-split', 'warning'], 'Assigned' => ['bi-person-check', 'primary'], 'New' => ['bi-stars', 'info']];

$history = [[
    'icon' => 'bi-cart-check', 'color' => 'info', 'title' => 'Booking placed',
    'text' => 'Booked by ' . $booking['customer'] . ' (' . ($booking['payment_method'] ?: 'Cash') . ' payment)',
    'time' => $booking['created'], 'by' => 'Customer', 'notified' => null,
]];
foreach ($data['history'] as $h) {
    [$icon, $color, $title] = $eventStyle[$h['event']] ?? ['bi-dot', 'secondary', ucfirst((string) $h['event'])];
    if ($h['event'] === 'status' && $h['new_status']) {
        [$icon, $color] = $statusIcons[$h['new_status']] ?? [$icon, $color];
        $title = 'Status changed to ' . $h['new_status'];
    }
    $text = (string) ($h['note'] ?? '');
    if ($h['event'] === 'status' && $h['old_status']) {
        $text = 'From ' . $h['old_status'] . ($text !== '' ? '. ' . $text : '');
    }
    $history[] = ['icon' => $icon, 'color' => $color, 'title' => $title, 'text' => $text, 'time' => $h['at'], 'by' => $h['by'], 'notified' => in_array($h['event'], ['assigned', 'reassigned', 'status', 'cancelled'], true) ? $h['notified'] : null];
}
$history = array_reverse($history);

$pageTitle   = 'Booking ' . $code;
$breadcrumbs = [['label' => 'Bookings', 'url' => 'bookings.php'], ['label' => $code]];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2 class="d-flex flex-wrap align-items-center gap-2">
            Booking <?= e($code) ?> <?= statusBadge($booking['status']) ?> <?= statusBadge($booking['payment_status'], false, 'Payment: ' . $booking['payment_status']) ?>
        </h2>
        <p>Placed on <?= fdate($booking['created'], 'd M Y, h:i A') ?></p>
    </div>
    <div class="actions">
        <a href="bookings.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back</a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>
</div>

<?php if ($pending): ?>
    <div class="alert alert-internal d-flex flex-wrap align-items-center gap-3 mb-3">
        <i class="bi bi-person-exclamation fs-4"></i>
        <div class="flex-grow-1">
            <strong>Waiting for a professional.</strong>
            Assign an internal professional so the customer receives the assignment update.
        </div>
        <?= assignButton($booking, '') ?>
    </div>
<?php endif; ?>

<!-- Progress -->
<div class="card mb-3">
    <div class="card-body py-4">
        <div class="stepper">
            <?php $i = 0; foreach ($steps as $label => $icon): ?>
                <?php
                if ($isCancelled) {
                    $cls = $i === 0 ? 'done' : '';
                } else {
                    $cls = $i < $stepIndex || ($i === $stepIndex && $booking['status'] === 'Completed') ? 'done' : ($i === $stepIndex ? 'current' : '');
                }
                ?>
                <div class="step <?= $cls ?>">
                    <div class="dot"><i class="bi <?= $icon ?>"></i></div>
                    <small><?= e($label) ?></small>
                </div>
            <?php $i++; endforeach; ?>
            <?php if ($isCancelled): ?>
                <div class="step cancelled">
                    <div class="dot"><i class="bi bi-x-lg"></i></div>
                    <small>Cancelled</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left: details -->
    <div class="col-xl-8">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-person me-1 text-primary"></i> Customer</h3>
                        <?php if ($booking['customer_id']): ?>
                            <a href="customer-view.php?id=<?= e($booking['customer_id']) ?>" class="fs-13 fw-semibold">View profile</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <?= avatar($booking['customer'] ?: 'Customer', 'avatar-lg') ?>
                            <div>
                                <strong class="text-heading d-block fs-6"><?= e($booking['customer'] ?: 'Unknown customer') ?></strong>
                                <span class="text-muted fs-13"><?= $booking['customer_id'] ? 'Customer #' . e($booking['customer_id']) : 'Guest' ?></span>
                            </div>
                        </div>
                        <dl class="detail-list">
                            <div class="row-item"><dt>Mobile</dt><dd><?php if ($booking['mobile']): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $booking['mobile'])) ?>"><?= e($booking['mobile']) ?></a><?php else: ?>-<?php endif; ?></dd></div>
                            <div class="row-item"><dt>Email</dt><dd><?= e(($customer['email'] ?? '') ?: ($booking['email'] ?: '-')) ?></dd></div>
                            <div class="row-item"><dt>Total bookings</dt><dd><?= (int) ($customer['bookings'] ?? 1) ?></dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-tools me-1 text-primary"></i> Service</h3></div>
                    <div class="card-body">
                        <dl class="detail-list">
                            <div class="row-item"><dt>Service</dt><dd><?= e($booking['service'] ?: '-') ?></dd></div>
                            <div class="row-item"><dt>Category</dt><dd><?= e($booking['category'] ?: '-') ?></dd></div>
                            <div class="row-item"><dt>Date</dt><dd><?= fdate($booking['date'], 'D, d M Y') ?></dd></div>
                            <div class="row-item"><dt>Time slot</dt><dd><?= e($booking['slot'] ?: '-') ?></dd></div>
                            <div class="row-item"><dt>Booking status</dt><dd><?= statusBadge($booking['status']) ?></dd></div>
                            <?php if ($booking['notes']): ?>
                                <div class="row-item"><dt>Customer note</dt><dd><?= e($booking['notes']) ?></dd></div>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-geo-alt me-1 text-primary"></i> Service Address</h3></div>
                    <div class="card-body">
                        <?php if ($booking['address']): ?>
                            <p class="mb-2 fw-semibold text-heading"><?= e($booking['address']) ?></p>
                            <p class="text-muted fs-13 mb-3">
                                <?php if ($booking['area']): ?>Area: <?= e($booking['area']) ?><?php endif; ?>
                                <?php if ($booking['pincode']): ?> &middot; PIN <?= e($booking['pincode']) ?><?php endif; ?>
                                <?php if ($booking['landmark']): ?><br>Landmark: <?= e($booking['landmark']) ?><?php endif; ?>
                            </p>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($booking['address']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light">
                                <i class="bi bi-map me-1"></i> Open in Google Maps
                            </a>
                        <?php else: ?>
                            <p class="text-muted mb-0">No address was saved with this booking.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-credit-card me-1 text-primary"></i> Payment</h3>
                        <?= statusBadge($booking['payment_status']) ?>
                    </div>
                    <div class="card-body">
                        <dl class="detail-list">
                            <?php foreach ($booking['items'] as $item): ?>
                                <div class="row-item"><dt><?= e($item['name']) ?></dt><dd><?= $item['price'] !== null ? money($item['price']) : '-' ?></dd></div>
                            <?php endforeach; ?>
                            <?php if ($booking['coupon_code'] !== ''): ?>
                                <?php if ($booking['gross_amount'] !== null): ?>
                                    <div class="row-item"><dt>Subtotal</dt><dd><?= money($booking['gross_amount']) ?></dd></div>
                                <?php endif; ?>
                                <div class="row-item"><dt>Coupon <span class="badge badge-soft-success"><?= e($booking['coupon_code']) ?></span></dt><dd class="text-success">- <?= money($booking['discount']) ?></dd></div>
                            <?php endif; ?>
                            <div class="row-item"><dt>Total</dt><dd class="fs-6"><?= money($booking['amount']) ?></dd></div>
                            <div class="row-item"><dt>Method</dt><dd><?= e($booking['payment_method'] === 'Online' ? 'Online (' . ($payment['mode'] ?? 'PhonePe') . ')' : 'Cash on service') ?></dd></div>
                            <div class="row-item"><dt>Transaction ID</dt><dd class="font-monospace fs-12"><?= e($booking['transaction_id'] ?: '-') ?></dd></div>
                            <?php if ($booking['refund_status']): ?>
                                <div class="row-item"><dt>Refund</dt><dd><?= statusBadge($booking['refund_status']) ?></dd></div>
                            <?php endif; ?>
                        </dl>
                        <?php if ($booking['payment_method'] !== 'Online' && $booking['payment_status'] === 'Pending' && !$isCancelled): ?>
                            <button type="button" class="btn btn-sm btn-success mt-3"
                                    data-confirm="Mark cash payment of <?= e(money($booking['amount'])) ?> as received?" data-confirm-title="Confirm Cash Payment" data-confirm-btn="Mark as Paid"
                                    data-api="bookings.mark_paid" data-api-params='<?= jsonAttr(['booking_id' => $booking['id']]) ?>' data-reload>
                                <i class="bi bi-cash-coin me-1"></i> Mark cash received
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><i class="bi bi-clock-history me-1 text-primary"></i> Status History</h3></div>
                    <div class="card-body">
                        <ul class="timeline">
                            <?php foreach ($history as $h): ?>
                                <li>
                                    <span class="t-icon icon-soft-<?= e($h['color']) ?>"><i class="bi <?= e($h['icon']) ?>"></i></span>
                                    <strong><?= e($h['title']) ?></strong>
                                    <?php if ($h['text'] !== ''): ?><p><?= e($h['text']) ?></p><?php endif; ?>
                                    <small>
                                        <?= fdate($h['time'], 'd M Y, h:i A') ?> &middot; by <?= e($h['by']) ?>
                                        <?php if ($h['notified'] === true): ?>
                                            &middot; <span class="text-success"><i class="bi bi-bell"></i> Customer notified</span>
                                        <?php elseif ($h['notified'] === false): ?>
                                            &middot; <span class="text-muted"><i class="bi bi-bell-slash"></i> Customer not notified</span>
                                        <?php endif; ?>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($data['notifications']): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="bi bi-bell me-1 text-primary"></i> Customer Notifications</h3></div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Message</th><th>Channels</th><th>Status</th><th>Sent</th></tr></thead>
                                <tbody>
                                <?php foreach ($data['notifications'] as $n): ?>
                                    <tr>
                                        <td><?= e($n['title']) ?></td>
                                        <td class="nowrap"><?= e(implode(', ', text_list($n['channels']))) ?></td>
                                        <td><?= statusBadge((string) $n['status'], false) ?><?php if ($n['status_detail']): ?><div class="cell-sub"><?= e($n['status_detail']) ?></div><?php endif; ?></td>
                                        <td class="nowrap"><?= fdate($n['created_at'], 'd M, h:i A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: action panel -->
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-person-badge me-1 text-primary"></i> Assigned Professional</h3></div>
            <div class="card-body">
                <?php if ($booking['professional']): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?= avatar($booking['professional'], 'avatar-lg') ?>
                        <div class="min-w-0">
                            <?php if ($booking['professional_id']): ?>
                                <a href="professional-view.php?id=<?= e($booking['professional_id']) ?>" class="fw-bold text-heading d-block"><?= e($booking['professional']) ?></a>
                            <?php else: ?>
                                <strong class="text-heading d-block"><?= e($booking['professional']) ?></strong>
                            <?php endif; ?>
                            <?php if (!empty($booking['professional_category'])): ?>
                                <span class="fs-13 text-muted d-block"><?= e($booking['professional_category']) ?></span>
                            <?php endif; ?>
                            <?php if ($booking['professional_mobile']): ?>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', $booking['professional_mobile'])) ?>" class="fs-13"><i class="bi bi-telephone"></i> <?= e($booking['professional_mobile']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$isClosed): ?>
                        <div class="d-grid"><?= assignButton($booking, '') ?></div>
                    <?php endif; ?>
                <?php elseif ($isCancelled): ?>
                    <p class="text-muted mb-0">No professional was assigned before cancellation.</p>
                <?php else: ?>
                    <div class="text-center py-2">
                        <span class="modal-icon icon-soft-warning mb-2"><i class="bi bi-person-exclamation"></i></span>
                        <p class="fw-semibold text-heading mb-1">Not assigned yet</p>
                        <p class="text-muted fs-13">Pick an internal professional for this job.</p>
                        <div class="d-grid"><?= assignButton($booking, '') ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$isClosed): ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="bi bi-arrow-repeat me-1 text-primary"></i> Update Status</h3></div>
                <div class="card-body">
                    <form class="needs-validation" novalidate data-api="bookings.update_status" data-reload>
                        <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>" data-keep>
                        <div class="mb-3">
                            <label class="form-label required" for="newStatus">New status</label>
                            <select class="form-select" id="newStatus" name="status" required>
                                <option value="">Select status</option>
                                <?php foreach (['Pending', 'Assigned', 'Ongoing', 'Completed'] as $st): ?>
                                    <?php $needsPro = $st !== 'Pending' && !$booking['professional']; ?>
                                    <option value="<?= e($st) ?>" <?= $st === $booking['status'] || $needsPro ? 'disabled' : '' ?>><?= e($st) ?><?= $st === $booking['status'] ? ' (current)' : ($needsPro ? ' (assign a professional first)' : '') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Choose a status.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="statusNote">Remarks</label>
                            <textarea class="form-control" id="statusNote" name="remarks" rows="2" maxlength="500" placeholder="Optional note saved in history"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="statusNotify" name="notify" checked>
                            <label class="form-check-label fs-13" for="statusNotify">Send status update to customer (SMS + push)</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i> Update Status</button>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h3 class="card-title mb-1">Cancel booking</h3>
                    <p class="text-muted fs-13">Online payments will be marked for refund.</p>
                    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="bi bi-x-circle me-1"></i> Cancel Booking
                    </button>
                </div>
            </div>
        <?php elseif ($isCancelled && $booking['cancel_reason']): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h3 class="card-title mb-1 text-danger">Cancelled</h3>
                    <p class="fs-13 mb-0"><?= e($booking['cancel_reason']) ?></p>
                    <?php if ($booking['cancelled_at']): ?><small class="text-muted"><?= fdate($booking['cancelled_at'], 'd M Y, h:i A') ?></small><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-sticky me-1 text-primary"></i> Internal Notes</h3></div>
            <div class="card-body">
                <form class="needs-validation" novalidate data-api="bookings.add_note" data-reload>
                    <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>" data-keep>
                    <textarea class="form-control mb-2" name="note" rows="2" maxlength="1000" placeholder="Visible to admins only" required></textarea>
                    <button type="submit" class="btn btn-sm btn-light">Add note</button>
                </form>
                <?php foreach ($notes as $n): ?>
                    <div class="border-top mt-3 pt-2">
                        <p class="fs-13 mb-1"><?= nl2br(e($n['note'])) ?></p>
                        <small class="text-muted"><?= e($n['admin_name']) ?> &middot; <?= fdate($n['created_at'], 'd M Y, h:i A') ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$isClosed): ?>
<!-- Cancel booking modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="bookings.cancel">
                <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>" data-keep>
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-1"></i> Cancel Booking <?= e($code) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required" for="cancelReason">Reason</label>
                        <select class="form-select" id="cancelReason" name="reason" required>
                            <option value="">Select a reason</option>
                            <option>Customer requested cancellation</option>
                            <option>No professional available</option>
                            <option>Service not available in this area</option>
                            <option>Duplicate booking</option>
                            <option>Payment failed</option>
                            <option>Other</option>
                        </select>
                        <div class="invalid-feedback">Please select a reason.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cancelDetails">Details</label>
                        <textarea class="form-control" id="cancelDetails" name="details" rows="3" maxlength="500" placeholder="Shared with the customer in the cancellation message"></textarea>
                    </div>
                    <?php if ($booking['payment_method'] === 'Online' && $booking['payment_status'] === 'Paid'): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="cancelRefund" name="refund" checked>
                            <label class="form-check-label fs-13" for="cancelRefund">Mark a refund of <strong><?= money($booking['amount']) ?></strong> as pending (process it in the PhonePe dashboard, then update it under Payments)</label>
                        </div>
                    <?php endif; ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="cancelNotify" name="notify" checked>
                        <label class="form-check-label fs-13" for="cancelNotify">Notify customer by SMS &amp; push</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep booking</button>
                    <button type="submit" class="btn btn-danger">Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
ob_start(); ?>
<script>
    // bookings.php "Cancel" opens this page with #cancel
    if (location.hash === '#cancel' && document.getElementById('cancelModal')) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelModal')).show();
    }
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
