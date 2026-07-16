<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Department Details</h1>
        <div class="text-muted">View department information and details.</div>
    </div>
    <div>
        <a href="<?= url('departments') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Departments</a>
        <a href="<?= url('departments/edit?id=' . $department['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Department</a>
    </div>
</div>

<div class="row g-4">
    <!-- Department Information -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Department Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 200px;">Department Name</th>
                        <td><strong><?= e($department['name']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Department Code</th>
                        <td><span class="badge bg-primary"><?= e($department['code'] ?? 'N/A') ?></span></td>
                    </tr>
                    <tr>
                        <th>Department Head</th>
                        <td><?= e($department['department_head'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?= e($department['description'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Contact Number</th>
                        <td><?= e($department['contact_number'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td><?= e($department['email_address'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Location</th>
                        <td><?= e($department['location'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php if ($department['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= date('M d, Y h:i A', strtotime($department['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= date('M d, Y h:i A', strtotime($department['updated_at'])) ?></td>
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
                    <?php if ($department['status'] === 'active'): ?>
                        <form method="POST" action="<?= url('departments/deactivate') ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $department['id'] ?>">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to deactivate this department?')">
                                <i class="bi bi-dash-circle"></i> Deactivate Department
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= url('departments/activate') ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $department['id'] ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Activate Department
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= url('departments/edit?id=' . $department['id']) ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Department
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Department Statistics</h5>
            </div>
            <div class="card-body">
                <div class="metric-card mb-3">
                    <div class="text-muted">Total Employees</div>
                    <div class="metric-value"><?= (int) $department['employee_count'] ?></div>
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
                <?php if ($department['status'] === 'active'): ?>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle-fill"></i> This department is active and can be assigned to new employees.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i> This department is inactive. It cannot be assigned to new employees but remains linked to existing employee records.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
