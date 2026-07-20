<?php
/**
 * Email Schedule — Safe Testing Mode
 * Administrator only.
 */
$scheduleLabels = [
    'manual'       => 'Manual Only',
    '15th'         => 'Send on 16th (1–15 report)',
    'end_of_month' => 'Send on 1st (16–end report)',
    'both'         => 'Both (16th & 1st of next month)',
];
?>

<div class="page-head">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-send-check me-2"></i>Email Schedule — Safe Testing</h1>
        <div class="text-muted">
            Simulate scheduled dates, preview reports, and test email delivery without touching production logic.
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('email-settings') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-gear me-1"></i>Email Settings
        </a>
        <a href="<?= url('email-logs') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-envelope-paper me-1"></i>Email Logs
        </a>
    </div>
</div>

<!-- ── Current Config Banner ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 bg-primary bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Production Schedule</div>
                <div class="fw-semibold"><?= e($scheduleLabels[$schedule] ?? $schedule) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-secondary bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Timezone</div>
                <div class="fw-semibold"><?= e($timezone) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-info bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Last Real Send</div>
                <div class="fw-semibold"><?= e($lastSent ?: '—') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-warning bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Report Recipient</div>
                <div class="fw-semibold text-truncate" title="<?= e($recipient) ?>">
                    <?= e($recipient ?: 'Not configured') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ── Left: Test Form ──────────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Test Configuration</h5>
            </div>
            <div class="card-body">

                <form id="testForm">
                    <?= csrf_field() ?>

                    <!-- Simulated Date -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Simulated Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="simulatedDate"
                               name="simulated_date" value="<?= e($suggestDates[0]) ?>" required>
                        <div class="form-text">
                            The scheduler will behave as if today is this date.
                            Use the 15th for a 1–15 report, or the last day for a 16–end report.
                        </div>

                        <!-- Quick-pick buttons -->
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <?php foreach ($suggestDates as $i => $d): ?>
                                <?php
                                $day     = (int) date('d', strtotime($d));
                                if ($day === 16) {
                                    $label   = '16th — triggers 1–15 report';
                                    $variant = 'outline-primary';
                                } else {
                                    $label   = '1st — triggers 16–end report (prev month)';
                                    $variant = 'outline-success';
                                }
                                ?>
                                <button type="button"
                                        class="btn btn-sm btn-<?= $variant ?> quick-date"
                                        data-date="<?= e($d) ?>">
                                    <i class="bi bi-calendar-check me-1"></i><?= e($d) ?> — <?= $label ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Mode selector -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Mode</label>

                        <div class="list-group">
                            <label class="list-group-item list-group-item-action d-flex gap-3 cursor-pointer">
                                <input class="form-check-input flex-shrink-0 mt-1" type="radio"
                                       name="mode" value="dry_run" id="modeDry" checked>
                                <span>
                                    <span class="fw-semibold">Dry Run</span>
                                    <span class="d-block text-muted small">
                                        Build the report, resolve the period, count employees —
                                        but <strong>do not send</strong> the email.
                                    </span>
                                </span>
                            </label>
                            <label class="list-group-item list-group-item-action d-flex gap-3 cursor-pointer">
                                <input class="form-check-input flex-shrink-0 mt-1" type="radio"
                                       name="mode" value="normal" id="modeNormal">
                                <span>
                                    <span class="fw-semibold">Normal Send</span>
                                    <span class="d-block text-muted small">
                                        Send the email using the exact same scheduler logic.
                                        Blocked if the period was already sent (duplicate guard active).
                                    </span>
                                </span>
                            </label>
                            <label class="list-group-item list-group-item-action d-flex gap-3 cursor-pointer border-danger">
                                <input class="form-check-input flex-shrink-0 mt-1" type="radio"
                                       name="mode" value="force_send" id="modeForce">
                                <span>
                                    <span class="fw-semibold text-danger">Force Send</span>
                                    <span class="d-block text-muted small">
                                        Send immediately regardless of date or duplicate guard.
                                        Use only to verify SMTP delivery in development.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="runBtn">
                        <i class="bi bi-play-circle me-1"></i>Run Test
                    </button>
                </form>

            </div>
        </div>

        <!-- Period Preview card (live-updated by JS) -->
        <div class="card mt-3" id="periodPreviewCard">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Resolved Period Preview</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Simulated Date</dt>
                    <dd class="col-7 fw-semibold" id="pvDate">—</dd>
                    <dt class="col-5 text-muted">Period Half</dt>
                    <dd class="col-7" id="pvHalf">—</dd>
                    <dt class="col-5 text-muted">Date From</dt>
                    <dd class="col-7" id="pvFrom">—</dd>
                    <dt class="col-5 text-muted">Date To</dt>
                    <dd class="col-7" id="pvTo">—</dd>
                    <dt class="col-5 text-muted">Period Label</dt>
                    <dd class="col-7 fw-semibold text-primary" id="pvLabel">—</dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- ── Right: Result panel ──────────────────────────────────────── -->
    <div class="col-lg-7">

        <div class="card h-100" id="resultPanel">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Test Result</h5>
                <span class="badge bg-secondary" id="resultBadge">Waiting…</span>
            </div>
            <div class="card-body" id="resultBody">
                <div class="text-center text-muted py-5">
                    <i class="bi bi-send fs-2 d-block mb-2 opacity-25"></i>
                    Configure a simulated date and click <strong>Run Test</strong>.
                </div>
            </div>
        </div>

        <!-- Duplicate warning (hidden until duplicate detected) -->
        <div class="card mt-3 border-warning d-none" id="duplicateCard">
            <div class="card-header bg-warning bg-opacity-25 text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Report already sent for this period</strong>
            </div>
            <div class="card-body">
                <p class="mb-3" id="duplicateMsg"></p>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" id="dupCancelBtn">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button class="btn btn-warning btn-sm" id="dupResendBtn">
                        <i class="bi bi-arrow-repeat me-1"></i>Resend Anyway (Force Send)
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── Recent Test Log ──────────────────────────────────────────────── -->
<div class="card mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Recent Test Executions</h5>
        <a href="<?= url('email-logs?is_test=1') ?>" class="btn btn-sm btn-outline-secondary">
            View All in Email Logs
        </a>
    </div>
    <div class="card-body p-0" id="recentLogsBody">
        <div class="text-center text-muted py-4 small">
            <span class="spinner-border spinner-border-sm me-1"></span>Loading…
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

/* ── Period preview (client-side, mirrors PHP resolvePeriod logic) ── */
function resolvePeriod(dateStr) {
    if (!dateStr) return null;
    const d    = new Date(dateStr + 'T00:00:00');
    const day  = d.getUTCDate();
    const y    = d.getUTCFullYear();
    const m    = String(d.getUTCMonth() + 1).padStart(2, '0');
    const mon  = d.toLocaleString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' });

    if (day === 16) {
        // Canonical: 16th → 1st–15th of same month
        return {
            half:  '1st Half (1–15)',
            from:  `${y}-${m}-01`,
            to:    `${y}-${m}-15`,
            label: `${mon} (1–15)`
        };
    }

    if (day === 1) {
        // Canonical: 1st → 16th–last of PREVIOUS month
        const prev     = new Date(Date.UTC(y, d.getUTCMonth() - 1, 1));
        const pY       = prev.getUTCFullYear();
        const pM       = String(prev.getUTCMonth() + 1).padStart(2, '0');
        const lastDay  = new Date(Date.UTC(pY, prev.getUTCMonth() + 1, 0)).getUTCDate();
        const lastDayS = String(lastDay).padStart(2, '0');
        const pMon     = prev.toLocaleString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' });
        return {
            half:  `2nd Half — prev month (16–${lastDay})`,
            from:  `${pY}-${pM}-16`,
            to:    `${pY}-${pM}-${lastDayS}`,
            label: `${pMon} (16–${lastDay})`
        };
    }

    // Ad-hoc / arbitrary date — best-fit half
    const last = new Date(Date.UTC(y, d.getUTCMonth() + 1, 0)).getUTCDate();
    if (day <= 15) {
        return { half: '1st Half (1–15)', from: `${y}-${m}-01`, to: `${y}-${m}-15`, label: `${mon} (1–15)` };
    }
    return {
        half:  `2nd Half (16–${last})`,
        from:  `${y}-${m}-16`,
        to:    `${y}-${m}-${String(last).padStart(2,'0')}`,
        label: `${mon} (16–${last})`
    };
}

function updatePreview() {
    const val = document.getElementById('simulatedDate').value;
    const p   = resolvePeriod(val);
    if (!p) { return; }
    document.getElementById('pvDate').textContent  = val;
    document.getElementById('pvHalf').textContent  = p.half;
    document.getElementById('pvFrom').textContent  = p.from;
    document.getElementById('pvTo').textContent    = p.to;
    document.getElementById('pvLabel').textContent = p.label;
}

document.getElementById('simulatedDate').addEventListener('input', updatePreview);
updatePreview();

/* ── Quick-pick ─────────────────────────────────────────────────── */
document.querySelectorAll('.quick-date').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('simulatedDate').value = btn.dataset.date;
        updatePreview();
    });
});

/* ── Result renderer ────────────────────────────────────────────── */
const badge = document.getElementById('resultBadge');
const body  = document.getElementById('resultBody');

function setBadge(text, cls) {
    badge.textContent = text;
    badge.className   = 'badge bg-' + cls;
}

function renderResult(data) {
    document.getElementById('duplicateCard').classList.add('d-none');

    if (data.duplicate) {
        setBadge('Duplicate Detected', 'warning');
        const dupCard = document.getElementById('duplicateCard');
        document.getElementById('duplicateMsg').textContent =
            data.message + ' Period: ' + (data.period_label || '');
        dupCard.classList.remove('d-none');
        body.innerHTML = '<div class="alert alert-warning mb-0">'+escHtml(data.message)+'</div>';
        return;
    }

    if (!data.success) {
        setBadge('Failed', 'danger');
        body.innerHTML = renderError(data);
        return;
    }

    if (data.dry_run) {
        setBadge('Dry Run Complete', 'info');
        body.innerHTML = renderDryRun(data);
    } else {
        setBadge('Email Sent ✓', 'success');
        body.innerHTML = renderSent(data);
    }

    loadRecentLogs();
}

function renderDryRun(d) {
    return `
<div class="alert alert-info mb-3">
  <i class="bi bi-info-circle me-2"></i>
  <strong>Dry Run</strong> — Report was generated but email was <strong>NOT sent</strong>.
</div>
<dl class="row small mb-0">
  <dt class="col-5 text-muted">Recipient</dt>      <dd class="col-7">${escHtml(d.recipient||'—')}</dd>
  ${d.cc  ? `<dt class="col-5 text-muted">CC</dt><dd class="col-7">${escHtml(d.cc)}</dd>` : ''}
  ${d.bcc ? `<dt class="col-5 text-muted">BCC</dt><dd class="col-7">${escHtml(d.bcc)}</dd>` : ''}
  <dt class="col-5 text-muted">Subject</dt>        <dd class="col-7 fw-semibold">${escHtml(d.subject||'—')}</dd>
  <dt class="col-5 text-muted">Period</dt>         <dd class="col-7 fw-semibold text-primary">${escHtml(d.period_label||'—')}</dd>
  <dt class="col-5 text-muted">Date From</dt>      <dd class="col-7">${escHtml(d.date_from||'—')}</dd>
  <dt class="col-5 text-muted">Date To</dt>        <dd class="col-7">${escHtml(d.date_to||'—')}</dd>
  <dt class="col-5 text-muted">Attachment</dt>     <dd class="col-7">${escHtml(d.attachment_name||'(none)')}</dd>
  <dt class="col-5 text-muted">Employees</dt>      <dd class="col-7 fw-semibold">${escHtml(String(d.employee_count??'0'))}</dd>
</dl>
${d.already_sent ? '<div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>This period\'s report has already been sent in production.</div>' : ''}`;
}

function renderSent(d) {
    return `
<div class="alert alert-success mb-3">
  <i class="bi bi-check-circle me-2"></i>
  <strong>Email sent successfully!</strong> ${escHtml(d.message||'')}
</div>
<dl class="row small mb-0">
  <dt class="col-5 text-muted">Recipient</dt>  <dd class="col-7">${escHtml(d.recipient||'—')}</dd>
  <dt class="col-5 text-muted">Subject</dt>    <dd class="col-7 fw-semibold">${escHtml(d.subject||'—')}</dd>
  <dt class="col-5 text-muted">Period</dt>     <dd class="col-7 text-primary fw-semibold">${escHtml(d.period_label||'—')}</dd>
  <dt class="col-5 text-muted">Date From</dt>  <dd class="col-7">${escHtml(d.date_from||'—')}</dd>
  <dt class="col-5 text-muted">Date To</dt>    <dd class="col-7">${escHtml(d.date_to||'—')}</dd>
  <dt class="col-5 text-muted">Employees</dt>  <dd class="col-7 fw-semibold">${escHtml(String(d.employee_count??'0'))}</dd>
</dl>`;
}

function renderError(d) {
    return `
<div class="alert alert-danger mb-3">
  <i class="bi bi-x-circle me-2"></i><strong>Error:</strong> ${escHtml(d.message||'Unknown error')}
</div>
${d.error ? `<pre class="text-danger small bg-light rounded p-2">${escHtml(String(d.error))}</pre>` : ''}`;
}

/* ── Form submission ─────────────────────────────────────────────── */
document.getElementById('testForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const btn  = document.getElementById('runBtn');
    const mode = document.querySelector('input[name="mode"]:checked')?.value ?? 'dry_run';
    const date = document.getElementById('simulatedDate').value;

    if (!date) { alert('Please set a simulated date.'); return; }

    setBadge('Running…', 'secondary');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Executing…</p></div>';
    btn.disabled   = true;

    const fd = new FormData(this);

    fetch('<?= url('email-schedule/test-run') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        renderResult(data);
    })
    .catch(err => {
        btn.disabled = false;
        setBadge('Error', 'danger');
        body.innerHTML = `<div class="alert alert-danger">Request failed: ${escHtml(String(err))}</div>`;
    });
});

/* ── Duplicate resend ─────────────────────────────────────────────── */
document.getElementById('dupCancelBtn').addEventListener('click', () => {
    document.getElementById('duplicateCard').classList.add('d-none');
});

document.getElementById('dupResendBtn').addEventListener('click', function () {
    document.querySelector('input[value="force_send"]').checked = true;
    document.getElementById('duplicateCard').classList.add('d-none');
    document.getElementById('testForm').dispatchEvent(new Event('submit'));
});

/* ── Recent logs loader ──────────────────────────────────────────── */
function loadRecentLogs() {
    fetch('<?= url('email-schedule/test-logs') ?>')
        .then(r => r.json())
        .then(rows => {
            const tb = document.getElementById('recentLogsBody');
            if (!rows.length) {
                tb.innerHTML = '<div class="text-center text-muted py-3 small">No test runs recorded yet.</div>';
                return;
            }
            let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">'
                + '<thead class="table-light"><tr>'
                + '<th>Executed</th><th>Simulated Date</th><th>Period</th>'
                + '<th>Recipient</th><th>Mode</th><th>Status</th><th>Error</th>'
                + '</tr></thead><tbody>';
            rows.forEach(r => {
                const statusCls = { sent:'success', failed:'danger', queued:'warning', retrying:'info' }[r.status] || 'secondary';
                const modeBadge = r.is_test_run == 1
                    ? '<span class="badge bg-info bg-opacity-75 text-dark">Test</span>'
                    : '<span class="badge bg-secondary">Production</span>';
                html += `<tr>
                    <td class="text-nowrap small">${escHtml(r.created_at||'')}</td>
                    <td class="text-nowrap">${escHtml(r.simulated_date||'—')}</td>
                    <td class="small">${escHtml(r.report_period||'—')}</td>
                    <td class="small text-truncate" style="max-width:160px">${escHtml(r.recipient||'')}</td>
                    <td>${modeBadge}</td>
                    <td><span class="badge bg-${statusCls}">${escHtml(r.status)}</span></td>
                    <td class="small text-danger text-truncate" style="max-width:160px">${escHtml(r.last_error||'')}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            tb.innerHTML = html;
        })
        .catch(() => {
            document.getElementById('recentLogsBody').innerHTML =
                '<div class="text-muted small text-center py-3">Could not load logs.</div>';
        });
}

loadRecentLogs();

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

})();
</script>
