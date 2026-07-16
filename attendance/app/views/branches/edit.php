<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Edit Branch</h1>
        <div class="text-muted">Update branch information.</div>
    </div>
    <div>
        <a href="<?= url('branches/show?id=' . $branch['id']) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Branch</a>
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
        <form method="POST" action="<?= url('branches/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $branch['id'] ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($branch['name']) ?>" required>
                    <small class="text-muted">Official name of the branch</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Branch Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control" value="<?= e($branch['code']) ?>" required maxlength="20">
                    <small class="text-muted">Unique code (e.g., HQ, MNL, CEB)</small>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= e($branch['address'] ?? '') ?>">
                    <small class="text-muted">Street address</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= e($branch['city'] ?? '') ?>">
                    <small class="text-muted">City or municipality</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Province/State</label>
                    <input type="text" name="province" class="form-control" value="<?= e($branch['province'] ?? '') ?>">
                    <small class="text-muted">Province or state</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($branch['phone'] ?? '') ?>">
                    <small class="text-muted">Branch phone number</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= e($branch['email'] ?? '') ?>">
                    <small class="text-muted">Branch email address</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Branch Manager</label>
                    <input type="text" name="branch_manager" class="form-control" value="<?= e($branch['branch_manager'] ?? '') ?>">
                    <small class="text-muted">Name of branch manager</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Time Zone</label>
                    <select name="time_zone" class="form-select">
                        <option value="Asia/Manila" <?= $branch['time_zone'] === 'Asia/Manila' ? 'selected' : '' ?>>Asia/Manila (PHT)</option>
                        <option value="Asia/Tokyo" <?= $branch['time_zone'] === 'Asia/Tokyo' ? 'selected' : '' ?>>Asia/Tokyo (JST)</option>
                        <option value="Asia/Singapore" <?= $branch['time_zone'] === 'Asia/Singapore' ? 'selected' : '' ?>>Asia/Singapore (SGT)</option>
                        <option value="Asia/Shanghai" <?= $branch['time_zone'] === 'Asia/Shanghai' ? 'selected' : '' ?>>Asia/Shanghai (CST)</option>
                        <option value="Asia/Kolkata" <?= $branch['time_zone'] === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (IST)</option>
                        <option value="America/New_York" <?= $branch['time_zone'] === 'America/New_York' ? 'selected' : '' ?>>America/New_York (EST)</option>
                        <option value="America/Los_Angeles" <?= $branch['time_zone'] === 'America/Los_Angeles' ? 'selected' : '' ?>>America/Los_Angeles (PST)</option>
                        <option value="Europe/London" <?= $branch['time_zone'] === 'Europe/London' ? 'selected' : '' ?>>Europe/London (GMT)</option>
                        <option value="UTC" <?= $branch['time_zone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                    </select>
                    <small class="text-muted">Time zone for branch operations</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $branch['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $branch['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <small class="text-muted">Active branches can be assigned to employees</small>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Branch</button>
                    <a href="<?= url('branches/show?id=' . $branch['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
