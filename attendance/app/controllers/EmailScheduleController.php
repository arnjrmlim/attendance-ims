<?php

/**
 * EmailScheduleController
 *
 * Routes:
 *   GET  /email-schedule/check     — Cron endpoint (localhost only, no auth)
 *   GET  /email-schedule/status    — JSON schedule status (Administrator)
 *   GET  /email-schedule/test      — Safe-testing UI page (Administrator)
 *   POST /email-schedule/test-run  — Execute test / dry-run / force-send
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmailScheduleService;
use App\Services\SettingsService;

final class EmailScheduleController extends BaseController
{
    // ----------------------------------------------------------------
    // Production cron endpoint
    // ----------------------------------------------------------------

    /**
     * GET /email-schedule/check
     * Called by the Windows Task Scheduler / cron job.
     * Bypasses authentication only for localhost requests.
     */
    public function check(): void
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($clientIp, ['127.0.0.1', '::1', 'localhost'], true)) {
            require_role(['administrator']);
        }

        $service = new EmailScheduleService();
        $result  = $service->checkAndSendScheduled();
        $this->json($result, $result['success'] ? 200 : 500);
    }

    // ----------------------------------------------------------------
    // Status JSON (used by existing Email Settings page widget)
    // ----------------------------------------------------------------

    /**
     * GET /email-schedule/status
     */
    public function status(): void
    {
        require_role(['administrator']);

        $cfg      = new SettingsService();
        $schedule = $cfg->get('email_schedule', 'manual');
        $timezone = $cfg->get('email_timezone', 'UTC');
        $lastSent = $cfg->get('last_email_sent_date', '');

        $now            = new \DateTime('now', new \DateTimeZone($timezone));
        $next15th       = clone $now;
        $nextEndOfMonth = clone $now;

        $next15th->modify('first day of next month');
        $next15th->setDate(
            (int) $next15th->format('Y'),
            (int) $next15th->format('m'),
            15
        );
        $nextEndOfMonth->modify('last day of this month');
        if ($nextEndOfMonth < $now) {
            $nextEndOfMonth->modify('last day of next month');
        }

        $nextSendDates = [];
        if (in_array($schedule, ['15th', 'both'], true)) {
            $nextSendDates[] = $next15th->format('Y-m-d') . ' (15th)';
        }
        if (in_array($schedule, ['end_of_month', 'both'], true)) {
            $nextSendDates[] = $nextEndOfMonth->format('Y-m-d') . ' (End of Month)';
        }

        $this->json([
            'schedule'        => $schedule,
            'timezone'        => $timezone,
            'last_sent_date'  => $lastSent,
            'next_send_dates' => $nextSendDates,
            'current_date'    => $now->format('Y-m-d'),
            'current_day'     => (int) $now->format('d'),
            'days_in_month'   => (int) $now->format('t'),
        ]);
    }

    // ----------------------------------------------------------------
    // Safe Testing Mode
    // ----------------------------------------------------------------

    /**
     * GET /email-schedule/test — render the testing UI.
     * Administrator only — never accessible to HR or lower roles.
     */
    public function testPage(): void
    {
        require_role(['administrator']);

        $cfg      = new SettingsService();
        $timezone = $cfg->get('email_timezone', 'UTC');
        $now      = new \DateTime('now', new \DateTimeZone($timezone));

        // Pre-fill the two canonical send dates
        // Day 16 of current month → triggers 1st–15th report
        // Day 1 of next month     → triggers 16th–end of current month report
        $suggestDates = [
            $now->format('Y-m') . '-16',
            (clone $now)->modify('first day of next month')->format('Y-m-d'),
        ];

        $this->render('email/schedule-test', [
            'title'        => 'Email Schedule — Safe Testing',
            'cfg'          => $cfg->all(),
            'timezone'     => $timezone,
            'today'        => $now->format('Y-m-d'),
            'suggestDates' => $suggestDates,
            'schedule'     => $cfg->get('email_schedule', 'manual'),
            'lastSent'     => $cfg->get('last_email_sent_date', ''),
            'recipient'    => $cfg->get('email_report_recipient', ''),
        ]);
    }

    /**
     * GET /email-schedule/test-logs — JSON list of recent test-run email log entries.
     * Used by the schedule-test view's AJAX log panel.
     */
    public function testLogs(): void
    {
        require_role(['administrator']);

        $db   = \App\Core\Database::connection();

        // Ensure columns exist (self-heal)
        foreach (['is_test_run', 'simulated_date'] as $col) {
            $exists = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'email_logs'
                    AND COLUMN_NAME  = '{$col}'"
            )->fetchColumn();
            if ($exists === 0) {
                $ddls = [
                    'is_test_run'    => "ALTER TABLE `email_logs` ADD COLUMN `is_test_run` TINYINT(1) NOT NULL DEFAULT 0 AFTER `retry_count`",
                    'simulated_date' => "ALTER TABLE `email_logs` ADD COLUMN `simulated_date` DATE DEFAULT NULL AFTER `is_test_run`",
                ];
                $db->exec($ddls[$col]);
            }
        }

        $rows = $db->query(
            "SELECT id, recipient, subject, report_period, status,
                    is_test_run, simulated_date, last_error, created_at
               FROM email_logs
              ORDER BY created_at DESC
              LIMIT 30"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->json($rows);
    }

    /**
     * POST /email-schedule/test-run
     *
     * POST params:
     *   simulated_date  YYYY-MM-DD  (required)
     *   mode            dry_run | force_send | normal
     */
    public function testRun(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $simulatedDate = trim($_POST['simulated_date'] ?? '');
        $mode          = $_POST['mode'] ?? 'dry_run';

        if (empty($simulatedDate)) {
            $this->json(['success' => false, 'message' => 'Simulated date is required.'], 400);
            return;
        }

        $dryRun    = ($mode === 'dry_run');
        $forceSend = ($mode === 'force_send');

        $service = new EmailScheduleService();
        $result  = $service->testRun($simulatedDate, $dryRun, $forceSend);

        // Attach resolved period for UI even on duplicate/error responses
        if (!isset($result['date_from'])) {
            try {
                $period = $service->getResolvedPeriod($simulatedDate);
                $result['date_from']    = $period['from'];
                $result['date_to']      = $period['to'];
                $result['period_label'] = $period['label'];
            } catch (\Throwable $e) { /* non-fatal */ }
        }

        $result['already_sent'] = $service->isPeriodAlreadySent($simulatedDate);

        $this->json($result);
    }
}
