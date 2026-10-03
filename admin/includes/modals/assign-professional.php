<?php
/**
 * Assign / Reassign professional modal.
 * Opened by assignButton() (functions.php); admin.js fills the booking details
 * and POSTs to bookings.assign. Only active professionals are listed.
 */
$activeProfessionals = api('professionals.options', [], ['items' => []])['items'];
?>
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-no-auto-submit>
                <input type="hidden" name="booking_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignModalTitle">
                        <i class="bi bi-person-plus text-primary me-1"></i>
                        <span data-assign-title>Assign Professional</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="rounded-3 p-3 mb-3" style="background:#f6f8fa">
                        <div class="row g-2 fs-13">
                            <div class="col-5 text-muted">Booking</div>
                            <div class="col-7 fw-bold text-heading" data-assign-booking>-</div>
                            <div class="col-5 text-muted">Service</div>
                            <div class="col-7 fw-semibold" data-assign-service>-</div>
                            <div class="col-5 text-muted">Schedule</div>
                            <div class="col-7 fw-semibold" data-assign-slot>-</div>
                        </div>
                        <div class="fs-13 mt-2 d-none" data-assign-current-wrap>
                            <span class="text-muted">Currently assigned:</span>
                            <strong data-assign-current></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="assignProfessional">Service professional</label>
                        <select class="form-select" id="assignProfessional" name="professional_id" required>
                            <option value="">Select a professional</option>
                            <?php foreach ($activeProfessionals as $pro): ?>
                                <option value="<?= e($pro['id']) ?>" data-name="<?= e($pro['name']) ?>" data-categories="<?= e(strtolower(implode('|', $pro['categories']))) ?>"><?= e($pro['name'] . ' - ' . ($pro['category'] ?: 'No category') . ($pro['area'] ? ' (' . $pro['area'] . ')' : '') . ' - ' . $pro['active_jobs'] . ' active jobs') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$activeProfessionals): ?>
                            <div class="form-text text-danger">No active professionals yet. <a href="professional-form.php">Add a professional</a> first.</div>
                        <?php else: ?>
                            <div class="form-text">&#9733; marks professionals who match this service category. Only active professionals are listed.</div>
                        <?php endif; ?>
                        <div class="invalid-feedback">Please select a professional.</div>
                    </div>

                    <div class="mb-3 d-none" data-assign-reason-wrap>
                        <label class="form-label" for="reassignReason">Reason for reassignment</label>
                        <select class="form-select" id="reassignReason" name="reassign_reason">
                            <option value="">Select reason</option>
                            <option>Professional unavailable</option>
                            <option>Customer request</option>
                            <option>Location mismatch</option>
                            <option>Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="assignNote">Note for professional (internal)</label>
                        <textarea class="form-control" id="assignNote" name="note" rows="2" placeholder="e.g. Customer prefers a call before arrival"></textarea>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notifyCustomer" name="notify_customer" checked>
                        <label class="form-check-label fs-13" for="notifyCustomer">
                            Notify customer by SMS &amp; push (assignment template)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Confirm Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
