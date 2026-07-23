<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AttendanceService
{
    /**
     * Return attendance monitoring rows with employee / dept / branch / shift context.
     *
     * Returns ONLY employees who have:
     * - Attendance records for the selected date, OR
     * - Approved leave covering the selected date
     *
     * Employees with no attendance activity and no approved leave are NOT displayed.
     *
     * Approved leave takes highest priority over all attendance calculations.
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

        // Determine monitoring date(s)
        $hasDateFilter = !empty($filters['start_date']) || !empty($filters['end_date']);
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $isDateRange = ($startDate && $endDate && $startDate !== $endDate);

        // Build parameter array with unique names
        $params = [];
        if ($hasDateFilter) {
            $params = [
                'attendance_start_date' => $startDate,
                'attendance_end_date' => $endDate,
                'leave_start_date' => $startDate,
                'leave_end_date' => $endDate,
            ];
        }

        // Build WHERE conditions
        $conditions = ["e.status = 'active'"];

        // Department filter
        if (!empty($filters['department_id'])) {
            $conditions[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        // Branch filter
        if (!empty($filters['branch_id'])) {
            $conditions[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        // Search filter
        if (!empty($filters['q'])) {
            $searchValue = '%' . $filters['q'] . '%';
            $conditions[] = '(e.employee_number LIKE :search1 OR e.first_name LIKE :search2 OR e.last_name LIKE :search3)';
            $params['search1'] = $searchValue;
            $params['search2'] = $searchValue;
            $params['search3'] = $searchValue;
        }

        // Status filter - will be applied separately to each UNION part
        $attendanceConditions = $conditions;
        $leaveConditions = $conditions;
        
        // Duplicate search parameters for leave part to avoid parameter conflicts
        if (!empty($filters['q'])) {
            $params['search4'] = $searchValue;
            $params['search5'] = $searchValue;
            $params['search6'] = $searchValue;
            $leaveConditions = array_map(function($cond) {
                $cond = str_replace(':search1', ':search4', $cond);
                $cond = str_replace(':search2', ':search5', $cond);
                $cond = str_replace(':search3', ':search6', $cond);
                return $cond;
            }, $leaveConditions);
        }
        
        // Duplicate department_id parameter for leave part
        if (!empty($filters['department_id'])) {
            $params['department_id_leave'] = $filters['department_id'];
            $leaveConditions = array_map(function($cond) {
                return str_replace(':department_id', ':department_id_leave', $cond);
            }, $leaveConditions);
        }
        
        // Duplicate branch_id parameter for leave part
        if (!empty($filters['branch_id'])) {
            $params['branch_id_leave'] = $filters['branch_id'];
            $leaveConditions = array_map(function($cond) {
                return str_replace(':branch_id', ':branch_id_leave', $cond);
            }, $leaveConditions);
        }
        
        // Leave type filter - only applies to leave part
        if (!empty($filters['leave_type'])) {
            $leaveConditions[] = 'lr.leave_type = :leave_type';
            $params['leave_type'] = $filters['leave_type'];
            // Exclude attendance records when filtering by leave type
            $attendanceConditions[] = '1=0';
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'leave') {
                // For leave filter, exclude attendance records
                $attendanceConditions[] = '1=0'; // No attendance records
                $leaveConditions[] = 'lr.status = :leave_status';
                $params['leave_status'] = 'Approved';
            } else {
                // For attendance status filter, exclude leave records
                $attendanceConditions[] = 's.day_status = :status';
                $params['status'] = $filters['status'];
                $leaveConditions[] = '1=0'; // No leave records
            }
        }

        // Build the main SQL query using UNION ALL to separate attendance and leave records
        // This ensures one row per employee per date instead of collapsing into one row per employee
        $sql = "SELECT
                    e.id AS employee_id,
                    e.employee_number,
                    e.first_name,
                    e.middle_name,
                    e.last_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.last_name AS sort_last_name,
                    e.first_name AS sort_first_name,
                    d.name AS department_name,
                    b.name AS branch_name,
                    sh.name AS shift_name,
                    s.id,
                    s.attendance_date,
                    s.attendance_date AS display_date,
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
                    NULL AS leave_id,
                    NULL AS leave_type,
                    NULL AS leave_start_date,
                    NULL AS leave_end_date,
                    NULL AS leave_duration,
                    NULL AS leave_status,
                    NULL AS leave_approval_date,
                    s.day_status AS computed_status,
                    'attendance' AS row_source
                FROM employees e
                INNER JOIN attendance_summary s ON s.employee_id = e.id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN shifts sh ON sh.id = e.shift_id
                WHERE " . implode(' AND ', $attendanceConditions);

        // Add date filter for attendance
        if ($hasDateFilter) {
            $sql .= " AND s.attendance_date BETWEEN :attendance_start_date AND :attendance_end_date";
        }

        $sql .= "
                UNION ALL
                SELECT
                    e.id AS employee_id,
                    e.employee_number,
                    e.first_name,
                    e.middle_name,
                    e.last_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.last_name AS sort_last_name,
                    e.first_name AS sort_first_name,
                    d.name AS department_name,
                    b.name AS branch_name,
                    sh.name AS shift_name,
                    NULL AS id,
                    lr.start_date AS attendance_date,
                    lr.start_date AS display_date,
                    NULL AS time_in,
                    NULL AS break_out,
                    NULL AS break_in,
                    NULL AS time_out,
                    NULL AS overtime_in,
                    NULL AS overtime_out,
                    NULL AS total_hours,
                    0 AS break_minutes,
                    NULL AS late_minutes,
                    NULL AS undertime_minutes,
                    NULL AS overtime_minutes,
                    NULL AS is_late,
                    NULL AS is_absent,
                    'leave' AS day_status,
                    lr.id AS leave_id,
                    lr.leave_type AS leave_type,
                    lr.start_date AS leave_start_date,
                    lr.end_date AS leave_end_date,
                    lr.number_of_days AS leave_duration,
                    lr.status AS leave_status,
                    lr.approval_date AS leave_approval_date,
                    'leave' AS computed_status,
                    'leave' AS row_source
                FROM employees e
                INNER JOIN leave_requests lr ON lr.employee_id = e.id AND lr.status = 'Approved'
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN shifts sh ON sh.id = e.shift_id
                WHERE " . implode(' AND ', $leaveConditions);

        // Add date filter for leave
        if ($hasDateFilter) {
            $sql .= " AND lr.start_date <= :leave_end_date AND lr.end_date >= :leave_start_date";
        }

        $sql .= " ORDER BY display_date DESC, sort_last_name ASC, sort_first_name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Post-process: update day_status to computed_status for leave records
        foreach ($results as &$row) {
            if ($row['computed_status'] === 'leave') {
                $row['day_status'] = 'leave';
                $row['leave_type'] = $row['leave_type'] ?? null;
                $row['leave_duration'] = $row['leave_duration'] ?? null;
                $row['leave_start_date'] = $row['leave_start_date'] ?? null;
                $row['leave_end_date'] = $row['leave_end_date'] ?? null;
                // Clear attendance fields when on leave
                $row['time_in'] = null;
                $row['break_out'] = null;
                $row['break_in'] = null;
                $row['time_out'] = null;
                $row['overtime_in'] = null;
                $row['overtime_out'] = null;
                $row['total_hours'] = null;
                $row['late_minutes'] = null;
                $row['undertime_minutes'] = null;
                $row['overtime_minutes'] = null;
                $row['is_late'] = null;
                $row['is_absent'] = null;
            }
        }

        return $results;
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
