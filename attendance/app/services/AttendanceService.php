<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AttendanceService
{
    /**
     * Return attendance_summary rows with employee / dept / branch / shift context.
     *
     * Selects all available columns.  The break_out, break_in, overtime_in,
     * overtime_out, and break_minutes columns are added by the upgrade migration
     * (upgrade_attendance_break_overtime.sql) or by AttendanceEngine::ensureSchema()
     * on first write.  If they are absent we fall back to NULL / 0 so the
     * monitoring page never throws SQLSTATE[42S22].
     */
    public function monitor(array $filters): array
    {
        $db = Database::connection();

        // Detect which optional columns exist so we never request a missing one
        $hasBreakOut   = $this->colExists($db, 'attendance_summary', 'break_out');
        $hasBreakIn    = $this->colExists($db, 'attendance_summary', 'break_in');
        $hasOtIn       = $this->colExists($db, 'attendance_summary', 'overtime_in');
        $hasOtOut      = $this->colExists($db, 'attendance_summary', 'overtime_out');
        $hasBreakMins  = $this->colExists($db, 'attendance_summary', 'break_minutes');

        $breakOutExpr  = $hasBreakOut  ? 's.break_out'      : 'NULL AS break_out';
        $breakInExpr   = $hasBreakIn   ? 's.break_in'       : 'NULL AS break_in';
        $otInExpr      = $hasOtIn      ? 's.overtime_in'    : 'NULL AS overtime_in';
        $otOutExpr     = $hasOtOut     ? 's.overtime_out'   : 'NULL AS overtime_out';
        $breakMinsExpr = $hasBreakMins ? 's.break_minutes'  : '0 AS break_minutes';

        $where  = [];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]  = 'e.id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[]  = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['branch_id'])) {
            $where[]  = 'e.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 's.day_status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['start_date'])) {
            $where[]  = 's.attendance_date >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[]  = 's.attendance_date <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }
        if (!empty($filters['q'])) {
            $where[]  = "(e.employee_number LIKE :q OR e.first_name LIKE :q OR e.last_name LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql = "SELECT
                    s.id,
                    s.employee_id,
                    s.attendance_date,
                    s.time_in,
                    {$breakOutExpr},
                    {$breakInExpr},
                    s.time_out,
                    {$otInExpr},
                    {$otOutExpr},
                    s.total_hours,
                    {$breakMinsExpr},
                    s.late_minutes,
                    s.undertime_minutes,
                    s.overtime_minutes,
                    s.is_late,
                    s.is_absent,
                    s.day_status,
                    e.employee_number,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    d.name  AS department_name,
                    b.name  AS branch_name,
                    sh.name AS shift_name
                FROM attendance_summary s
                INNER JOIN employees e  ON e.id   = s.employee_id
                LEFT  JOIN departments d ON d.id  = e.department_id
                LEFT  JOIN branches    b ON b.id  = e.branch_id
                LEFT  JOIN shifts     sh ON sh.id = e.shift_id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.attendance_date DESC, e.last_name ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Return attendance event counts grouped by date for the calendar view.
     */
    public function calendarEvents(string $month): array
    {
        $start = $month . '-01';
        $end   = date('Y-m-t', strtotime($start));
        $db    = Database::connection();

        $stmt = $db->prepare(
            "SELECT attendance_date AS event_date, day_status AS type, COUNT(*) AS total
               FROM attendance_summary
              WHERE attendance_date BETWEEN ? AND ?
              GROUP BY attendance_date, day_status"
        );
        $stmt->execute([$start, $end]);
        $events = $stmt->fetchAll();

        $stmt = $db->prepare(
            "SELECT holiday_date AS event_date, type, name AS label, description
               FROM holidays
              WHERE status = 'active' AND holiday_date BETWEEN ? AND ?"
        );
        $stmt->execute([$start, $end]);

        return ['attendance' => $events, 'holidays' => $stmt->fetchAll()];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function colExists(\PDO $db, string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = '{$table}'
                    AND COLUMN_NAME  = '{$column}'"
            )->fetchColumn() > 0;
        }
        return $cache[$key];
    }
}
