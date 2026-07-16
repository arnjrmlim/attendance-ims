<div class="page-head"><div><h1 class="h3 mb-1">Attendance Monitoring</h1><div class="text-muted">Today, weekly, monthly, employee, department and branch attendance views.</div></div></div>
<form class="panel p-3 mb-3 row g-2">
    <div class="col-md-2"><input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search"></div>
    <div class="col-md-2"><select class="form-select" name="employee_id"><option value="">All employees</option><?php foreach ($employees as $employee): ?><option value="<?= e($employee['id']) ?>" <?= ($_GET['employee_id'] ?? '') === $employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="department_id"><option value="">All departments</option><?php foreach ($departments as $department): ?><option value="<?= e($department['id']) ?>" <?= ($_GET['department_id'] ?? '') === $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="branch_id"><option value="">All branches</option><?php foreach ($branches as $branch): ?><option value="<?= e($branch['id']) ?>" <?= ($_GET['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><input class="form-control" type="date" name="start_date" value="<?= e($_GET['start_date'] ?? date('Y-m-01')) ?>"></div>
    <div class="col-md-1"><input class="form-control" type="date" name="end_date" value="<?= e($_GET['end_date'] ?? date('Y-m-d')) ?>"></div>
    <div class="col-md-1"><select class="form-select" name="status"><option value="">Status</option><?php foreach (['present','late','absent','leave','holiday','undertime'] as $status): ?><option <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><button class="btn btn-outline-primary w-100">Go</button></div>
</form>
<div class="panel p-3"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Date</th><th>Employee</th><th>Department</th><th>Branch</th><th>Shift</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Late</th><th>Undertime</th><th>Hours</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr>
        <td><?= e($row['attendance_date']) ?></td><td><?= e($row['employee_number'] . ' - ' . $row['employee_name']) ?></td><td><?= e($row['department_name']) ?></td><td><?= e($row['branch_name']) ?></td><td><?= e($row['shift_name']) ?></td>
        <td><span class="badge text-bg-info"><?= e($row['day_status']) ?></span></td><td><?= e($row['time_in']) ?></td><td><?= e($row['time_out']) ?></td><td><?= e($row['late_minutes']) ?></td><td><?= e($row['undertime_minutes']) ?></td><td><?= e($row['total_hours']) ?></td>
    </tr><?php endforeach; ?></tbody>
</table></div><?php if (!$rows): ?><div class="text-center text-muted py-5">No attendance records found.</div><?php endif; ?></div>
