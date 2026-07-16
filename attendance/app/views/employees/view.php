<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Employee Profile</h1>
        <div class="text-muted"><?= e($employee['full_name']) ?> - <?= e($employee['employee_number']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('employees/edit?id=' . $employee['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="<?= url('employees/print-qr?id=' . $employee['id']) ?>" class="btn btn-outline-info"><i class="bi bi-qr-code"></i> Print QR</a>
        <a href="<?= url('employees') ?>" class="btn btn-outline-secondary"><i class="bi bi-list"></i> Back to List</a>
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

<!-- Employee Credentials Modal -->
<?php if (isset($_SESSION['employee_credentials'])): ?>
    <?php $credentials = $_SESSION['employee_credentials']; unset($_SESSION['employee_credentials']); ?>
    <div class="modal fade" id="credentialsModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Employee Account Created</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> Please copy these credentials before closing this modal. The temporary password will not be shown again.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialUsername" value="<?= e($credentials['username']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('credentialUsername')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialRole" value="<?= e($credentials['role']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('credentialRole')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Temporary Password:</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="credentialPassword" value="<?= e($credentials['password']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('credentialPassword')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <small class="text-muted mt-1 d-block">
                            <i class="bi bi-exclamation-triangle"></i> The user will be required to change this password upon first login.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.location.href='<?= url('employees/view?id=' . $credentials['employee_id']) ?>'">
                        <i class="bi bi-x-lg"></i> Close
                    </button>
                    <a href="<?= url('employees/view?id=' . $credentials['employee_id']) ?>" class="btn btn-primary">
                        <i class="bi bi-person"></i> View Employee Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('credentialsModal'));
            modal.show();
        });

        function copyToClipboard(elementId) {
            var copyText = document.getElementById(elementId);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value).then(function() {
                var button = copyText.nextElementSibling;
                var originalHTML = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-success');
                setTimeout(function() {
                    button.innerHTML = originalHTML;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-secondary');
                }, 2000);
            });
        }
    </script>
<?php endif; ?>

<div class="row g-3">
    <!-- Profile Card -->
    <div class="col-lg-4">
        <div class="panel p-3 text-center">
            <div class="mb-3">
                <?php if ($employee['photo']): ?>
                    <img src="<?= url('uploads/' . $employee['photo']) ?>" alt="<?= e($employee['full_name']) ?>" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                        <i class="bi bi-person text-white" style="font-size: 4rem;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h4 class="mb-1"><?= e($employee['full_name']) ?></h4>
            <div class="text-muted mb-3"><?= e($employee['position'] ?? 'No Position') ?></div>
            
            <div class="d-flex justify-content-center gap-2 mb-3">
                <?php
                $statusClass = match($employee['employment_status']) {
                    'Active' => 'text-bg-success',
                    'Inactive' => 'text-bg-secondary',
                    'Suspended' => 'text-bg-warning',
                    'Resigned' => 'text-bg-info',
                    'Terminated' => 'text-bg-danger',
                    'Retired' => 'text-bg-primary',
                    default => 'text-bg-secondary'
                };
                ?>
                <span class="badge status-badge <?= $statusClass ?> fs-6"><?= e($employee['employment_status']) ?></span>
            </div>
            
            <div class="text-start">
                <div class="mb-2">
                    <strong>Employee Number:</strong><br>
                    <span class="text-muted"><?= e($employee['employee_number']) ?></span>
                </div>
                <div class="mb-2">
                    <strong>Department:</strong><br>
                    <span class="text-muted"><?= e($employee['department_name'] ?? '-') ?></span>
                </div>
                <div class="mb-2">
                    <strong>Branch:</strong><br>
                    <span class="text-muted"><?= e($employee['branch_name'] ?? '-') ?></span>
                </div>
                <div class="mb-2">
                    <strong>Shift:</strong><br>
                    <span class="text-muted"><?= e($employee['shift_name'] ?? '-') ?></span>
                </div>
                <div class="mb-2">
                    <strong>Date Hired:</strong><br>
                    <span class="text-muted"><?= e($employee['date_hired'] ?? '-') ?></span>
                </div>
                <div class="mb-2">
                    <strong>Employment Type:</strong><br>
                    <span class="text-muted"><?= e($employee['employment_type'] ?? '-') ?></span>
                </div>
            </div>
            
            <hr>
            
            <div class="d-grid gap-2">
                <a href="<?= url('employees/edit?id=' . $employee['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Profile</a>
                <?php if ($employee['status'] === 'active'): ?>
                    <form method="post" action="<?= url('employees/deactivate') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($employee['id']) ?>">
                        <button class="btn btn-warning" data-confirm="Deactivate this employee?"><i class="bi bi-person-x"></i> Deactivate</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= url('employees/activate') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($employee['id']) ?>">
                        <button class="btn btn-success" data-confirm="Activate this employee?"><i class="bi bi-person-check"></i> Activate</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- QR Code Card -->
        <div class="panel p-3 mt-3">
            <h5 class="mb-3"><i class="bi bi-qr-code"></i> Attendance QR Code</h5>
            <div class="text-center mb-3">
                <div class="bg-light d-inline-flex align-items-center justify-content-center p-3 rounded" style="width: 200px; height: 200px;">
                    <div class="text-muted">
                        <i class="bi bi-qr-code" style="font-size: 3rem;"></i>
                        <div class="small mt-2">QR Code</div>
                    </div>
                </div>
            </div>
            <div class="mb-2">
                <strong>QR Value:</strong>
                <code class="d-block mt-1 p-2 bg-light rounded"><?= e($employee['qr_code_value'] ?? 'Not Generated') ?></code>
            </div>
            <div class="d-grid gap-2">
                <a href="<?= url('employees/print-qr?id=' . $employee['id']) ?>" class="btn btn-outline-info"><i class="bi bi-printer"></i> Print QR Code</a>
                <form method="post" action="<?= url('employees/regenerate-qr') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($employee['id']) ?>">
                    <button class="btn btn-outline-warning" data-confirm="Regenerate QR code? Old QR will become invalid."><i class="bi bi-arrow-clockwise"></i> Regenerate QR</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Details & Timeline -->
    <div class="col-lg-8">
        <!-- Personal Information -->
        <div class="panel p-3 mb-3">
            <h5 class="mb-3"><i class="bi bi-person"></i> Personal Information</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <strong>First Name:</strong><br>
                    <span class="text-muted"><?= e($employee['first_name']) ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Middle Name:</strong><br>
                    <span class="text-muted"><?= e($employee['middle_name'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Last Name:</strong><br>
                    <span class="text-muted"><?= e($employee['last_name']) ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Suffix:</strong><br>
                    <span class="text-muted"><?= e($employee['suffix'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Gender:</strong><br>
                    <span class="text-muted"><?= e($employee['gender'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Date of Birth:</strong><br>
                    <span class="text-muted"><?= e($employee['date_of_birth'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Civil Status:</strong><br>
                    <span class="text-muted"><?= e($employee['civil_status'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Nationality:</strong><br>
                    <span class="text-muted"><?= e($employee['nationality'] ?? '-') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="panel p-3 mb-3">
            <h5 class="mb-3"><i class="bi bi-telephone"></i> Contact Information</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <strong>Mobile Number:</strong><br>
                    <span class="text-muted"><?= e($employee['contact_number'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Alternate Mobile:</strong><br>
                    <span class="text-muted"><?= e($employee['alternate_mobile'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Email:</strong><br>
                    <span class="text-muted"><?= e($employee['email'] ?? '-') ?></span>
                </div>
                <div class="col-12">
                    <strong>Home Address:</strong><br>
                    <span class="text-muted"><?= e($employee['home_address'] ?? '-') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Emergency Contact -->
        <div class="panel p-3 mb-3">
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Emergency Contact</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <strong>Contact Name:</strong><br>
                    <span class="text-muted"><?= e($employee['emergency_contact_name'] ?? '-') ?></span>
                </div>
                <div class="col-md-4">
                    <strong>Contact Number:</strong><br>
                    <span class="text-muted"><?= e($employee['emergency_contact_number'] ?? '-') ?></span>
                </div>
                <div class="col-md-4">
                    <strong>Relationship:</strong><br>
                    <span class="text-muted"><?= e($employee['emergency_contact_relationship'] ?? '-') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Employment Details -->
        <div class="panel p-3 mb-3">
            <h5 class="mb-3"><i class="bi bi-briefcase"></i> Employment Details</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <strong>Department:</strong><br>
                    <span class="text-muted"><?= e($employee['department_name'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Branch:</strong><br>
                    <span class="text-muted"><?= e($employee['branch_name'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Position:</strong><br>
                    <span class="text-muted"><?= e($employee['position'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Shift:</strong><br>
                    <span class="text-muted"><?= e($employee['shift_name'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Employment Status:</strong><br>
                    <span class="text-muted"><?= e($employee['employment_status']) ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Employment Type:</strong><br>
                    <span class="text-muted"><?= e($employee['employment_type'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Date Hired:</strong><br>
                    <span class="text-muted"><?= e($employee['date_hired'] ?? '-') ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Immediate Supervisor:</strong><br>
                    <span class="text-muted"><?= e($employee['supervisor_name'] ?? '-') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Attendance Credentials -->
        <div class="panel p-3 mb-3">
            <h5 class="mb-3"><i class="bi bi-clock"></i> Attendance Credentials</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <strong>PIN:</strong><br>
                    <span class="text-muted">••••••••</span>
                </div>
                <div class="col-md-4">
                    <strong>RFID Value:</strong><br>
                    <span class="text-muted"><?= e($employee['rfid_value'] ?? '-') ?></span>
                </div>
                <div class="col-md-4">
                    <strong>Account Status:</strong><br>
                    <?php
                    $accountStatusClass = ($employee['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary';
                    ?>
                    <span class="badge status-badge <?= $accountStatusClass ?>"><?= e($employee['status'] ?? '-') ?></span>
                </div>
                <div class="col-12">
                    <strong>QR Code Value:</strong><br>
                    <code class="d-block mt-1 p-2 bg-light rounded"><?= e($employee['qr_code_value'] ?? 'Not Generated') ?></code>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-clock-history"></i> Activity Timeline</h5>
            <?php if ($timeline): ?>
                <div class="timeline">
                    <?php foreach ($timeline as $entry): ?>
                        <div class="timeline-item mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($entry['description'] ?? ucfirst(str_replace('_', ' ', $entry['event_type']))) ?></strong>
                                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($entry['created_at'])) ?></small>
                            </div>
                            <div class="text-muted small">
                                Event: <?= e($entry['event_type']) ?>
                                <?php if ($entry['created_by_username']): ?>
                                    <br>By: <?= e($entry['created_by_username']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-muted">No activity recorded yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.timeline-item:last-child {
    border-bottom: none !important;
}
</style>
