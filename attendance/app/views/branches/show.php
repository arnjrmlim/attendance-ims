<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Branch Details</h1>
        <div class="text-muted">View branch information and details.</div>
    </div>
    <div>
        <a href="<?= url('branches') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Branches</a>
        <a href="<?= url('branches/edit?id=' . $branch['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Branch</a>
    </div>
</div>

<div class="row g-4">
    <!-- Branch Information -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Branch Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 200px;">Branch Name</th>
                        <td><strong><?= e($branch['name']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Branch Code</th>
                        <td><span class="badge bg-primary"><?= e($branch['code']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?= e($branch['address'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>City</th>
                        <td><?= e($branch['city'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Province/State</th>
                        <td><?= e($branch['province'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Contact Number</th>
                        <td><?= e($branch['phone'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td><?= e($branch['email'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Branch Manager</th>
                        <td><?= e($branch['branch_manager'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Time Zone</th>
                        <td><?= e($branch['time_zone']) ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php if ($branch['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= date('M d, Y h:i A', strtotime($branch['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= date('M d, Y h:i A', strtotime($branch['updated_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2">
                    <?php if ($branch['status'] === 'active'): ?>
                        <form method="POST" action="<?= url('branches/deactivate') ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to deactivate this branch?')">
                                <i class="bi bi-dash-circle"></i> Deactivate Branch
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= url('branches/activate') ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Activate Branch
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= url('branches/edit?id=' . $branch['id']) ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Branch
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Branch Statistics</h5>
            </div>
            <div class="card-body">
                <div class="metric-card mb-3">
                    <div class="text-muted">Total Employees</div>
                    <div class="metric-value"><?= (int) $branch['employee_count'] ?></div>
                </div>
                <div class="text-muted small">
                    <i class="bi bi-info-circle"></i> Only active employees are counted
                </div>
            </div>
        </div>
        
        <!-- Status Indicator -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Status</h5>
            </div>
            <div class="card-body">
                <?php if ($branch['status'] === 'active'): ?>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle-fill"></i> This branch is active and can be assigned to new employees.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i> This branch is inactive. It cannot be assigned to new employees but remains linked to existing employee records.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
