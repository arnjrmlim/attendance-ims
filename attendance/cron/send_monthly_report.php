<?php

/**
 * Cron: send_monthly_report.php
 *
 * DEPRECATED — This legacy script is preserved for reference only.
 *
 * The system now uses EmailScheduleService (Pipeline A) for all scheduled
 * email reports. Pipeline A is triggered via:
 *
 *   Windows Task Scheduler → HTTP → EmailScheduleController::check()
 *     → EmailScheduleService::checkAndSendScheduled()
 *
 * Pipeline A correctly respects:
 *   - email_schedule  (manual / 15th / end_of_month / both)
 *   - email_report_enabled toggle
 *   - Duplicate prevention
 *   - Bi-monthly periods (1–15 and 16–end)
 *   - Timezone-aware date calculation
 *
 * This script (Pipeline B) is kept as a fallback reference but will EXIT
 * immediately if Pipeline A settings indicate it should not run, preventing
 * duplicate or unwanted sends.
 *
 * If you no longer need this script, delete the corresponding Windows Task
 * Scheduler task ("IMS - Monthly Report") and this file.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\SettingsService;

if (!cron_lock('send_monthly_report')) {
    cron_log('Already running. Exiting.', 'WARN');
    exit(0);
}

$jobId = job_start('send_monthly_report');
$cfg   = new SettingsService();

// ── Guard: respect Email Settings before doing anything ─────────────────────
$schedule       = $cfg->get('email_schedule', 'manual');
$reportEnabled  = $cfg->get('email_report_enabled', '0');

if ($schedule === 'manual') {
    cron_log('Email schedule is set to Manual. Pipeline B will not send. Use EmailScheduleController for manual sends.');
    job_finish($jobId, true, 'Skipped — email_schedule = manual.');
    exit(0);
}

if ($reportEnabled !== '1') {
    cron_log('Auto Monthly Report is disabled (email_report_enabled != 1). Exiting.');
    job_finish($jobId, true, 'Skipped — email_report_enabled is off.');
    exit(0);
}

// ── Guard: Pipeline A (EmailScheduleService) should handle this ─────────────
// Pipeline B only runs if explicitly NOT using the HTTP-based cron endpoint.
// If you are using the Task Scheduler HTTP approach, disable this task entirely.
cron_log('WARNING: Pipeline B (send_monthly_report.php) is running. Verify you are not also running Pipeline A (email-schedule/check) for the same period — duplicate prevention only applies within each pipeline independently.');


// Determine report period: previous month
$year  = (int) date('Y');
$month = (int) date('n') - 1;
if ($month === 0) {
    $month = 12;
    $year--;
}
$periodLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$dateFrom    = sprintf('%04d-%02d-01', $year, $month);
$dateTo      = date('Y-m-t', strtotime($dateFrom));

cron_log("Generating report for period: {$periodLabel}");

try {
    $db = Database::connection();

    /* ── Gather stats ──────────────────────────────────────── */
    $totalEmployees = (int) $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();

    $stmt = $db->prepare(
        "SELECT
             SUM(CASE WHEN day_status='present' THEN 1 ELSE 0 END)  AS present,
             SUM(CASE WHEN is_late=1              THEN 1 ELSE 0 END)  AS late,
             SUM(CASE WHEN day_status='absent'   THEN 1 ELSE 0 END)  AS absent,
             SUM(CASE WHEN day_status='leave'    THEN 1 ELSE 0 END)  AS on_leave,
             SUM(overtime_minutes)                                    AS total_overtime_mins
         FROM attendance_summary
         WHERE attendance_date BETWEEN ? AND ?"
    );
    $stmt->execute([$dateFrom, $dateTo]);
    $stats = $stmt->fetch();

    /* ── Build detail rows ─────────────────────────────────── */
    $rows = $db->prepare(
        "SELECT
             e.employee_number, CONCAT(e.first_name,' ',e.last_name) AS name,
             d.name AS department,
             SUM(CASE WHEN s.day_status='present' THEN 1 ELSE 0 END) AS days_present,
             SUM(CASE WHEN s.is_late=1             THEN 1 ELSE 0 END) AS days_late,
             SUM(CASE WHEN s.day_status='absent'  THEN 1 ELSE 0 END) AS days_absent,
             SUM(CASE WHEN s.day_status='leave'   THEN 1 ELSE 0 END) AS days_leave,
             SUM(s.overtime_minutes) AS overtime_mins,
             SUM(s.late_minutes)     AS late_mins
         FROM attendance_summary s
         INNER JOIN employees e ON e.id = s.employee_id
         LEFT  JOIN departments d ON d.id = e.department_id
         WHERE s.attendance_date BETWEEN ? AND ?
           AND e.status = 'active'
         GROUP BY e.id
         ORDER BY e.last_name, e.first_name"
    );
    $rows->execute([$dateFrom, $dateTo]);
    $detail = $rows->fetchAll();

    /* ── Generate CSV ──────────────────────────────────────── */
    $reportsDir = dirname(__DIR__) . '/storage/reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0775, true);
    }
    $companyName = (string) $cfg->get('company_name', 'My Company');
    $safePeriod = str_replace(' ', '_', $periodLabel);
    $csvFile    = $reportsDir . "/attendance_{$safePeriod}.csv";

    $fh = fopen($csvFile, 'w');
    fputcsv($fh, ['Attendance Report — ' . $periodLabel]);
    fputcsv($fh, ['Company:', $companyName]);
    fputcsv($fh, ['Period:', "{$dateFrom} to {$dateTo}"]);
    fputcsv($fh, []);
    fputcsv($fh, ['Employee #', 'Name', 'Department',
                  'Days Present', 'Days Late', 'Days Absent',
                  'Days Leave', 'Overtime (min)', 'Late (min)']);
    foreach ($detail as $r) {
        fputcsv($fh, [
            $r['employee_number'], $r['name'], $r['department'],
            $r['days_present'],    $r['days_late'],   $r['days_absent'],
            $r['days_leave'],      $r['overtime_mins'],$r['late_mins'],
        ]);
    }
    fclose($fh);
    cron_log("CSV saved: {$csvFile}");

    /* ── Build email body ──────────────────────────────────── */
    $companyName = (string) $cfg->get('company_name', 'Company');
    $otHours     = round((int) ($stats['total_overtime_mins'] ?? 0) / 60, 1);
    $generated   = date('Y-m-d H:i:s');

    $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
        . 'body{font-family:Arial,sans-serif;font-size:14px;color:#172033}'
        . 'th{background:#0f766e;color:#fff}'
        . '.metric{display:inline-block;padding:12px 20px;background:#f5f7fb;border:1px solid #e6e8ee;border-radius:6px;margin:4px}'
        . '.metric strong{display:block;font-size:22px}'
        . '</style></head><body>'
        . '<h2>Monthly Attendance Report</h2>'
        . '<p><strong>Company:</strong> ' . htmlspecialchars($companyName) . '<br>'
        . '<strong>Branch:</strong> ' . htmlspecialchars($branchName) . '<br>'
        . '<strong>Period:</strong> ' . htmlspecialchars($periodLabel) . ' (' . $dateFrom . ' to ' . $dateTo . ')</p>'
        . '<div style="margin:16px 0">'
        . '<div class="metric"><strong>' . $totalEmployees . '</strong> Total Employees</div>'
        . '<div class="metric"><strong>' . (int)($stats['present'] ?? 0) . '</strong> Present Days</div>'
        . '<div class="metric"><strong>' . (int)($stats['late'] ?? 0) . '</strong> Late</div>'
        . '<div class="metric"><strong>' . (int)($stats['absent'] ?? 0) . '</strong> Absent</div>'
        . '<div class="metric"><strong>' . (int)($stats['on_leave'] ?? 0) . '</strong> On Leave</div>'
        . '<div class="metric"><strong>' . $otHours . 'h</strong> Overtime</div>'
        . '</div>'
        . '<p>Please find the detailed CSV report attached.</p>'
        . '<p style="color:#667085;font-size:12px">Generated automatically by Integrated Management Services, Inc. on ' . $generated . '.</p>'
        . '</body></html>';

    /* ── Send ──────────────────────────────────────────────── */
    $recipient  = (string) $cfg->get('email_report_recipient', '');
    $cc         = (string) $cfg->get('email_report_cc', '');
    $subject    = "Monthly Attendance Report — {$branchName} — {$periodLabel}";

    // Build combined recipients (to + cc for simplicity)
    $allRecipients = array_filter(array_map('trim', explode(',', $recipient . ',' . $cc)));
    if (empty($allRecipients)) {
        cron_log('No recipient configured. Set email_report_recipient in settings.', 'WARN');
        job_finish($jobId, false, '', 'No recipient configured.');
        exit(1);
    }

    $emailSvc = new EmailService();
    $allFailed = false;
    foreach ($allRecipients as $to) {
        $ok = $emailSvc->send($to, $subject, $htmlBody, $csvFile, $periodLabel);
        if (!$ok) {
            $allFailed = true;
            cron_log("Failed to send to {$to} — queued for retry.", 'WARN');
        } else {
            cron_log("Report sent to {$to}.");
        }
    }

    // Notify admins of permanent failure after retries exhausted (handled by retry script)
    if ($allFailed) {
        (new NotificationService())->notifyRoles(
            ['administrator'],
            'Monthly Report Email Failed',
            "The monthly attendance report for {$periodLabel} could not be sent. It has been queued for automatic retry.",
            'warning'
        );
    }

    $output = "Report period: {$periodLabel}. Employees: {$totalEmployees}. CSV: {$csvFile}.";
    job_finish($jobId, true, $output);
    cron_log("Done. {$output}");

} catch (\Throwable $e) {
    cron_log($e->getMessage(), 'ERROR');
    job_finish($jobId, false, '', $e->getMessage());
    exit(1);
}
