<?php
/**
 * Backup Scheduler Cron Runner
 * 
 * This script should be scheduled to run periodically (e.g., every minute)
 * via Windows Task Scheduler or cron to check if automatic backups are due.
 * 
 * Windows Task Scheduler setup:
 * - Trigger: Daily, repeat every 1 minute
 * - Action: Run a program
 * - Program: php.exe (e.g., C:\xampp\php\php.exe)
 * - Arguments: path\to\attendance\cron\backup_scheduler.php
 * - Start in: path\to\attendance
 * 
 * Cron setup (Linux):
 * * * * * * php /path/to/attendance/cron/backup_scheduler.php
 */

// Change to the application directory
chdir(dirname(__DIR__));

// Load required files
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/BackupSchedulerService.php';

use App\Services\BackupSchedulerService;

try {
    $scheduler = new BackupSchedulerService();
    $scheduler->checkAndRun();
    
    // Log successful execution (optional)
    // file_put_contents(__DIR__ . '/scheduler.log', date('Y-m-d H:i:s') . " - Scheduler check completed\n", FILE_APPEND);
    
} catch (\Throwable $e) {
    // Log error
    $errorMsg = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/scheduler_error.log', $errorMsg, FILE_APPEND);
    
    // Optionally send email notification about scheduler failure
    // This would require email configuration
    exit(1);
}
