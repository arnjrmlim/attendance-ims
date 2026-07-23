<div class="page-head">
    <div><h1 class="h3 mb-1">Reports</h1><div class="text-muted">Professional attendance, late, absent, leave and holiday reporting.</div></div>
    <div class="btn-group no-print"><a class="btn btn-outline-success" href="<?= url('reports/export?' . http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>">CSV</a><a class="btn btn-outline-success" href="<?= url('reports/export?' . http_build_query(array_merge($_GET, ['format' => 'xlsx']))) ?>">Excel</a><a class="btn btn-outline-danger" href="<?= url('reports/export?' . http_build_query(array_merge($_GET, ['format' => 'pdf']))) ?>">PDF</a><button class="btn btn-dark" onclick="window.print()">Print</button></div>
</div>
<form class="panel p-3 mb-3 row g-2 no-print">
    <div class="col-md-2"><select class="form-select" name="employee_id"><option value="">Employee</option><?php foreach ($employees as $employee): ?><option value="<?= e($employee['id']) ?>" <?= ($_GET['employee_id'] ?? '') === $employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="department_id"><option value="">Department</option><?php foreach ($departments as $department): ?><option value="<?= e($department['id']) ?>" <?= ($_GET['department_id'] ?? '') === $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="branch_id"><option value="">Branch</option><?php foreach ($branches as $branch): ?><option value="<?= e($branch['id']) ?>" <?= ($_GET['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input class="form-control" type="date" name="start_date" value="<?= e($_GET['start_date'] ?? '') ?>"></div>
    <div class="col-md-2"><input class="form-control" type="date" name="end_date" value="<?= e($_GET['end_date'] ?? '') ?>"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Generate</button></div>
</form>
<?php
$cfg = new \App\Services\SettingsService();
$companyLogo = $cfg->getCompanyLogo();
$companyName = $cfg->getCompanyName();
?>
<div class="panel p-4">
    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
        <div class="d-flex align-items-center gap-3"><img src="<?= asset_url($companyLogo) ?>" width="52" height="52" alt="IMS"><div><h2 class="h4 mb-0"><?= e($companyName) ?></h2><div class="text-muted">Generated <?= e(date('Y-m-d H:i')) ?> by <?= e(current_user()['username']) ?></div><div class="text-muted">Period: <?= e($period) ?></div></div></div>
        <div class="text-end text-muted">Page 1</div>
    </div>
    <div class="row g-3 mb-3"><?php foreach ($totals as $label => $value): ?><div class="col-sm-4 col-lg-2"><div class="metric-card"><div class="text-muted text-capitalize"><?= e($label) ?></div><div class="metric-value"><?= (int) $value ?></div></div></div><?php endforeach; ?></div>
    <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Employee</th><th>Department</th><th>Branch</th><th>Status</th><th>Late</th><th>Undertime</th><th>Hours</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= e($row['display_date']) ?></td><td><?= e($row['employee_name']) ?></td><td><?= e($row['department_name']) ?></td><td><?= e($row['branch_name']) ?></td><td><?= e($row['day_status']) ?></td><td><?= e($row['late_minutes']) ?></td><td><?= e($row['undertime_minutes']) ?></td><td><?= e($row['total_hours']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <footer class="border-top pt-3 text-muted small">Totals are based on the selected filters. Exported files include the same report period and generated-by context.</footer>
</div>
