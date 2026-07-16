<?php

/**
 * EmailLogsController
 *
 * Routes:
 *   GET  /email-logs           — List with search/filter/pagination
 *   POST /email-logs/resend    — Manually resend a queued/failed email
 *   POST /email-logs/delete    — Delete a log entry
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\AuditService;
use App\Services\EmailService;

final class EmailLogsController extends BaseController
{
    public function index(): void
    {
        require_role(['administrator', 'hr']);

        $status    = $_GET['status']    ?? '';
        $dateFrom  = $_GET['date_from'] ?? '';
        $dateTo    = $_GET['date_to']   ?? '';
        $q         = $_GET['q']         ?? '';
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $perPage   = 20;

        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }
        if ($dateFrom !== '') {
            $where[]  = 'created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[]  = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($q !== '') {
            $where[]  = '(recipient LIKE ? OR subject LIKE ? OR report_period LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $db          = Database::connection();

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM email_logs {$whereClause}");
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();

        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare(
            "SELECT * FROM email_logs {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Summary counts
        $counts = $db->query(
            "SELECT status, COUNT(*) AS cnt FROM email_logs GROUP BY status"
        )->fetchAll();
        $summary = array_column($counts, 'cnt', 'status');

        $this->render('email/logs', [
            'title'   => 'Email Logs',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'filters' => compact('status', 'dateFrom', 'dateTo', 'q'),
            'summary' => $summary,
        ]);
    }

    public function resend(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $id     = trim($_POST['id'] ?? '');
        $result = (new EmailService())->resend($id);

        (new AuditService())->log('EMAIL_RESENT', 'email', $id);

        if ($result) {
            flash('success', 'Email queued for resend.');
        } else {
            flash('error', 'Resend failed. Check SMTP settings or try again later.');
        }
        redirect('email-logs');
    }

    public function delete(): void
    {
        require_role(['administrator']);
        verify_csrf();

        $id = trim($_POST['id'] ?? '');
        Database::connection()->prepare('DELETE FROM email_logs WHERE id = ?')->execute([$id]);
        (new AuditService())->log('EMAIL_LOG_DELETED', 'email', $id);
        flash('success', 'Log entry deleted.');
        redirect('email-logs');
    }
}
