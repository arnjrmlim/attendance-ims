<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class DashboardService
{
    public function stats(?string $employeeId = null): array
    {
        $db = Database::connection();

        /* ── Employee view ─────────────────────────────────── */
        if ($employeeId) {
            $stmt = $db->prepare(
                'SELECT s.*, b.name AS branch_name
                 FROM attendance_summary s
                 LEFT JOIN employees e ON e.id = s.employee_id
                 LEFT JOIN branches b ON b.id = e.branch_id
                 WHERE s.employee_id = ?
                 ORDER BY s.attendance_date DESC, s.updated_at DESC LIMIT 10'
            );
            $stmt->execute([$employeeId]);
            $history = $stmt->fetchAll();

            $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'Pending'");
            $stmt->execute([$employeeId]);
            $pendingLeaves = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM attendance_corrections WHERE employee_id = ? AND status = 'Pending'");
            $stmt->execute([$employeeId]);
            $pendingCorrections = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM manual_attendance_requests WHERE employee_id = ? AND status = 'Pending'");
            $stmt->execute([$employeeId]);
            $pendingManualRequests = (int) $stmt->fetchColumn();

            // Today's attendance summary for this employee
            $stmt = $db->prepare(
                'SELECT * FROM attendance_summary WHERE employee_id = ? AND attendance_date = CURDATE()'
            );
            $stmt->execute([$employeeId]);
            $todaySummary = $stmt->fetch() ?: null;

            // Visible announcements
            $userStmt = $db->prepare('SELECT department_id FROM employees WHERE id = ?');
            $userStmt->execute([$employeeId]);
            $empRow = $userStmt->fetch();
            $announcements = (new AnnouncementService())->visible('', $employeeId, $empRow['department_id'] ?? null);

            return [
                'history'                => $history,
                'pending_leaves'         => $pendingLeaves,
                'pending_corrections'    => $pendingCorrections,
                'pending_manual_requests'=> $pendingManualRequests,
                'today_summary'          => $todaySummary,
                'announcements'          => $announcements,
                'leave_balance'          => 'See leave module for details',
            ];
        }

        /* ── Admin / HR view ──────────────────────────────── */
        // Phase 4: Enhanced employee statistics (backward compatible)
        $totalEmployees = (int) $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        $activeEmployees = (int) $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
        $inactiveEmployees = (int) $db->query("SELECT COUNT(*) FROM employees WHERE status = 'inactive'")->fetchColumn();
        $suspendedEmployees = (int) $db->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Suspended'")->fetchColumn();
        $resignedEmployees = (int) $db->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Resigned'")->fetchColumn();
        $recentHires = (int) $db->query("SELECT COUNT(*) FROM employees WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        
        $employees  = $activeEmployees;
        $present    = (int) $db->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_summary WHERE attendance_date = CURDATE() AND day_status = 'present'")->fetchColumn();
        $late       = (int) $db->query("SELECT COUNT(*) FROM attendance_summary WHERE attendance_date = CURDATE() AND is_late = 1")->fetchColumn();
        $onLeave    = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date")->fetchColumn();

        $pendingCorrections = (int) $db->query("SELECT COUNT(*) FROM attendance_corrections WHERE status = 'Pending'")->fetchColumn();
        $pendingLeaves      = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();

        // Phase 3 additions
        $pendingManual = (int) $db->query("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Pending'")->fetchColumn();
        $failedEmails  = (int) $db->query("SELECT COUNT(*) FROM email_logs WHERE status IN ('failed','queued')")->fetchColumn();

        $lastBackup = $db->query(
            "SELECT filename, created_at, status FROM backup_logs ORDER BY created_at DESC LIMIT 1"
        )->fetch();

        $recentAttendance = $db->query(
            "SELECT s.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
             FROM attendance_summary s
             INNER JOIN employees e ON e.id = s.employee_id
             ORDER BY s.attendance_date DESC, s.updated_at DESC LIMIT 10"
        )->fetchAll();

        // Recent announcements (published, not expired)
        $announcements = $db->query(
            "SELECT title, status, created_at FROM announcements
             WHERE status = 'published'
               AND (expire_at IS NULL OR expire_at >= NOW())
             ORDER BY pinned DESC, created_at DESC LIMIT 5"
        )->fetchAll();

        // Attendance trend: last 7 days present count
        $trend = $db->query(
            "SELECT attendance_date, COUNT(*) AS cnt
             FROM attendance_summary
             WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               AND day_status = 'present'
             GROUP BY attendance_date
             ORDER BY attendance_date ASC"
        )->fetchAll();

        return [
            'employees'           => $employees,
            'total_employees'     => $totalEmployees,
            'active_employees'    => $activeEmployees,
            'inactive_employees'  => $inactiveEmployees,
            'suspended_employees' => $suspendedEmployees,
            'resigned_employees'  => $resignedEmployees,
            'recent_hires'        => $recentHires,
            'present'             => $present,
            'absent'              => max(0, $employees - $present - $onLeave),
            'late'                => $late,
            'on_leave'            => $onLeave,
            'pending_corrections' => $pendingCorrections,
            'pending_leaves'      => $pendingLeaves,
            'pending_manual'      => $pendingManual,
            'failed_emails'       => $failedEmails,
            'last_backup'         => $lastBackup,
            'attendance_percentage'=> $employees > 0 ? round(($present / $employees) * 100, 1) : 0,
            'recent_attendance'   => $recentAttendance,
            'announcements'       => $announcements,
            'trend'               => $trend,
            'today'               => date('Y-m-d'),
        ];
    }
}
