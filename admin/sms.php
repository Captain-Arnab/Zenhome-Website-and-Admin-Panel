<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'SMS Management';
$activeMenu  = 'sms';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'SMS Management']];

$tplData       = api('sms.templates', [], ['items' => [], 'variables' => []]);
$smsTemplates  = $tplData['items'];
$smsVariables  = $tplData['variables'];
$gateway       = api('sms.gateway', [], ['ready' => false, 'message' => 'SMS gateway status unavailable.']);
$smsLog        = api_list('sms.log', [], 'SMS log entries')['items'];
$activeCoupons = api_list('coupons.list', ['validity' => 'active'], 'coupons')['items'];
$customerTotal = (int) (api('customers.list', ['limit' => 1], ['pagination' => ['total_items' => 0]])['pagination']['total_items'] ?? 0);
$promoTemplate = findBy($smsTemplates, 'key', 'promotional');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>SMS Management</h2>
        <p>Templates used for automatic booking SMS, plus promotional campaigns.</p>
    </div>
    <div class="actions">
        <?php if ($gateway['ready']): ?>
            <span class="badge badge-soft-success px-3 py-2"><i class="bi bi-broadcast me-1"></i> SMS gateway connected</span>
        <?php else: ?>
            <span class="badge badge-soft-warning px-3 py-2" title="<?= e($gateway['message']) ?>" data-bs-toggle="tooltip"><i class="bi bi-exclamation-triangle me-1"></i> SMS gateway not configured</span>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <ul class="nav nav-tabs-line px-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#templates" type="button"><i class="bi bi-file-text me-1"></i> Templates</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#promo" type="button"><i class="bi bi-megaphone me-1"></i> Send Promotional SMS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#log" type="button"><i class="bi bi-list-check me-1"></i> Send Log</button></li>
    </ul>

    <div class="tab-content">
        <?php if (!$gateway['ready']): ?>
            <div class="alert alert-warning fs-13 m-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> <?= e($gateway['message']) ?> Until it is set up, every SMS is logged as <strong>Not sent</strong> with the reason.</div>
        <?php endif; ?>
        <!-- Templates -->
        <div class="tab-pane fade show active" id="templates" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Template</th><th>Message</th><th>DLT Template ID</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php if (!$smsTemplates): ?>
                        <tr><td colspan="5"><?= emptyState('No SMS templates found. Run the admin migration to seed them.', 'bi-file-text') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($smsTemplates as $t): ?>
                        <tr>
                            <td class="nowrap"><span class="cell-title"><?= e($t['name']) ?></span><span class="cell-sub"><?= e($t['key']) ?></span></td>
                            <td style="min-width:300px;max-width:460px"><span class="fs-13"><?= e($t['body']) ?></span></td>
                            <td class="font-monospace fs-12 nowrap"><?= $t['dlt_id'] !== '' ? e($t['dlt_id']) : '<span class="text-warning font-sans-serif">Not set</span>' ?></td>
                            <td><?= statusSwitch($t['status'], $t['name'], 'Active', 'Inactive', (string) $t['id'], 'sms.template_status') ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-icon btn-soft-primary" title="Edit template"
                                        data-bs-toggle="modal" data-bs-target="#templateModal" data-title="Edit: <?= e($t['name']) ?>"
                                        data-fill="<?= jsonAttr(['id' => $t['id'], 'name' => $t['name'], 'dlt_id' => $t['dlt_id'], 'body' => $t['body'], 'status' => $t['status']]) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Promotional -->
        <div class="tab-pane fade p-3 p-md-4" id="promo" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-7">
                    <?php if ($promoTemplate && (!$promoTemplate['status'] || $promoTemplate['dlt_id'] === '')): ?>
                        <div class="alert alert-info fs-13 py-2"><i class="bi bi-info-circle me-1"></i> The <strong>Promotional</strong> template must be active and have a DLT template ID before campaigns can be sent.</div>
                    <?php endif; ?>
                    <form class="needs-validation" novalidate data-api="sms.send_promo" data-reload id="promoForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="promoAudience">Send to</label>
                                <select class="form-select" id="promoAudience" name="audience" required>
                                    <option value="all">All customers (<?= inr($customerTotal) ?>)</option>
                                    <option value="active">Active customers</option>
                                    <option value="inactive_30">No booking in 30 days</option>
                                    <option value="custom">Custom mobile numbers</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="promoCoupon">Attach coupon</label>
                                <select class="form-select" id="promoCoupon" name="coupon">
                                    <option value="">None</option>
                                    <?php foreach ($activeCoupons as $c): ?>
                                        <option><?= e($c['code']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 d-none" id="promoMobilesWrap">
                                <label class="form-label required" for="promoMobiles">Mobile numbers</label>
                                <textarea class="form-control font-monospace" id="promoMobiles" name="mobiles" rows="3" placeholder="10-digit numbers separated by commas, spaces or new lines"></textarea>
                                <div class="invalid-feedback">Enter at least one valid mobile number.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label required" for="promoBody">Message</label>
                                <textarea class="form-control" id="promoBody" name="message" rows="5" required maxlength="480" data-sms
                                          data-char-count="#promoCount" data-preview="#promoPreview">Hi {customer_name}, get 20% off on AC Service this week. Use code ZENFIRST. Book now: zencareservices.in - Zen Care</textarea>
                                <div class="invalid-feedback">Message is required.</div>
                                <small class="form-text d-block text-end" id="promoCount"></small>
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1">Insert variable</label>
                                <div>
                                    <?php foreach (['{customer_name}', '{coupon_code}', '{offer_value}', '{service_name}'] as $v): ?>
                                        <span class="var-chip" data-target="#promoBody"><?= e($v) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="promoSchedule">Schedule (optional)</label>
                                <input type="datetime-local" class="form-control" id="promoSchedule" name="schedule_at">
                                <?= schedulerNotice('campaigns') ?>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="promoDnd" checked disabled>
                                    <label class="form-check-label fs-13" for="promoDnd">Skip DND numbers (required by TRAI)</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-outline-secondary" id="promoTest" data-busy-text="Sending..." title="Sends this message to your own admin mobile">Send Test</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Send Campaign</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="p-4 rounded-4 h-100" style="background:#f6f8fa">
                        <p class="fs-12 text-uppercase fw-bold text-muted mb-2">Preview</p>
                        <div class="sms-preview" id="promoPreview"></div>
                        <p class="fs-12 text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i> Variables are replaced per customer when sending. Promotional SMS must use a DLT-approved template.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log -->
        <div class="tab-pane fade" id="log" role="tabpanel">
            <div class="d-flex flex-wrap gap-2 p-3">
                <div class="search-input" style="max-width:300px;flex:1">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Search mobile or message" data-dt-search="#smsLogTable">
                </div>
                <select class="form-select w-auto" data-dt-filter="#smsLogTable" data-column="4" data-exact aria-label="Status">
                    <option value="">All statuses</option>
                    <option>Sent</option>
                    <option>Partially sent</option>
                    <option>Not sent</option>
                    <option>Failed</option>
                    <option>Scheduled</option>
                    <option>Processing</option>
                </select>
            </div>
            <table class="table table-hover js-datatable" id="smsLogTable" data-empty="No SMS sent yet.">
                <thead><tr><th>Mobile</th><th>Template</th><th>Message</th><th>Sent at</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($smsLog as $l): ?>
                    <tr>
                        <td class="nowrap"><?= e($l['mobile']) ?><?php if ($l['booking_id']): ?><div class="cell-sub"><a href="booking-view.php?id=<?= (int) $l['booking_id'] ?>">View booking</a></div><?php endif; ?></td>
                        <td class="nowrap"><?= e($l['template']) ?></td>
                        <td style="min-width:260px"><span class="fs-13 text-muted"><?= e($l['message']) ?></span></td>
                        <td class="nowrap" data-order="<?= e($l['sent_at']) ?>"><?= fdate($l['sent_at'], 'd M Y, h:i A') ?></td>
                        <td data-search="<?= e($l['status']) ?>" data-export="<?= e($l['status']) ?>">
                            <?= statusBadge($l['status']) ?>
                            <?php if ($l['detail'] !== '' && $l['status'] !== 'Sent'): ?><div class="cell-sub" style="max-width:220px"><?= e(mb_strimwidth($l['detail'], 0, 120, '...')) ?></div><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit template modal -->
<div class="modal fade js-crud-modal" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="sms.template_save">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" data-modal-title>Edit Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="tplName">Template name</label>
                            <input type="text" class="form-control" id="tplName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tplDlt">DLT template ID</label>
                            <input type="text" class="form-control font-monospace" id="tplDlt" name="dlt_id" pattern="[0-9]{19}" maxlength="19">
                            <div class="invalid-feedback">DLT ID must be 19 digits.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="tplBody">Message</label>
                            <textarea class="form-control" id="tplBody" name="body" rows="4" required maxlength="480" data-sms data-char-count="#tplCount" data-preview="#tplPreview"></textarea>
                            <div class="invalid-feedback">Message is required.</div>
                            <small class="form-text d-block text-end" id="tplCount"></small>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Available variables <span class="text-muted fw-normal">(click to insert)</span></label>
                            <div>
                                <?php foreach ($smsVariables as $v): ?>
                                    <span class="var-chip" data-target="#tplBody"><?= e($v) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Preview</label>
                            <div class="sms-preview" id="tplPreview"></div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input js-status-toggle" type="checkbox" id="tplStatus" name="status" data-silent>
                                <label class="form-check-label" for="tplStatus">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    // Refresh counter + preview after the modal is prefilled
    document.getElementById('templateModal').addEventListener('shown.bs.modal', function () {
        document.getElementById('tplBody').dispatchEvent(new Event('input'));
    });

    (function () {
        const audience = document.getElementById('promoAudience');
        const mobiles = document.getElementById('promoMobiles');
        const sync = () => {
            const custom = audience.value === 'custom';
            document.getElementById('promoMobilesWrap').classList.toggle('d-none', !custom);
            mobiles.required = custom;
        };
        audience.addEventListener('change', sync);
        sync();

        const testBtn = document.getElementById('promoTest');
        testBtn.addEventListener('click', function () {
            const message = document.getElementById('promoBody').value.trim();
            if (!message) {
                window.AdminUI.toast('Write a message first.', 'warning');
                return;
            }
            AdminApi.busy(testBtn, AdminApi.post('sms.send_test', { message }))
                .then(res => window.AdminUI.toast(res.message || 'Test SMS sent.'))
                .catch(err => window.AdminUI.toast(err.message || 'Test SMS failed.', 'danger'));
        });
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
