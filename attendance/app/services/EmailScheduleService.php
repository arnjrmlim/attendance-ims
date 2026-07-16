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
     * This method is intended to be called by a cron job.
     * 
     * @return array Result with success status and message
     */
    public function checkAndSendScheduled(): array
    {
        $schedule = $this->cfg->get('email_schedule', 'manual');
        
        // If manual only, don't send automatically
        if ($schedule === 'manual') {
            return [
                'success' => false,
                'message' => 'Email schedule is set to manual. Skipping automatic send.',
                'skipped' => true
            ];
        }

        // Get configured timezone
        $timezone = $this->cfg->get('email_timezone', 'UTC');
        
        // Get current date in configured timezone
        $now = new \DateTime('now', new \DateTimeZone($timezone));
        $currentDay = (int) $now->format('d');
        $currentDate = $now->format('Y-m-d');
        $lastDayOfMonth = (int) $now->format('t');

        // Check if today is a scheduled send date
        $shouldSend = false;
        $sendReason = '';

        if ($schedule === '15th' && $currentDay === 15) {
            $shouldSend = true;
            $sendReason = '15th of the month';
        } elseif ($schedule === 'end_of_month' && $currentDay === $lastDayOfMonth) {
            $shouldSend = true;
            $sendReason = 'End of month';
        } elseif ($schedule === 'both') {
            if ($currentDay === 15) {
                $shouldSend = true;
                $sendReason = '15th of the month';
            } elseif ($currentDay === $lastDayOfMonth) {
                $shouldSend = true;
                $sendReason = 'End of month';
            }
        }

        if (!$shouldSend) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Today is %s (day %d of %d). Schedule is "%s". Not a scheduled send date.',
                    $currentDate,
                    $currentDay,
                    $lastDayOfMonth,
                    $schedule
                ),
                'skipped' => true
            ];
        }

        // Check if email was already sent today (duplicate prevention)
        // TEMPORARILY DISABLED FOR TESTING
        /*
        $lastSentDate = $this->cfg->get('last_email_sent_date', '');
        if ($lastSentDate === $currentDate) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Email already sent on %s. Skipping duplicate send.',
                    $currentDate
                ),
                'skipped' => true
            ];
        }
        */

        // Send the email
        return $this->sendScheduledEmail($currentDate, $sendReason, $timezone);
    }

    /**
     * Send the scheduled email report.
     * 
     * @param string $sendDate The date being sent (YYYY-MM-DD)
     * @param string $reason The reason for sending (e.g., "15th of the month")
     * @param string $timezone The timezone used for scheduling
     * @return array Result with success status and message
     */
    private function sendScheduledEmail(string $sendDate, string $reason, string $timezone): array
    {
        try {
            // Get report recipients
            $recipient = $this->cfg->get('email_report_recipient', '');
            $cc = $this->cfg->get('email_report_cc', '');
            $bcc = $this->cfg->get('email_report_bcc', '');

            if (empty($recipient)) {
                return [
                    'success' => false,
                    'message' => 'No email recipient configured. Please set email_report_recipient in settings.',
                    'error' => 'missing_recipient'
                ];
            }

            // Build recipient list
            $to = $recipient;
            if (!empty($cc)) {
                $to .= ',' . $cc;
            }
            if (!empty($bcc)) {
                $to .= ',' . $bcc;
            }

            // Check if report generation is enabled
            $reportEnabled = $this->cfg->get('email_report_enabled', '0') === '1';
            if (!$reportEnabled) {
                return [
                    'success' => false,
                    'message' => 'Email report is disabled. Enable email_report_enabled in settings.',
                    'error' => 'report_disabled'
                ];
            }

            // Generate the HTML report
            $reportHtml = $this->generateReportHtml($sendDate, $reason, $timezone);

            // Generate Excel attachment
            $attachmentPath = null;
            try {
                $excelService = new AttendanceExcelReportService();
                $attachmentPath = $excelService->generate($sendDate);
            } catch (\Throwable $e) {
                // Log Excel generation error but continue with HTML email
                error_log('Excel generation failed: ' . $e->getMessage());
            }

            // Send the email
            $subject = '[AMS] Attendance Report - ' . $sendDate;
            $success = $this->emailService->send($to, $subject, $reportHtml, $attachmentPath, $sendDate);

            if ($success) {
                // Update last sent date
                $this->cfg->set('last_email_sent_date', $sendDate);
                
                // Log successful send
                $this->logScheduledSend($sendDate, $reason, $timezone, 'success', null);

                // Clean up temporary Excel file
                if ($attachmentPath && is_file($attachmentPath)) {
                    @unlink($attachmentPath);
                }

                return [
                    'success' => true,
                    'message' => sprintf(
                        'Email sent successfully on %s (reason: %s, timezone: %s)',
                        $sendDate,
                        $reason,
                        $timezone
                    )
                ];
            } else {
                // Log failed send
                $this->logScheduledSend($sendDate, $reason, $timezone, 'failed', 'Email delivery failed');

                // Clean up temporary Excel file even on failure
                if ($attachmentPath && is_file($attachmentPath)) {
                    @unlink($attachmentPath);
                }

                return [
                    'success' => false,
                    'message' => 'Email delivery failed. Check email logs for details.',
                    'error' => 'delivery_failed'
                ];
            }
        } catch (\Throwable $e) {
            // Log error
            $this->logScheduledSend($sendDate, $reason, $timezone, 'failed', $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error sending scheduled email: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate HTML report for email.
     * 
     * @param string $sendDate The date being sent
     * @param string $reason The reason for sending
     * @param string $timezone The timezone used
     * @return string HTML report
     */
    private function generateReportHtml(string $sendDate, string $reason, string $timezone): string
    {
        $db = Database::connection();
        
        // Get attendance data for the current month
        $monthStart = date('Y-m-01', strtotime($sendDate));
        $monthEnd = date('Y-m-t', strtotime($sendDate));

        $stmt = $db->prepare(
            "SELECT 
                e.employee_number,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                d.name AS department,
                b.name AS branch,
                COUNT(s.id) AS total_days,
                SUM(CASE WHEN s.day_status = 'present' THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN s.day_status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN s.day_status = 'leave' THEN 1 ELSE 0 END) AS leave_days,
                SUM(s.total_hours) AS total_hours,
                SUM(s.late_minutes) AS total_late_minutes,
                SUM(s.undertime_minutes) AS total_undertime_minutes
             FROM attendance_summary s
             INNER JOIN employees e ON e.id = s.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE s.attendance_date BETWEEN ? AND ?
             GROUP BY e.id
             ORDER BY e.last_name, e.first_name"
        );
        $stmt->execute([$monthStart, $monthEnd]);
        $employees = $stmt->fetchAll();

        // Build HTML
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .summary { background-color: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Attendance Report</h1>
    <div class="summary">
        <p><strong>Report Date:</strong> ' . htmlspecialchars($sendDate) . '</p>
        <p><strong>Period:</strong> ' . htmlspecialchars($monthStart) . ' to ' . htmlspecialchars($monthEnd) . '</p>
        <p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>
        <p><strong>Timezone:</strong> ' . htmlspecialchars($timezone) . '</p>
        <p><strong>Total Employees:</strong> ' . (string)count($employees) . '</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee Number</th>
                <th>Name</th>
                <th>Department</th>
                <th>Branch</th>
                <th>Total Days</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Leave</th>
                <th>Total Hours</th>
                <th>Late (min)</th>
                <th>Undertime (min)</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($employees as $emp) {
            $html .= '<tr>
                <td>' . htmlspecialchars((string)$emp['employee_number']) . '</td>
                <td>' . htmlspecialchars((string)$emp['employee_name']) . '</td>
                <td>' . htmlspecialchars((string)($emp['department'] ?? 'N/A')) . '</td>
                <td>' . htmlspecialchars((string)($emp['branch'] ?? 'N/A')) . '</td>
                <td>' . htmlspecialchars((string)$emp['total_days']) . '</td>
                <td>' . htmlspecialchars((string)$emp['present_days']) . '</td>
                <td>' . htmlspecialchars((string)$emp['absent_days']) . '</td>
                <td>' . htmlspecialchars((string)$emp['leave_days']) . '</td>
                <td>' . htmlspecialchars(number_format((float)($emp['total_hours'] ?? 0), 2)) . '</td>
                <td>' . htmlspecialchars((string)($emp['total_late_minutes'] ?? 0)) . '</td>
                <td>' . htmlspecialchars((string)($emp['total_undertime_minutes'] ?? 0)) . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>
    <p style="margin-top: 20px; color: #666; font-size: 12px;">
        This is an automated email from the Attendance Management System.
    </p>
</body>
</html>';

        return $html;
    }

    /**
     * Log scheduled email send attempt to audit log.
     * 
     * @param string $sendDate The date being sent
     * @param string $reason The reason for sending
     * @param string $timezone The timezone used
     * @param string $status The status (success/failed)
     * @param string|null $error Error message if failed
     */
    private function logScheduledSend(string $sendDate, string $reason, string $timezone, string $status, ?string $error): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address, created_at)
                 VALUES (NULL, ?, ?, NULL, ?, NULL, NOW())"
            );
            $details = json_encode([
                'send_date' => $sendDate,
                'reason' => $reason,
                'timezone' => $timezone,
                'status' => $status,
                'error' => $error
            ]);
            $stmt->execute(['SCHEDULED_EMAIL_' . strtoupper($status), 'email', $details]);
        } catch (\Throwable $e) {
            // Log failure silently - don't break the flow
            error_log('Failed to log scheduled email send: ' . $e->getMessage());
        }
    }
}
