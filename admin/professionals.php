<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Service Professionals';
$activeMenu  = 'professionals';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Service Professionals']];

$result        = api_list('professionals.list', ['with_summary' => 1], 'professionals');
$professionals = $result['items'];
$summary       = $result['summary'] ?? ['total' => 0, 'active' => 0, 'pending' => 0, 'on_job' => 0, 'free' => 0];
$areaOptions   = $result['areas'] ?? [];
$categories    = api('categories.options', [], ['items' => []])['items'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="alert alert-internal d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-shield-lock-fill fs-5"></i>
    <div><strong>Internal use only.</strong> No professional login or registration on the website. Professionals are added here by admins and assigned to bookings manually.</div>
</div>

<div class="page-header">
    <div>
        <h2>Service Professionals</h2>
        <p>Your in-house technicians and service staff.</p>
    </div>
    <div class="actions">
        <a href="professional-form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Professional</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><?= statCard('Total Professionals', (string) $summary['total'], 'bi-person-badge', 'secondary', $summary['pending'] ? '<span class="text-warning fw-semibold">' . (int) $summary['pending'] . ' pending approval</span>' : '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Active', (string) $summary['active'], 'bi-person-check', 'success', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('On a Job Now', (string) $summary['on_job'], 'bi-tools', 'purple', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Free (no active job)', (string) $summary['free'], 'bi-calendar2-check', 'primary', '', null, 'stat-sm') ?></div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Name or mobile" data-dt-search="#prosTable">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#prosTable" data-column="2" aria-label="Category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#prosTable" data-column="3" aria-label="Area">
                    <option value="">All areas</option>
                    <?php foreach ($areaOptions as $area): ?>
                        <option><?= e($area) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select" data-dt-filter="#prosTable" data-column="6" data-exact aria-label="Status">
                    <option value="">All statuses</option>
                    <option>Active</option>
                    <option>Inactive</option>
                    <option>Pending</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="prosTable" data-empty="No professionals yet. Add your first technician.">
        <thead>
        <tr>
            <th>Professional</th>
            <th>Mobile</th>
            <th>Service / Category</th>
            <th>Area</th>
            <th class="text-center">Active Jobs</th>
            <th class="text-center">Completed</th>
            <th>Status</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($professionals as $p): ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <?= avatar($p['name']) ?>
                        <div>
                            <a href="professional-view.php?id=<?= e($p['id']) ?>" class="cell-title"><?= e($p['name']) ?></a>
                            <span class="cell-sub"><?= e($p['code']) ?><?php if ($p['rating'] !== null): ?> &middot; <i class="bi bi-star-fill text-warning"></i> <?= e($p['rating']) ?><?php endif; ?></span>
                        </div>
                    </div>
                </td>
                <td class="nowrap"><?= e($p['mobile']) ?></td>
                <td class="nowrap"><?= e($p['category'] ?: '-') ?></td>
                <td><?= e($p['area'] ?: '-') ?></td>
                <td class="text-center">
                    <?= $p['active_jobs'] ? '<span class="badge badge-soft-purple">' . (int) $p['active_jobs'] . '</span>' : '<span class="text-muted">0</span>' ?>
                </td>
                <td class="text-center fw-bold"><?= (int) $p['completed'] ?></td>
                <td data-search="<?= e($p['status_label']) ?>" data-export="<?= e($p['status_label']) ?>">
                    <?= statusSwitch($p['status'], $p['name'], 'Active', 'Inactive', (string) $p['id'], 'professionals.set_status') ?>
                    <?php if ($p['status_label'] === 'Pending'): ?><span class="badge badge-soft-warning mt-1">Pending approval</span><?php endif; ?>
                </td>
                <td>
                    <div class="table-actions justify-content-end">
                        <a href="professional-view.php?id=<?= e($p['id']) ?>" class="btn btn-icon btn-soft-info" title="View"><i class="bi bi-eye"></i></a>
                        <a href="professional-form.php?id=<?= e($p['id']) ?>" class="btn btn-icon btn-soft-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this professional', (string) $p['id'], true, 'professionals.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
