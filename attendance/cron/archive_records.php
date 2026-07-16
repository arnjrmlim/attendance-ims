<?php

/**
 * Cron: archive_records.php
 *
 * Archives old attendance records from the hot `attendance` and
 * `attendance_summary` tables into dedicated archive tables.
 * Archiving keeps the live tables lean without destroying data.
 *
 * Archive tables are created automatically on first run.
 *
 * Recommended schedule (Windows Task Scheduler):
 *   Trigger  : Monthly, Day 2, 3:00 AM
 *   Action   : "C:\xampp\php\php.exe"
 *   Arguments: "C:\xampp\htdocs\attendance-ims\attendance\cron\archive_records.php"
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\AuditService;
use App\Services\SettingsService;

if (!cron_lock('archive_records')) {
    cron_log('Already running. Exiting.', 'WARN');
    exit(0);
}

$jobId  = job_start('archive_records');
$cfg    = new SettingsService();
$db     = Database::connection();

$archiveMonths = max(6, (int) $cfg->get('archive_after_months', 12));
$cutoffDate    = date('Y-m-d', strtotime("-{$archiveMonths} months"));

cron_log("Archiving records older than {$cutoffDate} ({$archiveMonths} months)...");

try {
    /* ── Ensure archive tables exist ────────────────────────── */
    $db->exec(
        "CREATE TABLE IF NOT EXISTS `attendance_archive` LIKE `attendance`"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS `attendance_summary_archive` LIKE `attendance_summary`"
    );

    /* ── Archive raw attendance ─────────────────────────────── */
    $stmt = $db->prepare(
        "INSERT IGNORE INTO attendance_archive SELECT * FROM attendance WHERE attendance_date < ?"
    );
    $stmt->execute([$cutoffDate]);
    $rawArchived = $stmt->rowCount();

    $stmt = $db->prepare('DELETE FROM attendance WHERE attendance_date < ?');
    $stmt->execute([$cutoffDate]);
    $rawDeleted = $stmt->rowCount();

    cron_log("Raw attendance: archived={$rawArchived}, deleted={$rawDeleted}");

    /* ── Archive summary ────────────────────────────────────── */
    $stmt = $db->prepare(
        "INSERT IGNORE INTO attendance_summary_archive SELECT * FROM attendance_summary WHERE attendance_date < ?"
    );
    $stmt->execute([$cutoffDate]);
    $summaryArchived = $stmt->rowCount();

    $stmt = $db->prepare('DELETE FROM attendance_summary WHERE attendance_date < ?');
    $stmt->execute([$cutoffDate]);
    $summaryDeleted = $stmt->rowCount();

    cron_log("Summary: archived={$summaryArchived}, deleted={$summaryDeleted}");

    /* ── Audit log ──────────────────────────────────────────── */
    (new AuditService())->log('ARCHIVE_RECORDS', 'system', null, null, [
        'cutoff_date'      => $cutoffDate,
        'raw_archived'     => $rawArchived,
        'summary_archived' => $summaryArchived,
    ]);

    $summary = "Cutoff: {$cutoffDate}. Raw: {$rawArchived} archived, {$rawDeleted} deleted. Summary: {$summaryArchived} archived, {$summaryDeleted} deleted.";
    job_finish($jobId, true, $summary);
    cron_log("Archive complete. {$summary}");

} catch (\Throwable $e) {
    cron_log($e->getMessage(), 'ERROR');
    job_finish($jobId, false, '', $e->getMessage());
    exit(1);
}
