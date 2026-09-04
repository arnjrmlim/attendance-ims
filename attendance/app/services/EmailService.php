<?php

/**
 * EmailService
 *
 * Sends email via SMTP (raw socket with TLS/SSL support).
 * Does NOT require PHPMailer — uses PHP's built-in streams.
 * Queues failures to email_logs and supports retry.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class EmailService
{
    private SettingsService $cfg;

    public function __construct()
    {
        $this->cfg = new SettingsService();
    }

    /* ── Public API ─────────────────────────────────────────── */

    /**
     * Queue an email for delivery. Returns the email_log id.
     */
    public function queue(
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath = null,
        ?string $reportPeriod   = null
    ): string {
        $id   = uuid_v4();
        $stmt = Database::connection()->prepare(
            "INSERT INTO email_logs
             (id, recipient, subject, report_period, body_preview, attachment_path, status, retry_count, next_retry_at)
             VALUES (?, ?, ?, ?, ?, ?, 'queued', 0, NOW())"
        );
        $stmt->execute([
            $id,
            $to,
            $subject,
            $reportPeriod,
            substr(strip_tags($body), 0, 500),
            $attachmentPath,
        ]);
        return $id;
    }

    /**
     * Extended queue that also stores report_date_from / report_date_to for retry regeneration.
     */
    private function queueWithPeriod(
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath,
        ?string $reportPeriod,
        ?string $dateFrom,
        ?string $dateTo
    ): string {
        $id  = uuid_v4();
        $db  = Database::connection();

        // Ensure the period columns exist (self-heal)
        foreach (['report_date_from', 'report_date_to'] as $col) {
            $exists = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'email_logs'
                    AND COLUMN_NAME  = '{$col}'"
            )->fetchColumn();
            if ($exists === 0) {
                $db->exec("ALTER TABLE `email_logs`
                            ADD COLUMN `{$col}` DATE DEFAULT NULL
                            AFTER `simulated_date`");
            }
        }

        $db->prepare(
            "INSERT INTO email_logs
               (id, recipient, subject, report_period, body_preview, attachment_path,
                status, retry_count, next_retry_at, report_date_from, report_date_to)
             VALUES (?, ?, ?, ?, ?, ?, 'queued', 0, NOW(), ?, ?)"
        )->execute([
            $id,
            $to,
            $subject,
            $reportPeriod,
            substr(strip_tags($body), 0, 500),
            $attachmentPath,
            $dateFrom,
            $dateTo,
        ]);
        return $id;
    }

    /**
     * Deliver an email with proper BCC envelope separation (P7).
     * headerTo / headerCc appear in the visible To: / Cc: headers.
     * envelopeRecipients contains all SMTP RCPT TO targets (including BCC).
     */
    private function deliverLogWithBcc(
        string  $logId,
        string  $headerTo,
        string  $headerCc,
        array   $envelopeRecipients,
        string  $subject,
        string  $body,
        ?string $attachmentPath
    ): bool {
        Database::connection()->prepare(
            "UPDATE email_logs SET status = 'retrying', updated_at = NOW() WHERE id = ?"
        )->execute([$logId]);

        $result = $this->deliverSmtpWithBcc(
            $headerTo, $headerCc, $envelopeRecipients,
            $subject, $body, $attachmentPath
        );

        if ($result['ok']) {
            Database::connection()->prepare(
                "UPDATE email_logs
                 SET status = 'sent', sent_at = NOW(), last_error = NULL, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$logId]);
            return true;
        }

        $interval   = (int) $this->cfg->get('email_retry_interval', 60);
        $maxRetries = (int) $this->cfg->get('email_max_retries', 5);
        Database::connection()->prepare(
            "UPDATE email_logs
             SET status       = IF(retry_count + 1 >= ?, 'failed', 'queued'),
                 retry_count  = retry_count + 1,
                 last_error   = ?,
                 next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                 updated_at   = NOW()
             WHERE id = ?"
        )->execute([$maxRetries, $result['error'], $interval, $logId]);
        return false;
    }

    /**
     * Send immediately and log the outcome. Returns true on success.
     * (Legacy entry point — does not store period dates or handle BCC separately.)
     */
    public function send(
        string  $to,
        string  $subject,
        string  $htmlBody,
        ?string $attachmentPath = null,
        ?string $reportPeriod   = null
    ): bool {
        $logId = $this->queue($to, $subject, $htmlBody, $attachmentPath, $reportPeriod);
        return $this->deliverLog($logId, $to, $subject, $htmlBody, $attachmentPath);
    }

    /**
     * Send an attendance report email with proper To/CC/BCC separation and
     * period date storage for reliable retry attachment regeneration.
     *
     * Called by EmailScheduleService::executeReport().
     * This is the canonical send path for all scheduled reports.
     *
     * @param string      $to             Primary recipient(s) — comma-separated
     * @param string      $cc             CC recipients — comma-separated (shown in headers)
     * @param string      $bcc            BCC recipients — comma-separated (envelope only, hidden from headers)
     * @param string      $subject
     * @param string      $htmlBody
     * @param string|null $attachmentPath Path to .xlsx or .zip file
     * @param string|null $reportPeriod   Human-readable label e.g. "July 2026 (1–15)"
     * @param string|null $dateFrom       YYYY-MM-DD period start (stored for retry)
     * @param string|null $dateTo         YYYY-MM-DD period end   (stored for retry)
     * @return bool
     */
    public function sendWithPeriod(
        string  $to,
        string  $cc,
        string  $bcc,
        string  $subject,
        string  $htmlBody,
        ?string $attachmentPath,
        ?string $reportPeriod,
        ?string $dateFrom,
        ?string $dateTo
    ): bool {
        // All envelope recipients: To + CC + BCC
        $envelopeRecipients = array_filter(
            array_map('trim', explode(',', implode(',', [$to, $cc, $bcc])))
        );

        // Visible header recipients: To + CC only (BCC excluded)
        $headerTo = $to;
        $headerCc = $cc;

        // Create the log row with period dates stored for retry
        $logId = $this->queueWithPeriod(
            $to, $subject, $htmlBody, $attachmentPath,
            $reportPeriod, $dateFrom, $dateTo
        );

        $ok = $this->deliverLogWithBcc(
            $logId, $headerTo, $headerCc,
            $envelopeRecipients,
            $subject, $htmlBody, $attachmentPath
        );

        // Clean up attachment file after delivery attempt (success or first failure)
        // On retry, the file will be regenerated from stored period dates.
        if ($attachmentPath && is_file($attachmentPath)) {
            @unlink($attachmentPath);
        }

        return $ok;
    }

    /**
     * Manually resend a specific email_log entry.
     * If report_date_from / report_date_to are stored, the Excel attachment
     * is regenerated rather than relying on the original temp file (P5).
     */
    public function resend(string $logId): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM email_logs WHERE id = ?');
        $stmt->execute([$logId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        // Attempt to regenerate the Excel attachment from stored period dates (P5)
        $attachmentPath = null;
        $dateFrom = $row['report_date_from'] ?? null;
        $dateTo   = $row['report_date_to']   ?? null;
        if ($dateFrom && $dateTo) {
            try {
                $periodLabel    = $row['report_period'] ?? 'Report';
                $excelService   = new AttendanceExcelReportService();
                $attachmentPath = $excelService->generateForPeriod($dateFrom, $dateTo, $periodLabel);
            } catch (\Throwable $e) {
                error_log('Retry attachment regeneration failed: ' . $e->getMessage());
            }
        }

        // Fall back to original attachment path if regeneration failed and file still exists
        if (!$attachmentPath && !empty($row['attachment_path']) && is_file($row['attachment_path'])) {
            $attachmentPath = $row['attachment_path'];
        }

        $ok = $this->deliverLog(
            $row['id'],
            $row['recipient'],
            $row['subject'],
            $row['body_preview'] ?? '',
            $attachmentPath
        );

        // Clean up regenerated file after delivery attempt
        if ($attachmentPath && is_file($attachmentPath)) {
            @unlink($attachmentPath);
        }

        return $ok;
    }

    /**
     * Send a test email to verify SMTP settings.
     */
    public function sendTest(string $to): array
    {
        $companyName = (new SettingsService())->getCompanyName();
        $companyAbbreviation = (new SettingsService())->getCompanyAbbreviation();
        $subject = '[' . $companyAbbreviation . '] Test Email — ' . date('Y-m-d H:i:s');
        $body    = '<p>This is a test email from ' . e($companyName) . ' — Attendance Management Portal.</p>'
                 . '<p>If you received this, your SMTP settings are configured correctly.</p>';

        $ok = $this->deliverSmtp($to, $subject, $body, null);
        return ['success' => $ok['ok'], 'error' => $ok['error'] ?? ''];
    }

    /**
     * Send notification email for new leave request.
     * Uses the configured recipient, CC, and BCC from Email Settings.
     * Email failure does not affect the request save operation.
     */
    public function sendLeaveRequestNotification(array $leaveData, string $requestId): void
    {
        try {
            $recipient = (string) $this->cfg->get('email_report_recipient', '');
            $cc = (string) $this->cfg->get('email_report_cc', '');
            $bcc = (string) $this->cfg->get('email_report_bcc', '');
            
            if (empty($recipient) && empty($cc) && empty($bcc)) {
                error_log('Leave request notification skipped: No recipient configured in Email Settings.');
                return;
            }

            $companyAbbreviation = (new SettingsService())->getCompanyAbbreviation();
            $companyName = (new SettingsService())->getCompanyName();
            
            // Get employee details
            $stmt = Database::connection()->prepare(
                'SELECT e.employee_number, e.first_name, e.middle_name, e.last_name, 
                        e.department_id, e.branch_id, d.name as department_name, b.name as branch_name
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id
                 LEFT JOIN branches b ON b.id = e.branch_id
                 WHERE e.id = ?'
            );
            $stmt->execute([$leaveData['employee_id']]);
            $employee = $stmt->fetch();

            if (!$employee) {
                error_log('Leave request notification failed: Employee not found.');
                return;
            }

            $employeeName = trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']);
            $subject = "[{$companyAbbreviation}] New Leave Request - {$employeeName}";
            
            $body = $this->buildLeaveEmailBody($companyName, $employee, $leaveData, $requestId);
            
            // Queue and deliver the email with CC and BCC support
            $logId = $this->queueWithPeriod($recipient ?: $cc, $subject, $body, null, 'Leave Request', null, null);
            $this->deliverLogWithBcc($logId, $recipient ?: '', $cc, array_filter(array_map('trim', explode(',', implode(',', [$recipient, $cc, $bcc])))), $subject, $body, null);
            
        } catch (\Throwable $e) {
            error_log('Leave request notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Send notification email for new attendance correction request.
     * Uses the configured recipient, CC, and BCC from Email Settings.
     * Email failure does not affect the request save operation.
     */
    public function sendCorrectionRequestNotification(array $correctionData, string $requestId): void
    {
        try {
            $recipient = (string) $this->cfg->get('email_report_recipient', '');
            $cc = (string) $this->cfg->get('email_report_cc', '');
            $bcc = (string) $this->cfg->get('email_report_bcc', '');
            
            if (empty($recipient) && empty($cc) && empty($bcc)) {
                error_log('Correction request notification skipped: No recipient configured in Email Settings.');
                return;
            }

            $companyAbbreviation = (new SettingsService())->getCompanyAbbreviation();
            $companyName = (new SettingsService())->getCompanyName();
            
            // Debug: Log the raw correction data
            error_log('DEBUG EmailService - Raw correction data: ' . json_encode($correctionData));
            
            // Get employee details
            $stmt = Database::connection()->prepare(
                'SELECT e.employee_number, e.first_name, e.middle_name, e.last_name, 
                        e.department_id, e.branch_id, d.name as department_name, b.name as branch_name
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id
                 LEFT JOIN branches b ON b.id = e.branch_id
                 WHERE e.id = ?'
            );
            $stmt->execute([$correctionData['employee_id']]);
            $employee = $stmt->fetch();

            if (!$employee) {
                error_log('Correction request notification failed: Employee not found.');
                return;
            }

            $employeeName = trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']);
            $subject = "[{$companyAbbreviation}] New Attendance Correction Request - {$employeeName}";
            
            $body = $this->buildCorrectionEmailBody($companyName, $employee, $correctionData, $requestId);
            
            // Queue and deliver the email with CC and BCC support
            $logId = $this->queueWithPeriod($recipient ?: $cc, $subject, $body, null, 'Correction Request', null, null);
            $this->deliverLogWithBcc($logId, $recipient ?: '', $cc, array_filter(array_map('trim', explode(',', implode(',', [$recipient, $cc, $bcc])))), $subject, $body, null);
            
        } catch (\Throwable $e) {
            error_log('Correction request notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Build HTML email body for leave request notification.
     */
    private function buildLeaveEmailBody(string $companyName, array $employee, array $leaveData, string $requestId): string
    {
        $employeeName = htmlspecialchars(trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']));
        $employeeNumber = htmlspecialchars($employee['employee_number'] ?? '');
        $department = htmlspecialchars($employee['department_name'] ?? 'N/A');
        $branch = htmlspecialchars($employee['branch_name'] ?? 'N/A');
        $leaveType = htmlspecialchars($leaveData['leave_type'] ?? '');
        $startDate = htmlspecialchars($leaveData['start_date'] ?? '');
        $endDate = htmlspecialchars($leaveData['end_date'] ?? '');
        $numberOfDays = htmlspecialchars((string) ($leaveData['number_of_days'] ?? ''));
        $reason = htmlspecialchars($leaveData['reason'] ?? '');
        $submittedAt = date('Y-m-d H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Leave Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 20px; text-align: center; }
        .content { background: #f9fafb; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .field { margin: 10px 0; }
        .label { font-weight: bold; color: #1a56db; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="header">
        <h2>New Leave Request Submitted</h2>
    </div>
    <div class="content">
        <p>A new leave request has been submitted and requires your review.</p>
        <table>
            <tr><td class="label">Request ID:</td><td>{$requestId}</td></tr>
            <tr><td class="label">Employee Name:</td><td>{$employeeName}</td></tr>
            <tr><td class="label">Employee ID:</td><td>{$employeeNumber}</td></tr>
            <tr><td class="label">Department:</td><td>{$department}</td></tr>
            <tr><td class="label">Branch:</td><td>{$branch}</td></tr>
            <tr><td class="label">Leave Type:</td><td>{$leaveType}</td></tr>
            <tr><td class="label">Start Date:</td><td>{$startDate}</td></tr>
            <tr><td class="label">End Date:</td><td>{$endDate}</td></tr>
            <tr><td class="label">Number of Days:</td><td>{$numberOfDays}</td></tr>
            <tr><td class="label">Reason:</td><td>{$reason}</td></tr>
            <tr><td class="label">Status:</td><td>Pending</td></tr>
            <tr><td class="label">Submitted:</td><td>{$submittedAt}</td></tr>
        </table>
        <p style="margin-top: 20px;">Please log in to the Attendance System to review and process this request.</p>
    </div>
    <div class="footer">
        <p>This is an automated notification from {$companyName} — Attendance Management Portal.</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build HTML email body for correction request notification.
     */
    private function buildCorrectionEmailBody(string $companyName, array $employee, array $correctionData, string $requestId): string
    {
        $employeeName = htmlspecialchars(trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name']));
        $employeeNumber = htmlspecialchars($employee['employee_number'] ?? '');
        $department = htmlspecialchars($employee['department_name'] ?? 'N/A');
        $branch = htmlspecialchars($employee['branch_name'] ?? 'N/A');
        $correctionType = htmlspecialchars($correctionData['correction_type'] ?? '');
        $attendanceDate = htmlspecialchars($correctionData['attendance_date'] ?? '');
        
        // Format time values - extract date and time from datetime format
        $formatTime = function($time) {
            if (empty($time)) return '';
            error_log('DEBUG formatTime - Input: ' . $time);
            if (strlen($time) > 5 && str_contains($time, 'T')) {
                $parts = explode('T', $time);
                $result = $parts[0] . ' ' . substr($parts[1], 0, 5);
                error_log('DEBUG formatTime - Output (T format): ' . $result);
                return $result;
            }
            if (strlen($time) > 5 && str_contains($time, ' ')) {
                $parts = explode(' ', $time);
                $result = $parts[0] . ' ' . substr($parts[1], 0, 5);
                error_log('DEBUG formatTime - Output (space format): ' . $result);
                return $result;
            }
            // If only time is provided, return just the time
            $result = substr($time, 0, 5);
            error_log('DEBUG formatTime - Output (time only): ' . $result);
            return $result;
        };
        
        $originalTimeIn = $formatTime($correctionData['original_time_in'] ?? '');
        $originalTimeOut = $formatTime($correctionData['original_time_out'] ?? '');
        $requestedTimeIn = $formatTime($correctionData['requested_time_in'] ?? '');
        $requestedTimeOut = $formatTime($correctionData['requested_time_out'] ?? '');
        
        $reason = htmlspecialchars($correctionData['reason'] ?? '');
        $submittedAt = date('Y-m-d H:i:s');

        // Build table rows conditionally
        $rows = '';
        $rows .= "<tr><td class=\"label\">Request ID:</td><td>{$requestId}</td></tr>";
        $rows .= "<tr><td class=\"label\">Employee Name:</td><td>{$employeeName}</td></tr>";
        $rows .= "<tr><td class=\"label\">Employee ID:</td><td>{$employeeNumber}</td></tr>";
        $rows .= "<tr><td class=\"label\">Department:</td><td>{$department}</td></tr>";
        $rows .= "<tr><td class=\"label\">Branch:</td><td>{$branch}</td></tr>";
        $rows .= "<tr><td class=\"label\">Correction Type:</td><td>{$correctionType}</td></tr>";
        
        // Only show original times if they have values
        if (!empty($originalTimeIn)) {
            $rows .= "<tr><td class=\"label\">Original Time In:</td><td>{$originalTimeIn}</td></tr>";
        }
        if (!empty($originalTimeOut)) {
            $rows .= "<tr><td class=\"label\">Original Time Out:</td><td>{$originalTimeOut}</td></tr>";
        }
        
        if (!empty($requestedTimeIn)) {
            $rows .= "<tr><td class=\"label\">Requested Time In:</td><td>{$requestedTimeIn}</td></tr>";
        }
        if (!empty($requestedTimeOut)) {
            $rows .= "<tr><td class=\"label\">Requested Time Out:</td><td>{$requestedTimeOut}</td></tr>";
        }
        
        $rows .= "<tr><td class=\"label\">Reason:</td><td>{$reason}</td></tr>";
        $rows .= "<tr><td class=\"label\">Status:</td><td>Pending</td></tr>";
        $rows .= "<tr><td class=\"label\">Submitted:</td><td>{$submittedAt}</td></tr>";

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Attendance Correction Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a56db; color: white; padding: 20px; text-align: center; }
        .content { background: #f9fafb; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .field { margin: 10px 0; }
        .label { font-weight: bold; color: #1a56db; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="header">
        <h2>New Attendance Correction Request Submitted</h2>
    </div>
    <div class="content">
        <p>A new attendance correction request has been submitted and requires your review.</p>
        <table>
            {$rows}
        </table>
        <p style="margin-top: 20px;">Please log in to the Attendance System to review and process this request.</p>
    </div>
    <div class="footer">
        <p>This is an automated notification from {$companyName} — Attendance Management Portal.</p>
    </div>
</body>
</html>
HTML;
    }

    /* ── Internal ────────────────────────────────────────────── */

    private function deliverLog(
        string  $logId,
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath
    ): bool {
        // Mark as retrying
        Database::connection()->prepare(
            "UPDATE email_logs SET status = 'retrying', updated_at = NOW() WHERE id = ?"
        )->execute([$logId]);

        $result = $this->deliverSmtp($to, $subject, $body, $attachmentPath);

        if ($result['ok']) {
            Database::connection()->prepare(
                "UPDATE email_logs
                 SET status = 'sent', sent_at = NOW(), last_error = NULL, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$logId]);

            // Notify admins of success if there were prior failures
            return true;
        }

        // Update retry info
        $interval = (int) $this->cfg->get('email_retry_interval', 60);
        $maxRetries = (int) $this->cfg->get('email_max_retries', 5);
        Database::connection()->prepare(
            "UPDATE email_logs
             SET status      = IF(retry_count + 1 >= ?, 'failed', 'queued'),
                 retry_count = retry_count + 1,
                 last_error  = ?,
                 next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                 updated_at  = NOW()
             WHERE id = ?"
        )->execute([$maxRetries, $result['error'], $interval, $logId]);

        return false;
    }

    /**
     * Low-level SMTP delivery via PHP streams (TLS/SSL).
     * Returns ['ok' => bool, 'error' => string].
     */
    /**
     * SMTP delivery with proper BCC support (P7).
     *
     * $headerTo / $headerCc  — appear in the visible To: / Cc: message headers.
     * $envelopeRecipients    — ALL SMTP RCPT TO targets, including BCC addresses.
     *                          BCC recipients receive the email but are NOT
     *                          listed in any visible header.
     *
     * Returns ['ok' => bool, 'error' => string].
     */
    private function deliverSmtpWithBcc(
        string  $headerTo,
        string  $headerCc,
        array   $envelopeRecipients,
        string  $subject,
        string  $body,
        ?string $attachmentPath
    ): array {
        $host       = (string) $this->cfg->get('smtp_host', '');
        $port       = (int)    $this->cfg->get('smtp_port', 587);
        $username   = (string) $this->cfg->get('smtp_username', '');
        $password   = $this->decryptPassword((string) $this->cfg->get('smtp_password', ''));
        $encryption = strtolower((string) $this->cfg->get('smtp_encryption', 'tls'));
        $fromName   = (string) $this->cfg->get('smtp_from_name', 'Attendance System');
        $fromEmail  = (string) $this->cfg->get('smtp_from_email', '');

        if (empty($host) || empty($fromEmail)) {
            return ['ok' => false, 'error' => 'SMTP not configured.'];
        }
        if (empty($envelopeRecipients)) {
            return ['ok' => false, 'error' => 'No recipients specified.'];
        }

        try {
            $boundary   = '==Multipart_' . bin2hex(random_bytes(8));
            $companyAbbreviation = (new SettingsService())->getCompanyAbbreviation();
            $messageId  = '<' . uuid_v4() . '@' . strtolower($companyAbbreviation) . '>';
            $date       = date('r');
            $fromHeader = '"' . addslashes($fromName) . '" <' . $fromEmail . '>';

            // Build MIME — visible headers only contain To and Cc (not BCC)
            $hasAttachment = $attachmentPath && is_file($attachmentPath);
            $mime = $this->buildMimeWithCc(
                $fromHeader, $headerTo, $headerCc,
                $subject, $date, $messageId,
                $body, $attachmentPath, $boundary, $hasAttachment
            );

            // Open socket
            if ($encryption === 'ssl') {
                $conn = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
            } else {
                $conn = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
            }
            if (!$conn) {
                return ['ok' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"];
            }
            stream_set_timeout($conn, 15);

            $read = function () use ($conn): string {
                $response = '';
                while ($line = fgets($conn, 512)) {
                    $response .= $line;
                    if (isset($line[3]) && $line[3] === ' ') break;
                }
                return $response;
            };
            $write = fn(string $cmd) => fwrite($conn, $cmd . "\r\n");

            $read(); // greeting
            $write("EHLO {$host}"); $read();

            if ($encryption === 'tls') {
                $write('STARTTLS'); $read();
                $cryptoOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                stream_context_set_option($conn, $cryptoOptions);
                stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write("EHLO {$host}"); $read();
            }

            $write('AUTH LOGIN');           $read();
            $write(base64_encode($username)); $read();
            $write(base64_encode($password));
            $authResp = $read();
            if (!str_starts_with($authResp, '235')) {
                fclose($conn);
                return ['ok' => false, 'error' => 'SMTP auth failed: ' . trim($authResp)];
            }

            $write("MAIL FROM:<{$fromEmail}>"); $read();

            // SMTP envelope: send to ALL recipients (To + CC + BCC)
            foreach ($envelopeRecipients as $r) {
                if (!empty($r)) {
                    $write("RCPT TO:<{$r}>"); $read();
                }
            }

            $write('DATA'); $read();
            fwrite($conn, $mime . "\r\n.\r\n");
            $dataResp = $read();
            $write('QUIT');
            fclose($conn);

            if (!str_starts_with($dataResp, '250')) {
                return ['ok' => false, 'error' => 'SMTP DATA rejected: ' . trim($dataResp)];
            }
            return ['ok' => true, 'error' => ''];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build MIME message with separate visible To: and Cc: headers.
     * BCC is intentionally excluded from all headers.
     */
    private function buildMimeWithCc(
        string  $from,
        string  $to,
        string  $cc,
        string  $subject,
        string  $date,
        string  $messageId,
        string  $body,
        ?string $attachmentPath,
        string  $boundary,
        bool    $hasAttachment
    ): string {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $ccHeader = !empty($cc) ? "Cc: {$cc}\r\n" : '';

        if ($hasAttachment) {
            $headers  = "From: {$from}\r\nTo: {$to}\r\n{$ccHeader}";
            $headers .= "Subject: {$encodedSubject}\r\nDate: {$date}\r\nMessage-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $mime  = $headers . "\r\n--{$boundary}\r\n";
            $mime .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($body)) . "\r\n";

            $fileName    = basename((string) $attachmentPath);
            $fileContent = base64_encode((string) file_get_contents((string) $attachmentPath));
            $mimeType    = $this->mimeTypeFor($fileName);

            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
            $mime .= chunk_split($fileContent) . "\r\n--{$boundary}--";
        } else {
            $headers  = "From: {$from}\r\nTo: {$to}\r\n{$ccHeader}";
            $headers .= "Subject: {$encodedSubject}\r\nDate: {$date}\r\nMessage-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $mime     = $headers . "\r\n" . chunk_split(base64_encode($body));
        }
        return $mime;
    }

    private function deliverSmtp(
        string  $to,
        string  $subject,
        string  $body,
        ?string $attachmentPath
    ): array {
        $host       = (string) $this->cfg->get('smtp_host', '');
        $port       = (int)    $this->cfg->get('smtp_port', 587);
        $username   = (string) $this->cfg->get('smtp_username', '');
        $password   = $this->decryptPassword((string) $this->cfg->get('smtp_password', ''));
        $encryption = strtolower((string) $this->cfg->get('smtp_encryption', 'tls'));
        $fromName   = (string) $this->cfg->get('smtp_from_name', 'Attendance System');
        $fromEmail  = (string) $this->cfg->get('smtp_from_email', '');

        if (empty($host) || empty($fromEmail)) {
            return ['ok' => false, 'error' => 'SMTP not configured. Set smtp_host and smtp_from_email in System Settings.'];
        }

        try {
            $boundary  = '==Multipart_' . bin2hex(random_bytes(8));
            $messageId = '<' . uuid_v4() . '@ams>';
            $date      = date('r');
            $fromHeader = '"' . addslashes($fromName) . '" <' . $fromEmail . '>';

            // Build MIME message
            $hasAttachment = $attachmentPath && is_file($attachmentPath);
            $mime = $this->buildMime(
                $fromHeader, $to, $subject, $date, $messageId,
                $body, $attachmentPath, $boundary, $hasAttachment
            );

            // Open socket
            $context = stream_context_create();
            if ($encryption === 'ssl') {
                $conn = stream_socket_client(
                    "ssl://{$host}:{$port}", $errno, $errstr, 15,
                    STREAM_CLIENT_CONNECT, $context
                );
            } else {
                $conn = stream_socket_client(
                    "tcp://{$host}:{$port}", $errno, $errstr, 15
                );
            }

            if (!$conn) {
                return ['ok' => false, 'error' => "Cannot connect to {$host}:{$port} — {$errstr}"];
            }

            stream_set_timeout($conn, 15);

            $read = function () use ($conn): string {
                $response = '';
                while ($line = fgets($conn, 512)) {
                    $response .= $line;
                    if (isset($line[3]) && $line[3] === ' ') {
                        break;
                    }
                }
                return $response;
            };
            $write = function (string $cmd) use ($conn): void {
                fwrite($conn, $cmd . "\r\n");
            };

            $read(); // server greeting
            $write("EHLO {$host}");
            $ehlo = $read();

            if ($encryption === 'tls') {
                $write('STARTTLS');
                $read();
                $cryptoOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                stream_context_set_option($conn, $cryptoOptions);
                stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write("EHLO {$host}");
                $read();
            }

            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($username));
            $read();
            $write(base64_encode($password));
            $authResp = $read();
            if (!str_starts_with($authResp, '235')) {
                fclose($conn);
                return ['ok' => false, 'error' => 'SMTP authentication failed: ' . trim($authResp)];
            }

            $write("MAIL FROM:<{$fromEmail}>");
            $read();
            // Support multiple recipients
            foreach (array_map('trim', explode(',', $to)) as $recipient) {
                $write("RCPT TO:<{$recipient}>");
                $read();
            }

            $write('DATA');
            $read();
            fwrite($conn, $mime . "\r\n.\r\n");
            $dataResp = $read();

            $write('QUIT');
            fclose($conn);

            if (!str_starts_with($dataResp, '250')) {
                return ['ok' => false, 'error' => 'SMTP DATA rejected: ' . trim($dataResp)];
            }

            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildMime(
        string  $from,
        string  $to,
        string  $subject,
        string  $date,
        string  $messageId,
        string  $body,
        ?string $attachmentPath,
        string  $boundary,
        bool    $hasAttachment
    ): string {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        if ($hasAttachment) {
            $headers  = "From: {$from}\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$encodedSubject}\r\n";
            $headers .= "Date: {$date}\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $mime  = $headers . "\r\n";
            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $mime .= chunk_split(base64_encode($body)) . "\r\n";

            $fileName    = basename((string) $attachmentPath);
            $fileContent = base64_encode((string) file_get_contents((string) $attachmentPath));
            $mimeType    = $this->mimeTypeFor($fileName);

            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n";
            $mime .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
            $mime .= chunk_split($fileContent) . "\r\n";
            $mime .= "--{$boundary}--";
        } else {
            $headers  = "From: {$from}\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$encodedSubject}\r\n";
            $headers .= "Date: {$date}\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $mime     = $headers . "\r\n" . chunk_split(base64_encode($body));
        }

        return $mime;
    }

    private function mimeTypeFor(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf'  => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'csv'  => 'text/csv',
            'zip'  => 'application/zip',
            default => 'application/octet-stream',
        };
    }

    /* ── Password encryption helpers ─────────────────────────── */

    public static function encryptPassword(string $plain): string
    {
        $key = self::encKey();
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    private function decryptPassword(string $stored): string
    {
        if (empty($stored)) {
            return '';
        }
        try {
            $key  = self::encKey();
            $raw  = base64_decode($stored);
            $iv   = substr($raw, 0, 16);
            $enc  = substr($raw, 16);
            $plain = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
            return $plain !== false ? $plain : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function encKey(): string
    {
        // Derive a 32-byte key from the app base_url (unique per installation).
        return hash('sha256', (string) config('base_url', 'ams-default-key'), true);
    }
}
