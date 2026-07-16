<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AttendanceService
{
    public function monitor(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['employee_id'])) {
            $where[] = 'e.id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['branch_id'])) {
            $where[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 's.day_status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['start_date'])) {
            $where[] = 's.attendance_date >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[] = 's.attendance_date <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(e.employee_number LIKE :q OR e.first_name LIKE :q OR e.last_name LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        $sql = "SELECT s.*, e.employee_number, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    d.name AS department_name, b.name AS branch_name, sh.name AS shift_name
                FROM attendance_summary s
                INNER JOIN employees e ON e.id = s.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN shifts sh ON sh.id = e.shift_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.attendance_date DESC, e.last_name ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function calendarEvents(string $month): array
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT attendance_date AS event_date, day_status AS type, COUNT(*) AS total
             FROM attendance_summary WHERE attendance_date BETWEEN ? AND ? GROUP BY attendance_date, day_status"
        );
        $stmt->execute([$start, $end]);
        $events = $stmt->fetchAll();
        $stmt = $db->prepare("SELECT holiday_date AS event_date, type, name AS label, description FROM holidays WHERE status = 'active' AND holiday_date BETWEEN ? AND ?");
        $stmt->execute([$start, $end]);
        return ['attendance' => $events, 'holidays' => $stmt->fetchAll()];
    }
}
