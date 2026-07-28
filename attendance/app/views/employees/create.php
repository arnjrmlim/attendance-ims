<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Add New Employee</h1>
        <div class="text-muted">Register a new employee with complete profile information.</div>
    </div>
    <div>
        <a href="<?= url('employees') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
    </div>
</div>

<?php if (flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e(flash('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('employees') ?>" class="row g-3">
    <?= csrf_field() ?>

    <!-- Personal Information -->
    <div class="col-12">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-person"></i> Personal Information</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Employee Number <span class="text-danger">*</span></label>
                    <input class="form-control" name="employee_number" required placeholder="EMP-001" value="<?= e($old['employee_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="first_name" required value="<?= e($old['first_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Middle Name</label>
                    <input class="form-control" name="middle_name" value="<?= e($old['middle_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="last_name" required value="<?= e($old['last_name'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Suffix</label>
                    <select class="form-select" name="suffix">
                        <option value="" <?= ($old['suffix'] ?? '') === '' ? 'selected' : '' ?>>None</option>
                        <option value="Jr." <?= ($old['suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                        <option value="Sr." <?= ($old['suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                        <option value="II" <?= ($old['suffix'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
                        <option value="III" <?= ($old['suffix'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
                        <option value="IV" <?= ($old['suffix'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="" <?= ($old['gender'] ?? '') === '' ? 'selected' : '' ?>>Select</option>
                        <option value="Male" <?= ($old['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($old['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($old['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of Birth</label>
                    <input class="form-control" type="date" name="date_of_birth" value="<?= e($old['date_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Civil Status</label>
                    <select class="form-select" name="civil_status">
                        <option value="" <?= ($old['civil_status'] ?? '') === '' ? 'selected' : '' ?>>Select</option>
                        <option value="Single" <?= ($old['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                        <option value="Married" <?= ($old['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                        <option value="Widowed" <?= ($old['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                        <option value="Separated" <?= ($old['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                        <option value="Divorced" <?= ($old['civil_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nationality</label>
                    <input class="form-control" name="nationality" placeholder="e.g., Filipino" value="<?= e($old['nationality'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="col-12">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-telephone"></i> Contact Information</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                    <input class="form-control" name="contact_number" required placeholder="+63 912 345 6789" value="<?= e($old['contact_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Alternate Mobile</label>
                    <input class="form-control" name="alternate_mobile" placeholder="+63 912 345 6789" value="<?= e($old['alternate_mobile'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input class="form-control" type="email" name="email" required placeholder="employee@example.com" value="<?= e($old['email'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" placeholder="For system login" value="<?= e($old['username'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" placeholder="For system login" value="<?= e($old['password'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select" name="role_id" required>
                        <option value="">Select Role</option>
                        <?php if (empty($roles)): ?>
                            <option value="" disabled>No roles configured</option>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= e($role['id']) ?>" <?= ($old['role_id'] ?? '') === $role['id'] ? 'selected' : ($role['name'] === 'Employee' && empty($old['role_id']) ? 'selected' : '') ?>><?= e($role['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Home Address</label>
                    <textarea class="form-control" name="home_address" rows="2"><?= e($old['home_address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency Contact -->
    <div class="col-12">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Emergency Contact</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="emergency_contact_name" required value="<?= e($old['emergency_contact_name'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                    <input class="form-control" name="emergency_contact_number" required value="<?= e($old['emergency_contact_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Relationship <span class="text-danger">*</span></label>
                    <input class="form-control" name="emergency_contact_relationship" required placeholder="e.g., Spouse, Parent" value="<?= e($old['emergency_contact_relationship'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Employment Information -->
    <div class="col-12">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-briefcase"></i> Employment Information</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select class="form-select" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= e($dept['id']) ?>" <?= ($old['department_id'] ?? '') === $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" name="branch_id" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e($branch['id']) ?>" <?= ($old['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Position <span class="text-danger">*</span></label>
                    <input class="form-control" name="position" required value="<?= e($old['position'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_status" required>
                        <option value="Active" <?= ($old['employment_status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($old['employment_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="Suspended" <?= ($old['employment_status'] ?? '') === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                        <option value="Resigned" <?= ($old['employment_status'] ?? '') === 'Resigned' ? 'selected' : '' ?>>Resigned</option>
                        <option value="Terminated" <?= ($old['employment_status'] ?? '') === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
                        <option value="Retired" <?= ($old['employment_status'] ?? '') === 'Retired' ? 'selected' : '' ?>>Retired</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_type" required>
                        <option value="Regular" <?= ($old['employment_type'] ?? '') === 'Regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="Probationary" <?= ($old['employment_type'] ?? '') === 'Probationary' ? 'selected' : '' ?>>Probationary</option>
                        <option value="Contractual" <?= ($old['employment_type'] ?? '') === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                        <option value="Part-Time" <?= ($old['employment_type'] ?? '') === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                        <option value="Temporary" <?= ($old['employment_type'] ?? '') === 'Temporary' ? 'selected' : '' ?>>Temporary</option>
                        <option value="Intern" <?= ($old['employment_type'] ?? '') === 'Intern' ? 'selected' : '' ?>>Intern</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Hired <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" name="date_hired" required value="<?= e($old['date_hired'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Immediate Supervisor</label>
                    <select class="form-select" name="immediate_supervisor_id">
                        <option value="">None</option>
                        <?php foreach ($supervisors as $sup): ?>
                            <option value="<?= e($sup['id']) ?>" <?= ($old['immediate_supervisor_id'] ?? '') === $sup['id'] ? 'selected' : '' ?>><?= e($sup['full_name']) ?> (<?= e($sup['employee_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Assigned Shift <span class="text-danger">*</span></label>
                    <select class="form-select" name="shift_id" required>
                        <option value="">Select Shift</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?= e($shift['id']) ?>"
                                <?= ($old['shift_id'] ?? '') === $shift['id'] ? 'selected' : ((int)($shift['is_default'] ?? 0) && empty($old['shift_id']) ? 'selected' : '') ?>>
                                <?= e($shift['name']) ?>
                                (<?= date('h:i A', strtotime($shift['time_in'])) ?> – <?= date('h:i A', strtotime($shift['time_out'])) ?>)
                                <?= (int)($shift['is_default'] ?? 0) ? ' ★' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">★ marks the default shift.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Credentials -->
    <div class="col-12">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-clock"></i> Attendance Credentials</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Employee PIN <span class="text-danger">*</span></label>
                    <input class="form-control" name="pin" type="password" maxlength="10" required placeholder="4-10 digits" value="<?= e($old['pin'] ?? '') ?>">
                    <small class="text-muted">Unique PIN for attendance</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">RFID Number (Optional)</label>
                    <input class="form-control" name="rfid_value" placeholder="RFID card number" value="<?= e($old['rfid_value'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Account Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="active" <?= ($old['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> A unique QR code will be automatically generated after saving the employee record.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="col-12">
        <div class="panel p-3">
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= url('employees') ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Save Employee</button>
            </div>
        </div>
    </div>
</form>

