<div class="page-head">
    <div>
        <h1 class="h3 mb-1"><?= e($title) ?></h1>
        <div class="text-muted">
            <?= $shift ? 'Update shift settings. Employee assignments remain intact.' : 'Create a new work schedule for your employees.' ?>
        </div>
    </div>
    <div>
        <a href="<?= url('shifts') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Shifts
        </a>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" class="row g-4" id="shiftForm">
    <?= csrf_field() ?>
    <?php if ($shift): ?>
        <input type="hidden" name="id" value="<?= e($shift['id']) ?>">
    <?php endif; ?>

    <!-- Basic Information -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Shift Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-8">
                        <label class="form-label">Shift Name <span class="text-danger">*</span></label>
                        <input class="form-control"
                               type="text"
                               name="name"
                               value="<?= e($shift['name'] ?? '') ?>"
                               required
                               maxlength="80"
                               placeholder="e.g. Regular Office Hours">
                        <div class="form-text">Must be unique across all shifts.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Shift Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="type" id="shiftType">
                            <option value="regular"  <?= ($shift['type'] ?? 'regular') === 'regular'  ? 'selected' : '' ?>>Regular</option>
                            <option value="night"    <?= ($shift['type'] ?? '') === 'night'            ? 'selected' : '' ?>>Night</option>
                            <option value="flexible" <?= ($shift['type'] ?? '') === 'flexible'         ? 'selected' : '' ?>>Flexible</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"
                                  placeholder="Optional notes about this shift…"><?= e($shift['description'] ?? '') ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <!-- Schedule -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Schedule</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Time In <span class="text-danger">*</span></label>
                        <input class="form-control"
                               type="time"
                               name="time_in"
                               id="timeIn"
                               value="<?= e(substr($shift['time_in'] ?? '08:00', 0, 5)) ?>"
                               required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Time Out <span class="text-danger">*</span></label>
                        <input class="form-control"
                               type="time"
                               name="time_out"
                               id="timeOut"
                               value="<?= e(substr($shift['time_out'] ?? '17:00', 0, 5)) ?>"
                               required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Required Working Hours</label>
                        <div class="input-group">
                            <input class="form-control"
                                   type="number"
                                   name="required_hours"
                                   id="requiredHours"
                                   value="<?= e(number_format((float)($shift['required_hours'] ?? 8), 2)) ?>"
                                   min="0.5" max="24" step="0.5">
                            <span class="input-group-text">hrs</span>
                        </div>
                        <div class="form-text">Net hours after break deduction.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Break Start</label>
                        <input class="form-control"
                               type="time"
                               name="lunch_break_start"
                               value="<?= e(substr($shift['lunch_break_start'] ?? '12:00', 0, 5)) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Break End</label>
                        <input class="form-control"
                               type="time"
                               name="lunch_break_end"
                               value="<?= e(substr($shift['lunch_break_end'] ?? '13:00', 0, 5)) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Break Duration</label>
                        <div class="input-group">
                            <input class="form-control"
                                   type="number"
                                   name="lunch_break_minutes"
                                   id="breakMinutes"
                                   value="<?= e((int)($shift['lunch_break_minutes'] ?? 60)) ?>"
                                   min="0" max="240">
                            <span class="input-group-text">min</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Grace Period</label>
                        <div class="input-group">
                            <input class="form-control"
                                   type="number"
                                   name="grace_period_minutes"
                                   value="<?= e((int)($shift['grace_period_minutes'] ?? 15)) ?>"
                                   min="0" max="60">
                            <span class="input-group-text">min</span>
                        </div>
                        <div class="form-text">Allowed lateness before marking as late.</div>
                    </div>

                    <!-- Overnight notice — auto-shown by JS -->
                    <div class="col-12 d-none" id="overnightNotice">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-moon-stars me-2"></i>
                            <strong>Overnight Shift Detected.</strong>
                            Time Out is earlier than Time In — this shift spans midnight and will be
                            automatically flagged as overnight.
                        </div>
                    </div>
                    <input type="hidden" name="overnight" id="overnightFlag"
                           value="<?= (int)($shift['overnight'] ?? 0) ?>">

                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar options -->
    <div class="col-lg-4">

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Options</h5>
            </div>
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active"   <?= ($shift['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($shift['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input"
                           type="checkbox"
                           role="switch"
                           name="is_default"
                           id="isDefault"
                           value="1"
                           <?= (int)($shift['is_default'] ?? 0) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="isDefault">
                        Default Shift
                    </label>
                    <div class="form-text">
                        New employees are automatically assigned this shift unless another is chosen.
                        Only one shift may be the default at a time.
                    </div>
                </div>

            </div>
        </div>

        <!-- Live Preview -->
        <div class="card border-primary border-opacity-25">
            <div class="card-header bg-primary bg-opacity-10">
                <h6 class="mb-0 text-primary"><i class="bi bi-eye me-2"></i>Schedule Preview</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Time In</dt>
                    <dd class="col-7" id="previewTimeIn">—</dd>

                    <dt class="col-5 text-muted">Time Out</dt>
                    <dd class="col-7" id="previewTimeOut">—</dd>

                    <dt class="col-5 text-muted">Break</dt>
                    <dd class="col-7" id="previewBreak">—</dd>

                    <dt class="col-5 text-muted">Working Hours</dt>
                    <dd class="col-7 fw-semibold" id="previewHours">—</dd>

                    <dt class="col-5 text-muted">Grace Period</dt>
                    <dd class="col-7" id="previewGrace">—</dd>

                    <dt class="col-5 text-muted">Overnight</dt>
                    <dd class="col-7" id="previewOvernight">No</dd>
                </dl>
            </div>
        </div>

    </div>

    <!-- Actions -->
    <div class="col-12">
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= url('shifts') ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>
                <?= $shift ? 'Update Shift' : 'Create Shift' ?>
            </button>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';

    const timeIn       = document.getElementById('timeIn');
    const timeOut      = document.getElementById('timeOut');
    const reqHours     = document.getElementById('requiredHours');
    const breakMin     = document.getElementById('breakMinutes');
    const overnightFlg = document.getElementById('overnightFlag');
    const overnightDiv = document.getElementById('overnightNotice');

    function fmt12(val) {
        if (!val) return '—';
        const [h, m] = val.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12  = ((h % 12) || 12);
        return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
    }

    function updatePreview() {
        const inVal  = timeIn.value;
        const outVal = timeOut.value;

        document.getElementById('previewTimeIn').textContent  = fmt12(inVal);
        document.getElementById('previewTimeOut').textContent = fmt12(outVal);
        document.getElementById('previewGrace').textContent   =
            (document.querySelector('[name="grace_period_minutes"]').value || '0') + ' min';

        // Detect overnight
        let overnight = false;
        if (inVal && outVal) {
            const [ih, im] = inVal.split(':').map(Number);
            const [oh, om] = outVal.split(':').map(Number);
            overnight = (oh * 60 + om) <= (ih * 60 + im);
        }

        overnightFlg.value = overnight ? '1' : '0';
        overnightDiv.classList.toggle('d-none', !overnight);
        document.getElementById('previewOvernight').textContent = overnight ? 'Yes' : 'No';

        // Break preview
        const brStart = document.querySelector('[name="lunch_break_start"]').value;
        const brEnd   = document.querySelector('[name="lunch_break_end"]').value;
        document.getElementById('previewBreak').textContent =
            (brStart && brEnd) ? fmt12(brStart) + ' – ' + fmt12(brEnd) : '—';

        // Auto-calc required hours from times if the field hasn't been manually edited
        if (inVal && outVal) {
            let [ih, im] = inVal.split(':').map(Number);
            let [oh, om] = outVal.split(':').map(Number);
            let grossMin = (oh * 60 + om) - (ih * 60 + im);
            if (overnight) grossMin += 24 * 60;
            const brMins = parseInt(breakMin.value, 10) || 0;
            const netHrs = Math.max(0, (grossMin - brMins) / 60);
            document.getElementById('previewHours').textContent = netHrs.toFixed(1) + ' hrs';
        } else {
            document.getElementById('previewHours').textContent = '—';
        }
    }

    // Attach listeners
    [timeIn, timeOut, breakMin, reqHours,
     document.querySelector('[name="lunch_break_start"]'),
     document.querySelector('[name="lunch_break_end"]'),
     document.querySelector('[name="grace_period_minutes"]')
    ].forEach(el => el && el.addEventListener('input', updatePreview));

    // Initial render
    updatePreview();
})();
</script>
