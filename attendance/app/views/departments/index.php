<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Department Management</h1>
        <div class="text-muted">Manage departments, organizational units, and assignments.</div>
    </div>
    <div>
        <a href="<?= url('departments/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Department</a>
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

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('departments') ?>" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Search by name, code, or location..." value="<?= e($filters['q'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                <a href="<?= url('departments') ?>" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Department List -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Department Code</th>
                        <th>Department Name</th>
                        <th>Department Head</th>
                        <th>Employees</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $department): ?>
                        <tr>
                            <td><strong><?= e($department['code'] ?? 'N/A') ?></strong></td>
                            <td><?= e($department['name']) ?></td>
                            <td><?= e($department['department_head'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-info"><?= (int) $department['employee_count'] ?></span></td>
                            <td>
                                <?php if ($department['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('departments/show?id=' . $department['id']) ?>" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= url('departments/edit?id=' . $department['id']) ?>" class="btn btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($department['status'] === 'active'): ?>
                                        <form method="POST" action="<?= url('departments/deactivate') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $department['id'] ?>">
                                            <button type="submit" class="btn btn-outline-warning" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this department?')">
                                                <i class="bi bi-dash-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="<?= url('departments/activate') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $department['id'] ?>">
                                            <button type="submit" class="btn btn-outline-success" title="Activate">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="text-muted">No departments found.</div>
                                <a href="<?= url('departments/create') ?>" class="btn btn-sm btn-primary mt-2">Add your first department</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($meta['pages'] > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $meta['pages']; $i++): ?>
                <li class="page-item <?= $i === $meta['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="<?= url('departments?page=' . $i . '&per_page=' . $meta['per_page'] . '&' . http_build_query($filters)) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
