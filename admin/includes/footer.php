<?php
/**
 * Closing layout + shared modals + scripts.
 * Optional page variables:
 *   $withAssignModal bool    Include the Assign/Reassign professional modal
 *   $pageScripts     string  Page-specific <script> markup (captured with ob_start)
 */
$plugins = $plugins ?? [];
?>
</main>

<footer class="page-footer d-flex flex-wrap justify-content-between gap-2">
    <span>&copy; <?= date('Y') ?> Zen Home Experts. All rights reserved.</span>
    <span>Admin Panel v1.0</span>
</footer>
</div><!-- /.main-wrapper -->

<!-- Global confirm modal (delete / cancel / destructive actions) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body p-4">
                <span class="modal-icon icon-soft-danger mb-3"><i class="bi bi-exclamation-triangle"></i></span>
                <h5 class="mb-2" id="confirmModalTitle">Are you sure?</h5>
                <p class="text-muted fs-13 mb-4" id="confirmModalText">This action cannot be undone.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmModalBtn">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($withAssignModal)) include __DIR__ . '/modals/assign-professional.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (in_array('datatables', $plugins, true)): ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<?php endif; ?>
<?php if (in_array('charts', $plugins, true)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?php endif; ?>
<?php if (in_array('editor', $plugins, true)): ?>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<?php endif; ?>
<script src="assets/js/api.js?v=2"></script>
<script src="assets/js/admin.js?v=3"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
