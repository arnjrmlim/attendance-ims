<?php

/**
 * EmailScheduleService
 *
 * Handles automatic email scheduling for attendance reports.
 * Supports sending on the 15th, end of month, or both dates.
 * Includes timezone handling and duplicate send prevention.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class EmailScheduleService
{
    private SettingsService $cfg;
    private EmailService $emailService;

    public function __construct()
    {
        $this->cfg = new SettingsService();
        $this->emailService = new EmailService();
    }

    /**
     * Check if scheduled email should be sent today and send it.
     * Called by the cron endpoint every hour — safe to call repeatedly.
     *
     * Send schedule (new):
     *   Day 16 of any month  → report covers 1st–15th of the SAME month
     *   Day  1 of any month  → report covers 16th–last day of the PREVIOUS month
     *
     * @return array Result with success status and message
     */
    public function checkAndSendScheduled(): array
    {
        $schedule = $this->cfg->get('email_schedule', 'manual');

        if ($schedule === 'manual') {
            return [
                'success' => false,
                'message' => 'Email schedule is set to manual. Skipping automatic send.',
                'skipped' => true,
            ];
        }

        $timezone = $this->cfg->get('email_timezone', 'UTC');
        $now         = new \DateTime('now', new \DateTimeZone($timezone));
        $currentDay  = (int) $now->format('d');
        $currentDate = $now->format('Y-m-d');

        // Determine whether today is a send date and which period it covers
        $shouldSend = false;
        $sendReason = '';

        if ($schedule === '15th' || $schedule === 'both') {
            // "15th" setting now means: send on the 16th (first-half report)
            if ($currentDay === 16) {
                $shouldSend = true;
                $sendReason = '16th of the month — First-half report (1–15)';
            }
        }

        if ($schedule === 'end_of_month' || $schedule === 'both') {
            // "end_of_month" setting now means: send on the 1st (second-half report)
            if ($currentDay === 1) {
                $shouldSend = true;
                $sendReason = '1st of the month — Second-half report (16–end of previous month)';
            }
        }

        if (!$shouldSend) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Today is %s (day %d). Schedule is "%s". Not a send date (expected 16th or 1st).',
                    $currentDate, $currentDay, $schedule
                ),
                'skipped' => true,
            ];
        }

        // Duplicate-prevention: check if this period's report was already sent
        $periodKey = $this->resolvePeriodKey($currentDate);
        if ($this->periodAlreadySent($periodKey)) {
            return [
                'success'    => false,
                'message'    => sprintf(
                    'Report for period "%s" was already sent. Skipping duplicate.',
                    $periodKey
                ),
                'skipped'    => true,
                'period_key' => $periodKey,
            ];
        }

        return $this->executeReport($currentDate, $sendReason, $timezone, false, false);
    }

    /**
     * Execute the report pipeline.
     *
     * Shared by both the production scheduler and the manual test runner.
     *
     * @param string $sendDate   YYYY-MM-DD anchor date (real or simulated)
     * @param string $reason     Human-readable trigger reason
     * @param string $timezone   Timezone label for logging
     * @param bool   $dryRun     When true, build the report but do NOT send
     * @param bool   $isTest     When true, log as a test run (not a real send)
     * @return array
     */
    private function executeReport(
        string $sendDate,
        string $reason,
        string $timezone,
        bool   $dryRun  = false,
        bool   $isTest  = false
    ): array {
        try {
            $recipient = $this->cfg->get('email_report_recipient', '');
            $cc        = $this->cfg->get('email_report_cc', '');
            $bcc       = $this->cfg->get('email_report_bcc', '');

            if (empty($recipient)) {
                return [
                    'success' => false,
                    'message' => 'No email recipient configured. Set email_report_recipient in settings.',
                    'error'   => 'missing_recipient',
                ];
            }

            $reportEnabled = $this->cfg->get('email_report_enabled', '0') === '1';
            if (!$reportEnabled) {
                return [
                    'success' => false,
                    'message' => 'Email report is disabled. Enable email_report_enabled in settings.',
                    'error'   => 'report_disabled',
                ];
            }

            // Resolve the exact attendance period for this anchor date
            ['from' => $dateFrom, 'to' => $dateTo, 'label' => $periodLabel] =
                $this->resolvePeriod($sendDate);

            // Generate HTML report scoped to the resolved period
            $reportHtml = $this->generateReportHtml($sendDate, $reason, $timezone, $dateFrom, $dateTo, $periodLabel);

            // Count employees in the period (for dry-run display)
            $employeeCount = $this->countEmployeesInPeriod($dateFrom, $dateTo);

            // ── Generate Excel attachment ─────────────────────────────────
            $attachmentPath = null;
            $attachmentName = null;
            try {
                $excelService   = new AttendanceExcelReportService();
                $attachmentPath = $excelService->generateForPeriod($dateFrom, $dateTo, $periodLabel);
                $attachmentName = $attachmentPath ? basename($attachmentPath) : null;
            } catch (\Throwable $e) {
                error_log('Excel generation failed: ' . $e->getMessage());
            }

            // ── ZIP compression (P4) ──────────────────────────────────────
            $compress = $this->cfg->get('email_report_compress', '0') === '1';
            if ($compress && $attachmentPath && is_file($attachmentPath)) {
                if (!class_exists('ZipArchive')) {
                    // Extension not loaded — skip ZIP, attach xlsx as-is
                    error_log('ZipArchive extension is not enabled in php.ini. '
                        . 'Enable extension=zip to use ZIP compression. '
                        . 'Attaching raw Excel file instead.');
                } else {
                    $zipPath = $attachmentPath . '.zip';
                    $zip     = new \ZipArchive();
                    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                        $zip->addFile($attachmentPath, basename($attachmentPath));
                        $zip->close();
                        @unlink($attachmentPath);          // remove the raw xlsx
                        $attachmentPath = $zipPath;
                        $attachmentName = basename($zipPath);
                    } else {
                        // ZIP failed — continue with the xlsx as-is
                        error_log('ZipArchive failed to create: ' . $zipPath);
                    }
                }
            }

            $companyAbbreviation = (string) $this->cfg->get('company_abbreviation', 'IMS');
            $subject   = $companyAbbreviation . ' – Attendance Report (' . $this->periodShortLabel($dateFrom, $dateTo) . ')';
            $periodKey = $this->resolvePeriodKey($sendDate);

            // ── DRY RUN: stop before sending ─────────────────────────────
            if ($dryRun) {
                // Dry-run test logs are fine to write — not a real send
                $this->logEmailTest($sendDate, $reason, $timezone, $periodLabel,
                    $recipient, $subject, 'dry_run', null, true, $companyAbbreviation);
                if ($attachmentPath && is_file($attachmentPath)) {
                    @unlink($attachmentPath);
                }
                return [
                    'success'         => true,
                    'dry_run'         => true,
                    'message'         => 'Dry run completed. Email was NOT sent.',
                    'recipient'       => $recipient,
                    'cc'              => $cc,
                    'bcc'             => $bcc,
                    'subject'         => $subject,
                    'period_label'    => $periodLabel,
                    'date_from'       => $dateFrom,
                    'date_to'         => $dateTo,
                    'attachment_name' => $attachmentName,
                    'employee_count'  => $employeeCount,
                    'compressed'      => $compress && str_ends_with((string)$attachmentName, '.zip'),
                    'report_html_preview' => mb_substr(strip_tags($reportHtml), 0, 300),
                ];
            }

            // ── REAL SEND ─────────────────────────────────────────────────
            // P8: EmailService::send() creates the single authoritative email_log row.
            // We do NOT call logEmailTest() for production sends — that would duplicate the row.
            // For test runs (isTest=true) we DO call logEmailTest() after, to tag the row.

            $success = $this->emailService->sendWithPeriod(
                $recipient, $cc, $bcc,
                $subject, $reportHtml,
                $attachmentPath, $periodLabel,
                $dateFrom, $dateTo
            );

            // Attachment file is now owned by EmailService; do NOT unlink here.
            // EmailService::sendWithPeriod() handles cleanup after SMTP delivery.

            if ($success) {
                if (!$isTest) {
                    // Production: mark period as sent so duplicate prevention fires
                    $this->cfg->set('last_email_sent_date', $sendDate);
                    $this->markPeriodSent($periodKey, $sendDate);
                } else {
                    // Test run: annotate the email_logs row created by EmailService
                    $this->logEmailTest($sendDate, $reason, $timezone, $periodLabel,
                        $recipient, $subject, 'success', null, true, $companyAbbreviation);
                }

                return [
                    'success'        => true,
                    'dry_run'        => false,
                    'message'        => sprintf(
                        'Email sent successfully. Period: %s. Recipient: %s.',
                        $periodLabel, $recipient
                    ),
                    'recipient'      => $recipient,
                    'subject'        => $subject,
                    'period_label'   => $periodLabel,
                    'date_from'      => $dateFrom,
                    'date_to'        => $dateTo,
                    'employee_count' => $employeeCount,
                ];
            }

            if ($isTest) {
                $this->logEmailTest($sendDate, $reason, $timezone, $periodLabel,
                    $recipient, $subject, 'failed', 'SMTP delivery failed', true, $companyAbbreviation);
            }
            return [
                'success' => false,
                'message' => 'Email delivery failed. Check email logs for details.',
                'error'   => 'delivery_failed',
            ];

        } catch (\Throwable $e) {
            if ($isTest) {
                $this->logEmailTest($sendDate, $reason ?? '', $timezone ?? '', '',
                    '', '', 'failed', $e->getMessage(), true, $companyAbbreviation);
            }
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a clean summary-only HTML email body.
     * No per-employee attendance table — that lives in the Excel attachment.
     */
    private function generateReportHtml(
        string $sendDate,
        string $reason,
        string $timezone,
        string $dateFrom,
        string $dateTo,
        string $periodLabel
    ): string {
        $db = Database::connection();

        // Compute period summary statistics
        $stmt = $db->prepare(
            "SELECT
                COUNT(DISTINCT s.employee_id)                               AS employee_count,
                SUM(CASE WHEN s.day_status = 'present' THEN 1 ELSE 0 END)  AS present_days,
                SUM(CASE WHEN s.is_late    = 1          THEN 1 ELSE 0 END)  AS late_days,
                SUM(CASE WHEN s.day_status = 'absent'  THEN 1 ELSE 0 END)  AS absent_days,
                SUM(CASE WHEN s.day_status = 'leave'   THEN 1 ELSE 0 END)  AS leave_days,
                ROUND(SUM(COALESCE(s.overtime_minutes,  0)) / 60, 2)       AS overtime_hours,
                ROUND(SUM(COALESCE(s.undertime_minutes, 0)) / 60, 2)       AS undertime_hours
             FROM attendance_summary s
             WHERE s.attendance_date BETWEEN ? AND ?"
        );
        $stmt->execute([$dateFrom, $dateTo]);
        $st = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $companyName = (string) $this->cfg->get('company_name', 'My Company');
        $companyAbbreviation = (string) $this->cfg->get('company_abbreviation', 'IMS');
        $appName     = $companyName;
        $tz          = $this->formatTimezone($timezone);
        $esc         = static fn($v) => htmlspecialchars((string) ($v ?? ''));
        $shortLabel  = $this->periodShortLabel($dateFrom, $dateTo);

        // Summary row values
        $empCount       = (int) ($st['employee_count']   ?? 0);
        $presentDays    = (int) ($st['present_days']     ?? 0);
        $lateDays       = (int) ($st['late_days']        ?? 0);
        $absentDays     = (int) ($st['absent_days']      ?? 0);
        $leaveDays      = (int) ($st['leave_days']       ?? 0);
        $overtimeHours  = number_format((float) ($st['overtime_hours']  ?? 0), 2);
        $undertimeHours = number_format((float) ($st['undertime_hours'] ?? 0), 2);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Report — {$esc($shortLabel)}</title>
<style>
  body      { font-family: Arial, sans-serif; font-size: 14px; color: #111827; margin: 0; padding: 24px; background: #f9fafb; }
  .card     { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 28px 32px; max-width: 620px; margin: 0 auto; }
  .meta     { margin: 0 0 20px; border-collapse: collapse; width: 100%; }
  .meta td  { padding: 5px 0; vertical-align: top; }
  .meta .lbl{ color: #6b7280; width: 160px; font-size: 13px; }
  .meta .val{ font-weight: 600; font-size: 13px; }
  .divider  { border: none; border-top: 1px solid #e5e7eb; margin: 20px 0; }
  .stats    { border-collapse: collapse; width: 100%; margin: 0 0 20px; }
  .stats th { background: #1a56db; color: #fff; padding: 8px 12px; text-align: left; font-size: 13px; }
  .stats td { border-bottom: 1px solid #f3f4f6; padding: 7px 12px; font-size: 13px; }
  .stats tr:last-child td { border-bottom: none; }
  .stats .num { text-align: right; font-weight: 600; }
  .footer   { font-size: 11px; color: #9ca3af; margin-top: 24px; }
</style>
</head>
<body>
<div class="card">

  <table class="meta">
    <tr>
      <td class="lbl">Period</td>
      <td class="val">{$esc($periodLabel)}</td>
    </tr>
    <tr>
      <td class="lbl"></td>
      <td style="font-size:12px;color:#6b7280">{$esc($dateFrom)} – {$esc($dateTo)}</td>
    </tr>
    <tr><td colspan="2" style="padding:4px 0"></td></tr>
    <tr>
      <td class="lbl">Trigger</td>
      <td class="val">{$esc($reason)}</td>
    </tr>
    <tr>
      <td class="lbl">Timezone</td>
      <td class="val">{$esc($tz)}</td>
    </tr>
    <tr>
      <td class="lbl">Generated</td>
      <td class="val">{$esc(date('Y-m-d H:i:s'))}</td>
    </tr>
    <tr>
      <td class="lbl">Employees Included</td>
      <td class="val">{$esc($empCount)}</td>
    </tr>
  </table>

  <hr class="divider">

  <table class="stats">
    <thead>
      <tr><th>Attendance Summary</th><th class="num" style="text-align:right">Count</th></tr>
    </thead>
    <tbody>
      <tr><td>Present Days</td>   <td class="num">{$presentDays}</td></tr>
      <tr><td>Late Days</td>      <td class="num">{$lateDays}</td></tr>
      <tr><td>Absent Days</td>    <td class="num">{$absentDays}</td></tr>
      <tr><td>Leave Days</td>     <td class="num">{$leaveDays}</td></tr>
      <tr><td>Overtime Hours</td> <td class="num">{$overtimeHours}</td></tr>
      <tr><td>Undertime Hours</td><td class="num">{$undertimeHours}</td></tr>
    </tbody>
  </table>

  <p style="font-size:13px;color:#374151;margin:0 0 8px">
    Please refer to the <strong>attached Excel report</strong> for complete attendance details.
  </p>

  <div class="footer">
    This is an automated attendance report generated by {$esc($appName)}.<br>
    Do not reply to this email.
  </div>

</div>
</body>
</html>
HTML;
    }

    /**
     * Write a rich log entry to email_logs for every scheduled/test execution.
     */
    private function logEmailTest(
        string  $sendDate,
        string  $reason,
        string  $timezone,
        string  $periodLabel,
        string  $recipient,
        string  $subject,
        string  $status,
        ?string $error,
        bool    $isTest,
        string  $companyAbbreviation = 'IMS'
    ): void {
        try {
            $db = Database::connection();
            $this->ensureEmailLogsTestColumns($db);

            $id = uuid_v4();
            $db->prepare(
                "INSERT INTO email_logs
                   (id, recipient, subject, report_period, body_preview,
                    status, retry_count, is_test_run, simulated_date, last_error,
                    sent_at, created_at, updated_at)
                 VALUES
                   (?, ?, ?, ?, ?,
                    ?, 0, ?, ?, ?,
                    IF(? = 'success', NOW(), NULL), NOW(), NOW())"
            )->execute([
                $id,
                $recipient ?: '(none)',
                $subject   ?: '[' . $companyAbbreviation . '] Attendance Report',
                $periodLabel,
                "Trigger: {$reason} | TZ: {$timezone} | Date: {$sendDate}" . ($isTest ? ' | TEST RUN' : ''),
                $status === 'dry_run' ? 'queued' : ($status === 'success' ? 'sent' : 'failed'),
                $isTest   ? 1 : 0,
                $isTest   ? $sendDate : null,
                $error,
                $status,
            ]);

            // Also write to audit log for traceability
            $db->prepare(
                "INSERT INTO audit_logs
                   (user_id, action, module, record_id, details, ip_address, created_at)
                 VALUES (?, ?, 'email', ?, ?, NULL, NOW())"
            )->execute([
                current_user()['id'] ?? null,
                'SCHEDULED_EMAIL_' . strtoupper($status) . ($isTest ? '_TEST' : ''),
                $id,
                json_encode(compact('sendDate', 'reason', 'timezone', 'periodLabel', 'status', 'error', 'isTest')),
            ]);
        } catch (\Throwable $e) {
            error_log('Failed to log email test: ' . $e->getMessage());
        }
    }

    /**
     * Self-heal: add is_test_run, simulated_date, report_date_from, report_date_to
     * columns to email_logs if missing.
     */
    private function ensureEmailLogsTestColumns(\PDO $db): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $needed = [
            'is_test_run'      => "ALTER TABLE `email_logs` ADD COLUMN `is_test_run` TINYINT(1) NOT NULL DEFAULT 0 AFTER `retry_count`",
            'simulated_date'   => "ALTER TABLE `email_logs` ADD COLUMN `simulated_date` DATE DEFAULT NULL AFTER `is_test_run`",
            'report_date_from' => "ALTER TABLE `email_logs` ADD COLUMN `report_date_from` DATE DEFAULT NULL COMMENT 'Period start — used to regenerate attachment on retry' AFTER `simulated_date`",
            'report_date_to'   => "ALTER TABLE `email_logs` ADD COLUMN `report_date_to`   DATE DEFAULT NULL COMMENT 'Period end — used to regenerate attachment on retry'   AFTER `report_date_from`",
        ];
        foreach ($needed as $col => $ddl) {
            $exists = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'email_logs'
                    AND COLUMN_NAME  = '{$col}'"
            )->fetchColumn();
            if ($exists === 0) {
                $db->exec($ddl);
            }
        }
    }

    // ----------------------------------------------------------------
    // Public Test API
    // ----------------------------------------------------------------

    /**
     * Manual test runner — safe to call any day of the month.
     *
     * @param string $simulatedDate YYYY-MM-DD date to pretend it is today
     * @param bool   $dryRun        Build the report but do NOT send
     * @param bool   $forceSend     Send even if a duplicate would normally be blocked
     * @return array
     */
    public function testRun(
        string $simulatedDate,
        bool   $dryRun     = true,
        bool   $forceSend  = false
    ): array {
        // Validate simulated date
        $ts = strtotime($simulatedDate);
        if ($ts === false || $ts === -1) {
            return ['success' => false, 'message' => 'Invalid simulated date.'];
        }
        $simulatedDate = date('Y-m-d', $ts);

        $timezone = $this->cfg->get('email_timezone', 'UTC');
        $day      = (int) date('d', $ts);

        // Match the new production trigger language
        if ($day === 16) {
            $reason = '16th of the month — First-half report (1–15) [simulated]';
        } elseif ($day === 1) {
            $reason = '1st of the month — Second-half report (16–end) [simulated]';
        } else {
            $reason = "Manual test — simulated date {$simulatedDate}";
        }

        $periodKey = $this->resolvePeriodKey($simulatedDate);

        // Duplicate check (skip if forceSend or dryRun)
        if (!$forceSend && !$dryRun && $this->periodAlreadySent($periodKey)) {
            ['from' => $dateFrom, 'to' => $dateTo, 'label' => $periodLabel] =
                $this->resolvePeriod($simulatedDate);
            return [
                'success'      => false,
                'duplicate'    => true,
                'message'      => "Report for period \"{$periodLabel}\" was already sent.",
                'period_label' => $periodLabel,
                'date_from'    => $dateFrom,
                'date_to'      => $dateTo,
                'period_key'   => $periodKey,
            ];
        }

        return $this->executeReport($simulatedDate, $reason, $timezone, $dryRun, true);
    }

    /**
     * Check whether a specific period has already been sent.
     */
    public function isPeriodAlreadySent(string $simulatedDate): bool
    {
        return $this->periodAlreadySent($this->resolvePeriodKey($simulatedDate));
    }

    /**
     * Return the resolved period details for display in the UI.
     * @return array{from:string,to:string,label:string}
     */
    public function getResolvedPeriod(string $simulatedDate): array
    {
        return $this->resolvePeriod($simulatedDate);
    }

    // ----------------------------------------------------------------
    // Period helpers
    // ----------------------------------------------------------------

    /**
     * Determine the attendance period that should be reported given an anchor date.
     *
     * New schedule:
     *   Anchor day = 16  → 1st–15th of the SAME month
     *   Anchor day =  1  → 16th–last day of the PREVIOUS month
     *   Any other day    → best-fit half for ad-hoc/test runs
     *
     * @return array{from:string, to:string, label:string}
     */
    private function resolvePeriod(string $anchorDate): array
    {
        $ts  = strtotime($anchorDate);
        $day = (int) date('d', $ts);

        if ($day === 16) {
            // Send date is 16th → report is 1st–15th of this month
            $year  = date('Y', $ts);
            $month = date('m', $ts);
            return [
                'from'  => "{$year}-{$month}-01",
                'to'    => "{$year}-{$month}-15",
                'label' => date('F Y', $ts) . ' (1–15)',
            ];
        }

        if ($day === 1) {
            // Send date is 1st → report is 16th–last day of PREVIOUS month
            $prev     = strtotime('first day of last month', $ts);
            $year     = date('Y', $prev);
            $month    = date('m', $prev);
            $lastDay  = (int) date('t', $prev);
            return [
                'from'  => "{$year}-{$month}-16",
                'to'    => "{$year}-{$month}-{$lastDay}",
                'label' => date('F Y', $prev) . " (16–{$lastDay})",
            ];
        }

        // Ad-hoc / test date — use the half that contains the anchor day
        $year    = date('Y', $ts);
        $month   = date('m', $ts);
        $lastDay = (int) date('t', $ts);
        $monName = date('F Y', $ts);

        if ($day <= 15) {
            return [
                'from'  => "{$year}-{$month}-01",
                'to'    => "{$year}-{$month}-15",
                'label' => "{$monName} (1–15)",
            ];
        }

        return [
            'from'  => "{$year}-{$month}-16",
            'to'    => "{$year}-{$month}-{$lastDay}",
            'label' => "{$monName} (16–{$lastDay})",
        ];
    }

    /**
     * Canonical period key for duplicate-prevention.
     *
     * Maps the anchor date to the period it will report:
     *   anchor 2026-07-16  → "2026-07-H1"  (July 1–15)
     *   anchor 2026-08-01  → "2026-07-H2"  (July 16–31)
     *   ad-hoc day ≤ 15    → "YYYY-MM-H1"
     *   ad-hoc day > 15    → "YYYY-MM-H2"
     */
    private function resolvePeriodKey(string $anchorDate): string
    {
        $ts  = strtotime($anchorDate);
        $day = (int) date('d', $ts);

        if ($day === 16) {
            return date('Y-m', $ts) . '-H1';
        }

        if ($day === 1) {
            $prev = strtotime('first day of last month', $ts);
            return date('Y-m', $prev) . '-H2';
        }

        // Ad-hoc
        return date('Y-m', $ts) . ($day <= 15 ? '-H1' : '-H2');
    }

    /**
     * Check if the given period key exists as a sent entry in email_logs.
     */
    private function periodAlreadySent(string $periodKey): bool
    {
        try {
            $db = Database::connection();
            $this->ensureEmailLogsTestColumns($db);

            // Check email_logs for a non-test successful send for this period
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM email_logs
                  WHERE report_period LIKE ?
                    AND status        = 'sent'
                    AND (is_test_run  = 0 OR is_test_run IS NULL)"
            );
            // period_key is YYYY-MM-H1/H2 but label stored is "Month YYYY (1–15)"
            // We store the key in body_preview — check both
            $stmt->execute(['%' . $periodKey . '%']);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }

            // Fallback: check last_email_sent_date setting
            $lastSent = $this->cfg->get('last_email_sent_date', '');
            if ($lastSent) {
                return $this->resolvePeriodKey($lastSent) === $periodKey;
            }
        } catch (\Throwable $e) {
            // If check fails, allow send — better to send twice than block incorrectly
        }
        return false;
    }

    /**
     * Record that a period was successfully sent (production path only).
     */
    private function markPeriodSent(string $periodKey, string $sendDate): void
    {
        $this->cfg->set('last_email_sent_date', $sendDate);
        // Also update body_preview of the sent log to include the key for future duplicate checks
        try {
            Database::connection()->prepare(
                "UPDATE email_logs
                    SET body_preview = CONCAT(COALESCE(body_preview,''), ' | key:', ?)
                  WHERE status = 'sent'
                    AND (is_test_run = 0 OR is_test_run IS NULL)
                    AND DATE(created_at) = CURDATE()
                  ORDER BY created_at DESC
                  LIMIT 1"
            )->execute([$periodKey]);
        } catch (\Throwable $e) { /* non-fatal */ }
    }

    /**
     * Count distinct employees that have attendance records in the period.
     */
    private function countEmployeesInPeriod(string $dateFrom, string $dateTo): int
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(DISTINCT employee_id) FROM attendance_summary
                  WHERE attendance_date BETWEEN ? AND ?"
            );
            $stmt->execute([$dateFrom, $dateTo]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Short human-readable period label for subject lines and filenames.
     * e.g. "July 1–15, 2026" or "July 16–31, 2026"
     */
    private function periodShortLabel(string $dateFrom, string $dateTo): string
    {
        $dayFrom  = (int) date('d', strtotime($dateFrom));
        $dayTo    = (int) date('d', strtotime($dateTo));
        $monthYear = date('F Y', strtotime($dateFrom));
        return "{$monthYear} {$dayFrom}–{$dayTo}";  // "July 2026 1–15"
    }

    /**
     * Format timezone string for display, e.g. "Asia/Manila (UTC+08:00)".
     */
    private function formatTimezone(string $tz): string
    {
        try {
            $dtz    = new \DateTimeZone($tz);
            $offset = $dtz->getOffset(new \DateTime('now', $dtz));
            $sign   = $offset >= 0 ? '+' : '-';
            $abs    = abs($offset);
            $h      = str_pad((string) floor($abs / 3600), 2, '0', STR_PAD_LEFT);
            $m      = str_pad((string) (($abs % 3600) / 60), 2, '0', STR_PAD_LEFT);
            return "{$tz} (UTC{$sign}{$h}:{$m})";
        } catch (\Throwable $e) {
            return $tz;
        }
    }
}
