<div class="page-head"><div><h1 class="h3 mb-1">Holiday Management</h1><div class="text-muted">Regular, special, company and branch holidays used in attendance calculations.</div></div></div>
<form class="panel p-3 mb-3 row g-3" method="post" action="<?= url('holidays') ?>">
    <?= csrf_field() ?>
    <div class="col-md-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
    <div class="col-md-2"><label class="form-label">Date</label><input class="form-control" type="date" name="holiday_date" required></div>
    <div class="col-md-2"><label class="form-label">Type</label><select class="form-select" name="type"><?php foreach (['regular','special','company','branch'] as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">All branches</option><?php foreach ($branches as $branch): ?><option value="<?= e($branch['id']) ?>"><?= e($branch['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Description</label><input class="form-control" name="description"></div>
    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-success w-100"><i class="bi bi-save"></i></button></div>
    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_recurring" value="1"> Recurring annually</label></div>
</form>
<div class="panel p-3"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Date</th><th>Type</th><th>Branch</th><th>Recurring</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e($row['holiday_date']) ?></td><td><?= e($row['type']) ?></td><td><?= e($row['branch_name'] ?? 'All branches') ?></td><td><?= $row['is_recurring'] ? 'Yes' : 'No' ?></td><td><?= e($row['status']) ?></td><td class="text-end"><form method="post" action="<?= url('holidays/deactivate') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Deactivate this holiday?"><i class="bi bi-archive"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
