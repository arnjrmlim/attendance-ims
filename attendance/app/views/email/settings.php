<?php /** Email / SMTP Settings */ ?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-envelope-gear me-2"></i>Email Settings</h4>
        <small class="text-muted">SMTP configuration and monthly report settings</small>
    </div>
    <a href="<?= url('email-logs') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-list-ul"></i> Email Logs
    </a>
</div>

<?php
// Build a quick lookup: key => value from the rows array
$cfg = [];
foreach ($settings as $row) {
    $cfg[$row['key']] = $row['value'];
}
function sv(array $cfg, string $key, string $default = ''): string {
    return htmlspecialchars($cfg[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}
?>

<div class="row">
    <!-- SMTP Config -->
    <div class="col-lg-8">
        <form method="post" action="<?= url('email-settings') ?>">
            <?= csrf_field() ?>
            <div class="panel p-4 mb-4">
                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">SMTP Server</h6>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" class="form-control" name="smtp_host" value="<?= sv($cfg,'smtp_host') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Port</label>
                        <input type="number" class="form-control" name="smtp_port" value="<?= sv($cfg,'smtp_port','587') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Encryption</label>
                        <select class="form-select" name="smtp_encryption">
                            <option value="tls"  <?= ($cfg['smtp_encryption'] ?? 'tls') === 'tls'  ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                            <option value="ssl"  <?= ($cfg['smtp_encryption'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($cfg['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="smtp_username" value="<?= sv($cfg,'smtp_username') ?>" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="smtp_password_plain"
                                   placeholder="<?= $cfg['smtp_password'] ? 'Leave blank to keep current' : 'Enter password' ?>"
                                   autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" id="togglePwd">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Name</label>
                        <input type="text" class="form-control" name="smtp_from_name" value="<?= sv($cfg,'smtp_from_name','Attendance System') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Email</label>
                        <input type="email" class="form-control" name="smtp_from_email" value="<?= sv($cfg,'smtp_from_email') ?>">
                    </div>
                </div>
            </div>

            <div class="panel p-4 mb-4">
                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">Report Recipients</h6>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Primary Recipient</label>
                        <input type="email" class="form-control" name="email_report_recipient" value="<?= sv($cfg,'email_report_recipient') ?>" placeholder="main-branch@company.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CC <span class="text-muted small">(comma-separated)</span></label>
                        <input type="text" class="form-control" name="email_report_cc" value="<?= sv($cfg,'email_report_cc') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">BCC <span class="text-muted small">(comma-separated)</span></label>
                        <input type="text" class="form-control" name="email_report_bcc" value="<?= sv($cfg,'email_report_bcc') ?>">
                    </div>
                </div>
            </div>

            <div class="panel p-4 mb-4">
                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">Report & Retry Settings</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Retry Interval (min)</label>
                        <input type="number" class="form-control" name="email_retry_interval" value="<?= sv($cfg,'email_retry_interval','60') ?>" min="5">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Max Retries</label>
                        <input type="number" class="form-control" name="email_max_retries" value="<?= sv($cfg,'email_max_retries','5') ?>" min="1" max="20">
                    </div>
                    <div class="col-md-3 d-flex align-items-end pb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_report_enabled" id="reportEnabled"
                                   <?= !empty($cfg['email_report_enabled']) && $cfg['email_report_enabled'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reportEnabled">Auto Monthly Report</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end pb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_report_compress" id="reportCompress"
                                   <?= !empty($cfg['email_report_compress']) && $cfg['email_report_compress'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reportCompress">Compress to ZIP</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel p-4 mb-4">
                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">Email Schedule</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Schedule</label>
                        <select class="form-select" name="email_schedule">
                            <option value="manual" <?= ($cfg['email_schedule'] ?? 'manual') === 'manual' ? 'selected' : '' ?>>Manual Only</option>
                            <option value="15th" <?= ($cfg['email_schedule'] ?? '') === '15th' ? 'selected' : '' ?>>Every 15th of the Month</option>
                            <option value="end_of_month" <?= ($cfg['email_schedule'] ?? '') === 'end_of_month' ? 'selected' : '' ?>>End of Month</option>
                            <option value="both" <?= ($cfg['email_schedule'] ?? '') === 'both' ? 'selected' : '' ?>>Both (15th & End of Month)</option>
                        </select>
                        <small class="text-muted">Automatic email sending schedule for attendance reports</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Timezone</label>
                        <select class="form-select" name="email_timezone">
                            <option value="UTC" <?= ($cfg['email_timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                            <option value="America/New_York" <?= ($cfg['email_timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>America/New_York (EST)</option>
                            <option value="America/Chicago" <?= ($cfg['email_timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>America/Chicago (CST)</option>
                            <option value="America/Denver" <?= ($cfg['email_timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>America/Denver (MST)</option>
                            <option value="America/Los_Angeles" <?= ($cfg['email_timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>America/Los_Angeles (PST)</option>
                            <option value="Europe/London" <?= ($cfg['email_timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>Europe/London (GMT)</option>
                            <option value="Europe/Paris" <?= ($cfg['email_timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Europe/Paris (CET)</option>
                            <option value="Asia/Shanghai" <?= ($cfg['email_timezone'] ?? '') === 'Asia/Shanghai' ? 'selected' : '' ?>>Asia/Shanghai (CST)</option>
                            <option value="Asia/Tokyo" <?= ($cfg['email_timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Asia/Tokyo (JST)</option>
                            <option value="Asia/Singapore" <?= ($cfg['email_timezone'] ?? '') === 'Asia/Singapore' ? 'selected' : '' ?>>Asia/Singapore (SGT)</option>
                            <option value="Asia/Manila" <?= ($cfg['email_timezone'] ?? '') === 'Asia/Manila' ? 'selected' : '' ?>>Asia/Manila (PHT)</option>
                            <option value="Asia/Kolkata" <?= ($cfg['email_timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (IST)</option>
                            <option value="Australia/Sydney" <?= ($cfg['email_timezone'] ?? '') === 'Australia/Sydney' ? 'selected' : '' ?>>Australia/Sydney (AEST)</option>
                        </select>
                        <small class="text-muted">Timezone for determining send date</small>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Settings</button>
            </div>
        </form>
    </div>

    <!-- Test Email Card -->
    <div class="col-lg-4">
        <div class="panel p-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-send-check me-2 text-success"></i>Test Email</h6>
            <p class="text-muted small mb-3">Send a test email to verify your SMTP configuration before going live.</p>
            <div class="mb-3">
                <label class="form-label">Send to</label>
                <input type="email" class="form-control" id="testEmail" placeholder="your@email.com">
            </div>
            <button class="btn btn-outline-success w-100" id="sendTestBtn">
                <i class="bi bi-send me-1"></i>Send Test Email
            </button>
            <div class="mt-3 d-none" id="testResult"></div>
        </div>

        <div class="panel p-4 mt-3">
            <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-2 text-info"></i>Quick Reference</h6>
            <table class="table table-sm table-borderless text-sm mb-0">
                <tr><td class="text-muted">Gmail</td><td>smtp.gmail.com:587 TLS</td></tr>
                <tr><td class="text-muted">Outlook</td><td>smtp.office365.com:587 TLS</td></tr>
                <tr><td class="text-muted">Yahoo</td><td>smtp.mail.yahoo.com:587 TLS</td></tr>
                <tr><td class="text-muted">SendGrid</td><td>smtp.sendgrid.net:587 TLS</td></tr>
            </table>
            <p class="text-muted small mt-2 mb-0">Gmail users: enable "App Passwords" in your Google account if 2FA is active.</p>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePwd').addEventListener('click', function() {
    const input = this.previousElementSibling;
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    this.querySelector('i').className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
});

// Send test email via AJAX
document.getElementById('sendTestBtn').addEventListener('click', function() {
    const to  = document.getElementById('testEmail').value.trim();
    const res = document.getElementById('testResult');
    if (!to) { res.innerHTML = '<div class="alert alert-warning py-1">Enter a recipient email.</div>'; res.classList.remove('d-none'); return; }

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';

    const fd = new FormData();
    fd.append('test_email', to);
    fd.append('_csrf', document.querySelector('meta[name="csrf-token"]').content);

    fetch('<?= url('email-settings/test') ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            res.innerHTML = data.success
                ? '<div class="alert alert-success py-1"><i class="bi bi-check-circle me-1"></i>Test email sent successfully!</div>'
                : '<div class="alert alert-danger py-1"><i class="bi bi-x-circle me-1"></i>' + (data.error || 'Send failed.') + '</div>';
            res.classList.remove('d-none');
        })
        .catch(() => {
            res.innerHTML = '<div class="alert alert-danger py-1">Request failed.</div>';
            res.classList.remove('d-none');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-send me-1"></i>Send Test Email';
        });
});
</script>
