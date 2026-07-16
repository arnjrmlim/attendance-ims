<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class SearchController extends BaseController
{
    public function index(): void
    {
        require_login();
        $q = trim((string) ($_GET['q'] ?? ''));
        $results = [];
        if ($q !== '') {
            $db = Database::connection();
            $like = '%' . $q . '%';
            $queries = [
                'Employees' => ["SELECT employee_number AS label, CONCAT(first_name, ' ', last_name) AS detail FROM employees WHERE employee_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 10", [$like, $like, $like]],
                'Departments' => ['SELECT name AS label, code AS detail FROM departments WHERE name LIKE ? OR code LIKE ? LIMIT 10', [$like, $like]],
                'Leaves' => ['SELECT leave_type AS label, status AS detail FROM leave_requests WHERE reason LIKE ? OR leave_type LIKE ? LIMIT 10', [$like, $like]],
                'Corrections' => ['SELECT correction_type AS label, status AS detail FROM attendance_corrections WHERE reason LIKE ? OR correction_type LIKE ? LIMIT 10', [$like, $like]],
                'Notifications' => ['SELECT title AS label, message AS detail FROM notifications WHERE recipient_user_id = ? AND (title LIKE ? OR message LIKE ?) LIMIT 10', [current_user()['id'], $like, $like]],
            ];
            foreach ($queries as $group => [$sql, $params]) {
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $results[$group] = $stmt->fetchAll();
            }
        }
        $this->render('search/index', ['title' => 'Search', 'q' => $q, 'results' => $results]);
    }
}
