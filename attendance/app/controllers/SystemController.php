<?php

/**
 * SystemController
 *
 * Routes:
 *   GET  /system/settings      — System configuration form (grouped)
 *   POST /system/settings      — Save system settings
 *   GET  /system/health        — System health dashboard
 *   GET  /system/job-logs      — Background job history
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\SettingsService;
use App\Services\SystemHealthService;
use App\Core\Database;

final class SystemController extends BaseController
{
    /* ── Settings ───────────────────────────────────────────── */

    public function settings(): void
    {
        require_role(['administrator']);

        $cfg     = new SettingsService();
        $grouped = $cfg->allGrouped();

        // Mask SMTP password
        foreach ($grouped['email'] ?? [] as &$row) {
            if ($row['key'] === 'smtp_password') {
                $row['value'] = $row['value'] !== '' ? '••••••••' : '';
            }
        }
        unset($row);

        $this->render('system/settings', [
            'title'   => 'System Settings',
            'grouped' => $grouped,
        ]);
    }

    public function saveSettings(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $cfg    = new SettingsService();
        $post   = $_POST;

        // Groups the admin can modify on this page (NOT email — handled by EmailSettingsController)
        $allowedGroups = ['company', 'attendance', 'system', 'security', 'backup', 'maintenance'];

        // Fetch all keys belonging to allowed groups
        $db     = Database::connection();
        $stmt   = $db->query(
            "SELECT `key`, `group` FROM settings WHERE `group` IN ('" . implode("','", $allowedGroups) . "')"
        );
        $rows   = $stmt->fetchAll();

        $data = [];
        foreach ($rows as $row) {
            $key = $row['key'];
            if (array_key_exists($key, $post)) {
                $data[$key] = $post[$key];
            }
        }

        // Boolean checkboxes default to 0 when unchecked
        $boolKeys = [
            'backup_enabled','backup_daily','backup_weekly','backup_monthly',
            'backup_compress','late_deduction','method_pin','method_qr',
            'method_rfid','method_manual',
        ];
        foreach ($boolKeys as $bk) {
            if (array_key_exists($bk, $post) || in_array($bk, array_keys($data), true)) {
                $data[$bk] = isset($post[$bk]) ? '1' : '0';
            }
        }

        // Handle logo upload
        if (!empty($_FILES['company_logo']['name'])) {
            try {
                $logoPath = save_upload('company_logo', ['svg', 'png', 'jpg', 'jpeg']);
                if ($logoPath) {
                    $data['company_logo'] = $logoPath;
                }
            } catch (\RuntimeException $e) {
                flash('error', 'Logo upload failed: ' . $e->getMessage());
                redirect('system/settings');
            }
        }

        $old = [];
        foreach (array_keys($data) as $k) {
            $old[$k] = $cfg->get($k);
        }

        $cfg->saveMany($data);
        $cfg->flushCache();

        (new AuditService())->log('SYSTEM_SETTINGS_UPDATED', 'system', null, $old, $data);
        flash('success', 'System settings saved.');
        redirect('system/settings');
    }

    /* ── Health Dashboard ───────────────────────────────────── */

    public function health(): void
    {
        require_role(['administrator', 'hr']);

        $report = (new SystemHealthService())->report();

        $this->render('system/health', [
            'title'  => 'System Health',
            'report' => $report,
        ]);
    }

    /* ── Job Logs ───────────────────────────────────────────── */

    public function jobLogs(): void
    {
        require_role(['administrator']);

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        $db    = Database::connection();
        $total = (int) $db->query('SELECT COUNT(*) FROM job_logs')->fetchColumn();
        $stmt  = $db->prepare(
            'SELECT * FROM job_logs ORDER BY started_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$perPage, $offset]);
        $rows = $stmt->fetchAll();

        $this->render('system/job_logs', [
            'title'   => 'Background Job Logs',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ]);
    }
}
