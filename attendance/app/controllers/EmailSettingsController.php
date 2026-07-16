<?php

/**
 * EmailSettingsController
 *
 * Routes:
 *   GET  /email-settings       — SMTP configuration form
 *   POST /email-settings       — Save settings
 *   POST /email-settings/test  — Send test email
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\SettingsService;

final class EmailSettingsController extends BaseController
{
    public function index(): void
    {
        require_role(['administrator']);
        $cfg = new SettingsService();

        $settings = $cfg->all('email');
        // Add email schedule settings which are stored in general group
        $scheduleSettings = [
            'email_schedule' => $cfg->get('email_schedule', 'manual'),
            'email_timezone' => $cfg->get('email_timezone', 'UTC'),
        ];
        // Merge schedule settings into the email settings array
        foreach ($scheduleSettings as $key => $value) {
            $settings[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'string',
                'group' => 'general',
                'description' => null,
            ];
        }

        $this->render('email/settings', [
            'title'    => 'Email Settings',
            'settings' => $settings,
        ]);
    }

    public function save(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $cfg  = new SettingsService();
        $post = $_POST;

        // Handle password separately — only update if a new value was entered
        $newPassword = $post['smtp_password_plain'] ?? '';
        unset($post['smtp_password_plain']);

        $allowed = [
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption',
            'smtp_from_name', 'smtp_from_email', 'email_report_recipient',
            'email_report_cc', 'email_report_bcc',
            'email_retry_interval', 'email_max_retries',
            'email_report_enabled', 'email_report_compress',
            'email_schedule', 'email_timezone',
        ];

        $data = [];
        foreach ($allowed as $key) {
            if (isset($post[$key])) {
                $data[$key] = $post[$key];
            }
        }
        // Checkboxes default to 0 when unchecked
        foreach (['email_report_enabled', 'email_report_compress'] as $boolKey) {
            $data[$boolKey] = isset($post[$boolKey]) ? '1' : '0';
        }

        $cfg->saveMany($data);

        if ($newPassword !== '') {
            $cfg->saveSmtpPassword($newPassword);
        }

        (new AuditService())->log('EMAIL_SETTINGS_UPDATED', 'email', null, null, array_keys($data));
        flash('success', 'Email settings saved.');
        redirect('email-settings');
    }

    public function test(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $to = trim($_POST['test_email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Invalid email address.'], 422);
            return;
        }

        $result = (new EmailService())->sendTest($to);
        $this->json($result, $result['success'] ? 200 : 500);
    }
}
