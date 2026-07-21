<?php

/**
 * Cron: send_monthly_report.php
 *
 * Generates the previous month's attendance report (CSV + HTML email body),
 * saves it locally, then sends via SMTP. If delivery fails the file is kept
 * and the failure is logged so retry_failed_emails.php can resend it.
 *
 * Recommended schedule (Windows Task Scheduler):
 *   Trigger  : Monthly, Day 1, 12:00 AM
 *   Action   : "C:\xampp\php\php.exe"
 *   Arguments: "C:\xampp\htdocs\attendance-ims\attendance\cron\send_monthly_report.php"
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
    $branchName = (string) $cfg->get('company_branch', 'Branch');
    $safePeriod = str_replace(' ', '_', $periodLabel);
    $csvFile    = $reportsDir . "/attendance_{$safePeriod}.csv";

    $fh = fopen($csvFile, 'w');
    fputcsv($fh, ['Attendance Report — ' . $periodLabel]);
    fputcsv($fh, ['Branch:', $branchName]);
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

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;font-size:14px;color:#172033}
  table{border-collapse:collapse;width:100%}
  th,td{border:1px solid #e6e8ee;padding:8px 12px;text-align:left}
  th{background:#0f766e;color:#fff}
  .metric{display:inline-block;padding:12px 20px;background:#f5f7fb;border:1px solid #e6e8ee;border-radius:6px;margin:4px}
  .metric strong{display:block;font-size:22px}
</style></head>
<body>
<h2>Monthly Attendance Report</h2>
<p><strong>Company:</strong> {$companyName}<br>
   <strong>Branch:</strong> {$branchName}<br>
   <strong>Period:</strong> {$periodLabel} ({$dateFrom} to {$dateTo})</p>

<div style="margin:16px 0">
  <div class="metric"><strong>{$totalEmployees}</strong> Total Employees</div>
  <div class="metric"><strong>{$stats['present']}</strong> Present Days</div>
  <div class="metric"><strong>{$stats['late']}</strong> Late</div>
  <div class="metric"><strong>{$stats['absent']}</strong> Absent</div>
  <div class="metric"><strong>{$stats['on_leave']}</strong> On Leave</div>
  <div class="metric"><strong>{$otHours}h</strong> Overtime</div>
</div>

<p>Please find the detailed CSV report attached.</p>
<p style="color:#667085;font-size:12px">Generated automatically by Integrated Management Services, Inc. — Attendance Management Portal on HTML;
    $htmlBody .= date('Y-m-d H:i:s') . '.</p></body></html>';

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
