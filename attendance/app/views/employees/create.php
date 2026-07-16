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

<form method="post" action="<?= url('employees') ?>" enctype="multipart/form-data" class="row g-3">
    <?= csrf_field() ?>

    <!-- Personal Information -->
    <div class="col-12">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-person"></i> Personal Information</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Profile Photo</label>
                    <div class="text-center">
                        <div id="photoPreview" class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 120px; height: 120px;">
                            <i class="bi bi-person text-white fs-1"></i>
                        </div>
                        <input type="file" class="form-control form-control-sm" name="photo" accept="image/jpeg,image/jpg,image/png" id="photoInput">
                        <small class="text-muted">JPG or PNG, max 2MB</small>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Employee Number <span class="text-danger">*</span></label>
                            <input class="form-control" name="employee_number" required placeholder="EMP-001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input class="form-control" name="middle_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Suffix</label>
                            <select class="form-select" name="suffix">
                                <option value="">None</option>
                                <option>Jr.</option>
                                <option>Sr.</option>
                                <option>II</option>
                                <option>III</option>
                                <option>IV</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Birth</label>
                            <input class="form-control" type="date" name="date_of_birth">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Civil Status</label>
                            <select class="form-select" name="civil_status">
                                <option value="">Select</option>
                                <option>Single</option>
                                <option>Married</option>
                                <option>Widowed</option>
                                <option>Separated</option>
                                <option>Divorced</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nationality</label>
                            <input class="form-control" name="nationality" placeholder="e.g., Filipino">
                        </div>
                    </div>
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
                    <input class="form-control" name="contact_number" required placeholder="+63 912 345 6789">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Alternate Mobile</label>
                    <input class="form-control" name="alternate_mobile" placeholder="+63 912 345 6789">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input class="form-control" type="email" name="email" required placeholder="employee@example.com">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" placeholder="For system login">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" placeholder="For system login">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Home Address</label>
                    <textarea class="form-control" name="home_address" rows="2"></textarea>
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
                    <input class="form-control" name="emergency_contact_name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                    <input class="form-control" name="emergency_contact_number" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Relationship <span class="text-danger">*</span></label>
                    <input class="form-control" name="emergency_contact_relationship" required placeholder="e.g., Spouse, Parent">
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
                            <option value="<?= e($dept['id']) ?>"><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" name="branch_id" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e($branch['id']) ?>"><?= e($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Position <span class="text-danger">*</span></label>
                    <input class="form-control" name="position" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Resigned">Resigned</option>
                        <option value="Terminated">Terminated</option>
                        <option value="Retired">Retired</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_type" required>
                        <option value="Regular">Regular</option>
                        <option value="Probationary">Probationary</option>
                        <option value="Contractual">Contractual</option>
                        <option value="Part-Time">Part-Time</option>
                        <option value="Temporary">Temporary</option>
                        <option value="Intern">Intern</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Hired <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" name="date_hired" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Immediate Supervisor</label>
                    <select class="form-select" name="immediate_supervisor_id">
                        <option value="">None</option>
                        <?php foreach ($supervisors as $sup): ?>
                            <option value="<?= e($sup['id']) ?>"><?= e($sup['full_name']) ?> (<?= e($sup['employee_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Assigned Shift <span class="text-danger">*</span></label>
                    <select class="form-select" name="shift_id" required>
                        <option value="">Select Shift</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?= e($shift['id']) ?>"><?= e($shift['name']) ?> (<?= e($shift['time_in']) ?> - <?= e($shift['time_out']) ?>)</option>
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
                    <label class="form-label">Employee PIN <span class="text-danger">*</span></label>
                    <input class="form-control" name="pin" type="password" maxlength="10" required placeholder="4-10 digits">
                    <small class="text-muted">Unique PIN for attendance</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">RFID Number (Optional)</label>
                    <input class="form-control" name="rfid_value" placeholder="RFID card number">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Account Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Photo preview
    const photoInput = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');
    
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                alert('Photo must not exceed 2MB.');
                photoInput.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">`;
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
