<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Ratings & Reviews';
$activeMenu  = 'reviews';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Ratings & Reviews']];

$result  = api_list('reviews.list', ['with_summary' => 1], 'reviews');
$reviews = $result['items'];
$summary = $result['summary'] ?? ['total' => 0, 'average' => 0, 'approved' => 0, 'hidden' => 0, 'pending' => 0, 'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
$avgRating    = $summary['average'];
$distribution = $summary['distribution'];
$totalReviews = (int) $summary['total'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Ratings &amp; Reviews</h2>
        <p>Approved reviews are shown on the website. Hidden reviews stay visible only to admins. New reviews arrive as <em>Pending</em>.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4 col-xl-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <p class="stat-label text-muted fw-semibold mb-1">Average rating</p>
                <div class="fw-800 text-heading" style="font-size:42px;line-height:1"><?= $avgRating ?></div>
                <div class="my-2 fs-5"><?= stars($avgRating) ?></div>
                <small class="text-muted">Based on <?= $totalReviews ?> review<?= $totalReviews === 1 ? '' : 's' ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-8 col-xl-5">
        <div class="card h-100">
            <div class="card-body">
                <?php foreach ($distribution as $star => $count): ?>
                    <?php $pct = $totalReviews ? round($count / $totalReviews * 100) : 0; ?>
                    <div class="d-flex align-items-center gap-2 mb-2 fs-13">
                        <span class="text-nowrap" style="width:36px"><?= $star ?> <i class="bi bi-star-fill text-warning"></i></span>
                        <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
                        <span class="text-muted text-end" style="width:28px"><?= $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-2"><?= statCard('Approved', (string) $summary['approved'], 'bi-eye', 'success', $summary['pending'] ? '<span class="text-warning fw-semibold">' . (int) $summary['pending'] . ' pending</span>' : '') ?></div>
    <div class="col-6 col-xl-2"><?= statCard('Hidden', (string) $summary['hidden'], 'bi-eye-slash', 'muted') ?></div>
</div>

<div class="card filter-bar mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-5">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Customer, service or review text" data-dt-search="#reviewsTable">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#reviewsTable" data-column="7" data-exact aria-label="Rating">
                    <option value="">All ratings</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" data-dt-filter="#reviewsTable" data-column="5" data-exact aria-label="Visibility">
                    <option value="">All</option>
                    <option>Approved</option>
                    <option>Hidden</option>
                    <option>Pending</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <table class="table table-hover js-datatable" id="reviewsTable" data-empty="No reviews yet. Customers rate completed bookings from the app.">
        <thead>
        <tr>
            <th>Customer</th>
            <th>Service / Booking</th>
            <th>Rating</th>
            <th>Review</th>
            <th>Date</th>
            <th>Visibility</th>
            <th>Featured</th>
            <th class="text-end" data-orderable="false">Actions</th>
            <th data-visible="false">Stars</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reviews as $r): ?>
            <tr>
                <td><div class="user-cell" style="min-width:160px"><?= avatar($r['customer'], 'avatar-sm') ?><span class="fw-semibold text-heading"><?= e($r['customer']) ?></span></div></td>
                <td class="nowrap"><?= e($r['service'] ?: '-') ?><div class="cell-sub"><?php if ($r['booking_id']): ?><a href="booking-view.php?id=<?= e($r['booking_id']) ?>"><?= e($r['booking']) ?></a><?php endif; ?><?= $r['professional'] ? ' &middot; ' . e($r['professional']) : '' ?></div></td>
                <td data-order="<?= e($r['rating']) ?>"><?= stars($r['rating']) ?><div class="cell-sub"><?= e($r['rating']) ?> / 5</div></td>
                <td style="min-width:260px;max-width:380px"><span class="fs-13"><?= $r['text'] !== '' ? e($r['text']) : '<span class="text-muted fst-italic">No written review</span>' ?></span></td>
                <td class="nowrap" data-order="<?= e($r['date']) ?>"><?= fdate($r['date']) ?></td>
                <td data-search="<?= e($r['status']) ?>" data-export="<?= e($r['status']) ?>">
                    <?= statusSwitch($r['approved'], 'Review by ' . $r['customer'], 'Approved', 'Hidden', (string) $r['id'], 'reviews.set_status') ?>
                    <?php if ($r['status'] === 'Pending'): ?><span class="badge badge-soft-warning mt-1">Pending</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($r['status'] === 'Approved'): ?>
                        <?= statusSwitch($r['featured'], 'Review by ' . $r['customer'], 'Featured', 'Not featured', (string) $r['id'], 'reviews.set_featured') ?>
                    <?php else: ?>
                        <span class="text-muted fs-12">Approve first</span>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= isSuperAdmin() ? deleteButton('this review', (string) $r['id'], true, 'reviews.delete') : '' ?></td>
                <td><?= (int) floor($r['rating']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
