<?php

/**
 * Cron: system_cleanup.php
 *
 * Performs routine database and file system maintenance:
 *   - Deletes expired sessions
 *   - Deletes old audit logs beyond retention period
 *   - Deletes old job logs
 *   - Optimizes MySQL tables
 *   - Deletes stale temp/upload files
 *   - Publishes scheduled announcements
 *
 * Recommended schedule (Windows Task Scheduler):
 *   Trigger  : Daily, 2:00 AM
 *   Action   : "C:\xampp\php\php.exe"
 *   Arguments: "C:\xampp\htdocs\attendance-ims\attendance\cron\system_cleanup.php"
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\AnnouncementService;
use App\Services\SettingsService;

if (!cron_lock('system_cleanup')) {
    cron_log('Already running. Exiting.', 'WARN');
    exit(0);
}

$jobId  = job_start('system_cleanup');
$cfg    = new SettingsService();
$db     = Database::connection();
$output = [];

try {
    /* ── 1. Expired sessions ───────────────────────────────── */
    $sessionDays   = max(1, (int) $cfg->get('session_cleanup_days', 7));
    $sessionCutoff = date('Y-m-d H:i:s', time() - $sessionDays * 86400);
    // user_sessions stores last_activity as UNIX timestamp
    $stmt = $db->prepare(
        'DELETE FROM user_sessions WHERE last_activity < ?'
    );
    $stmt->execute([strtotime($sessionCutoff)]);
    $sessDeleted = $stmt->rowCount();
    $output[] = "Sessions deleted: {$sessDeleted}";
    cron_log("Expired sessions removed: {$sessDeleted}");

    /* ── 2. Old audit logs ─────────────────────────────────── */
    $logDays = max(30, (int) $cfg->get('log_retention_days', 90));
    $stmt = $db->prepare('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->execute([$logDays]);
    $auditDeleted = $stmt->rowCount();
    $output[] = "Audit logs deleted: {$auditDeleted}";
    cron_log("Old audit logs removed: {$auditDeleted} (>{$logDays} days)");

    /* ── 3. Old job logs (keep last 30 days) ───────────────── */
    $stmt = $db->prepare('DELETE FROM job_logs WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $stmt->execute();
    $jobsDeleted = $stmt->rowCount();
    $output[] = "Job logs deleted: {$jobsDeleted}";
    cron_log("Old job logs removed: {$jobsDeleted}");

    /* ── 4. Old email logs (keep last 90 days, sent only) ──── */
    $stmt = $db->prepare(
        "DELETE FROM email_logs WHERE status = 'sent' AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );
    $stmt->execute();
    $emailDeleted = $stmt->rowCount();
    $output[] = "Old email logs deleted: {$emailDeleted}";
    cron_log("Old sent email logs removed: {$emailDeleted}");

    /* ── 5. Optimize tables ────────────────────────────────── */
    $tables = [
        'attendance', 'attendance_summary', 'audit_logs',
        'notifications', 'user_sessions', 'job_logs', 'email_logs',
    ];
    foreach ($tables as $table) {
        try {
            $db->exec("OPTIMIZE TABLE `{$table}`");
        } catch (\Throwable) {
            // Non-critical — InnoDB may return "Table does not support optimize"
        }
    }
    cron_log('Table optimization complete.');
    $output[] = 'Tables optimized: ' . count($tables);

    /* ── 6. Stale temp upload files (> 24h) ───────────────── */
    $uploadDir = dirname(__DIR__) . '/uploads';
    $deleted   = 0;
    if (is_dir($uploadDir)) {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->isFile() && (time() - $file->getMTime()) > 86400 * 2) {
                // Only delete files in the /tmp sub-folder if it exists
                if (str_contains($file->getPath(), 'tmp')) {
                    @unlink($file->getPathname());
                    $deleted++;
                }
            }
        }
    }
    $output[] = "Temp files deleted: {$deleted}";
    cron_log("Stale temp files removed: {$deleted}");

    /* ── 7. Publish scheduled announcements ───────────────── */
    $published = (new AnnouncementService())->publishScheduled();
    $output[]  = "Announcements published: {$published}";
    cron_log("Scheduled announcements published: {$published}");

    /* ── 8. Expired notifications (> 60 days) ─────────────── */
    $stmt = $db->prepare(
        'DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY) AND is_read = 1'
    );
    $stmt->execute();
    $notifDeleted = $stmt->rowCount();
    $output[] = "Old notifications deleted: {$notifDeleted}";
    cron_log("Old read notifications removed: {$notifDeleted}");

    $summary = implode('; ', $output);
    job_finish($jobId, true, $summary);
    cron_log("Cleanup complete: {$summary}");

} catch (\Throwable $e) {
    cron_log($e->getMessage(), 'ERROR');
    job_finish($jobId, false, '', $e->getMessage());
    exit(1);
}
