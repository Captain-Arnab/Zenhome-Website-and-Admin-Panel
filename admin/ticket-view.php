<?php
require_once __DIR__ . '/includes/functions.php';

$data = api('tickets.get', ['id' => (int) ($_GET['id'] ?? 0)], null, true);
if (!$data) {
    renderNotFound('Ticket not found', 'This support ticket does not exist or was removed.', 'support-tickets.php', 'Back to tickets');
}
$ticket   = $data['ticket'];
$messages = $data['messages'];
$remarks  = $data['remarks'];
$booking  = $data['booking'];

$pageTitle   = 'Ticket ' . $ticket['code'];
$activeMenu  = 'tickets';
$breadcrumbs = [['label' => 'Support Tickets', 'url' => 'support-tickets.php'], ['label' => $ticket['code']]];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2 class="d-flex flex-wrap align-items-center gap-2"><?= e($ticket['subject']) ?> <?= statusBadge($ticket['status']) ?></h2>
        <p><?= e($ticket['code']) ?> &middot; opened <?= fdate($ticket['created'], 'd M Y, h:i A') ?> by <?= e($ticket['customer']) ?></p>
    </div>
    <div class="actions">
        <a href="support-tickets.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <!-- Issue + conversation -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Conversation</h3>
                <span class="text-muted fs-12"><?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?></span>
            </div>
            <div class="card-body">
                <?php if (!$messages): ?>
                    <?= emptyState('No messages on this ticket yet.', 'bi-chat-dots') ?>
                <?php else: ?>
                    <div class="chat-thread">
                        <?php foreach ($messages as $m): ?>
                            <div class="chat-msg <?= $m['from'] === 'admin' ? 'admin' : '' ?>">
                                <?= avatar($m['name'], 'avatar-sm') ?>
                                <div>
                                    <div class="meta"><strong class="text-heading"><?= e($m['name']) ?></strong><?= $m['from'] === 'admin' ? ' (Support)' : '' ?> &middot; <?= fdate($m['time'], 'd M, h:i A') ?></div>
                                    <div class="bubble"><?= nl2br(e($m['text'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reply -->
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-reply me-1 text-primary"></i> Reply to customer</h3></div>
            <div class="card-body">
                <form class="needs-validation" novalidate data-api="tickets.reply" data-reload>
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <div class="mb-2">
                        <select class="form-select form-select-sm w-auto mb-2" aria-label="Quick reply" onchange="if (this.value) { document.getElementById('replyText').value = this.value; this.selectedIndex = 0; }">
                            <option value="">Insert quick reply...</option>
                            <option value="We have rescheduled your booking as requested. You will receive a confirmation SMS shortly.">Rescheduled confirmation</option>
                            <option value="Your refund has been initiated and will reflect in 5-7 working days.">Refund initiated</option>
                            <option value="A professional has now been assigned to your booking. You will receive their details by SMS.">Professional assigned</option>
                        </select>
                        <textarea class="form-control" id="replyText" name="message" rows="4" required maxlength="2000" placeholder="Type your reply..."></textarea>
                        <div class="invalid-feedback">Reply cannot be empty.</div>
                    </div>
                    <div class="form-text mb-2"><i class="bi bi-info-circle me-1"></i> Customers do not have a ticket screen in the app yet, so the reply is also sent as a push notification (when push is configured).</div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="replyClose" name="close">
                            <label class="form-check-label fs-13" for="replyClose">Close ticket after sending</label>
                        </div>
                        <button type="submit" class="btn btn-primary" data-busy-text="Sending..."><i class="bi bi-send me-1"></i> Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <!-- Status -->
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Ticket status</h3></div>
            <div class="card-body">
                <form class="needs-validation" novalidate data-api="tickets.update" data-reload>
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <div class="btn-group w-100 mb-3" role="group" aria-label="Ticket status">
                        <input type="radio" class="btn-check" name="status" id="stOpen" value="Open" <?= $ticket['status'] === 'Open' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="stOpen"><i class="bi bi-envelope-open me-1"></i> Open</label>
                        <input type="radio" class="btn-check" name="status" id="stClosed" value="Closed" <?= $ticket['status'] === 'Closed' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="stClosed"><i class="bi bi-check2-circle me-1"></i> Closed</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ticketPriority">Priority</label>
                        <select class="form-select" id="ticketPriority" name="priority">
                            <?php foreach (['High', 'Medium', 'Low'] as $p): ?>
                                <option <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update</button>
                </form>
            </div>
        </div>

        <!-- Details -->
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Issue details</h3></div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="row-item"><dt>Customer</dt><dd><?= $ticket['customer_id'] ? '<a href="customer-view.php?id=' . e($ticket['customer_id']) . '">' . e($ticket['customer']) . '</a>' : e($ticket['customer']) ?></dd></div>
                    <div class="row-item"><dt>Mobile</dt><dd><?= e($ticket['mobile'] ?: '-') ?></dd></div>
                    <div class="row-item"><dt>Category</dt><dd><?= e($ticket['category']) ?></dd></div>
                    <div class="row-item"><dt>Priority</dt><dd><?= statusBadge($ticket['priority'], false) ?></dd></div>
                    <div class="row-item"><dt>Booking</dt><dd><?= $booking ? '<a href="booking-view.php?id=' . e($booking['id']) . '">' . e(bookingCode($booking)) . '</a>' : ($ticket['booking'] ? e($ticket['booking']) : '-') ?></dd></div>
                    <?php if ($booking): ?>
                        <div class="row-item"><dt>Service</dt><dd><?= e($booking['service']) ?></dd></div>
                        <div class="row-item"><dt>Booking status</dt><dd><?= statusBadge($booking['status']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($ticket['closed_at']): ?>
                        <div class="row-item"><dt>Closed</dt><dd><?= fdate($ticket['closed_at'], 'd M Y, h:i A') ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Internal remarks -->
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-lock me-1 text-primary"></i> Internal remarks</h3></div>
            <div class="card-body">
                <?php if ($remarks): ?>
                    <ul class="timeline mb-3">
                        <?php foreach ($remarks as $r): ?>
                            <li>
                                <span class="t-icon icon-soft-secondary"><i class="bi bi-sticky"></i></span>
                                <p><?= nl2br(e($r['text'])) ?></p>
                                <small><?= e($r['name']) ?> &middot; <?= fdate($r['time'], 'd M, h:i A') ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted fs-13 mb-3">No remarks yet.</p>
                <?php endif; ?>
                <form class="needs-validation" novalidate data-api="tickets.add_remark" data-reload>
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <textarea class="form-control mb-2" name="remark" rows="2" required maxlength="1000" placeholder="Visible to admins only"></textarea>
                    <button type="submit" class="btn btn-sm btn-light">Add remark</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
