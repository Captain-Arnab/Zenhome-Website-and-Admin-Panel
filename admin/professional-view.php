<?php
require_once __DIR__ . '/includes/functions.php';

$activeMenu      = 'professionals';
$withAssignModal = true;

$jobFilter = in_array($_GET['jobs'] ?? '', ['current', 'completed'], true) ? $_GET['jobs'] : '';
$jobPage   = max(1, (int) ($_GET['page'] ?? 1));
$proId     = (int) ($_GET['id'] ?? 0);
$data = api('professionals.get', ['id' => $proId, 'jobs' => $jobFilter, 'limit' => 20, 'page' => $jobPage], null, true);
if (!$data) {
    $pageTitle   = 'Professional not found';
    $breadcrumbs = [['label' => 'Service Professionals', 'url' => 'professionals.php'], ['label' => 'Not found']];
    renderNotFound('Professional not found', 'This professional does not exist or was deleted.', 'professionals.php', 'Back to professionals');
}
$pro        = $data['professional'];
$jobs       = $data['jobs']['items'];
$jobPager   = $data['jobs']['pagination'];
$jobUrl     = fn(array $q) => 'professional-view.php?' . http_build_query(array_filter($q + ['id' => $proId, 'jobs' => $jobFilter]));

$pageTitle   = 'Professional Details';
$breadcrumbs = [['label' => 'Service Professionals', 'url' => 'professionals.php'], ['label' => $pro['name']]];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="alert alert-internal d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-shield-lock-fill fs-5"></i>
    <div><strong>Internal use only.</strong> No professional login or registration on the website.</div>
</div>

<div class="page-header">
    <div>
        <h2><?= e($pro['name']) ?> <span class="text-muted fs-6 fw-semibold">#<?= e($pro['id']) ?></span></h2>
        <p><?= e($pro['category'] ?: 'No category') ?> &middot; added <?= fdate($pro['joined']) ?></p>
    </div>
    <div class="actions">
        <a href="professionals.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back</a>
        <a href="professional-form.php?id=<?= e($pro['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4 col-xl-3">
        <div class="card profile-card mb-3">
            <div class="card-body p-4">
                <?= avatar($pro['name'], 'avatar-xl') ?>
                <h5><?= e($pro['name']) ?></h5>
                <p class="text-muted fs-13 mb-2"><?= e($pro['category']) ?></p>
                <?= statusBadge($pro['status_label']) ?>
                <div class="profile-stats">
                    <div><strong><?= (int) $pro['active_jobs'] ?></strong><small>Active</small></div>
                    <div><strong><?= (int) $pro['completed'] ?></strong><small>Completed</small></div>
                    <div><strong><?= $pro['rating'] !== null ? e($pro['rating']) : '-' ?></strong><small>Rating<?= $pro['reviews'] ? ' (' . (int) $pro['reviews'] . ')' : '' ?></small></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <dl class="detail-list">
                    <div class="row-item"><dt>Mobile</dt><dd><a href="tel:<?= e(preg_replace('/\s+/', '', $pro['mobile'])) ?>"><?= e($pro['mobile']) ?></a></dd></div>
                    <?php if ($pro['alternate_mobile']): ?>
                        <div class="row-item"><dt>Alternate</dt><dd><?= e($pro['alternate_mobile']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($pro['email']): ?>
                        <div class="row-item"><dt>Email</dt><dd><?= e($pro['email']) ?></dd></div>
                    <?php endif; ?>
                    <div class="row-item"><dt>Category</dt><dd><?= e($pro['category'] ?: '-') ?></dd></div>
                    <div class="row-item"><dt>Areas</dt><dd><?= e($pro['area'] ?: '-') ?></dd></div>
                    <?php if ($pro['experience']): ?>
                        <div class="row-item"><dt>Experience</dt><dd><?= e($pro['experience']) ?></dd></div>
                    <?php endif; ?>
                    <div class="row-item"><dt>Status</dt><dd><?= statusSwitch($pro['status'], $pro['name'], 'Active', 'Inactive', (string) $pro['id'], 'professionals.set_status') ?></dd></div>
                </dl>
                <?php if ($pro['remarks']): ?>
                    <div class="mt-3 p-3 rounded-3 fs-13" style="background:#f6f8fa">
                        <strong class="d-block text-heading mb-1"><i class="bi bi-sticky me-1"></i>Remarks</strong>
                        <?= e($pro['remarks']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8 col-xl-9">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Assigned Jobs</h3>
                    <p class="card-subtitle"><?= (int) $pro['active_jobs'] ?> in progress &middot; <?= (int) $jobPager['total_items'] ?> <?= $jobFilter === '' ? 'total' : e($jobFilter) ?></p>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Filter jobs">
                    <a href="<?= e($jobUrl(['jobs' => null])) ?>" class="btn btn-outline-secondary <?= $jobFilter === '' ? 'active' : '' ?>">All</a>
                    <a href="<?= e($jobUrl(['jobs' => 'current'])) ?>" class="btn btn-outline-secondary <?= $jobFilter === 'current' ? 'active' : '' ?>">Current</a>
                    <a href="<?= e($jobUrl(['jobs' => 'completed'])) ?>" class="btn btn-outline-secondary <?= $jobFilter === 'completed' ? 'active' : '' ?>">Completed</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Schedule</th><th>Area</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($jobs as $b): ?>
                        <tr>
                            <td class="nowrap"><a href="booking-view.php?id=<?= e($b['id']) ?>" class="fw-bold"><?= e(bookingCode($b)) ?></a></td>
                            <td class="nowrap"><?= e($b['customer']) ?><div class="cell-sub"><?= e($b['mobile']) ?></div></td>
                            <td><?= e($b['service'] ?: '-') ?></td>
                            <td class="nowrap"><?= fdate($b['date']) ?><div class="cell-sub"><?= e($b['slot']) ?></div></td>
                            <td><?= e($b['area'] ?: '-') ?></td>
                            <td><?= statusBadge($b['status']) ?></td>
                            <td class="text-end nowrap">
                                <?php if (in_array($b['status'], ['Assigned', 'Ongoing'], true)): ?>
                                    <?= assignButton($b) ?>
                                <?php endif; ?>
                                <a href="booking-view.php?id=<?= e($b['id']) ?>" class="btn btn-icon btn-soft-info" title="View"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$jobs): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clipboard-x"></i><p class="mt-2 mb-0">No <?= $jobFilter === '' ? '' : e($jobFilter) . ' ' ?>jobs yet.</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($jobPager['total_pages'] > 1): ?>
                <?php $pg = (int) $jobPager['page']; $pages = (int) $jobPager['total_pages']; ?>
                <div class="table-footer">
                    <span>Page <?= $pg ?> of <?= $pages ?> &middot; <?= (int) $jobPager['total_items'] ?> jobs</span>
                    <nav><ul class="pagination pagination-sm m-0">
                        <li class="page-item <?= $pg <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($jobUrl(['page' => $pg - 1])) ?>"><i class="bi bi-chevron-left"></i></a></li>
                        <?php for ($i = max(1, $pg - 2); $i <= min($pages, $pg + 2); $i++): ?>
                            <li class="page-item <?= $i === $pg ? 'active' : '' ?>"><a class="page-link" href="<?= e($jobUrl(['page' => $i])) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pg >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($jobUrl(['page' => $pg + 1])) ?>"><i class="bi bi-chevron-right"></i></a></li>
                    </ul></nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
