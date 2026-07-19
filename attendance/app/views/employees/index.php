<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Employee Management</h1>
        <div class="text-muted">Manage employee records, profiles, and attendance credentials.</div>
    </div>
    <div>
        <a href="<?= url('employees/create') ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add Employee</a>
    </div>
</div>

<?php if (flash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e(flash('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e(flash('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Employee Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="metric-card">
            <div class="text-muted">Total Employees</div>
            <div class="metric-value"><?= (int) $statistics['total'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="metric-card">
            <div class="text-muted">Active</div>
            <div class="metric-value text-success"><?= (int) $statistics['active_employment'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="metric-card">
            <div class="text-muted">Inactive</div>
            <div class="metric-value text-warning"><?= (int) $statistics['inactive'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="metric-card">
            <div class="text-muted">Recent Hires (30 days)</div>
            <div class="metric-value text-info"><?= (int) $statistics['recent_hires'] ?></div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="panel p-3 mb-3">
    <form method="get" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Employee # or name">
        </div>
        <div class="col-md-2">
            <label class="form-label">Department</label>
            <select class="form-select" name="department_id">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= e($dept['id']) ?>" <?= ($filters['department_id'] ?? '') === $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Branch</label>
            <select class="form-select" name="branch_id">
                <option value="">All Branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= e($branch['id']) ?>" <?= ($filters['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Employment Status</label>
            <select class="form-select" name="employment_status">
                <option value="">All Statuses</option>
                <?php foreach (['Active', 'Inactive', 'Suspended', 'Resigned', 'Terminated', 'Retired'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['employment_status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Employment Type</label>
            <select class="form-select" name="employment_type">
                <option value="">All Types</option>
                <?php foreach (['Regular', 'Probationary', 'Contractual', 'Part-Time', 'Temporary', 'Intern'] as $type): ?>
                    <option value="<?= e($type) ?>" <?= ($filters['employment_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
        </div>
        <div class="col-12">
            <a href="<?= url('employees') ?>" class="small text-decoration-none">Clear filters</a>
        </div>
    </form>
</div>

<!-- Bulk Actions -->
<div class="panel p-3 mb-3">
    <form method="post" action="<?= url('employees/bulk-action') ?>" id="bulkActionForm">
        <?= csrf_field() ?>
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2 align-items-center">
                <input type="checkbox" class="form-check-input" id="selectAll">
                <label class="form-check-label" for="selectAll">Select All</label>
                <select class="form-select form-select-sm" name="action" style="width: auto;">
                    <option value="">Bulk Action</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" data-confirm="Apply this action to selected employees?">Apply</button>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('employees/export') ?>?<?= http_build_query($filters) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
                <a href="<?= url('employees/import') ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-upload"></i> Import</a>
            </div>
        </div>
    </form>
</div>

<!-- Employee List -->
<div class="panel p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th width="40"><input type="checkbox" class="form-check-input" disabled></th>
                    <th>Photo</th>
                    <th>Employee #</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Firm</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th>Date Hired</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input employee-checkbox" name="ids[]" value="<?= e($emp['id']) ?>"></td>
                        <td>
                            <img src="<?= profile_picture_url($emp) ?>"
                                 alt=""
                                 class="rounded-circle"
                                 style="width:40px;height:40px;object-fit:cover;">
                        </td>
                        <td><strong><?= e($emp['employee_number']) ?></strong></td>
                        <td>
                            <a href="<?= url('employees/show?id=' . $emp['id']) ?>" class="text-decoration-none">
                                <?= e($emp['full_name']) ?>
                            </a>
                        </td>
                        <td><?= e($emp['department_name'] ?? '-') ?></td>
                        <td><?= e($emp['position'] ?? '-') ?></td>
                        <td><?= e($emp['branch_name'] ?? '-') ?></td>
                        <td><?= e($emp['shift_name'] ?? '-') ?></td>
                        <td>
                            <?php
                            $statusClass = match($emp['employment_status']) {
                                'Active' => 'text-bg-success',
                                'Inactive' => 'text-bg-secondary',
                                'Suspended' => 'text-bg-warning',
                                'Resigned' => 'text-bg-info',
                                'Terminated' => 'text-bg-danger',
                                'Retired' => 'text-bg-primary',
                                default => 'text-bg-secondary'
                            };
                            ?>
                            <span class="badge status-badge <?= $statusClass ?>"><?= e($emp['employment_status']) ?></span>
                        </td>
                        <td><?= e($emp['employment_type'] ?? '-') ?></td>
                        <td><?= e($emp['date_hired'] ?? '-') ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= url('employees/show?id=' . $emp['id']) ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <a href="<?= url('employees/edit?id=' . $emp['id']) ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="<?= url('employees/print-qr?id=' . $emp['id']) ?>" class="btn btn-outline-info" title="Print QR"><i class="bi bi-qr-code"></i></a>
                                <?php if ($emp['status'] === 'active'): ?>
                                    <form method="post" action="<?= url('employees/deactivate') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e($emp['id']) ?>">
                                        <button class="btn btn-outline-warning" title="Deactivate" data-confirm="Deactivate this employee?"><i class="bi bi-person-x"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= url('employees/activate') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e($emp['id']) ?>">
                                        <button class="btn btn-outline-success" title="Activate" data-confirm="Activate this employee?"><i class="bi bi-person-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($meta['pages'] > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php if ($meta['page'] > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('employees?page=' . ($meta['page'] - 1) . '&' . http_build_query($filters)) ?>">Previous</a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $meta['pages']; $i++): ?>
                    <?php if ($i === $meta['page'] || abs($i - $meta['page']) <= 2 || $i === 1 || $i === $meta['pages']): ?>
                        <li class="page-item <?= $i === $meta['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('employees?page=' . $i . '&' . http_build_query($filters)) ?>"><?= $i ?></a>
                        </li>
                    <?php elseif (abs($i - $meta['page']) === 3): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($meta['page'] < $meta['pages']): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('employees?page=' . ($meta['page'] + 1) . '&' . http_build_query($filters)) ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
    
    // Update select all when individual checkboxes change
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            selectAll.checked = [...checkboxes].every(c => c.checked);
        });
    });
});
</script>
