<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class AuditController extends BaseController
{
    public function index(): void
    {
        require_role(['administrator', 'hr']);
        $where = [];
        $params = [];
        if (!empty($_GET['module'])) {
            $where[] = 'module = :module';
            $params['module'] = $_GET['module'];
        }
        if (!empty($_GET['q'])) {
            $where[] = '(username LIKE :q OR action LIKE :q OR record_id LIKE :q)';
            $params['q'] = '%' . $_GET['q'] . '%';
        }
        $sql = 'SELECT * FROM audit_logs';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 200';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $this->render('audit/index', ['title' => 'Audit Trail', 'rows' => $stmt->fetchAll()]);
    }
}
