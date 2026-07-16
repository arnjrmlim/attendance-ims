<?php

/**
 * EmailScheduleController
 *
 * Handles manual triggering of scheduled email jobs and provides
 * an endpoint for cron jobs to call.
 *
 * Routes:
 *   GET  /email-schedule/check  — Check and send scheduled email (for cron)
 *   GET  /email-schedule/status  — Get current schedule status
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmailScheduleService;

final class EmailScheduleController extends BaseController
{
    /**
     * Check and send scheduled email.
     * This endpoint is intended to be called by a cron job.
     * Bypasses authentication for localhost requests for cron job compatibility.
     */
    public function check(): void
    {
        // Allow localhost requests without authentication for cron jobs
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($clientIp, ['127.0.0.1', '::1', 'localhost'])) {
            require_role(['administrator']);
        }
        
        $service = new EmailScheduleService();
        $result = $service->checkAndSendScheduled();
        
        // Return JSON response for cron job monitoring
        $this->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Get current schedule status.
     * Shows the current configuration and next scheduled send date.
     */
    public function status(): void
    {
        require_role(['administrator']);
        
        $cfg = new \App\Services\SettingsService();
        
        $schedule = $cfg->get('email_schedule', 'manual');
        $timezone = $cfg->get('email_timezone', 'UTC');
        $lastSent = $cfg->get('last_email_sent_date', '');
        
        // Calculate next scheduled send dates
        $now = new \DateTime('now', new \DateTimeZone($timezone));
        $next15th = clone $now;
        $next15th->modify('first day of next month');

        $next15th->setDate(
            (int)$next15th->format('Y'),
            (int)$next15th->format('m'),
            15
        );
        
        $nextEndOfMonth = clone $now;
        $nextEndOfMonth->modify('last day of this month');
        if ($nextEndOfMonth < $now) {
            $nextEndOfMonth->modify('last day of next month');
        }
        
        $nextSendDates = [];
        if ($schedule === '15th' || $schedule === 'both') {
            $nextSendDates[] = $next15th->format('Y-m-d') . ' (15th)';
        }
        if ($schedule === 'end_of_month' || $schedule === 'both') {
            $nextSendDates[] = $nextEndOfMonth->format('Y-m-d') . ' (End of Month)';
        }
        
        $this->json([
            'schedule' => $schedule,
            'timezone' => $timezone,
            'last_sent_date' => $lastSent,
            'next_send_dates' => $nextSendDates,
            'current_date' => $now->format('Y-m-d'),
            'current_day' => (int) $now->format('d'),
            'days_in_month' => (int) $now->format('t')
        ]);
    }
}
