<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Edit Department</h1>
        <div class="text-muted">Update department information.</div>
    </div>
    <div>
        <a href="<?= url('departments/show?id=' . $department['id']) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Department</a>
    </div>
</div>

<?php if (flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e(flash('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= url('departments/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $department['id'] ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Department Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($department['name']) ?>" required>
                    <small class="text-muted">Official name of the department</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department Code</label>
                    <input type="text" name="code" class="form-control" value="<?= e($department['code'] ?? '') ?>" maxlength="20">
                    <small class="text-muted">Unique code (e.g., IT, HR, FINANCE)</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Department Head</label>
                    <input type="text" name="department_head" class="form-control" value="<?= e($department['department_head'] ?? '') ?>">
                    <small class="text-muted">Name of department head</small>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($department['description'] ?? '') ?></textarea>
                    <small class="text-muted">Department description and responsibilities</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= e($department['contact_number'] ?? '') ?>">
                    <small class="text-muted">Department contact number</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email_address" class="form-control" value="<?= e($department['email_address'] ?? '') ?>">
                    <small class="text-muted">Department email address</small>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?= e($department['location'] ?? '') ?>">
                    <small class="text-muted">Physical location or floor</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $department['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $department['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <small class="text-muted">Active departments can be assigned to employees</small>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Department</button>
                    <a href="<?= url('departments/show?id=' . $department['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
