<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Offers & Coupons';
$activeMenu  = 'coupons';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Offers & Coupons']];

$coupons = api_list('coupons.list', [], 'coupons')['items'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Offers &amp; Coupons</h2>
        <p>Discount codes customers can apply at checkout. Usage counts a booking when it is placed and gives the use back if the booking is cancelled.</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#couponModal" data-title="Create Coupon"
                data-fill="<?= jsonAttr(['type' => 'percentage', 'min' => 0, 'per_user' => 1, 'from' => date('Y-m-d'), 'status' => true]) ?>">
            <i class="bi bi-plus-lg me-1"></i> Create Coupon
        </button>
    </div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-5">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Search coupon code" data-dt-search="#couponsTable">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#couponsTable" data-column="1" aria-label="Type">
                    <option value="">All types</option>
                    <option value="Percentage">Percentage</option>
                    <option value="Fixed">Fixed amount</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#couponsTable" data-column="5" data-exact aria-label="Validity">
                    <option value="">All validity</option>
                    <option>Active</option>
                    <option>Scheduled</option>
                    <option>Expired</option>
                    <option>Inactive</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="couponsTable" data-empty="No coupons yet. Create the first one.">
        <thead>
        <tr>
            <th>Code</th>
            <th>Discount</th>
            <th>Min. Booking</th>
            <th>Validity</th>
            <th>Usage</th>
            <th>State</th>
            <th>Active</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($coupons as $c): ?>
            <?php $usage = $c['limit'] ? min(100, round($c['used'] / $c['limit'] * 100)) : 0; ?>
            <tr>
                <td>
                    <span class="badge badge-soft-secondary font-monospace fs-12 px-2 py-2"><i class="bi bi-ticket-perforated me-1"></i><?= e($c['code']) ?></span>
                    <div class="cell-sub mt-1"><?= e($c['description']) ?></div>
                </td>
                <td class="nowrap">
                    <strong class="text-heading"><?= $c['type'] === 'percentage' ? e(rtrim(rtrim(number_format($c['value'], 2, '.', ''), '0'), '.')) . '%' : money($c['value']) ?></strong>
                    <div class="cell-sub"><?= $c['type'] === 'percentage' ? 'Percentage' . ($c['max'] ? ' &middot; max ' . money($c['max']) : '') : 'Fixed' ?></div>
                </td>
                <td class="nowrap"><?= money($c['min']) ?></td>
                <td class="nowrap" data-order="<?= e($c['to']) ?>"><?= fdate($c['from'], 'd M') ?> &ndash; <?= fdate($c['to']) ?></td>
                <td style="min-width:130px">
                    <div class="d-flex justify-content-between fs-12 mb-1"><span><?= (int) $c['used'] ?> / <?= $c['limit'] ? (int) $c['limit'] : '&infin;' ?></span><?php if ($c['limit']): ?><span class="text-muted"><?= $usage ?>%</span><?php endif; ?></div>
                    <div class="progress" style="height:5px"><div class="progress-bar" style="width:<?= $usage ?>%"></div></div>
                </td>
                <td><?= statusBadge($c['validity']) ?></td>
                <td><?= statusSwitch($c['status'], $c['code'], 'On', 'Off', (string) $c['id'], 'coupons.set_status') ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <button type="button" class="btn btn-icon btn-soft-primary" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#couponModal" data-title="Edit Coupon"
                                data-fill="<?= jsonAttr(['id' => $c['id'], 'code' => $c['code'], 'description' => $c['description'], 'type' => $c['type'], 'value' => $c['value'], 'min' => $c['min'], 'max' => $c['max'], 'from' => $c['from'], 'to' => $c['to'], 'limit' => $c['limit'], 'per_user' => $c['per_user'], 'status' => $c['status']]) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this coupon', (string) $c['id'], true, 'coupons.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit coupon modal -->
<div class="modal fade js-crud-modal" id="couponModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="coupons.save">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title" data-modal-title>Create Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="cpCode">Coupon code</label>
                            <input type="text" class="form-control text-uppercase font-monospace" id="cpCode" name="code" required pattern="[A-Za-z0-9]{4,20}" maxlength="20" placeholder="ZENFIRST">
                            <div class="form-text">4-20 letters/numbers, no spaces.</div>
                            <div class="invalid-feedback">Enter a valid code (4-20 letters/numbers).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cpDesc">Description</label>
                            <input type="text" class="form-control" id="cpDesc" name="description" maxlength="100" placeholder="Shown to customers">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="cpType">Discount type</label>
                            <select class="form-select" id="cpType" name="type" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="cpValue">Discount value</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text" id="cpValueUnit">%</span>
                                <input type="number" class="form-control" id="cpValue" name="value" min="1" step="0.01" required>
                                <div class="invalid-feedback">Enter a discount value.</div>
                            </div>
                        </div>
                        <div class="col-md-4" id="cpMaxWrap">
                            <label class="form-label" for="cpMax">Max discount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="cpMax" name="max" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="cpMin">Min. booking amount</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="cpMin" name="min" min="0" required value="0">
                                <div class="invalid-feedback">Required (use 0 for none).</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="cpFrom">Valid from</label>
                            <input type="date" class="form-control" id="cpFrom" name="from" required>
                            <div class="invalid-feedback">Start date required.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="cpTo">Valid to</label>
                            <input type="date" class="form-control" id="cpTo" name="to" required>
                            <div class="invalid-feedback">End date must be after start date.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cpLimit">Total usage limit</label>
                            <input type="number" class="form-control" id="cpLimit" name="limit" min="0" placeholder="Unlimited">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cpPerUser">Uses per customer</label>
                            <input type="number" class="form-control" id="cpPerUser" name="per_user" min="1" value="1">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input js-status-toggle" type="checkbox" id="cpStatus" name="status" checked data-silent>
                                <label class="form-check-label" for="cpStatus">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    (function () {
        const type = document.getElementById('cpType');
        const from = document.getElementById('cpFrom');
        const to = document.getElementById('cpTo');
        // Switch unit + hide "max discount" for fixed coupons
        const syncType = () => {
            const pct = type.value === 'percentage';
            document.getElementById('cpValueUnit').textContent = pct ? '%' : '₹';
            document.getElementById('cpValue').max = pct ? 100 : '';
            document.getElementById('cpMaxWrap').classList.toggle('d-none', !pct);
        };
        type.addEventListener('change', syncType);
        syncType();
        // End date cannot be before start date
        const syncDates = () => {
            to.min = from.value;
            to.setCustomValidity(from.value && to.value && to.value < from.value ? 'invalid' : '');
        };
        from.addEventListener('change', syncDates);
        to.addEventListener('change', syncDates);
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
