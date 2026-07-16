<div class="page-head">
    <div><h1 class="h3 mb-1">Attendance Corrections</h1><div class="text-muted">Forgot time in/out, incorrect attendance and method correction workflow.</div></div>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#correctionForm"><i class="bi bi-plus-lg"></i> New Correction</button>
</div>
<div id="correctionForm" class="collapse panel p-3 mb-3">
    <form method="post" action="<?= url('corrections') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <?php if (!$ownOnly): ?><div class="col-md-3"><label class="form-label">Employee</label><select class="form-select" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= e($employee['id']) ?>"><?= e($employee['employee_number'] . ' - ' . $employee['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="correction_type" required><?php foreach ($types as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Date</label><input class="form-control" type="date" name="attendance_date" required></div>
        <div class="col-md-2"><label class="form-label">Requested In</label><input class="form-control" type="datetime-local" name="requested_time_in"></div>
        <div class="col-md-2"><label class="form-label">Requested Out</label><input class="form-control" type="datetime-local" name="requested_time_out"></div>
        <div class="col-md-3"><label class="form-label">Attachment</label><input class="form-control" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="col-12"><label class="form-label">Reason</label><textarea class="form-control" name="reason" required></textarea></div>
        <div class="col-12"><button class="btn btn-success">Submit Correction</button></div>
    </form>
</div>
<form class="panel p-3 mb-3 row g-2">
    <div class="col-md-6"><input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search corrections"></div>
    <div class="col-md-4"><select class="form-select" name="status"><option value="">All statuses</option><?php foreach (['Pending','Approved','Rejected'] as $status): ?><option <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<div class="panel p-3"><div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>Employee</th><th>Date</th><th>Type</th><th>Requested</th><th>Status</th><th>Reason</th><th class="text-end">Actions</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr>
        <td><?= e(($row['employee_number'] ?? '') . ' ' . ($row['employee_name'] ?? '')) ?></td><td><?= e($row['attendance_date']) ?></td><td><?= e($row['correction_type']) ?></td>
        <td><?= e(($row['requested_time_in'] ?? '') . ' / ' . ($row['requested_time_out'] ?? '')) ?></td>
        <td><span class="badge text-bg-<?= $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= e($row['status']) ?></span></td>
        <td><?= e($row['reason']) ?></td>
        <td class="text-end"><?php if ($row['status'] === 'Pending' && has_role(['administrator','hr'])): ?>
            <form class="d-inline" method="post" action="<?= url('corrections/approve') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="admin_remarks" value="Approved"><button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button></form>
            <form class="d-inline" method="post" action="<?= url('corrections/reject') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input name="admin_remarks" class="form-control form-control-sm d-inline-block" style="width:130px" placeholder="Remarks"><button class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i></button></form>
        <?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
</table></div><?php if (!$rows): ?><div class="text-center text-muted py-5">No correction requests found.</div><?php endif; ?></div>
