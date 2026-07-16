<?php

/**
 * Cron: database_health_check.php
 *
 * Checks database integrity, table health, disk usage, and
 * sends alerts to administrators if issues are detected.
 * Also repairs tables flagged as crashed.
 *
 * Recommended schedule (Windows Task Scheduler):
 *   Trigger  : Daily, 4:00 AM
 *   Action   : "C:\xampp\php\php.exe"
 *   Arguments: "C:\xampp\htdocs\attendance-ims\attendance\cron\database_health_check.php"
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\NotificationService;
use App\Services\SettingsService;

if (!cron_lock('database_health_check')) {
    cron_log('Already running. Exiting.', 'WARN');
    exit(0);
}

$jobId   = job_start('database_health_check');
$cfg     = new SettingsService();
$db      = Database::connection();
$alerts  = [];
$output  = [];

try {
    /* ── 1. Check all tables ─────────────────────────────────── */
    cron_log('Running CHECK TABLE on all tables...');
    $tables = $db->query(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
    )->fetchAll(\PDO::FETCH_COLUMN);

    $crashed    = [];
    $needRepair = [];

    foreach ($tables as $table) {
        $result = $db->query("CHECK TABLE `{$table}` FAST")->fetchAll();
        foreach ($result as $row) {
            if (isset($row['Msg_type']) && in_array($row['Msg_type'], ['error', 'warning'], true)) {
                if (stripos($row['Msg_text'] ?? '', 'crash') !== false) {
                    $crashed[] = $table;
                } else {
                    $needRepair[] = $table . ': ' . ($row['Msg_text'] ?? '');
                }
            }
        }
    }

    $output[] = 'Tables checked: ' . count($tables);
    cron_log('Tables checked: ' . count($tables));

    /* ── 2. Repair crashed tables ───────────────────────────── */
    if (!empty($crashed)) {
        foreach ($crashed as $table) {
            cron_log("Repairing crashed table: {$table}", 'WARN');
            $db->exec("REPAIR TABLE `{$table}`");
        }
        $alerts[] = 'Crashed and repaired tables: ' . implode(', ', $crashed);
    }

    if (!empty($needRepair)) {
        $alerts[] = 'Tables with warnings: ' . implode('; ', $needRepair);
    }

    /* ── 3. Database size ───────────────────────────────────── */
    $dbSizeBytes = (int) $db->query(
        "SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()"
    )->fetchColumn();
    $dbSizeMB = round($dbSizeBytes / 1048576, 2);
    $output[] = "Database size: {$dbSizeMB} MB";
    cron_log("Database size: {$dbSizeMB} MB");

    /* ── 4. Disk usage ──────────────────────────────────────── */
    $docRoot     = CRON_ROOT;
    $diskTotal   = disk_total_space($docRoot);
    $diskFree    = disk_free_space($docRoot);
    $diskPercent = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;
    $output[]    = "Disk usage: {$diskPercent}%";
    cron_log("Disk usage: {$diskPercent}% ({$diskFree} bytes free)");

    if ($diskPercent > 90) {
        $alerts[] = "CRITICAL: Disk usage is at {$diskPercent}%. Free space: " . round($diskFree / 1073741824, 2) . ' GB';
    } elseif ($diskPercent > 80) {
        $alerts[] = "WARNING: Disk usage is at {$diskPercent}%.";
    }

    /* ── 5. Failed background jobs in last 24h ──────────────── */
    $failedJobs = (int) $db->query(
        "SELECT COUNT(*) FROM job_logs WHERE status = 'failed' AND started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    )->fetchColumn();
    $output[] = "Failed jobs (24h): {$failedJobs}";
    if ($failedJobs > 0) {
        $alerts[] = "{$failedJobs} background job(s) failed in the last 24 hours.";
        cron_log("Failed background jobs: {$failedJobs}", 'WARN');
    }

    /* ── 6. Permanent email failures ────────────────────────── */
    $maxRetries      = (int) $cfg->get('email_max_retries', 5);
    $permanentFailed = (int) $db->prepare(
        "SELECT COUNT(*) FROM email_logs WHERE status = 'failed' AND retry_count >= ?"
    )->execute([$maxRetries]) ? (int) $db->prepare(
        "SELECT COUNT(*) FROM email_logs WHERE status = 'failed' AND retry_count >= ?"
    )->execute([$maxRetries]) : 0;

    // Re-query properly
    $pfStmt = $db->prepare("SELECT COUNT(*) FROM email_logs WHERE status = 'failed' AND retry_count >= ?");
    $pfStmt->execute([$maxRetries]);
    $permanentFailed = (int) $pfStmt->fetchColumn();

    if ($permanentFailed > 0) {
        $alerts[] = "{$permanentFailed} email(s) permanently failed delivery.";
    }

    /* ── 7. Last backup age ─────────────────────────────────── */
    $lastBackup = $db->query(
        "SELECT created_at FROM backup_logs WHERE status='success' ORDER BY created_at DESC LIMIT 1"
    )->fetchColumn();

    if (!$lastBackup) {
        $alerts[] = 'No successful backup on record.';
    } else {
        $backupAge = (int) floor((time() - strtotime($lastBackup)) / 86400);
        $output[]  = "Last backup: {$backupAge} day(s) ago";
        if ($backupAge > 3) {
            $alerts[] = "Last successful backup was {$backupAge} days ago. Check backup configuration.";
        }
    }

    /* ── 8. Send alert notifications ────────────────────────── */
    if (!empty($alerts)) {
        $message = implode("\n", $alerts);
        cron_log("Health alerts detected:\n{$message}", 'WARN');
        (new NotificationService())->notifyRoles(
            ['administrator'],
            'Database Health Alert',
            implode(' | ', $alerts),
            count($crashed) > 0 ? 'danger' : 'warning'
        );
    } else {
        cron_log('All health checks passed.');
    }

    $summary = implode('; ', $output) . (empty($alerts) ? ' — No issues.' : ' — Alerts: ' . count($alerts));
    job_finish($jobId, true, $summary);
    cron_log("Health check complete. {$summary}");

} catch (\Throwable $e) {
    cron_log($e->getMessage(), 'ERROR');
    job_finish($jobId, false, '', $e->getMessage());
    exit(1);
}
