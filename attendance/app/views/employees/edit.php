<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Edit Employee</h1>
        <div class="text-muted">Update employee profile information.</div>
    </div>
    <div>
        <a href="<?= url('employees/show?id=' . $employee['id']) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Profile</a>
    </div>
</div>

<?php if (flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e(flash('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('employees/update') ?>" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($employee['id']) ?>">

    <!-- Personal Information -->
    <div class="col-12">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-person"></i> Personal Information</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Employee Number <span class="text-danger">*</span></label>
                    <input class="form-control" name="employee_number" value="<?= e($employee['employee_number']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="first_name" value="<?= e($employee['first_name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Middle Name</label>
                    <input class="form-control" name="middle_name" value="<?= e($employee['middle_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="last_name" value="<?= e($employee['last_name']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Suffix</label>
                    <select class="form-select" name="suffix">
                        <option value="">None</option>
                        <option value="Jr." <?= ($employee['suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                        <option value="Sr." <?= ($employee['suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                        <option value="II" <?= ($employee['suffix'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
                        <option value="III" <?= ($employee['suffix'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
                        <option value="IV" <?= ($employee['suffix'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select</option>
                        <option value="Male" <?= ($employee['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($employee['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($employee['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of Birth</label>
                    <input class="form-control" type="date" name="date_of_birth" value="<?= e($employee['date_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Civil Status</label>
                    <select class="form-select" name="civil_status">
                        <option value="">Select</option>
                        <option value="Single" <?= ($employee['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                        <option value="Married" <?= ($employee['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                        <option value="Widowed" <?= ($employee['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                        <option value="Separated" <?= ($employee['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                        <option value="Divorced" <?= ($employee['civil_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nationality</label>
                    <input class="form-control" name="nationality" value="<?= e($employee['nationality'] ?? '') ?>">
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
                    <input class="form-control" name="contact_number" value="<?= e($employee['contact_number'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Alternate Mobile</label>
                    <input class="form-control" name="alternate_mobile" value="<?= e($employee['alternate_mobile'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input class="form-control" type="email" name="email" value="<?= e($employee['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" value="<?= e($employee['username'] ?? '') ?>" placeholder="For system login">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role_id">
                        <option value="">No System Access</option>
                        <?php if (empty($roles)): ?>
                            <option value="" disabled>No roles configured</option>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <?php 
                                $canAssign = $current_user_role === 'administrator' || !in_array($role['name'], ['Administrator', 'HR'], true);
                                $selected = isset($employee['user_role_id']) && $employee['user_role_id'] == $role['id'] ? 'selected' : '';
                                ?>
                                <option value="<?= e($role['id']) ?>" <?= $selected ?> <?= !$canAssign ? 'disabled' : '' ?>>
                                    <?= e($role['name']) ?>
                                    <?= !$canAssign ? ' (Only Admin)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if ($current_user_role !== 'administrator'): ?>
                        <small class="text-muted">Only administrators can assign Administrator or HR roles</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Home Address</label>
                    <textarea class="form-control" name="home_address" rows="2"><?= e($employee['home_address'] ?? '') ?></textarea>
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
                    <input class="form-control" name="emergency_contact_name" value="<?= e($employee['emergency_contact_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                    <input class="form-control" name="emergency_contact_number" value="<?= e($employee['emergency_contact_number'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Relationship <span class="text-danger">*</span></label>
                    <input class="form-control" name="emergency_contact_relationship" value="<?= e($employee['emergency_contact_relationship'] ?? '') ?>" required>
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
                            <option value="<?= e($dept['id']) ?>" <?= ($employee['department_id'] ?? '') === $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" name="branch_id" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e($branch['id']) ?>" <?= ($employee['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Position <span class="text-danger">*</span></label>
                    <input class="form-control" name="position" value="<?= e($employee['position'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_status" required>
                        <option value="Active" <?= ($employee['employment_status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($employee['employment_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="Suspended" <?= ($employee['employment_status'] ?? '') === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                        <option value="Resigned" <?= ($employee['employment_status'] ?? '') === 'Resigned' ? 'selected' : '' ?>>Resigned</option>
                        <option value="Terminated" <?= ($employee['employment_status'] ?? '') === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
                        <option value="Retired" <?= ($employee['employment_status'] ?? '') === 'Retired' ? 'selected' : '' ?>>Retired</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_type" required>
                        <option value="Regular" <?= ($employee['employment_type'] ?? '') === 'Regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="Probationary" <?= ($employee['employment_type'] ?? '') === 'Probationary' ? 'selected' : '' ?>>Probationary</option>
                        <option value="Contractual" <?= ($employee['employment_type'] ?? '') === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                        <option value="Part-Time" <?= ($employee['employment_type'] ?? '') === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                        <option value="Temporary" <?= ($employee['employment_type'] ?? '') === 'Temporary' ? 'selected' : '' ?>>Temporary</option>
                        <option value="Intern" <?= ($employee['employment_type'] ?? '') === 'Intern' ? 'selected' : '' ?>>Intern</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Hired <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" name="date_hired" value="<?= e($employee['date_hired'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Immediate Supervisor</label>
                    <select class="form-select" name="immediate_supervisor_id">
                        <option value="">None</option>
                        <?php foreach ($supervisors as $sup): ?>
                            <option value="<?= e($sup['id']) ?>" <?= ($employee['immediate_supervisor_id'] ?? '') === $sup['id'] ? 'selected' : '' ?>><?= e($sup['full_name']) ?> (<?= e($sup['employee_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Assigned Shift <span class="text-danger">*</span></label>
                    <select class="form-select" name="shift_id" required>
                        <option value="">Select Shift</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?= e($shift['id']) ?>" <?= ($employee['shift_id'] ?? '') === $shift['id'] ? 'selected' : '' ?>><?= e($shift['name']) ?> (<?= e($shift['time_in']) ?> - <?= e($shift['time_out']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
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
                    <label class="form-label">Employee PIN</label>
                    <input class="form-control" name="pin" type="password" maxlength="10" placeholder="Leave blank to keep current">
                    <small class="text-muted">Leave blank to keep current PIN</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">RFID Number</label>
                    <input class="form-control" name="rfid_value" value="<?= e($employee['rfid_value'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Account Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="active" <?= ($employee['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($employee['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">QR Code Value</label>
                    <input class="form-control" value="<?= e($employee['qr_code_value'] ?? '') ?>" readonly>
                    <small class="text-muted">Auto-generated, unique</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="col-12">
        <div class="panel p-3">
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= url('employees/show?id=' . $employee['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Update Employee</button>
            </div>
        </div>
    </div>
</form>

