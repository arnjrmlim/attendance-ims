<?php

/**
 * Cron: daily_backup.php
 *
 * Creates a database backup and applies retention cleanup.
 * On Sundays, also runs a weekly backup.
 * On the 1st of each month, also runs a monthly backup.
 *
 * Recommended schedule (Windows Task Scheduler):
 *   Trigger  : Daily, 1:00 AM
 *   Action   : "C:\xampp\php\php.exe"
 *   Arguments: "C:\xampp\htdocs\attendance-ims\attendance\cron\daily_backup.php"
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\BackupService;
use App\Services\NotificationService;
use App\Services\SettingsService;

if (!cron_lock('daily_backup')) {
    cron_log('Already running. Exiting.', 'WARN');
    exit(0);
}

$jobId  = job_start('daily_backup');
$cfg    = new SettingsService();
$backup = new BackupService();

if (!(bool)(int) $cfg->get('backup_enabled', 1)) {
    cron_log('Backups disabled in settings. Exiting.');
    job_finish($jobId, true, 'Backups disabled.');
    exit(0);
}

$errors = [];

try {
    /* ── Daily backup ──────────────────────────────────────── */
    if ((bool)(int) $cfg->get('backup_daily', 1)) {
        cron_log('Running daily backup...');
        $result = $backup->run('daily', 'automatic', null);
        if ($result['success']) {
            cron_log("Daily backup OK — file: {$result['file']} ({$result['size']} bytes)");
        } else {
            $errors[] = 'Daily backup failed: ' . ($result['error'] ?? '');
            cron_log('Daily backup FAILED: ' . ($result['error'] ?? ''), 'ERROR');
        }
    }

    /* ── Weekly backup (every Sunday = day 0) ─────────────── */
    if ((bool)(int) $cfg->get('backup_weekly', 1) && (int) date('w') === 0) {
        cron_log('Running weekly backup...');
        $result = $backup->run('weekly', 'automatic', null);
        if ($result['success']) {
            cron_log("Weekly backup OK — file: {$result['file']}");
        } else {
            $errors[] = 'Weekly backup failed: ' . ($result['error'] ?? '');
            cron_log('Weekly backup FAILED: ' . ($result['error'] ?? ''), 'ERROR');
        }
    }

    /* ── Monthly backup (1st of month) ────────────────────── */
    if ((bool)(int) $cfg->get('backup_monthly', 1) && (int) date('j') === 1) {
        cron_log('Running monthly backup...');
        $result = $backup->run('monthly', 'automatic', null);
        if ($result['success']) {
            cron_log("Monthly backup OK — file: {$result['file']}");
        } else {
            $errors[] = 'Monthly backup failed: ' . ($result['error'] ?? '');
            cron_log('Monthly backup FAILED: ' . ($result['error'] ?? ''), 'ERROR');
        }
    }

    if (!empty($errors)) {
        // Notify admins
        (new NotificationService())->notifyRoles(
            ['administrator'],
            'Backup Failure',
            'One or more scheduled backups failed: ' . implode('; ', $errors),
            'danger'
        );
        job_finish($jobId, false, '', implode('; ', $errors));
        exit(1);
    }

    job_finish($jobId, true, 'All scheduled backups completed.');
    cron_log('Backup cron finished.');

} catch (\Throwable $e) {
    cron_log($e->getMessage(), 'ERROR');
    job_finish($jobId, false, '', $e->getMessage());
    exit(1);
}
