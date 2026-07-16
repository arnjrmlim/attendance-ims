<?php

/**
 * SystemHealthService
 *
 * Gathers real-time metrics about the server, PHP, database and background jobs.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SystemHealthService
{
    public function report(): array
    {
        $db = Database::connection();

        /* ── PHP / Runtime ──────────────────────────────────── */
        $phpVersion     = PHP_VERSION;
        $phpMemoryLimit = ini_get('memory_limit');
        $phpMaxExec     = ini_get('max_execution_time');

        /* ── Apache / Web Server ────────────────────────────── */
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';

        /* ── Database ────────────────────────────────────────── */
        $dbVersion = (string) $db->query('SELECT VERSION()')->fetchColumn();
        $dbSize    = $this->databaseSize();
        $tableCount = (int) $db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();

        /* ── Storage / Disk ──────────────────────────────────── */
        $docRoot     = defined('CRON_ROOT') ? CRON_ROOT : dirname(__DIR__, 2);
        $diskTotal   = disk_total_space($docRoot);
        $diskFree    = disk_free_space($docRoot);
        $diskUsed    = $diskTotal - $diskFree;
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        /* ── Backup ──────────────────────────────────────────── */
        $lastBackup = $db->query(
            "SELECT filename, filesize, created_at FROM backup_logs WHERE status = 'success' ORDER BY created_at DESC LIMIT 1"
        )->fetch();

        /* ── Email ───────────────────────────────────────────── */
        $lastEmail = $db->query(
            "SELECT recipient, subject, sent_at FROM email_logs WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 1"
        )->fetch();
        $failedEmails = (int) $db->query(
            "SELECT COUNT(*) FROM email_logs WHERE status IN ('failed','queued') AND retry_count >= (SELECT COALESCE(MAX(CAST(value AS SIGNED)),5) FROM settings WHERE `key`='email_max_retries')"
        )->fetchColumn();

        /* ── Background Jobs ─────────────────────────────────── */
        $recentJobs  = $db->query(
            "SELECT job_name, status, started_at, finished_at FROM job_logs ORDER BY started_at DESC LIMIT 10"
        )->fetchAll();
        $failedJobs  = (int) $db->query(
            "SELECT COUNT(*) FROM job_logs WHERE status = 'failed' AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();

        /* ── Session / Auth ──────────────────────────────────── */
        $activeSessions = (int) $db->query(
            'SELECT COUNT(*) FROM user_sessions WHERE last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 HOUR))'
        )->fetchColumn();

        /* ── Pending queues ──────────────────────────────────── */
        $pendingManual      = (int) $db->query("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Pending'")->fetchColumn();
        $pendingCorrections = (int) $db->query("SELECT COUNT(*) FROM attendance_corrections WHERE status = 'Pending'")->fetchColumn();
        $pendingLeaves      = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();

        /* ── Uptime (approx via server) ──────────────────────── */
        $serverUptime = $this->serverUptime();

        /* ── Alerts ──────────────────────────────────────────── */
        $alerts = [];
        if ($diskPercent > 90) {
            $alerts[] = ['level' => 'danger',  'message' => "Disk usage is critically high ({$diskPercent}%)"];
        } elseif ($diskPercent > 75) {
            $alerts[] = ['level' => 'warning', 'message' => "Disk usage is high ({$diskPercent}%)"];
        }
        if ($failedJobs > 0) {
            $alerts[] = ['level' => 'warning', 'message' => "{$failedJobs} background job(s) failed in the last 7 days"];
        }
        if ($failedEmails > 0) {
            $alerts[] = ['level' => 'warning', 'message' => "{$failedEmails} email(s) permanently failed delivery"];
        }
        if (!$lastBackup) {
            $alerts[] = ['level' => 'warning', 'message' => 'No successful backup found. Configure and run a backup.'];
        }

        return [
            'php_version'        => $phpVersion,
            'php_memory_limit'   => $phpMemoryLimit,
            'php_max_exec'       => $phpMaxExec,
            'server_software'    => $serverSoftware,
            'db_version'         => $dbVersion,
            'db_size_bytes'      => $dbSize,
            'db_size_human'      => $this->humanBytes($dbSize),
            'db_table_count'     => $tableCount,
            'disk_total'         => $this->humanBytes($diskTotal),
            'disk_free'          => $this->humanBytes($diskFree),
            'disk_used'          => $this->humanBytes($diskUsed),
            'disk_percent'       => $diskPercent,
            'last_backup'        => $lastBackup,
            'last_email'         => $lastEmail,
            'failed_emails'      => $failedEmails,
            'recent_jobs'        => $recentJobs,
            'failed_jobs'        => $failedJobs,
            'active_sessions'    => $activeSessions,
            'pending_manual'     => $pendingManual,
            'pending_corrections'=> $pendingCorrections,
            'pending_leaves'     => $pendingLeaves,
            'server_uptime'      => $serverUptime,
            'alerts'             => $alerts,
            'generated_at'       => date('Y-m-d H:i:s'),
        ];
    }

    private function databaseSize(): int
    {
        $stmt = Database::connection()->query(
            "SELECT COALESCE(SUM(data_length + index_length), 0)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()"
        );
        return (int) $stmt->fetchColumn();
    }

    private function humanBytes(int|float $bytes): string
    {
        if ($bytes < 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function serverUptime(): string
    {
        // Works on Windows with XAMPP
        $uptime = '';
        if (PHP_OS_FAMILY === 'Windows') {
            exec('net statistics workstation 2>NUL', $lines);
            foreach ($lines as $line) {
                if (stripos($line, 'since') !== false || stripos($line, 'Statistics since') !== false) {
                    $uptime = trim($line);
                    break;
                }
            }
        }
        return $uptime ?: 'N/A';
    }
}
