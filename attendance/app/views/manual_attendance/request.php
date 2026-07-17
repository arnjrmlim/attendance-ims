<?php
/**
 * Employee: Self-service manual attendance — all 6 types
 * Clean neutral UI redesign.
 * Route: GET /manual-attendance/request
 */
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-semibold mb-0">Manual Attendance</h4>
        <small class="text-muted">Record your attendance for today</small>
    </div>
    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>My Records
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-5 col-md-7">

        <!-- Inline alert (errors / warnings only) -->
        <div id="reqAlert" class="d-none mb-3"></div>

        <div class="panel p-4">

            <!-- ── Success state ─────────────────────────────────── -->
            <div id="successState" class="d-none text-center py-2">
                <div class="mb-3" style="font-size:2.5rem;color:#16a34a;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h5 class="fw-semibold mb-1">Attendance Recorded</h5>
                <p class="text-muted mb-1" id="successType"></p>
                <p class="text-muted small mb-4" id="successDateTime"></p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <button class="btn btn-outline-primary btn-sm" id="btnAnother">
                        <i class="bi bi-arrow-repeat me-1"></i>Submit Another
                    </button>
                    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-list-ul me-1"></i>View My Records
                    </a>
                </div>
            </div>

            <!-- ── Form state ─────────────────────────────────────── -->
            <div id="formState">

                <p class="form-label fw-semibold mb-3">
                    Select Attendance Type
                </p>

                <!-- Neutral type buttons — 3×2 grid -->
                <div class="row g-2 mb-3" id="typeGrid">
                    <?php
                    $types = [
                        ['time_in',     'bi-box-arrow-in-right', 'Time In'],
                        ['break_out',   'bi-door-open',          'Break Out'],
                        ['break_in',    'bi-door-closed',        'Break In'],
                        ['time_out',    'bi-box-arrow-right',    'Time Out'],
                        ['overtime_in', 'bi-plus-circle',        'OT In'],
                        ['overtime_out','bi-dash-circle',        'OT Out'],
                    ];
                    foreach ($types as $i => [$val, $icon, $label]):
                    ?>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="reqType"
                               id="rType_<?= $val ?>" value="<?= $val ?>"
                               <?= $i === 0 ? 'checked' : '' ?>>
                        <label class="att-type-btn" for="rType_<?= $val ?>">
                            <i class="bi <?= $icon ?>"></i>
                            <?= $label ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Selected type indicator -->
                <div class="att-selection-bar mb-4" id="selectionBar">
                    <i class="bi bi-check2-circle"></i>
                    <span>Selected: <strong id="selectionLabel">Time In</strong></span>
                </div>

                <!-- Date / time (server-controlled) -->
                <div class="att-info-note mb-4">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Recorded at the current server time —
                        <strong id="livDate"><?= date('M j, Y') ?></strong>,
                        <strong id="livTime"><?= date('h:i A') ?></strong>.
                        No admin approval required.
                    </span>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" id="btnSubmit">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="submitSpinner"></span>
                        <i class="bi bi-send me-1"></i>Submit Attendance
                    </button>
                    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>

            </div><!-- /formState -->
        </div><!-- /panel -->

    </div>
</div>

<script>
(function () {
    const CSRF    = <?= json_encode(csrf_token()) ?>;
    const API_URL = <?= json_encode(url('manual-attendance/api/request')) ?>;

    const $ = id => document.getElementById(id);

    /* ── Label map ─────────────────────────────────────────── */
    const LABELS = {
        time_in:     'Time In',
        break_out:   'Break Out',
        break_in:    'Break In',
        time_out:    'Time Out',
        overtime_in: 'Overtime In',
        overtime_out:'Overtime Out',
    };

    /* ── Live clock ────────────────────────────────────────── */
    const MOS  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    function updateClock() {
        const now  = new Date();
        const h    = now.getHours() % 12 || 12;
        const m    = String(now.getMinutes()).padStart(2, '0');
        const ampm = now.getHours() >= 12 ? 'PM' : 'AM';
        $('livDate').textContent =
            `${DAYS[now.getDay()]}, ${MOS[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        $('livTime').textContent = `${h}:${m} ${ampm}`;
    }
    updateClock();
    setInterval(updateClock, 30000);

    /* ── Update selection bar when a type is picked ─────────── */
    function syncSelectionBar() {
        const checked = document.querySelector('input[name="reqType"]:checked');
        if (checked) {
            $('selectionLabel').textContent = LABELS[checked.value] ?? checked.value;
        }
    }
    document.querySelectorAll('input[name="reqType"]').forEach(r => {
        r.addEventListener('change', syncSelectionBar);
    });
    syncSelectionBar(); // initialise

    /* ── Friendly error display ─────────────────────────────── */
    function showAlert(msg, type) {
        const el = $('reqAlert');
        el.className = `alert alert-${type} alert-dismissible fade show`;
        el.innerHTML = `<i class="bi bi-${type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>`
                     + msg
                     + `<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        el.classList.remove('d-none');
    }

    /* ── Submit ─────────────────────────────────────────────── */
    $('btnSubmit').addEventListener('click', async () => {
        const type = document.querySelector('input[name="reqType"]:checked')?.value;
        if (!type) { showAlert('Please select an attendance type.', 'danger'); return; }

        const btn = $('btnSubmit'), sp = $('submitSpinner');
        btn.disabled = true; sp.classList.remove('d-none');

        const fd = new FormData();
        fd.append('request_type', type);

        try {
            const res  = await fetch(API_URL, {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json();

            if (!data.success) {
                // Show friendly message — never expose raw SQL/stack traces
                const msg = data.error?.startsWith('SQLSTATE') || data.error?.includes('Unknown column')
                    ? 'Attendance could not be processed. Please contact the system administrator.'
                    : (data.error ?? 'Attendance could not be processed.');
                showAlert(msg, 'danger');
                return;
            }

            // Success
            const label = LABELS[type] ?? type;
            const now   = new Date();
            $('successType').textContent    = `${label} recorded successfully.`;
            $('successDateTime').textContent =
                now.toLocaleDateString('en-US', {
                    weekday:'long', year:'numeric', month:'long', day:'numeric'
                }) + ' · ' + now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });

            if (data.warnings?.length) {
                showAlert(data.warnings.join(' · '), 'warning');
            }

            $('formState').classList.add('d-none');
            $('successState').classList.remove('d-none');

        } catch {
            showAlert('An unexpected error occurred. Please try again.', 'danger');
        } finally {
            btn.disabled = false; sp.classList.add('d-none');
        }
    });

    /* ── Submit another ─────────────────────────────────────── */
    $('btnAnother').addEventListener('click', () => {
        $('successState').classList.add('d-none');
        $('formState').classList.remove('d-none');
        $('reqAlert').classList.add('d-none');
        document.getElementById('rType_time_in').checked = true;
        syncSelectionBar();
    });
})();
</script>
