<?php $isAdminHr = !$ownOnly; ?>

<div class="page-head">
    <div><h1 class="h3 mb-1">Attendance Corrections</h1><div class="text-muted">Forgot time in/out, incorrect attendance and method correction workflow.</div></div>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#correctionForm"><i class="bi bi-plus-lg"></i> New Correction</button>
</div>
<div id="correctionForm" class="collapse panel p-3 mb-3">
    <form method="post" action="<?= url('corrections') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <?php if (!$ownOnly): ?><div class="col-md-3"><label class="form-label">Employee</label><select class="form-select" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= e($employee['id']) ?>"><?= e($employee['employee_number'] . ' - ' . $employee['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="correction_type" id="correctionType" required><?php foreach ($types as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Date</label><input class="form-control" type="date" name="attendance_date" required></div>
        <div class="col-md-2" id="requestedTimeInGroup"><label class="form-label">Requested In</label><input class="form-control" type="datetime-local" name="requested_time_in" id="requestedTimeIn"></div>
        <div class="col-md-2" id="requestedTimeOutGroup"><label class="form-label">Requested Out</label><input class="form-control" type="datetime-local" name="requested_time_out" id="requestedTimeOut"></div>
        <div class="col-md-3"><label class="form-label">Attachment</label><input class="form-control" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="col-12"><label class="form-label">Reason</label><textarea class="form-control" name="reason" required></textarea></div>
        <div class="col-12"><button class="btn btn-success">Submit Correction</button></div>
    </form>
</div>
<form class="panel p-3 mb-3" method="get" action="<?= url('corrections') ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Search</label><input class="form-control form-control-sm" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= $ownOnly ? 'Search by reason' : 'Employee name, number or reason' ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Type</label><select class="form-select form-select-sm" name="correction_type"><option value="">All Types</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= ($_GET['correction_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Status</label><select class="form-select form-select-sm" name="status"><option value="">All Statuses</option><?php foreach (['Pending','Approved','Rejected','Cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">From</label><input class="form-control form-control-sm" type="date" name="start_date" value="<?= e($_GET['start_date'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">To</label><input class="form-control form-control-sm" type="date" name="end_date" value="<?= e($_GET['end_date'] ?? '') ?>"></div>
        <div class="col-md-auto d-flex gap-2"><button class="btn btn-sm btn-secondary"><i class="bi bi-search"></i> Filter</button><a href="<?= url('corrections') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Reset</a></div>
    </div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <?php if ($isAdminHr): ?><th>Employee</th><?php endif; ?>
                    <th>Date</th><th>Type</th><th>Requested</th><th>Status</th><th>Reason</th><th>Attachment</th><th>Remarks</th>
                    <th class="text-nowrap" style="width:1%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?= $isAdminHr ? 9 : 8 ?>" class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-4 d-block mb-2"></i>No correction requests found.</td></tr>
            <?php else: foreach ($rows as $row):
                $statusClass = match($row['status']) {
                    'Approved' => 'text-bg-success', 'Rejected' => 'text-bg-danger',
                    'Cancelled' => 'text-bg-secondary', default => 'text-bg-warning',
                };
                $requestedTimes = implode(' / ', array_filter([$row['requested_time_in'] ?? '', $row['requested_time_out'] ?? ''])) ?: '—';
            ?>
                <tr>
                    <?php if ($isAdminHr): ?><td><div class="fw-semibold small"><?= e($row['employee_name'] ?? '—') ?></div><div class="text-muted" style="font-size:.75rem"><?= e($row['employee_number'] ?? '') ?></div></td><?php endif; ?>
                    <td><small><?= e($row['attendance_date']) ?></small></td>
                    <td><small><?= e($row['correction_type']) ?></small></td>
                    <td><small><?= e($requestedTimes) ?></small></td>
                    <td><span class="badge <?= $statusClass ?>"><?= e($row['status']) ?></span></td>
                    <td><small title="<?= e($row['reason']) ?>"><?= e(mb_strimwidth($row['reason'], 0, 60, '…')) ?></small></td>
                    <td><?php if (!empty($row['attachment'])): ?><a class="btn btn-xs btn-outline-primary" href="<?= url('corrections/attachment?id=' . rawurlencode($row['id'])) ?>" target="_blank" rel="noopener" title="View attachment"><i class="bi bi-paperclip"></i> View</a><?php else: ?><small class="text-muted">No file</small><?php endif; ?></td>
                    <?php if (current_user()): ?>
                        <td><small class="text-muted"><?= e($row['admin_remarks'] ?? '—') ?></small></td>
                    <?php endif; ?>
                    <td class="text-nowrap" style="width:1%">
                        <?php if ($row['status'] === 'Pending' && $isAdminHr): ?>
                            <form class="d-inline" method="post" action="<?= url('corrections/approve') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="admin_remarks" value="Approved"><button class="btn btn-xs btn-success" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                            <button class="btn btn-xs btn-danger ms-1" data-bs-toggle="modal" data-bs-target="#rejectCorrectionModal" data-id="<?= e($row['id']) ?>" data-name="<?= e($row['employee_name'] ?? '') ?>" title="Reject"><i class="bi bi-x-lg"></i></button>
                        <?php endif; ?>
                        <?php if ($row['status'] === 'Pending'): ?><form class="d-inline ms-1" method="post" action="<?= url('corrections/cancel') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-xs btn-outline-secondary" data-confirm="Cancel this correction request?" title="Cancel"><i class="bi bi-slash-circle"></i></button></form><?php endif; ?>
                        <button class="btn btn-xs btn-outline-secondary ms-1" onclick="window.print()" title="Print"><i class="bi bi-printer"></i></button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isAdminHr): ?>
<div class="modal fade" id="rejectCorrectionModal" tabindex="-1" aria-labelledby="rejectCorrectionModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger" id="rejectCorrectionModalLabel"><i class="bi bi-x-circle me-2"></i>Reject Correction Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post" action="<?= url('corrections/reject') ?>">
            <?= csrf_field() ?><input type="hidden" name="id" id="rejectCorrectionId">
            <div class="modal-body"><p class="mb-2">Reject correction request for <strong id="rejectCorrectionName"></strong>?</p><label class="form-label">Reason for rejection <span class="text-danger">*</span></label><textarea class="form-control" name="admin_remarks" rows="2" required placeholder="Provide a reason…"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger btn-sm">Reject</button></div>
        </form>
    </div></div>
</div>
<script>
document.querySelectorAll('[data-bs-target="#rejectCorrectionModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('rejectCorrectionId').value = btn.dataset.id;
        document.getElementById('rejectCorrectionName').textContent = btn.dataset.name;
    });
});
</script>
<?php endif; ?>

<script>
(() => {
    const type = document.getElementById('correctionType');
    const inGroup = document.getElementById('requestedTimeInGroup');
    const outGroup = document.getElementById('requestedTimeOutGroup');
    const inInput = document.getElementById('requestedTimeIn');
    const outInput = document.getElementById('requestedTimeOut');

    const updateRequestedTimes = () => {
        const forgotTimeIn = type.value === 'Forgot Time In';
        const forgotTimeOut = type.value === 'Forgot Time Out';

        inGroup.classList.toggle('d-none', forgotTimeOut);
        outGroup.classList.toggle('d-none', forgotTimeIn);
        inInput.required = forgotTimeIn;
        outInput.required = forgotTimeOut;

        if (forgotTimeOut) inInput.value = '';
        if (forgotTimeIn) outInput.value = '';
    };

    type.addEventListener('change', updateRequestedTimes);
    updateRequestedTimes();
})();
</script>
