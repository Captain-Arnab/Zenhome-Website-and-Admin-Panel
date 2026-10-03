<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Customers';
$activeMenu  = 'customers';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Customers']];

$isDate     = fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d);
$joinedFrom = $isDate($_GET['joined_from'] ?? '') ? $_GET['joined_from'] : '';
$joinedTo   = $isDate($_GET['joined_to'] ?? '') ? $_GET['joined_to'] : '';

$query = ['with_summary' => 1];
if ($joinedFrom !== '') $query['joined_from'] = $joinedFrom;
if ($joinedTo !== '')   $query['joined_to'] = $joinedTo;
$filtered = $joinedFrom !== '' || $joinedTo !== '';

$result    = api_list('customers.list', $query, 'customers');
$customers = $result['items'];
$summary   = $result['summary'] ?? ['total' => 0, 'active' => 0, 'inactive' => 0, 'new_this_month' => 0];
$cityOptions = $result['cities'] ?? [];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Customers</h2>
        <p>Registered website customers. Customers sign up on the website; admins can view and activate/deactivate them.</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-outline-secondary" data-export="#customersTable" data-filename="customers-<?= date('Y-m-d') ?>.csv"><i class="bi bi-download me-1"></i> Export</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><?= statCard('Total Customers', inr($summary['total']), 'bi-people', 'secondary', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Active', inr($summary['active']), 'bi-person-check', 'success', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('Inactive', inr($summary['inactive']), 'bi-person-slash', 'muted', '', null, 'stat-sm') ?></div>
    <div class="col-6 col-xl-3"><?= statCard('New This Month', inr($summary['new_this_month']), 'bi-person-plus', 'primary', '', null, 'stat-sm') ?></div>
</div>

<!-- Filter bar -->
<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Name, mobile or email" data-dt-search="#customersTable">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" data-dt-filter="#customersTable" data-column="6" data-exact>
                    <option value="">All</option>
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">City</label>
                <select class="form-select" data-dt-filter="#customersTable" data-column="2">
                    <option value="">All cities</option>
                    <?php foreach ($cityOptions as $city): ?>
                        <option><?= e($city) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Joined between</label>
                <form method="get" class="input-group">
                    <input type="date" class="form-control" name="joined_from" aria-label="From date" value="<?= e($joinedFrom) ?>" onchange="this.form.submit()">
                    <input type="date" class="form-control" name="joined_to" aria-label="To date" value="<?= e($joinedTo) ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="col-md-1 d-grid">
                <?php if ($filtered): ?>
                    <a href="customers.php" class="btn btn-light" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php else: ?>
                    <button type="button" class="btn btn-light" data-dt-reset="#customersTable" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="customersTable" data-empty="<?= $filtered ? 'No customers joined in this period' : 'No customers yet' ?>">
        <thead>
        <tr>
            <th>Customer</th>
            <th>Mobile</th>
            <th>City / Area</th>
            <th class="text-center">Bookings</th>
            <th>Total Spent</th>
            <th>Joined</th>
            <th>Status</th>
            <th class="text-end" data-orderable="false">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <?= avatar($c['name']) ?>
                        <div class="min-w-0">
                            <a href="customer-view.php?id=<?= e($c['id']) ?>" class="cell-title"><?= e($c['name']) ?></a>
                            <span class="cell-sub"><?= e($c['email']) ?></span>
                        </div>
                    </div>
                </td>
                <td class="nowrap"><?= e($c['mobile']) ?></td>
                <td class="nowrap"><?= e($c['city'] ?: '-') ?><div class="cell-sub"><?= e($c['area']) ?></div></td>
                <td class="text-center fw-bold"><?= (int) $c['bookings'] ?></td>
                <td class="nowrap" data-order="<?= (float) $c['spent'] ?>"><?= money($c['spent']) ?></td>
                <td class="nowrap" data-order="<?= e($c['joined']) ?>"><?= fdate($c['joined']) ?></td>
                <td data-search="<?= $c['status'] ? 'Active' : 'Inactive' ?>" data-export="<?= $c['status'] ? 'Active' : 'Inactive' ?>"><?= statusSwitch($c['status'], $c['name'], 'Active', 'Inactive', (string) $c['id'], 'customers.set_status') ?></td>
                <td>
                    <div class="table-actions justify-content-end">
                        <a href="customer-view.php?id=<?= e($c['id']) ?>" class="btn btn-icon btn-soft-info" title="View"><i class="bi bi-eye"></i></a>
                        <?php if (isSuperAdmin()): ?>
                            <?= deleteButton('this customer', (string) $c['id'], true, 'customers.delete') ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
