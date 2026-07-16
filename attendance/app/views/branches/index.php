<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Branch Management</h1>
        <div class="text-muted">Manage branches, locations, and organizational units.</div>
    </div>
    <div>
        <a href="<?= url('branches/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Branch</a>
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
        <form method="GET" action="<?= url('branches') ?>" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Search by name, code, or city..." value="<?= e($filters['q'] ?? '') ?>">
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
                <a href="<?= url('branches') ?>" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Branch List -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Branch Code</th>
                        <th>Branch Name</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Manager</th>
                        <th>Employees</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td><strong><?= e($branch['code']) ?></strong></td>
                            <td><?= e($branch['name']) ?></td>
                            <td>
                                <?= e($branch['city'] ?? 'N/A') ?>
                                <?php if ($branch['province']): ?>
                                    <small class="text-muted d-block"><?= e($branch['province']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($branch['phone']): ?>
                                    <div><?= e($branch['phone']) ?></div>
                                <?php endif; ?>
                                <?php if ($branch['email']): ?>
                                    <small class="text-muted"><?= e($branch['email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($branch['branch_manager'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-info"><?= (int) $branch['employee_count'] ?></span></td>
                            <td>
                                <?php if ($branch['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('branches/show?id=' . $branch['id']) ?>" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= url('branches/edit?id=' . $branch['id']) ?>" class="btn btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($branch['status'] === 'active'): ?>
                                        <form method="POST" action="<?= url('branches/deactivate') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                                            <button type="submit" class="btn btn-outline-warning" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this branch?')">
                                                <i class="bi bi-dash-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="<?= url('branches/activate') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                                            <button type="submit" class="btn btn-outline-success" title="Activate">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($branches)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">No branches found.</div>
                                <a href="<?= url('branches/create') ?>" class="btn btn-sm btn-primary mt-2">Add your first branch</a>
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
                    <a class="page-link" href="<?= url('branches?page=' . $i . '&per_page=' . $meta['per_page'] . '&' . http_build_query($filters)) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
