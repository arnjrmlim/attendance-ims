<?php
/**
 * Manual Attendance — Approval Queue (Admin/HR) + Employee Request List
 */
$isAdminHr      = has_role(['administrator', 'hr']);
$hasPendingRows = $isAdminHr && !empty(array_filter($rows, fn($r) => $r['status'] === 'Pending'));
$pagination     = pagination_meta($total, $page, $per_page);
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-person-badge me-2"></i>Manual Attendance</h4>
        <small class="text-muted">
            <?= $isAdminHr ? 'Manage employee manual attendance requests and direct entries' : 'Your manual attendance requests' ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isAdminHr): ?>
            <a href="<?= url('manual-attendance/create') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Create Manual Entry
            </a>
        <?php endif; ?>
        <a href="<?= url('manual-attendance/request') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-send"></i> Submit Attendance
        </a>
    </div>
</div>

<!-- Filters -->
<form class="panel p-3 mb-3" method="get" action="<?= url('manual-attendance') ?>">
    <div class="row g-2">
        <div class="col-sm-3">
            <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>
        <div class="col-sm-3">
            <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
        </div>
        <div class="col-sm-auto">
            <button class="btn btn-sm btn-secondary"><i class="bi bi-search"></i> Filter</button>
            <a href="<?= url('manual-attendance') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<!-- Bulk Action Form (admin/HR only) -->
<?php if ($hasPendingRows): ?>
<form method="post" action="<?= url('manual-attendance/bulk-action') ?>" id="bulkForm">
    <?= csrf_field() ?>
<?php endif; ?>

<!-- Requests Table -->
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <?php if ($hasPendingRows): ?>
                        <th width="36"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <?php endif; ?>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Submitted</th>
                    <?php if ($isAdminHr): ?><th class="no-print">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No requests found.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <?php if ($hasPendingRows): ?>
                            <td><input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= e($row['id']) ?>"></td>
                        <?php endif; ?>
                        <td>
                            <div class="fw-semibold"><?= e($row['employee_name']) ?></div>
                            <small class="text-muted"><?= e($row['employee_number']) ?></small>
                        </td>
                        <td><small><?= e($row['department_name'] ?? '—') ?></small></td>
                        <td>
                            <span class="badge bg-<?= $row['request_type'] === 'time_in' ? 'info' : 'warning' ?> text-dark">
                                <?= $row['request_type'] === 'time_in' ? 'Time In' : 'Time Out' ?>
                            </span>
                        </td>
                        <td><?= e($row['request_date']) ?></td>
                        <td><small><?= e(substr($row['created_at'], 0, 16)) ?></small></td>
                        <?php if ($isAdminHr): ?>
                            <td class="no-print">
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <button type="button"
                                            class="btn btn-xs btn-success me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#approveModal"
                                            data-id="<?= e($row['id']) ?>"
                                            data-name="<?= e($row['employee_name']) ?>"
                                            data-date="<?= e($row['request_date']) ?>">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-xs btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#rejectModal"
                                            data-id="<?= e($row['id']) ?>"
                                            data-name="<?= e($row['employee_name']) ?>"
                                            data-date="<?= e($row['request_date']) ?>">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Bulk actions bar -->
    <?php if ($hasPendingRows): ?>
    <div class="p-3 border-top d-flex align-items-center gap-2" id="bulkBar">
        <span class="text-muted small" id="selectedCount">0 selected</span>
        <input type="text" class="form-control form-control-sm w-auto" name="admin_remarks" placeholder="Remarks (optional)">
        <button class="btn btn-sm btn-success" name="action" value="approve">
            <i class="bi bi-check-all"></i> Approve Selected
        </button>
        <button class="btn btn-sm btn-danger" name="action" value="reject">
            <i class="bi bi-x-circle"></i> Reject Selected
        </button>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($pagination['pages'] > 1): ?>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $per_page, $total) ?> of <?= $total ?>
        </small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $pagination['pages']; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>">
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php if ($hasPendingRows): ?>
</form>
<?php endif; ?>

<!-- Recent Admin Entries (admin/HR only) -->
<?php if ($isAdminHr && !empty($adminEntries)): ?>
<h5 class="mt-4 fw-semibold">Recent Direct Entries</h5>
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Employee</th><th>Date</th><th>Time In</th><th>Time Out</th>
                    <th>Status</th><th>Method</th><th>Created By</th><th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminEntries as $e): ?>
                    <tr>
                        <td><?= e($e['employee_name']) ?> <small class="text-muted"><?= e($e['employee_number']) ?></small></td>
                        <td><?= e($e['attendance_date']) ?></td>
                        <td><?= $e['time_in'] ? e(date('h:i A', strtotime($e['time_in']))) : '—' ?></td>
                        <td><?= $e['time_out'] ? e(date('h:i A', strtotime($e['time_out']))) : '—' ?></td>
                        <td><span class="badge bg-secondary"><?= e($e['attendance_status']) ?></span></td>
                        <td><small><?= e($e['method']) ?></small></td>
                        <td><small><?= e($e['created_by_name'] ?? '—') ?></small></td>
                        <td><small title="<?= e($e['admin_remarks'] ?? '') ?>"><?= e(mb_strimwidth($e['reason'], 0, 40, '…')) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Approve Modal -->
<?php if ($isAdminHr): ?>
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= url('manual-attendance/approve') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="approveId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-success"><i class="bi bi-check-circle me-2"></i>Approve Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Approve manual attendance request for <strong id="approveName"></strong> on <strong id="approveDate"></strong>?</p>
                    <p class="text-muted small mb-2">This will update the employee's attendance record immediately.</p>
                    <label class="form-label">Remarks <span class="text-muted">(optional)</span></label>
                    <textarea class="form-control" name="admin_remarks" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= url('manual-attendance/reject') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="rejectId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Reject Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reject manual attendance request for <strong id="rejectName"></strong> on <strong id="rejectDate"></strong>?</p>
                    <label class="form-label">Reason for rejection <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="admin_remarks" rows="2" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Populate modals
document.querySelectorAll('[data-bs-target="#approveModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('approveId').value   = btn.dataset.id;
        document.getElementById('approveName').textContent = btn.dataset.name;
        document.getElementById('approveDate').textContent = btn.dataset.date;
    });
});
document.querySelectorAll('[data-bs-target="#rejectModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('rejectId').value   = btn.dataset.id;
        document.getElementById('rejectName').textContent = btn.dataset.name;
        document.getElementById('rejectDate').textContent = btn.dataset.date;
    });
});

// Select all
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', () => {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = selectAll.checked);
        updateCount();
    });
    document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateCount));
    function updateCount() {
        const n = document.querySelectorAll('.row-check:checked').length;
        document.getElementById('selectedCount').textContent = n + ' selected';
    }
}
</script>
