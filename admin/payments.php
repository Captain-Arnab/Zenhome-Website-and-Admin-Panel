<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Payments';
$activeMenu  = 'payments';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Payments']];

$isDate   = fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d);
$dateFrom = $isDate($_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$dateTo   = $isDate($_GET['date_to'] ?? '') ? $_GET['date_to'] : '';
$filtered = $dateFrom !== '' || $dateTo !== '';

$query = ['with_summary' => 1];
if ($dateFrom !== '') $query['date_from'] = $dateFrom;
if ($dateTo !== '')   $query['date_to'] = $dateTo;

$result   = api_list('payments.list', $query, 'payments');
$payments = $result['items'];
$summary  = $result['summary'] ?? ['received' => 0, 'pending' => 0, 'refunds' => 0, 'refunds_pending' => 0, 'online_count' => 0, 'cash_count' => 0];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Payments</h2>
        <p>Online (PhonePe) and cash-on-service payments for all bookings.</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-outline-secondary" data-export="#paymentsTable" data-filename="payments-<?= date('Y-m-d') ?>.csv"><i class="bi bi-download me-1"></i> Export</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><?= statCard('Total Received', money($summary['received']), 'bi-wallet2', 'success', '<span class="text-muted">' . ($filtered ? 'In selected period' : 'Paid transactions') . '</span>') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Pending', money($summary['pending']), 'bi-hourglass-split', 'warning', '<span class="text-muted">Cash to collect</span>') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Refunds', money($summary['refunds']), 'bi-arrow-counterclockwise', 'info', '<span class="text-muted">' . ($summary['refunds_pending'] ? money($summary['refunds_pending']) . ' pending' : 'Processed + pending') . '</span>') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Online / Cash', (int) $summary['online_count'] . ' / ' . (int) $summary['cash_count'], 'bi-phone', 'primary', '<span class="text-muted">Transactions</span>') ?></div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6 col-xl-3">
                <label class="form-label">Search</label>
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Transaction ID, booking, customer" data-dt-search="#paymentsTable">
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <label class="form-label">Method</label>
                <select class="form-select" data-dt-filter="#paymentsTable" data-column="4">
                    <option value="">All methods</option>
                    <option>Online</option>
                    <option>Cash</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <label class="form-label">Status</label>
                <select class="form-select" data-dt-filter="#paymentsTable" data-column="5" data-exact>
                    <option value="">All statuses</option>
                    <option>Paid</option>
                    <option>Pending</option>
                    <option>Refunded</option>
                    <option>Failed</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <label class="form-label">Refund</label>
                <select class="form-select" data-dt-filter="#paymentsTable" data-column="6" data-exact>
                    <option value="">Any</option>
                    <option>Refund Pending</option>
                    <option>Processed</option>
                </select>
            </div>
            <div class="col-6 col-md-6 col-xl-3">
                <label class="form-label">Date range</label>
                <form method="get" class="input-group">
                    <input type="date" class="form-control" name="date_from" aria-label="From" value="<?= e($dateFrom) ?>" onchange="this.form.submit()">
                    <input type="date" class="form-control" name="date_to" aria-label="To" value="<?= e($dateTo) ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="col-md-3 col-xl-auto d-grid">
                <?php if ($filtered): ?>
                    <a href="payments.php" class="btn btn-light" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php else: ?>
                    <button type="button" class="btn btn-light" data-dt-reset="#paymentsTable" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="paymentsTable" data-empty="<?= $filtered ? 'No payments in this period' : 'No payments yet' ?>">
        <thead>
        <tr>
            <th>Transaction ID</th>
            <th>Booking</th>
            <th>Customer</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Refund</th>
            <th>Date</th>
            <th class="text-end" data-orderable="false">Details</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td class="nowrap font-monospace fs-12"><?= e($p['txn']) ?></td>
                <td><?php if ($p['booking_id']): ?><a href="booking-view.php?id=<?= e($p['booking_id']) ?>" class="fw-bold"><?= e($p['booking']) ?></a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                <td class="nowrap"><?php if ($p['customer_id']): ?><a href="customer-view.php?id=<?= e($p['customer_id']) ?>" class="text-heading"><?= e($p['customer']) ?></a><?php else: ?><?= e($p['customer']) ?><?php endif; ?></td>
                <td class="nowrap fw-bold" data-order="<?= (float) $p['amount'] ?>"><?= money($p['amount']) ?></td>
                <td class="nowrap">
                    <i class="bi <?= $p['method'] === 'Online' ? 'bi-phone text-primary' : 'bi-cash-stack text-success' ?> me-1"></i><?= e($p['method']) ?>
                    <div class="cell-sub"><?= e($p['gateway']) ?></div>
                </td>
                <td><?= statusBadge($p['status']) ?></td>
                <td><?= $p['refund'] === '-' ? '<span class="text-muted">-</span>' : statusBadge($p['refund'], false) ?></td>
                <td class="nowrap" data-order="<?= e($p['date']) ?>"><?= fdate($p['date'], 'd M Y') ?><div class="cell-sub"><?= fdate($p['date'], 'h:i A') ?></div></td>
                <td class="text-end">
                    <button type="button" class="btn btn-icon btn-soft-info" title="View invoice"
                            data-bs-toggle="modal" data-bs-target="#paymentModal" data-txn="<?= e($p['txn']) ?>">
                        <i class="bi bi-receipt"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Payment / invoice detail modal (loaded from payments.get) -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-1 text-primary"></i> Invoice <span data-p="booking"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center py-5 d-none" data-invoice-loading>
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted fs-13 mt-2 mb-0">Loading invoice...</p>
                </div>
                <div class="alert alert-danger d-none" data-invoice-error></div>
                <div data-invoice-body id="invoiceBody">
                    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                        <div>
                            <img src="assets/img/logo.png" alt="<?= e(site_setting('company_name')) ?>" height="48">
                            <p class="fs-12 text-muted mb-0 mt-2"><?= e(site_setting('company_name')) ?><br><?= e(implode(', ', array_slice(site_address_lines(), -2))) ?> &middot; <?= e(site_phone_intl(site_setting('phone'))) ?></p>
                        </div>
                        <div class="text-md-end">
                            <p class="mb-1 fs-13 text-muted">Invoice no.</p>
                            <strong class="text-heading" data-p="invoice_no"></strong>
                            <p class="mb-0 fs-13 text-muted mt-1" data-p="date_fmt"></p>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <p class="fs-12 text-uppercase text-muted fw-bold mb-1">Billed to</p>
                            <strong class="text-heading d-block" data-p="customer"></strong>
                            <span class="fs-13 d-block" data-p="mobile"></span>
                            <span class="fs-13 text-muted" data-p="address"></span>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-12 text-uppercase text-muted fw-bold mb-1">Payment</p>
                            <dl class="detail-list">
                                <div class="row-item"><dt>Method</dt><dd><span data-p="method"></span> (<span data-p="gateway"></span>)</dd></div>
                                <div class="row-item"><dt>Transaction ID</dt><dd class="font-monospace fs-12" data-p="txn"></dd></div>
                                <div class="row-item"><dt>Status</dt><dd data-p="status"></dd></div>
                                <div class="row-item"><dt>Refund</dt><dd data-p="refund"></dd></div>
                            </dl>
                        </div>
                    </div>
                    <div class="table-responsive border rounded-3">
                        <table class="table mb-0">
                            <thead><tr><th>Service</th><th>Scheduled</th><th class="text-end">Amount</th></tr></thead>
                            <tbody data-invoice-items></tbody>
                            <tfoot>
                            <tr><td colspan="2" class="text-end fw-bold">Total</td><td class="text-end fw-bold text-heading" data-p="amount_fmt"></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-secondary" data-invoice-print><i class="bi bi-printer me-1"></i> Print</button>
                <?php if (isSuperAdmin()): ?>
                    <button type="button" class="btn btn-outline-danger d-none" data-invoice-refund
                            data-bs-toggle="modal" data-bs-target="#refundModal" data-title="Update Refund">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Refund
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (isSuperAdmin()): ?>
<!-- Refund tracking (Super Admin) -->
<div class="modal fade js-crud-modal" id="refundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="payments.refund_update">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" data-modal-title>Update Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info fs-13 py-2">
                        <i class="bi bi-info-circle me-1"></i> Refunds are processed in the PhonePe dashboard. Record the status here so reports and the booking history stay correct.
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="refundStatus">Refund status</label>
                        <select class="form-select" id="refundStatus" name="refund_status" required>
                            <option value="Refund Pending">Refund Pending</option>
                            <option value="Processed">Processed</option>
                            <option value="None">No refund (clear)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="refundAmount">Amount</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="refundAmount" name="amount" min="1" step="0.01">
                            <div class="invalid-feedback">Enter a valid amount.</div>
                        </div>
                        <div class="form-text">Leave as is for a full refund.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="refundNote">Note</label>
                        <input type="text" class="form-control" id="refundNote" name="note" maxlength="255" placeholder="e.g. PhonePe refund ID">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Save Refund Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php ob_start(); ?>
<script>
    (function () {
        const modal = document.getElementById('paymentModal');
        const loading = modal.querySelector('[data-invoice-loading]');
        const error = modal.querySelector('[data-invoice-error]');
        const body = modal.querySelector('[data-invoice-body]');
        const refundBtn = modal.querySelector('[data-invoice-refund]');
        const inr = n => '\u20b9' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
        const fmtDate = s => s ? new Date(s.replace(' ', 'T')).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';

        modal.addEventListener('show.bs.modal', function (e) {
            if (!e.relatedTarget || !e.relatedTarget.dataset.txn) return;
            loading.classList.remove('d-none');
            body.classList.add('d-none');
            error.classList.add('d-none');
            if (refundBtn) refundBtn.classList.add('d-none');

            AdminApi.get('payments.get', { id: e.relatedTarget.dataset.txn }).then(res => {
                const d = res.data, p = d.payment, b = d.booking || {};
                const values = {
                    booking: p.booking || '', invoice_no: d.invoice_no, date_fmt: fmtDate(p.date),
                    customer: b.customer || p.customer, mobile: b.mobile || p.mobile || '', address: b.address || '',
                    method: p.method, gateway: p.gateway || '-', txn: p.txn, status: p.status,
                    refund: d.refund && d.refund.status ? d.refund.status + (d.refund.amount ? ' (' + inr(d.refund.amount) + ')' : '') : '-',
                    amount_fmt: inr(p.amount)
                };
                modal.querySelectorAll('[data-p]').forEach(el => { el.textContent = values[el.dataset.p] ?? '-'; });

                const rows = modal.querySelector('[data-invoice-items]');
                rows.innerHTML = '';
                const items = (b.items && b.items.length) ? b.items : [{ name: b.service || 'Service', price: p.amount }];
                const scheduled = b.date ? new Date(b.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) + (b.slot ? ', ' + b.slot : '') : '-';
                items.forEach((item, i) => {
                    const tr = document.createElement('tr');
                    [item.name, i === 0 ? scheduled : '', item.price !== null && item.price !== undefined ? inr(item.price) : '-'].forEach((text, c) => {
                        const td = document.createElement('td');
                        if (c === 2) td.className = 'text-end';
                        td.textContent = text;
                        tr.appendChild(td);
                    });
                    rows.appendChild(tr);
                });

                if (refundBtn && p.kind === 'online' && (p.status === 'Paid' || p.status === 'Refunded')) {
                    refundBtn.classList.remove('d-none');
                    refundBtn.dataset.fill = JSON.stringify({
                        id: p.txn,
                        refund_status: d.refund && d.refund.status ? d.refund.status : 'Refund Pending',
                        amount: d.refund && d.refund.amount ? d.refund.amount : p.amount,
                        note: d.refund && d.refund.note ? d.refund.note : ''
                    });
                }
                body.classList.remove('d-none');
            }).catch(err => {
                error.textContent = err.message;
                error.classList.remove('d-none');
            }).finally(() => loading.classList.add('d-none'));
        });

        modal.querySelector('[data-invoice-print]').addEventListener('click', () => {
            const w = window.open('', '_blank', 'width=800,height=900');
            if (!w) return;
            w.document.write('<html><head><title>Invoice</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/admin.css?v=3"></head><body class="p-4">' + document.getElementById('invoiceBody').innerHTML + '</body></html>');
            w.document.close();
            w.onload = () => { w.focus(); w.print(); };
        });
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
