<?php

/**
 * Cron: retry_failed_emails.php
 *
 * Retries delivery of queued/failed email_log entries.
 * After max_retries is exceeded, the entry status becomes 'failed'
 * permanently and administrators are notified once.
 *
 * Recommended schedule (Windows Task Scheduler):
 *   Trigger  : Every 1 hour (repeat task every 60 minutes indefinitely)
 *   Action   : "C:\xampp\php\php.exe"
 *   Arguments: "C:\xampp\htdocs\attendance-ims\attendance\cron\retry_failed_emails.php"
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\SettingsService;

if (!cron_lock('retry_failed_emails')) {
    cron_log('Already running. Exiting.', 'WARN');
    exit(0);
}

$jobId  = job_start('retry_failed_emails');
$cfg    = new SettingsService();

try {
    $maxRetries = (int) $cfg->get('email_max_retries', 5);
    $db         = Database::connection();

    // Find emails eligible for retry
    $stmt = $db->prepare(
        "SELECT id, recipient, subject, report_period, body_preview, attachment_path, retry_count
         FROM email_logs
         WHERE status IN ('queued','retrying')
           AND retry_count < ?
           AND (next_retry_at IS NULL OR next_retry_at <= NOW())
         ORDER BY created_at ASC
         LIMIT 20"
    );
    $stmt->execute([$maxRetries]);
    $pending = $stmt->fetchAll();

    if (empty($pending)) {
        cron_log('No emails pending retry.');
        job_finish($jobId, true, 'No pending emails.');
        exit(0);
    }

    cron_log('Found ' . count($pending) . ' email(s) to retry.');

    $svc       = new EmailService();
    $sent      = 0;
    $stillFail = 0;

    foreach ($pending as $row) {
        cron_log("Retrying email_log {$row['id']} (attempt " . ($row['retry_count'] + 1) . "/{$maxRetries}) to {$row['recipient']}");

        // Rebuild a minimal body from the preview (full body not stored)
        $body = '<p>This is an automated retry of a previously failed report email.</p>'
              . '<p><strong>Period:</strong> ' . htmlspecialchars($row['report_period'] ?? '') . '</p>'
              . '<p><strong>Original subject:</strong> ' . htmlspecialchars($row['subject']) . '</p>'
              . ($row['body_preview'] ? '<hr><p>' . nl2br(htmlspecialchars($row['body_preview'])) . '</p>' : '');

        $ok = $svc->resend($row['id']);

        if ($ok) {
            cron_log("Sent OK: {$row['id']}");
            $sent++;
        } else {
            cron_log("Still failed: {$row['id']}", 'WARN');
            $stillFail++;
        }
    }

    // Notify admins about permanently failed emails (retry_count >= max)
    $permanentFails = $db->prepare(
        "SELECT id FROM email_logs WHERE status = 'failed' AND retry_count >= ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    );
    $permanentFails->execute([$maxRetries]);
    $permaCount = count($permanentFails->fetchAll());
    if ($permaCount > 0) {
        (new NotificationService())->notifyRoles(
            ['administrator'],
            'Emails Permanently Failed',
            "{$permaCount} email(s) have permanently failed after {$maxRetries} retries. Check Email Logs for details.",
            'danger'
        );
        cron_log("Notified admins: {$permaCount} permanently failed email(s).", 'WARN');
    }

    $summary = "Retried: " . count($pending) . ". Sent: {$sent}. Still failing: {$stillFail}.";
    job_finish($jobId, true, $summary);
    cron_log("Retry run complete. {$summary}");

} catch (\Throwable $e) {
    cron_log($e->getMessage(), 'ERROR');
    job_finish($jobId, false, '', $e->getMessage());
    exit(1);
}
