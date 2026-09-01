<?php

/**
 * BackupSettingsController
 *
 * Manages automatic backup configuration and settings.
 * Routes:
 *   GET  /backups/settings       — Backup settings page
 *   POST /backups/settings/save  — Save backup settings
 *   POST /backups/settings/test  — Test backup configuration
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BackupSchedulerService;

final class BackupSettingsController extends BaseController
{
    private BackupSchedulerService $schedulerService;

    public function __construct()
    {
        $this->schedulerService = new BackupSchedulerService();
    }

    public function index(): void
    {
        require_role(['administrator']);

        $schedule = $this->schedulerService->getSchedule();
        $status = $this->schedulerService->getStatus();

        $this->render('backups/settings', [
            'title' => 'Backup Settings',
            'schedule' => $schedule,
            'status' => $status,
        ]);
    }

    public function save(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $data = [
            'enabled' => (int)($_POST['enabled'] ?? 0),
            'frequency' => $_POST['frequency'] ?? 'daily',
            'backup_time' => $_POST['backup_time'] ?? '00:00:00',
            'weekly_day' => $_POST['weekly_day'] ?? null,
            'monthly_day' => $_POST['monthly_day'] ?? null,
            'backup_directory' => $_POST['backup_directory'] ?? '/storage/backups/',
            'retention_count' => (int)($_POST['retention_count_custom'] ?? $_POST['retention_count'] ?? 7),
            'compress_backup' => (int)($_POST['compress_backup'] ?? 1),
            'include_uploads' => (int)($_POST['include_uploads'] ?? 0),
        ];

        $result = $this->schedulerService->updateSchedule($data);

        if ($result['success']) {
            (new AuditService())->log('BACKUP_SETTINGS_UPDATED', 'backup_settings', null, null, $data);
            flash('success', 'Backup settings saved successfully. Next scheduled backup: ' . ($result['next_run_at'] ?? 'Not scheduled'));
        } else {
            flash('error', 'Failed to save backup settings: ' . $result['error']);
        }

        redirect('backups/settings');
    }

    public function test(): void
    {
        require_role(['administrator']);
        verify_csrf();

        // Run a test backup immediately
        $backupService = new \App\Services\BackupService();
        $user = current_user();
        $result = $backupService->run('manual', 'manual', $user['id']);

        if ($result['success']) {
            flash('success', 'Test backup completed successfully.');
        } else {
            flash('error', 'Test backup failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        redirect('backups/settings');
    }

    public function runScheduler(): void
    {
        require_role(['administrator']);
        verify_csrf();

        // Manually trigger the scheduler check
        $this->schedulerService->checkAndRun();
        
        flash('success', 'Scheduler check executed. If a backup was due, it should have run.');
        redirect('backups/settings');
    }
}
