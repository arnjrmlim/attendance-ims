<?php

/**
 * BackupController
 *
 * Routes:
 *   GET  /backups               — Backup history + controls
 *   POST /backups/run           — Run manual backup
 *   GET  /backups/download/{id} — Download backup file
 *   POST /backups/restore       — Restore from backup
 *   POST /backups/delete        — Delete backup log + file
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\NotificationService;

final class BackupController extends BaseController
{
    private BackupService $svc;

    public function __construct()
    {
        $this->svc = new BackupService();
    }

    public function index(): void
    {
        require_role(['administrator']);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = $this->svc->logs($page, 20);

        $this->render('backups/index', [
            'title'   => 'Database Backups',
            'rows'    => $data['rows'],
            'total'   => $data['total'],
            'page'    => $page,
            'perPage' => 20,
        ]);
    }

    public function run(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $type   = in_array($_POST['type'] ?? '', ['daily','weekly','monthly','manual'], true)
                  ? $_POST['type']
                  : 'manual';
        $user   = current_user();
        $result = $this->svc->run($type, 'manual', $user['id']);

        if ($result['success']) {
            (new AuditService())->log('BACKUP_CREATED', 'backup', $result['log_id'], null, ['type' => $type]);
            flash('success', 'Backup created successfully.');
        } else {
            (new NotificationService())->notifyRoles(
                ['administrator'],
                'Manual Backup Failed',
                'Backup failed: ' . ($result['error'] ?? 'Unknown error'),
                'danger'
            );
            flash('error', 'Backup failed: ' . ($result['error'] ?? 'Unknown error'));
        }
        redirect('backups');
    }

    public function download(): void
    {
        require_role(['administrator']);

        // Route: /backups/download  with GET param id
        $id   = trim($_GET['id'] ?? '');
        $path = $this->svc->getFilePath($id);

        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo 'Backup file not found.';
            return;
        }

        (new AuditService())->log('BACKUP_DOWNLOADED', 'backup', $id);

        $filename = basename($path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        readfile($path);
        exit;
    }

    public function restore(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $id     = trim($_POST['id'] ?? '');
        $result = $this->svc->restore($id);

        if ($result['success']) {
            (new AuditService())->log('BACKUP_RESTORED', 'backup', $id);
            flash('success', 'Database restored successfully. Please verify your data.');
        } else {
            flash('error', 'Restore failed: ' . ($result['error'] ?? 'Unknown error'));
        }
        redirect('backups');
    }

    public function delete(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $id = trim($_POST['id'] ?? '');
        $this->svc->delete($id);
        (new AuditService())->log('BACKUP_DELETED', 'backup', $id);
        flash('success', 'Backup deleted.');
        redirect('backups');
    }
}
