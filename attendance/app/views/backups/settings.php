<?php /** Backup Settings */
$daysOfWeek = [
    0 => 'Sunday',
    1 => 'Monday', 
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
];

$retentionOptions = [7, 14, 30, 60, 90];
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-gear-fill me-2"></i>Backup Settings</h4>
        <small class="text-muted">Configure automatic backup schedules and retention policies</small>
    </div>
    <div>
        <a href="<?= url('backups') ?>" class="btn btn-outline-secondary btn-sm me-2">
            <i class="bi bi-arrow-left"></i> Back to Backups
        </a>
        <form method="post" action="<?= url('backups/settings/run-scheduler') ?>" class="d-inline me-2">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-info btn-sm">
                <i class="bi bi-clock-fill"></i> Run Scheduler Check
            </button>
        </form>
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#testBackupModal">
            <i class="bi bi-play-fill"></i> Test Backup
        </button>
    </div>
</div>

<!-- Backup Status -->
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value <?= $status['enabled'] ? 'text-success' : 'text-muted' ?>">
                <i class="bi bi-<?= $status['enabled'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                <?= $status['enabled'] ? 'Enabled' : 'Disabled' ?>
            </div>
            <div class="text-muted small">Automatic Backup Status</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value text-info">
                <?= $status['last_successful'] ? date('M d, H:i', strtotime($status['last_successful'])) : 'Never' ?>
            </div>
            <div class="text-muted small">Last Successful Backup</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value text-warning">
                <?= $status['last_failed'] ? date('M d, H:i', strtotime($status['last_failed'])) : 'Never' ?>
            </div>
            <div class="text-muted small">Last Failed Backup</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value text-primary">
                <?= $status['next_run'] ? date('M d, H:i', strtotime($status['next_run'])) : 'Not scheduled' ?>
            </div>
            <div class="text-muted small">Next Scheduled Backup</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="metric-card text-center">
            <div class="metric-value"><?= e($status['backup_location'] ?? 'N/A') ?></div>
            <div class="text-muted small">Backup Location</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="metric-card text-center">
            <div class="metric-value"><?= $status['stored_backups'] ?></div>
            <div class="text-muted small">Number of Stored Backups</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="metric-card text-center">
            <div class="metric-value"><?= $status['available_space'] ? round($status['available_space'] / 1073741824, 2) . ' GB' : 'N/A' ?></div>
            <div class="text-muted small">Available Disk Space</div>
        </div>
    </div>
</div>

<!-- Automatic Backup Configuration -->
<div class="panel mb-4">
    <div class="panel-header p-3">
        <h5 class="panel-title"><i class="bi bi-clock-history me-2"></i>Automatic Backup Configuration</h5>
    </div>
    <div class="panel-body p-4">
        <form method="post" action="<?= url('backups/settings/save') ?>">
            <?= csrf_field() ?>
            
            <!-- Enable Automatic Backup -->
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" 
                       value="1" <?= $schedule['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="enabled">
                    Enable Automatic Backup
                </label>
                <div class="text-muted small">When enabled, backups will run automatically according to the schedule below.</div>
            </div>

            <!-- Backup Frequency -->
            <div class="mb-3">
                <label class="form-label">Backup Frequency</label>
                <select class="form-select" id="frequency" name="frequency" onchange="toggleScheduleOptions()">
                    <option value="daily" <?= $schedule['frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $schedule['frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $schedule['frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>

            <!-- Backup Time -->
            <div class="mb-3">
                <label class="form-label">Backup Time</label>
                <input type="time" class="form-control" id="backup_time" name="backup_time" 
                       value="<?= substr($schedule['backup_time'], 0, 5) ?>" required>
                <div class="text-muted small">The time when the backup should run each day/week/month.</div>
            </div>

            <!-- Weekly Schedule -->
            <div class="mb-3" id="weeklySchedule" style="display: <?= $schedule['frequency'] === 'weekly' ? 'block' : 'none' ?>;">
                <label class="form-label">Day of Week</label>
                <select class="form-select" name="weekly_day">
                    <?php foreach ($daysOfWeek as $dayNum => $dayName): ?>
                        <option value="<?= $dayNum ?>" <?= ($schedule['weekly_day'] ?? 1) == $dayNum ? 'selected' : '' ?>>
                            <?= $dayName ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Monthly Schedule -->
            <div class="mb-3" id="monthlySchedule" style="display: <?= $schedule['frequency'] === 'monthly' ? 'block' : 'none' ?>;">
                <label class="form-label">Day of Month</label>
                <select class="form-select" name="monthly_day">
                    <option value="1" <?= ($schedule['monthly_day'] ?? 1) == 1 ? 'selected' : '' ?>>1st</option>
                    <option value="15" <?= ($schedule['monthly_day'] ?? 1) == 15 ? 'selected' : '' ?>>15th</option>
                    <option value="last" <?= ($schedule['monthly_day'] ?? '') === 'last' ? 'selected' : '' ?>>Last Day of Month</option>
                    <?php for ($i = 1; $i <= 31; $i++): ?>
                        <?php if ($i !== 1 && $i !== 15): ?>
                            <option value="<?= $i ?>" <?= ($schedule['monthly_day'] ?? 1) == $i ? 'selected' : '' ?>>
                                <?= $i ?><?php echo $i >= 11 && $i <= 13 ? 'th' : ($i % 10 === 1 ? 'st' : ($i % 10 === 2 ? 'nd' : ($i % 10 === 3 ? 'rd' : 'th'))) ?>
                            </option>
                        <?php endif; ?>
                    <?php endfor; ?>
                </select>
                <div class="text-muted small">If the selected day doesn't exist (e.g., 31 in February), the backup will run on the last day of that month.</div>
            </div>

            <!-- Backup Destination -->
            <div class="mb-3">
                <label class="form-label">Backup Destination</label>
                <input type="text" class="form-control" id="backup_directory" name="backup_directory" 
                       value="<?= e($schedule['backup_directory']) ?>" required>
                <div class="text-muted small">Directory where backup files will be stored. Default: /storage/backups/</div>
            </div>

            <!-- Retention Policy -->
            <div class="mb-3">
                <label class="form-label">Retention Policy - Keep the last</label>
                <div class="row g-2">
                    <?php foreach ($retentionOptions as $count): ?>
                        <div class="col-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="retention_count" 
                                       id="retention_<?= $count ?>" value="<?= $count ?>" 
                                       <?= ($schedule['retention_count'] ?? 7) == $count ? 'checked' : '' ?>>
                                <label class="form-check-label" for="retention_<?= $count ?>">
                                    <?= $count ?> backup<?= $count > 1 ? 's' : '' ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-auto">
                        <div class="input-group input-group-sm" style="width: 120px;">
                            <input type="number" class="form-control" name="retention_count_custom" 
                                   placeholder="Custom" min="1" 
                                   value="<?= !in_array($schedule['retention_count'] ?? 7, $retentionOptions) ? $schedule['retention_count'] : '' ?>">
                            <span class="input-group-text">backups</span>
                        </div>
                    </div>
                </div>
                <div class="text-muted small">Older backups will be automatically deleted beyond this limit.</div>
            </div>

            <!-- Compress Backup -->
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="compress_backup" name="compress_backup" 
                       value="1" <?= $schedule['compress_backup'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="compress_backup">
                    Compress Backup
                </label>
                <div class="text-muted small">Save backup as .zip file to reduce storage space.</div>
            </div>

            <!-- Include Uploaded Files -->
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" id="include_uploads" name="include_uploads" 
                       value="1" <?= $schedule['include_uploads'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="include_uploads">
                    Include Uploaded Files
                </label>
                <div class="text-muted small">Include the uploads directory together with the database backup.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Save Settings
                </button>
                <a href="<?= url('backups') ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleScheduleOptions() {
    const frequency = document.getElementById('frequency').value;
    document.getElementById('weeklySchedule').style.display = frequency === 'weekly' ? 'block' : 'none';
    document.getElementById('monthlySchedule').style.display = frequency === 'monthly' ? 'block' : 'none';
}

// Handle custom retention count
document.querySelectorAll('input[name="retention_count"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelector('input[name="retention_count_custom"]').value = '';
    });
});

document.querySelector('input[name="retention_count_custom"]').addEventListener('input', function() {
    document.querySelectorAll('input[name="retention_count"]').forEach(radio => {
        radio.checked = false;
    });
});

document.querySelector('form').addEventListener('submit', function() {
    const customRetention = document.querySelector('input[name="retention_count_custom"]').value;
    if (customRetention) {
        const radio = document.querySelector('input[name="retention_count"][value="' + customRetention + '"]');
        if (radio) {
            radio.checked = true;
        } else {
            // Create hidden input for custom value
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'retention_count';
            hidden.value = customRetention;
            this.appendChild(hidden);
        }
    }
});
</script>

<!-- Test Backup Modal -->
<div class="modal fade" id="testBackupModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="post" action="<?= url('backups/settings/test') ?>">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-play-fill me-2"></i>Test Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Run a test backup immediately to verify your configuration?</p>
                    <p class="text-muted small mb-0">This will create a manual backup using your current settings.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-play-fill"></i> Run Test
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
