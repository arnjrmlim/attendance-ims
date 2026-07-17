<?php
/**
 * Admin: Create Manual Attendance — standalone page (all 6 types)
 * Clean neutral design. Submits via AJAX; no full-page reload.
 * Route: GET /manual-attendance/create
 */
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-semibold mb-0">Create Manual Attendance</h4>
        <small class="text-muted">Administrator / HR direct attendance entry</small>
    </div>
    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<!-- Inline feedback -->
<div id="formAlert" class="d-none mb-3"></div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="panel p-4">

            <!-- ── Employee & Date ──────────────────────────────── -->
            <p class="text-uppercase text-muted fw-semibold mb-3"
               style="font-size:.7rem;letter-spacing:.07em;">Employee &amp; Date</p>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Employee <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="cEmployee" required>
                        <option value="">— Select Employee —</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= e($emp['id']) ?>">
                                <?= e($emp['employee_number']) ?> — <?= e($emp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" id="cDate"
                           value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Day Status</label>
                    <select class="form-select form-select-sm" id="cStatus">
                        <option value="present">Present</option>
                        <option value="half_day">Half Day</option>
                        <option value="absent">Absent</option>
                        <option value="leave">Leave</option>
                        <option value="holiday">Holiday</option>
                    </select>
                </div>
            </div>

            <!-- ── Time Records ─────────────────────────────────── -->
            <div class="d-flex align-items-baseline gap-3 mb-1">
                <p class="text-uppercase text-muted fw-semibold mb-0"
                   style="font-size:.7rem;letter-spacing:.07em;">Time Records</p>
                <small class="text-muted">Fill only the fields that apply</small>
            </div>
            <p class="text-muted small mb-3">
                Multiple entries of the same type are accepted.
                The engine keeps the <em>earliest</em> tap for Time In, Break Out, and OT In,
                and the <em>latest</em> tap for Break In, Time Out, and OT Out.
            </p>

            <div class="row g-3 mb-2">
                <?php
                $timeFields = [
                    ['cTimeIn',   'Time In',    'time_in',     'Earliest tap used'],
                    ['cBreakOut', 'Break Out',  'break_out',   'Earliest tap used'],
                    ['cBreakIn',  'Break In',   'break_in',    'Latest tap used'],
                    ['cTimeOut',  'Time Out',   'time_out',    'Latest tap used'],
                    ['cOtIn',     'OT In',      'overtime_in', 'Earliest tap used'],
                    ['cOtOut',    'OT Out',     'overtime_out','Latest tap used'],
                ];
                foreach ($timeFields as [$fieldId, $fieldLabel, , $hint]):
                ?>
                <div class="col-md-4">
                    <label class="form-label small mb-1">
                        <?= $fieldLabel ?>
                        <span class="text-muted fw-normal">— <?= $hint ?></span>
                    </label>
                    <input type="time" class="form-control form-control-sm"
                           id="<?= $fieldId ?>" data-label="<?= $fieldLabel ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Sequence error / out-of-hours warning -->
            <div class="text-danger small d-none mb-2" id="seqError"></div>
            <div class="alert alert-warning py-2 d-none mb-3" id="timeWarning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <span id="timeWarningMsg"></span>
            </div>

            <!-- ── Method ────────────────────────────────────────── -->
            <div class="mb-4">
                <label class="form-label">Method</label>
                <select class="form-select form-select-sm" id="cMethod" style="max-width:260px">
                    <option value="Manual Entry">Manual Entry</option>
                    <option value="PIN">PIN</option>
                    <option value="QR Code">QR Code</option>
                    <option value="RFID">RFID</option>
                    <option value="System Generated">System Generated</option>
                </select>
            </div>

            <!-- ── Documentation ─────────────────────────────────── -->
            <p class="text-uppercase text-muted fw-semibold mb-3"
               style="font-size:.7rem;letter-spacing:.07em;">Documentation</p>

            <div class="mb-3">
                <label class="form-label">
                    Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control form-control-sm" id="cReason" rows="2" required
                          placeholder="Explain why this manual entry is needed…"></textarea>
            </div>
            <div class="mb-4">
                <label class="form-label">
                    Administrator Remarks
                    <span class="text-muted fw-normal">(internal — not shown to employee)</span>
                </label>
                <textarea class="form-control form-control-sm" id="cAdminRemarks" rows="2"></textarea>
            </div>

            <!-- ── Actions ──────────────────────────────────────── -->
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button class="btn btn-primary btn-sm" id="btnSave">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="saveSpinner"></span>
                    <i class="bi bi-save me-1"></i>Save Manual Attendance
                </button>
                <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary btn-sm">
                    Cancel
                </a>
                <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary btn-sm d-none" id="btnViewList">
                    <i class="bi bi-list-ul me-1"></i>View All Records
                </a>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    const CSRF        = <?= json_encode(csrf_token()) ?>;
    const API_URL     = <?= json_encode(url('manual-attendance/api/store')) ?>;
    const ALLOWED_FROM = '06:00', ALLOWED_TO = '22:00';

    const $   = id => document.getElementById(id);
    const fld = id => $(id)?.value.trim() ?? '';

    /* ── Inline alert ────────────────────────────────────────── */
    function showAlert(msg, type) {
        const el = $('formAlert');
        el.className = `alert alert-${type} alert-dismissible fade show`;
        el.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>`
                     + msg
                     + `<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        el.classList.remove('d-none');
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* ── Out-of-hours warning ────────────────────────────────── */
    $('cTimeIn').addEventListener('change', () => {
        const v = fld('cTimeIn');
        if (v && (v < ALLOWED_FROM || v > ALLOWED_TO)) {
            $('timeWarningMsg').textContent =
                `Time In (${v}) is outside the normal window (${ALLOWED_FROM}–${ALLOWED_TO}).`;
            $('timeWarning').classList.remove('d-none');
        } else {
            $('timeWarning').classList.add('d-none');
        }
    });

    /* ── Sequence validation ─────────────────────────────────── */
    function validateSequence() {
        const slots = [
            ['Time In',   fld('cTimeIn')],
            ['Break Out', fld('cBreakOut')],
            ['Break In',  fld('cBreakIn')],
            ['Time Out',  fld('cTimeOut')],
            ['OT In',     fld('cOtIn')],
            ['OT Out',    fld('cOtOut')],
        ].filter(([, v]) => v !== '');

        for (let i = 1; i < slots.length; i++) {
            if (slots[i][1] < slots[i - 1][1]) {
                return `${slots[i][0]} must be after ${slots[i - 1][0]}.`;
            }
        }
        return null;
    }

    /* ── Save ────────────────────────────────────────────────── */
    $('btnSave').addEventListener('click', async () => {
        const empId  = fld('cEmployee');
        const date   = fld('cDate');
        const reason = fld('cReason');

        if (!empId || !date || !reason) {
            showAlert('Employee, date, and reason are required.', 'danger');
            return;
        }

        const seqErr = validateSequence();
        if (seqErr) {
            $('seqError').textContent = seqErr;
            $('seqError').classList.remove('d-none');
            return;
        }
        $('seqError').classList.add('d-none');

        const btn = $('btnSave'), sp = $('saveSpinner');
        btn.disabled = true; sp.classList.remove('d-none');

        const fd = new FormData();
        fd.append('employee_id',       empId);
        fd.append('attendance_date',   date);
        fd.append('attendance_status', fld('cStatus'));
        fd.append('time_in',           fld('cTimeIn'));
        fd.append('break_out',         fld('cBreakOut'));
        fd.append('break_in',          fld('cBreakIn'));
        fd.append('time_out',          fld('cTimeOut'));
        fd.append('overtime_in',       fld('cOtIn'));
        fd.append('overtime_out',      fld('cOtOut'));
        fd.append('method',            fld('cMethod'));
        fd.append('reason',            reason);
        fd.append('admin_remarks',     fld('cAdminRemarks'));

        try {
            const res  = await fetch(API_URL, {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json();

            if (!data.success) {
                // Mask raw SQL errors from the user
                const msg = data.error?.startsWith('SQLSTATE') || data.error?.includes('Unknown column')
                    ? 'The record could not be saved. Please contact the system administrator.'
                    : (data.error ?? 'Save failed.');
                showAlert(msg, 'danger');
                return;
            }

            let msg = data.message ?? 'Manual attendance saved successfully.';
            if (data.warnings?.length) {
                msg += '<br><small>⚠ ' + data.warnings.join(' · ') + '</small>';
            }
            showAlert(msg, 'success');

            // Reset form fields
            ['cEmployee', 'cStatus', 'cMethod'].forEach(id => {
                const el = $(id);
                if (el) el.value = el.options[0]?.value ?? '';
            });
            $('cDate').value = new Date().toISOString().slice(0, 10);
            ['cTimeIn','cBreakOut','cBreakIn','cTimeOut','cOtIn','cOtOut','cReason','cAdminRemarks']
                .forEach(id => { const el = $(id); if (el) el.value = ''; });
            $('timeWarning').classList.add('d-none');
            $('btnViewList').classList.remove('d-none');

        } catch {
            showAlert('An unexpected error occurred. Please try again.', 'danger');
        } finally {
            btn.disabled = false; sp.classList.add('d-none');
        }
    });
})();
</script>
