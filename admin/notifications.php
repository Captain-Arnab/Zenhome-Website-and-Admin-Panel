<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Notifications';
$activeMenu  = 'notifications';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Notifications']];

$typeColors = ['booking_confirmation' => 'info', 'assignment' => 'primary', 'status_update' => 'purple', 'cancellation' => 'danger', 'payment' => 'success', 'promotional' => 'warning', 'support' => 'secondary'];

$notificationTypes = api('notifications.types', [], ['items' => []])['items'];
$sentNotifications = api_list('notifications.history', [], 'notifications')['items'];
$customerTotal     = (int) (api('customers.list', ['limit' => 1], ['pagination' => ['total_items' => 0]])['pagination']['total_items'] ?? 0);
$cityNames         = array_column(api('locations.cities', ['limit' => 200], ['items' => []])['items'], 'name');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Notifications</h2>
        <p>Send push / in-app notifications to customers. Booking, assignment, status and payment notifications are also sent automatically.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Compose -->
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-send me-1 text-primary"></i> Compose notification</h3></div>
            <div class="card-body">
                <form class="needs-validation" novalidate data-api="notifications.send" data-reload>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="nType">Type</label>
                            <select class="form-select" id="nType" name="type" required>
                                <option value="">Select type</option>
                                <?php foreach ($notificationTypes as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Select a notification type.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="nAudience">Audience</label>
                            <select class="form-select" id="nAudience" name="audience" required>
                                <option value="all">All customers (<?= inr($customerTotal) ?>)</option>
                                <option value="active">Active customers</option>
                                <option value="no_booking_30">No booking in last 30 days</option>
                                <option value="city">Customers in a city</option>
                                <option value="booking">Customer of a specific booking</option>
                                <option value="single">Single customer</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="nCityWrap">
                            <label class="form-label" for="nCity">City</label>
                            <?php if ($cityNames): ?>
                                <select class="form-select" id="nCity" name="city">
                                    <?php foreach ($cityNames as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control" id="nCity" name="city" maxlength="60" placeholder="City name">
                                <div class="form-text">No cities set up yet. Matches customers whose address contains this text.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 d-none" id="nTargetWrap">
                            <label class="form-label" for="nTarget">Booking ID / customer mobile</label>
                            <input type="text" class="form-control" id="nTarget" name="target" placeholder="ZC1012 or 98480 12345">
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="nTitle">Title</label>
                            <input type="text" class="form-control" id="nTitle" name="title" required maxlength="65" placeholder="e.g. Your professional is on the way" data-char-count="#nTitleCount" data-preview="#pvTitle">
                            <div class="invalid-feedback">Title is required.</div>
                            <small class="form-text d-block text-end" id="nTitleCount"></small>
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="nMessage">Message</label>
                            <textarea class="form-control" id="nMessage" name="message" rows="4" required maxlength="240" placeholder="Write the notification message" data-char-count="#nMsgCount" data-preview="#pvBody"></textarea>
                            <div class="invalid-feedback">Message is required.</div>
                            <small class="form-text d-block text-end" id="nMsgCount"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Channels</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="chPush" name="channels[]" value="push" checked><label class="form-check-label" for="chPush">Push</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="chSms" name="channels[]" value="sms"><label class="form-check-label" for="chSms">SMS</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="chEmail" name="channels[]" value="email"><label class="form-check-label" for="chEmail">Email</label></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nSchedule">Schedule (optional)</label>
                            <input type="datetime-local" class="form-control" id="nSchedule" name="schedule_at">
                            <?= schedulerNotice('notifications') ?>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-light border fs-13 py-2 mb-0">
                                <i class="bi bi-info-circle me-1"></i> Push and email providers are not configured yet, so those channels are logged as <strong>Not sent</strong>. SMS goes out only when the SMS gateway is set up (see <a href="sms.php">SMS Management</a>).
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="reset" class="btn btn-light">Clear</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Send Notification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Preview</h3></div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center" style="background:linear-gradient(160deg,#f6fbff,#edf6ef)">
                <div class="bg-white rounded-4 shadow-sm p-3 w-100" style="max-width:360px">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="assets/img/logo.png" alt="" height="22">
                        <small class="text-muted">Zen Home Experts &middot; now</small>
                    </div>
                    <strong class="d-block text-heading" id="pvTitle" data-empty="Notification title">Notification title</strong>
                    <p class="fs-13 mb-0 text-muted" id="pvBody" data-empty="Your message will appear here.">Your message will appear here.</p>
                </div>
                <p class="fs-12 text-muted mt-3 mb-0 text-center">Automatic notifications use the templates in <a href="sms.php">SMS Management</a>.</p>
            </div>
        </div>
    </div>
</div>

<!-- Sent history -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Sent history</h3>
        <div class="d-flex gap-2 flex-wrap">
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Search" data-dt-search="#sentTable">
            </div>
            <select class="form-select form-select-sm w-auto" data-dt-filter="#sentTable" data-column="1" data-exact aria-label="Type">
                <option value="">All types</option>
                <?php foreach ($notificationTypes as $label): ?><option><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <table class="table table-hover js-datatable" id="sentTable" data-empty="Nothing sent yet.">
        <thead><tr><th>Title</th><th>Type</th><th>Audience</th><th>Channel</th><th>Sent / scheduled</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($sentNotifications as $n): ?>
            <tr>
                <td class="fw-semibold text-heading" style="min-width:200px"><?= e($n['title']) ?><div class="cell-sub fw-normal"><?= e(mb_strimwidth($n['message'], 0, 90, '...')) ?></div></td>
                <td data-search="<?= e($n['type_label']) ?>"><span class="badge badge-soft-<?= e($typeColors[$n['type']] ?? 'secondary') ?>"><?= e($n['type_label']) ?></span></td>
                <td><?= e($n['audience']) ?><div class="cell-sub"><?= (int) $n['recipients'] ?> recipient<?= $n['recipients'] === 1 ? '' : 's' ?><?= $n['booking_id'] ? ' &middot; <a href="booking-view.php?id=' . (int) $n['booking_id'] . '">booking</a>' : '' ?></div></td>
                <td class="nowrap"><?= e($n['channel'] ?: '-') ?></td>
                <td class="nowrap" data-order="<?= e($n['sent_at']) ?>"><?= fdate($n['sent_at'], 'd M Y, h:i A') ?><div class="cell-sub">by <?= e($n['by']) ?></div></td>
                <td data-export="<?= e($n['status']) ?>"><span title="<?= e($n['detail']) ?>" data-bs-toggle="tooltip"><?= statusBadge($n['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php ob_start(); ?>
<script>
    // Show extra audience fields only when needed
    (function () {
        const audience = document.getElementById('nAudience');
        const sync = () => {
            document.getElementById('nCityWrap').classList.toggle('d-none', audience.value !== 'city');
            document.getElementById('nTargetWrap').classList.toggle('d-none', !['booking', 'single'].includes(audience.value));
        };
        audience.addEventListener('change', sync);
        sync();
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
