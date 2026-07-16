<?php /** Admin: Create Manual Attendance Entry */ ?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-calendar-plus me-2"></i>Create Manual Attendance</h4>
        <small class="text-muted">Administrator / HR direct attendance entry</small>
    </div>
    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="post" action="<?= url('manual-attendance/create') ?>" id="manualForm">
            <?= csrf_field() ?>
            <div class="panel p-4">
                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">Employee & Date</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">— Select Employee —</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= e($emp['id']) ?>">
                                    <?= e($emp['employee_number']) ?> — <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Attendance Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="attendance_date"
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="attendance_status">
                            <option value="present">Present</option>
                            <option value="half_day">Half Day</option>
                            <option value="absent">Absent</option>
                            <option value="leave">Leave</option>
                            <option value="holiday">Holiday</option>
                        </select>
                    </div>
                </div>

                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">Time Records</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Time In</label>
                        <input type="time" class="form-control" name="time_in">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time Out</label>
                        <input type="time" class="form-control" name="time_out">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attendance Method</label>
                        <select class="form-select" name="method">
                            <option value="Manual Entry">Manual Entry</option>
                            <option value="PIN">PIN</option>
                            <option value="QR Code">QR Code</option>
                            <option value="RFID">RFID</option>
                            <option value="System Generated">System Generated</option>
                        </select>
                    </div>
                </div>

                <!-- Out-of-hours warning banner (shown by JS) -->
                <div class="alert alert-warning py-2 d-none" id="timeWarning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="timeWarningMsg">Time entered is outside the normal allowed window.</span>
                </div>

                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">Documentation</h6>
                <div class="mb-3">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="reason" rows="2" required
                              placeholder="Explain why this manual entry is needed…"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Administrator Remarks</label>
                    <textarea class="form-control" name="admin_remarks" rows="2"
                              placeholder="Internal notes (not shown to employee)…"></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Manual Attendance
                    </button>
                    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Warn if time_in is before 6AM or after 10PM
const timeInField  = document.querySelector('[name="time_in"]');
const timeWarning  = document.getElementById('timeWarning');
const timeWarnMsg  = document.getElementById('timeWarningMsg');
const ALLOWED_FROM = '06:00';
const ALLOWED_TO   = '22:00';

if (timeInField) {
    timeInField.addEventListener('change', () => {
        const val = timeInField.value;
        if (val && (val < ALLOWED_FROM || val > ALLOWED_TO)) {
            timeWarnMsg.textContent = `Time In (${val}) is outside the allowed window (${ALLOWED_FROM}–${ALLOWED_TO}). Verify before saving.`;
            timeWarning.classList.remove('d-none');
        } else {
            timeWarning.classList.add('d-none');
        }
    });
}

// Validate time_out > time_in before submit
document.getElementById('manualForm').addEventListener('submit', function(e) {
    const ti = document.querySelector('[name="time_in"]').value;
    const to = document.querySelector('[name="time_out"]').value;
    if (ti && to && to <= ti) {
        e.preventDefault();
        alert('Time Out must be after Time In.');
    }
});
</script>
