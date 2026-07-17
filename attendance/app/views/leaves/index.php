<?php
/**
 * Leave Management
 * - Employees: see only their own requests; no employee/dept/branch filters
 * - HR / Admin: see all requests with full filter set and approve/reject controls
 */
$isAdminHr = $isAdminHr ?? has_role(['administrator', 'hr']);
$ownOnly   = $ownOnly   ?? !$isAdminHr;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Leave Management</h1>
        <div class="text-muted small">
            <?= $isAdminHr
                ? 'View, approve, reject and manage all employee leave requests.'
                : 'Submit and track your leave requests.' ?>
        </div>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#leaveForm">
        <i class="bi bi-plus-lg me-1"></i>New Leave Request
    </button>
</div>

<!-- ── New leave request form ────────────────────────────────────── -->
<div id="leaveForm" class="collapse panel p-4 mb-3">
    <h6 class="fw-semibold mb-3">Submit Leave Request</h6>
    <form method="post" action="<?= url('leaves') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-3">
            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" name="leave_type" required>
                <?php foreach ($types as $type): ?>
                    <option><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Start Date <span class="text-danger">*</span></label>
            <input class="form-control form-control-sm" type="date" name="start_date" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">End Date <span class="text-danger">*</span></label>
            <input class="form-control form-control-sm" type="date" name="end_date" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Attachment</label>
            <input class="form-control form-control-sm" type="file" name="attachment"
                   accept=".pdf,.jpg,.jpeg,.png">
        </div>
        <div class="col-12">
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea class="form-control form-control-sm" name="reason" rows="2" required></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-success btn-sm">
                <i class="bi bi-send me-1"></i>Submit Request
            </button>
        </div>
    </form>
</div>

<!-- ── Filters ────────────────────────────────────────────────────── -->
<form class="panel p-3 mb-3" method="get" action="<?= url('leaves') ?>">
    <div class="row g-2 align-items-end">

        <?php if ($isAdminHr): ?>
        <!-- Admin/HR: full filter set including employee search -->
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input class="form-control form-control-sm" name="q"
                   value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Employee name, number or reason">
        </div>
        <?php else: ?>
        <!-- Employee: search own records by reason only -->
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input class="form-control form-control-sm" name="q"
                   value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Search by reason">
        </div>
        <?php endif; ?>

        <div class="col-md-2">
            <label class="form-label small mb-1">Leave Type</label>
            <select class="form-select form-select-sm" name="leave_type">
                <option value="">All Types</option>
                <?php foreach ($types as $type): ?>
                    <option <?= ($_GET['leave_type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['Pending','Approved','Rejected','Cancelled'] as $status): ?>
                    <option <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small mb-1">From</label>
            <input class="form-control form-control-sm" type="date" name="start_date"
                   value="<?= e($_GET['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">To</label>
            <input class="form-control form-control-sm" type="date" name="end_date"
                   value="<?= e($_GET['end_date'] ?? '') ?>">
        </div>

        <div class="col-md-auto d-flex gap-2">
            <button class="btn btn-sm btn-secondary">
                <i class="bi bi-search"></i> Filter
            </button>
            <a href="<?= url('leaves') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Reset
            </a>
        </div>
    </div>
</form>

<!-- ── Leave requests table ───────────────────────────────────────── -->
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <?php if ($isAdminHr): ?>
                        <th>Employee</th>
                    <?php endif; ?>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <?php if ($isAdminHr): ?>
                        <th>Remarks</th>
                    <?php endif; ?>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= $isAdminHr ? 8 : 6 ?>"
                        class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x fs-4 d-block mb-2"></i>
                        No leave requests found.
                    </td>
                </tr>
            <?php else: foreach ($rows as $row):
                $statusClass = match($row['status']) {
                    'Approved'  => 'text-bg-success',
                    'Rejected'  => 'text-bg-danger',
                    'Cancelled' => 'text-bg-secondary',
                    default     => 'text-bg-warning',
                };
            ?>
                <tr>
                    <?php if ($isAdminHr): ?>
                        <td>
                            <div class="fw-semibold small"><?= e($row['employee_name'] ?? '—') ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= e($row['employee_number'] ?? '') ?></div>
                        </td>
                    <?php endif; ?>
                    <td><small><?= e($row['leave_type']) ?></small></td>
                    <td class="text-nowrap">
                        <small><?= e($row['start_date']) ?></small><br>
                        <small class="text-muted">to <?= e($row['end_date']) ?></small>
                    </td>
                    <td><small><?= e($row['number_of_days']) ?></small></td>
                    <td>
                        <span class="badge <?= $statusClass ?>">
                            <?= e($row['status']) ?>
                        </span>
                    </td>
                    <td>
                        <small title="<?= e($row['reason']) ?>">
                            <?= e(mb_strimwidth($row['reason'], 0, 60, '…')) ?>
                        </small>
                    </td>
                    <?php if ($isAdminHr): ?>
                        <td>
                            <small class="text-muted">
                                <?= e($row['admin_remarks'] ?? '—') ?>
                            </small>
                        </td>
                    <?php endif; ?>
                    <td class="text-end text-nowrap">
                        <?php if ($row['status'] === 'Pending' && $isAdminHr): ?>
                            <!-- Approve -->
                            <form class="d-inline" method="post"
                                  action="<?= url('leaves/approve') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <input type="hidden" name="admin_remarks" value="">
                                <button class="btn btn-xs btn-success" title="Approve">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <!-- Reject with inline remarks -->
                            <button class="btn btn-xs btn-danger ms-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectModal"
                                    data-id="<?= e($row['id']) ?>"
                                    data-name="<?= e($row['employee_name'] ?? '') ?>"
                                    title="Reject">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        <?php endif; ?>

                        <?php if ($row['status'] === 'Pending'): ?>
                            <!-- Cancel — available to the owner and to admin/hr -->
                            <form class="d-inline ms-1" method="post"
                                  action="<?= url('leaves/cancel') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <button class="btn btn-xs btn-outline-secondary"
                                        data-confirm="Cancel this leave request?"
                                        title="Cancel">
                                    <i class="bi bi-slash-circle"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                        <button class="btn btn-xs btn-outline-secondary ms-1"
                                onclick="window.print()" title="Print">
                            <i class="bi bi-printer"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Reject modal ───────────────────────────────────────────────── -->
<?php if ($isAdminHr): ?>
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="rejectModalLabel">
                    <i class="bi bi-x-circle me-2"></i>Reject Leave Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?= url('leaves/reject') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="rejectLeaveId">
                <div class="modal-body">
                    <p class="mb-2">Reject leave request for
                        <strong id="rejectLeaveName"></strong>?
                    </p>
                    <label class="form-label">
                        Reason for rejection <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" name="admin_remarks" rows="2"
                              required placeholder="Provide a reason…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-bs-target="#rejectModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('rejectLeaveId').value   = btn.dataset.id;
        document.getElementById('rejectLeaveName').textContent = btn.dataset.name;
    });
});
</script>
<?php endif; ?>
