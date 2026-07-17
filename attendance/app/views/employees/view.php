<?php
/**
 * Employee Profile View
 * Route: GET /employees/show?id={uuid}
 */
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Employee Profile</h1>
        <div class="text-muted"><?= e($employee['full_name']) ?> &mdash; <?= e($employee['employee_number']) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('employees/edit?id=' . $employee['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="<?= url('employees/print-qr?id=' . $employee['id']) ?>" class="btn btn-outline-info"><i class="bi bi-qr-code"></i> Print QR</a>
        <a href="<?= url('employees') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
    </div>
</div>

<?php /* ── Credentials modal (shown once after employee creation) ───────────── */ ?>
<?php if (isset($_SESSION['employee_credentials'])): ?>
    <?php $credentials = $_SESSION['employee_credentials']; unset($_SESSION['employee_credentials']); ?>
    <div class="modal fade" id="credentialsModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="credentialsModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="credentialsModalLabel"><i class="bi bi-check-circle"></i> Employee Account Created</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> Copy these credentials before closing. The temporary password will not be shown again.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialUsername" value="<?= e($credentials['username']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyField('credentialUsername',this)"><i class="bi bi-clipboard"></i> Copy</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialRole" value="<?= e($credentials['role']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyField('credentialRole',this)"><i class="bi bi-clipboard"></i> Copy</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Temporary Password</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="credentialPassword" value="<?= e($credentials['password']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyField('credentialPassword',this)"><i class="bi bi-clipboard"></i> Copy</button>
                        </div>
                        <small class="text-muted mt-1 d-block"><i class="bi bi-exclamation-triangle"></i> The user will be required to change this password on first login.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Close</button>
                    <a href="<?= url('employees/show?id=' . e($credentials['employee_id'])) ?>" class="btn btn-primary"><i class="bi bi-person"></i> View Employee Profile</a>
                </div>
            </div>
        </div>
    </div>
    <script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('credentialsModal')).show();});</script>
<?php endif; ?>

<div class="row g-3">
    <?php /* ── Left column: photo card + QR card ─────────────────────────────── */ ?>
    <div class="col-lg-4">

        <?php /* Profile card */ ?>
        <div class="panel p-3 text-center mb-3">
            <div class="mb-3">
                <?php if (!empty($employee['photo'])): ?>
                    <img src="<?= url('uploads/' . e($employee['photo'])) ?>"
                         alt="<?= e($employee['full_name']) ?>"
                         class="rounded-circle border"
                         style="width:150px;height:150px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto"
                         style="width:150px;height:150px;">
                        <i class="bi bi-person text-white" style="font-size:4rem;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <h4 class="mb-1"><?= e($employee['full_name']) ?></h4>
            <div class="text-muted mb-2"><?= e($employee['position'] ?? 'No Position') ?></div>

            <?php
            $empStatusClass = match($employee['employment_status'] ?? '') {
                'Active'     => 'text-bg-success',
                'Inactive'   => 'text-bg-secondary',
                'Suspended'  => 'text-bg-warning',
                'Resigned'   => 'text-bg-info',
                'Terminated' => 'text-bg-danger',
                'Retired'    => 'text-bg-primary',
                default      => 'text-bg-secondary',
            };
            ?>
            <span class="badge <?= $empStatusClass ?> fs-6 mb-3"><?= e($employee['employment_status'] ?? '-') ?></span>

            <hr>
            <dl class="text-start row row-cols-1 g-2 mb-3">
                <div class="col"><dt class="small text-muted">Employee No.</dt><dd><?= e($employee['employee_number']) ?></dd></div>
                <div class="col"><dt class="small text-muted">Department</dt><dd><?= e($employee['department_name'] ?? '-') ?></dd></div>
                <div class="col"><dt class="small text-muted">Branch</dt><dd><?= e($employee['branch_name'] ?? '-') ?></dd></div>
                <div class="col"><dt class="small text-muted">Shift</dt><dd><?= e($employee['shift_name'] ?? '-') ?></dd></div>
                <div class="col"><dt class="small text-muted">Date Hired</dt><dd><?= e($employee['date_hired'] ?? '-') ?></dd></div>
                <div class="col"><dt class="small text-muted">Employment Type</dt><dd><?= e($employee['employment_type'] ?? '-') ?></dd></div>
            </dl>

            <div class="d-grid gap-2">
                <a href="<?= url('employees/edit?id=' . $employee['id']) ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit Profile
                </a>
                <?php if (($employee['status'] ?? '') === 'active'): ?>
                    <form method="post" action="<?= url('employees/deactivate') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($employee['id']) ?>">
                        <button class="btn btn-warning btn-sm w-100" data-confirm="Deactivate this employee?">
                            <i class="bi bi-person-x"></i> Deactivate Account
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= url('employees/activate') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($employee['id']) ?>">
                        <button class="btn btn-success btn-sm w-100" data-confirm="Activate this employee?">
                            <i class="bi bi-person-check"></i> Activate Account
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php /* QR Code card */ ?>
        <div class="panel p-3 mb-3">
            <h6 class="mb-3"><i class="bi bi-qr-code"></i> Attendance QR Code</h6>
            <div class="text-center mb-3">
                <div class="bg-light d-inline-flex align-items-center justify-content-center p-3 rounded" style="width:180px;height:180px;">
                    <div class="text-muted text-center">
                        <i class="bi bi-qr-code" style="font-size:3rem;"></i>
                        <div class="small mt-1">QR Code</div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block mb-1">QR Value</small>
                <code class="d-block p-2 bg-light rounded small"><?= e($employee['qr_code_value'] ?? 'Not Generated') ?></code>
            </div>
            <div class="d-grid gap-2">
                <a href="<?= url('employees/print-qr?id=' . $employee['id']) ?>" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-printer"></i> Print QR Code
                </a>
                <form method="post" action="<?= url('employees/regenerate-qr') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($employee['id']) ?>">
                    <button class="btn btn-outline-warning btn-sm w-100" data-confirm="Regenerate QR code? The old QR will become invalid.">
                        <i class="bi bi-arrow-clockwise"></i> Regenerate QR
                    </button>
                </form>
            </div>
        </div>

    </div><!-- /col-lg-4 -->

    <?php /* ── Right column: all detail panels ─────────────────────────────── */ ?>
    <div class="col-lg-8">

        <?php /* ── Attendance Summary Cards ──────────────────────────────────── */ ?>
        <div class="row row-cols-2 row-cols-md-4 g-2 mb-3">
            <div class="col">
                <div class="panel p-3 text-center">
                    <div class="fs-2 fw-bold text-success"><?= (int) ($attendance['present'] ?? 0) ?></div>
                    <div class="small text-muted"><i class="bi bi-check-circle"></i> Present</div>
                </div>
            </div>
            <div class="col">
                <div class="panel p-3 text-center">
                    <div class="fs-2 fw-bold text-warning"><?= (int) ($attendance['late'] ?? 0) ?></div>
                    <div class="small text-muted"><i class="bi bi-clock-history"></i> Late</div>
                </div>
            </div>
            <div class="col">
                <div class="panel p-3 text-center">
                    <div class="fs-2 fw-bold text-danger"><?= (int) ($attendance['absent'] ?? 0) ?></div>
                    <div class="small text-muted"><i class="bi bi-x-circle"></i> Absent</div>
                </div>
            </div>
            <div class="col">
                <div class="panel p-3 text-center">
                    <div class="fs-2 fw-bold text-primary"><?= number_format((float) ($attendance['overtime_hours'] ?? 0), 1) ?>h</div>
                    <div class="small text-muted"><i class="bi bi-lightning"></i> Overtime</div>
                    <?php if (($attendance['overtime_days'] ?? 0) > 0): ?>
                        <div class="text-muted" style="font-size:.72rem;"><?= (int) $attendance['overtime_days'] ?> day<?= $attendance['overtime_days'] !== 1 ? 's' : '' ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php /* ── Personal Information ─────────────────────────────────────── */ ?>
        <div class="panel p-3 mb-3">
            <h6 class="mb-3"><i class="bi bi-person"></i> Personal Information</h6>
            <div class="row g-3">
                <div class="col-sm-6"><dt class="small text-muted">First Name</dt><dd><?= e($employee['first_name']) ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Middle Name</dt><dd><?= e($employee['middle_name'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Last Name</dt><dd><?= e($employee['last_name']) ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Suffix</dt><dd><?= e($employee['suffix'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Gender</dt><dd><?= e($employee['gender'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Date of Birth</dt><dd><?= e($employee['date_of_birth'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Civil Status</dt><dd><?= e($employee['civil_status'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Nationality</dt><dd><?= e($employee['nationality'] ?? '-') ?></dd></div>
            </div>
        </div>

        <?php /* ── Employment Information ───────────────────────────────────── */ ?>
        <div class="panel p-3 mb-3">
            <h6 class="mb-3"><i class="bi bi-briefcase"></i> Employment Information</h6>
            <div class="row g-3">
                <div class="col-sm-6"><dt class="small text-muted">Branch</dt><dd><?= e($employee['branch_name'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Department</dt><dd><?= e($employee['department_name'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Position</dt><dd><?= e($employee['position'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Shift</dt><dd><?= e($employee['shift_name'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Employment Status</dt><dd><?= e($employee['employment_status'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Employment Type</dt><dd><?= e($employee['employment_type'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Date Hired</dt><dd><?= e($employee['date_hired'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Immediate Supervisor</dt><dd><?= e($employee['supervisor_name'] ?? '-') ?></dd></div>
            </div>
        </div>

        <?php /* ── Contact Information ──────────────────────────────────────── */ ?>
        <div class="panel p-3 mb-3">
            <h6 class="mb-3"><i class="bi bi-telephone"></i> Contact Information</h6>
            <div class="row g-3">
                <div class="col-sm-6"><dt class="small text-muted">Email Address</dt><dd><?= e($employee['email'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Contact Number</dt><dd><?= e($employee['contact_number'] ?? '-') ?></dd></div>
                <div class="col-sm-6"><dt class="small text-muted">Alternate Contact Number</dt><dd><?= e($employee['alternate_mobile'] ?? '-') ?></dd></div>
                <div class="col-12"><dt class="small text-muted">Home Address</dt><dd><?= e($employee['home_address'] ?? '-') ?></dd></div>
                <div class="col-sm-4"><dt class="small text-muted">Emergency Contact</dt><dd><?= e($employee['emergency_contact_name'] ?? '-') ?></dd></div>
                <div class="col-sm-4"><dt class="small text-muted">Emergency Contact Number</dt><dd><?= e($employee['emergency_contact_number'] ?? '-') ?></dd></div>
                <div class="col-sm-4"><dt class="small text-muted">Relationship</dt><dd><?= e($employee['emergency_contact_relationship'] ?? '-') ?></dd></div>
            </div>
        </div>

        <?php /* ── Account Information ─────────────────────────────────────── */ ?>
        <div class="panel p-3 mb-3">
            <h6 class="mb-3"><i class="bi bi-shield-lock"></i> Account Information</h6>
            <div class="row g-3">
                <div class="col-sm-6">
                    <dt class="small text-muted">Username</dt>
                    <dd><?= e($employee['user_username'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Assigned Role</dt>
                    <dd><?= e($employee['user_role_name'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Account Status</dt>
                    <dd>
                        <?php $acctClass = ($employee['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>
                        <span class="badge <?= $acctClass ?>"><?= e(ucfirst($employee['status'] ?? '-')) ?></span>
                    </dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">RFID Value</dt>
                    <dd><?= e($employee['rfid_value'] ?? '-') ?></dd>
                </div>
                <div class="col-12">
                    <dt class="small text-muted">QR Code Value</dt>
                    <dd><code class="p-1 bg-light rounded"><?= e($employee['qr_code_value'] ?? 'Not Generated') ?></code></dd>
                </div>
            </div>
        </div>

        <?php /* ── Employee Timeline ────────────────────────────────────────── */ ?>
        <div class="panel p-3">
            <h6 class="mb-3"><i class="bi bi-clock-history"></i> Employee Timeline</h6>
            <?php if ($timeline): ?>
                <div class="timeline">
                    <?php
                    $iconMap = [
                        'employee_created'        => 'bi-person-plus text-success',
                        'employee_updated'        => 'bi-pencil text-primary',
                        'employee_activated'      => 'bi-person-check text-success',
                        'employee_deactivated'    => 'bi-person-x text-warning',
                        'account_created'         => 'bi-key text-success',
                        'account_updated'         => 'bi-key text-primary',
                        'account_deleted'         => 'bi-key text-danger',
                        'role_changed'            => 'bi-shield text-info',
                        'password_changed'        => 'bi-lock text-warning',
                        'profile_picture_updated' => 'bi-image text-info',
                        'department_changed'      => 'bi-diagram-3 text-info',
                        'branch_changed'          => 'bi-building text-info',
                        'shift_changed'           => 'bi-clock text-secondary',
                        'position_changed'        => 'bi-briefcase text-secondary',
                        'status_changed'          => 'bi-toggle-on text-warning',
                        'photo_updated'           => 'bi-camera text-info',
                        'qr_code_regenerated'     => 'bi-qr-code text-secondary',
                        'imported'                => 'bi-upload text-primary',
                    ];
                    foreach ($timeline as $entry):
                        $icon = $iconMap[$entry['event_type']] ?? 'bi-circle text-muted';
                    ?>
                        <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0 pt-1">
                                <i class="bi <?= $icon ?> fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between flex-wrap gap-1">
                                    <strong class="small"><?= e($entry['description'] ?? ucwords(str_replace('_', ' ', $entry['event_type']))) ?></strong>
                                    <small class="text-muted"><?= date('M j, Y g:i A', strtotime($entry['created_at'])) ?></small>
                                </div>
                                <?php if (!empty($entry['created_by_username'])): ?>
                                    <div class="text-muted small">By <?= e($entry['created_by_username']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-muted small">No activity recorded yet.</div>
            <?php endif; ?>
        </div>

    </div><!-- /col-lg-8 -->
</div><!-- /row -->

<script>
function copyField(id, btn) {
    var el = document.getElementById(id);
    navigator.clipboard.writeText(el.value).then(function () {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        btn.classList.replace('btn-outline-secondary', 'btn-success');
        setTimeout(function () {
            btn.innerHTML = orig;
            btn.classList.replace('btn-success', 'btn-outline-secondary');
        }, 2000);
    });
}
</script>
