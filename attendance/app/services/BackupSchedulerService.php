<?php

/**
 * BackupSchedulerService
 *
 * Handles automatic backup scheduling and execution.
 * Checks schedules and runs backups when due.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class BackupSchedulerService
{
    private BackupService $backupService;
    private NotificationService $notificationService;
    private SettingsService $settingsService;

    public function __construct()
    {
        $this->backupService = new BackupService();
        $this->notificationService = new NotificationService();
        $this->settingsService = new SettingsService();
    }

    /**
     * Check if a backup is due and execute if needed.
     * This should be called periodically (e.g., every minute via cron).
     */
    public function checkAndRun(): void
    {
        $schedule = $this->getSchedule();
        
        if (!$schedule || !$schedule['enabled']) {
            return;
        }

        $now = new \DateTime();
        $nextRun = $schedule['next_run_at'] ? new \DateTime($schedule['next_run_at']) : null;

        // Check if it's time to run
        if ($nextRun && $now >= $nextRun) {
            $this->runBackup($schedule);
        }
    }

    /**
     * Run a scheduled backup.
     */
    private function runBackup(array $schedule): void
    {
        $start = microtime(true);
        $logId = uuid_v4();

        try {
            // Determine backup type based on frequency
            $type = match($schedule['frequency']) {
                'daily' => 'daily',
                'weekly' => 'weekly',
                'monthly' => 'monthly',
                default => 'manual'
            };

            // Run the backup
            $result = $this->backupService->run($type, 'automatic', null);

            if ($result['success']) {
                // Update schedule with success info
                $this->updateScheduleAfterSuccess($schedule['id']);
                
                // Send success notification
                $this->notificationService->notifyRoles(
                    ['administrator'],
                    'Automatic Backup Completed',
                    sprintf(
                        'Backup completed successfully. Type: %s, Size: %s',
                        $type,
                        $this->formatSize($result['size'] ?? 0)
                    ),
                    'success'
                );

                // Log to job_logs
                $this->logJobExecution('backup_scheduler', 'success', $start);
            } else {
                throw new \RuntimeException($result['error'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            // Update schedule with failure info
            $this->updateScheduleAfterFailure($schedule['id'], $e->getMessage());
            
            // Send failure notification
            $this->notificationService->notifyRoles(
                ['administrator'],
                'Automatic Backup Failed',
                sprintf('Backup failed: %s', $e->getMessage()),
                'danger'
            );

            // Log to job_logs
            $this->logJobExecution('backup_scheduler', 'failed', $start, $e->getMessage());
        }
    }

    /**
     * Get the backup schedule configuration.
     */
    public function getSchedule(): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM backup_schedules WHERE id = 's0000000-0000-0000-0000-000000000001'"
        );
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Update schedule configuration.
     */
    public function updateSchedule(array $data): array
    {
        $schedule = $this->getSchedule();
        if (!$schedule) {
            return ['success' => false, 'error' => 'Schedule configuration not found'];
        }

        // Validate data
        $validation = $this->validateScheduleData($data);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Validate backup directory
        if (!empty($data['backup_directory'])) {
            $dirValidation = $this->validateBackupDirectory($data['backup_directory']);
            if (!$dirValidation['valid']) {
                return ['success' => false, 'error' => $dirValidation['error']];
            }
        }

        // Build update data
        $updateData = [
            'enabled' => (int)($data['enabled'] ?? 0),
            'frequency' => $data['frequency'] ?? 'daily',
            'backup_time' => $data['backup_time'] ?? '00:00:00',
            'backup_directory' => $data['backup_directory'] ?? '/storage/backups/',
            'retention_count' => (int)($data['retention_count'] ?? 7),
            'compress_backup' => (int)($data['compress_backup'] ?? 1),
            'include_uploads' => (int)($data['include_uploads'] ?? 0),
        ];

        // Set frequency-specific fields
        if ($updateData['frequency'] === 'weekly') {
            $updateData['weekly_day'] = (int)($data['weekly_day'] ?? 1);
            $updateData['monthly_day'] = null;
        } elseif ($updateData['frequency'] === 'monthly') {
            $updateData['monthly_day'] = (int)($data['monthly_day'] ?? 1);
            $updateData['weekly_day'] = null;
        } else {
            $updateData['weekly_day'] = null;
            $updateData['monthly_day'] = null;
        }

        // Calculate next run time if enabled
        if ($updateData['enabled']) {
            $updateData['next_run_at'] = $this->calculateNextRun($updateData);
        } else {
            $updateData['next_run_at'] = null;
        }

        // Update database
        $sql = "UPDATE backup_schedules SET 
                enabled = ?, 
                frequency = ?, 
                backup_time = ?, 
                weekly_day = ?, 
                monthly_day = ?, 
                backup_directory = ?, 
                retention_count = ?, 
                compress_backup = ?, 
                include_uploads = ?, 
                next_run_at = ?,
                updated_at = NOW()
                WHERE id = 's0000000-0000-0000-0000-000000000001'";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            $updateData['enabled'],
            $updateData['frequency'],
            $updateData['backup_time'],
            $updateData['weekly_day'],
            $updateData['monthly_day'],
            $updateData['backup_directory'],
            $updateData['retention_count'],
            $updateData['compress_backup'],
            $updateData['include_uploads'],
            $updateData['next_run_at']
        ]);

        return ['success' => true, 'next_run_at' => $updateData['next_run_at']];
    }

    /**
     * Get backup status information.
     */
    public function getStatus(): array
    {
        $schedule = $this->getSchedule();
        if (!$schedule) {
            return [
                'enabled' => false,
                'last_successful' => null,
                'last_failed' => null,
                'next_run' => null,
                'backup_location' => null,
                'stored_backups' => 0,
                'available_space' => 0
            ];
        }

        // Get last successful backup
        $stmt = Database::connection()->prepare(
            "SELECT created_at FROM backup_logs 
             WHERE status = 'success' AND trigger_type = 'automatic'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute();
        $lastSuccess = $stmt->fetchColumn();

        // Get last failed backup
        $stmt = Database::connection()->prepare(
            "SELECT created_at FROM backup_logs 
             WHERE status = 'failed' AND trigger_type = 'automatic'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute();
        $lastFailure = $stmt->fetchColumn();

        // Count stored backups
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM backup_logs WHERE status = 'success'"
        );
        $stmt->execute();
        $storedBackups = (int)$stmt->fetchColumn();

        // Get available disk space
        $backupDir = $schedule['backup_directory'];
        $freeSpace = 0;
        if (is_dir($backupDir)) {
            $freeSpace = disk_free_space($backupDir);
        }

        return [
            'enabled' => (bool)$schedule['enabled'],
            'last_successful' => $lastSuccess,
            'last_failed' => $lastFailure,
            'next_run' => $schedule['next_run_at'],
            'backup_location' => $schedule['backup_directory'],
            'stored_backups' => $storedBackups,
            'available_space' => $freeSpace
        ];
    }

    /**
     * Calculate the next run time based on schedule.
     */
    private function calculateNextRun(array $schedule): ?string
    {
        $now = new \DateTime();
        $backupTime = explode(':', $schedule['backup_time']);
        $hour = (int)$backupTime[0];
        $minute = (int)$backupTime[1];

        $nextRun = clone $now;
        $nextRun->setTime($hour, $minute, 0);

        switch ($schedule['frequency']) {
            case 'daily':
                if ($nextRun <= $now) {
                    $nextRun->modify('+1 day');
                }
                break;

            case 'weekly':
                $targetDay = (int)($schedule['weekly_day'] ?? 1); // 0=Sunday, 1=Monday, etc.
                $currentDay = (int)$now->format('w');
                
                $daysToAdd = $targetDay - $currentDay;
                if ($daysToAdd <= 0) {
                    $daysToAdd += 7;
                }
                
                $nextRun->modify("+{$daysToAdd} days");
                if ($nextRun <= $now) {
                    $nextRun->modify('+1 week');
                }
                break;

            case 'monthly':
                $targetDay = (int)($schedule['monthly_day'] ?? 1);
                $currentDay = (int)$now->format('j');
                
                $nextRun->setDate((int)$nextRun->format('Y'), (int)$nextRun->format('m'), 1);
                
                // Handle last day of month
                if ($targetDay === null || $targetDay > 28) {
                    $nextRun->modify('last day of this month');
                } else {
                    $nextRun->setDate((int)$nextRun->format('Y'), (int)$nextRun->format('m'), $targetDay);
                }
                
                if ($nextRun <= $now) {
                    $nextRun->modify('+1 month');
                    if ($targetDay === null || $targetDay > 28) {
                        $nextRun->modify('last day of this month');
                    } else {
                        $nextRun->setDate((int)$nextRun->format('Y'), (int)$nextRun->format('m'), $targetDay);
                    }
                }
                break;
        }

        return $nextRun->format('Y-m-d H:i:s');
    }

    /**
     * Update schedule after successful backup.
     */
    private function updateScheduleAfterSuccess(string $scheduleId): void
    {
        $schedule = $this->getSchedule();
        if (!$schedule) return;

        $nextRun = $this->calculateNextRun($schedule);

        Database::connection()->prepare(
            "UPDATE backup_schedules 
             SET last_run_at = NOW(), 
                 last_success_at = NOW(), 
                 next_run_at = ? 
             WHERE id = ?"
        )->execute([$nextRun, $scheduleId]);
    }

    /**
     * Update schedule after failed backup.
     */
    private function updateScheduleAfterFailure(string $scheduleId, string $error): void
    {
        $schedule = $this->getSchedule();
        if (!$schedule) return;

        // Retry in 1 hour on failure
        $nextRun = (new \DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');

        Database::connection()->prepare(
            "UPDATE backup_schedules 
             SET last_run_at = NOW(), 
                 last_failure_at = NOW(), 
                 next_run_at = ? 
             WHERE id = ?"
        )->execute([$nextRun, $scheduleId]);
    }

    /**
     * Validate schedule data.
     */
    private function validateScheduleData(array $data): array
    {
        // Validate retention count
        if (isset($data['retention_count']) && ((int)$data['retention_count'] < 1)) {
            return ['valid' => false, 'error' => 'Retention count must be at least 1'];
        }

        // Validate backup time (accept HH:MM or HH:MM:SS)
        if (isset($data['backup_time'])) {
            // Convert HH:MM to HH:MM:00 for validation
            $timeToValidate = $data['backup_time'];
            if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $timeToValidate)) {
                $timeToValidate .= ':00';
            }
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $timeToValidate)) {
                return ['valid' => false, 'error' => 'Invalid backup time format. Use HH:MM'];
            }
            // Normalize to HH:MM:00 for storage
            $data['backup_time'] = $timeToValidate;
        }

        // Validate frequency
        if (isset($data['frequency'])) {
            $validFrequencies = ['daily', 'weekly', 'monthly'];
            if (!in_array($data['frequency'], $validFrequencies)) {
                return ['valid' => false, 'error' => 'Invalid frequency'];
            }
        }

        // Validate weekly day
        if (isset($data['frequency']) && $data['frequency'] === 'weekly') {
            if (!isset($data['weekly_day']) || $data['weekly_day'] < 0 || $data['weekly_day'] > 6) {
                return ['valid' => false, 'error' => 'Weekly day must be between 0 (Sunday) and 6 (Saturday)'];
            }
        }

        // Validate monthly day
        if (isset($data['frequency']) && $data['frequency'] === 'monthly') {
            if (!isset($data['monthly_day']) || $data['monthly_day'] < 1 || $data['monthly_day'] > 31) {
                return ['valid' => false, 'error' => 'Monthly day must be between 1 and 31'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate backup directory.
     */
    private function validateBackupDirectory(string $directory): array
    {
        // Sanitize path to prevent directory traversal
        $directory = str_replace(['..', '\\'], ['', '/'], $directory);
        $directory = rtrim($directory, '/');

        // Try to create directory if it doesn't exist
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0775, true)) {
                return ['valid' => false, 'error' => 'Cannot create backup directory. Check permissions.'];
            }
        }

        // Check if directory is writable
        if (!is_writable($directory)) {
            return ['valid' => false, 'error' => 'Backup directory is not writable. Check permissions.'];
        }

        return ['valid' => true];
    }

    /**
     * Log job execution to job_logs table.
     */
    private function logJobExecution(string $jobName, string $status, float $start, ?string $error = null): void
    {
        $duration = (int) round(microtime(true) - $start);
        
        Database::connection()->prepare(
            "INSERT INTO job_logs (job_name, status, output, error, started_at, finished_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        )->execute([$jobName, $status, "Duration: {$duration}s", $error]);
    }

    /**
     * Format file size for display.
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Clean up old backups based on retention policy.
     */
    public function cleanupOldBackups(): int
    {
        $schedule = $this->getSchedule();
        if (!$schedule) {
            return 0;
        }

        $retentionCount = (int)$schedule['retention_count'];
        
        // Get all successful automatic backups, ordered by date (newest first)
        $stmt = Database::connection()->prepare(
            "SELECT id, filepath FROM backup_logs 
             WHERE status = 'success' AND trigger_type = 'automatic'
             ORDER BY created_at DESC"
        );
        $stmt->execute();
        $backups = $stmt->fetchAll();

        // Keep only the most recent N backups
        $deleted = 0;
        foreach (array_slice($backups, $retentionCount) as $backup) {
            if ($backup['filepath'] && is_file($backup['filepath'])) {
                @unlink($backup['filepath']);
            }
            Database::connection()->prepare('DELETE FROM backup_logs WHERE id = ?')
                ->execute([$backup['id']]);
            $deleted++;
        }

        return $deleted;
    }
}
