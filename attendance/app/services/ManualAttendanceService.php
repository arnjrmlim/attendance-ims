<?php

/**
 * ManualAttendanceService
 *
 * Handles:
 *   - Employee self-service manual time-in/out requests (pending → approve/reject)
 *   - Administrator direct manual attendance entry
 *   - Attendance validation (duplicate, time-order, out-of-hours warnings)
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ManualAttendanceService
{
    /** Fixed reason stored for auto-approved manual entries (column is NOT NULL). */
    private const AUTO_REASON = 'Manual attendance entry';

    private AuditService        $audit;
    private NotificationService $notify;
    private SettingsService     $cfg;

    public function __construct()
    {
        $this->audit  = new AuditService();
        $this->notify = new NotificationService();
        $this->cfg    = new SettingsService();
    }

    /* ════════════════════════════════════════════════════════
     * Employee Requests
     * ════════════════════════════════════════════════════════ */

    /**
     * Submit a manual time-in or time-out request (employee).
     * Returns ['success' => bool, 'warnings' => [], 'error' => '']
     */
    public function submitRequest(array $data): array
    {
        $employeeId = $data['employee_id']  ?? '';
        $type       = $data['request_type'] ?? '';   // time_in | time_out

        // Date and time are ALWAYS the server's current date/time. They are
        // deliberately never read from client input, so an employee cannot
        // edit, backdate, or otherwise choose when their entry is recorded.
        $date = date('Y-m-d');
        $time = date('H:i:s');

        /* ── Validation ──────────────────────────────────────── */
        if (!in_array($type, ['time_in', 'time_out'], true)) {
            return ['success' => false, 'error' => 'Invalid request type.'];
        }

        $warnings = $this->validateRequest($employeeId, $type, $date, $time);

        // Prevent recording the same type twice in one day (requests are
        // auto-approved now, so any existing row for today already counts).
        $dup = Database::connection()->prepare(
            "SELECT id FROM manual_attendance_requests
             WHERE employee_id = ? AND request_type = ? AND request_date = ?"
        );
        $dup->execute([$employeeId, $type, $date]);
        if ($dup->fetch()) {
            $typeLabelDup = $type === 'time_in' ? 'Time In' : 'Time Out';
            return ['success' => false, 'error' => "A manual {$typeLabelDup} has already been recorded today."];
        }

        // Manual attendance is the primary attendance method: it is recorded
        // immediately and does not require administrator approval.
        $id = uuid_v4();
        Database::connection()->prepare(
            "INSERT INTO manual_attendance_requests
             (id, employee_id, request_type, request_date, requested_time, reason, status, reviewed_at, admin_remarks)
             VALUES (?, ?, ?, ?, ?, ?, 'Approved', NOW(), 'Auto-approved: manual attendance does not require admin approval.')"
        )->execute([$id, $employeeId, $type, $date, $time, self::AUTO_REASON]);

        $this->audit->log('MANUAL_ATTENDANCE_REQUEST_SUBMITTED', 'manual_attendance', $id, null, [
            'employee_id' => $employeeId,
            'type'        => $type,
            'date'        => $date,
            'time'        => $time,
        ]);

        // Apply immediately — no approval step required.
        $this->applyApprovedRequest([
            'employee_id'   => $employeeId,
            'request_date'  => $date,
            'requested_time' => $time,
            'request_type'  => $type,
        ]);

        $this->audit->log('MANUAL_ATTENDANCE_AUTO_APPROVED', 'manual_attendance', $id, null, [
            'employee_id' => $employeeId,
            'type'        => $type,
            'date'        => $date,
            'time'        => $time,
        ]);

        // Notify admins/HR for visibility (informational, not an approval request)
        $typeLabel = $type === 'time_in' ? 'Time In' : 'Time Out';
        $empName   = $this->getEmployeeName($employeeId);
        $this->notify->notifyRoles(
            ['administrator', 'hr'],
            "Manual Attendance Recorded",
            "{$empName} recorded a manual {$typeLabel} for {$date}.",
            'info'
        );

        return ['success' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * List manual attendance requests (for admin approval queue or employee own).
     */
    public function listRequests(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]  = 'mar.employee_id = ?';
            $params[] = $filters['employee_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'mar.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'mar.request_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'mar.request_date <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM manual_attendance_requests mar
             INNER JOIN employees e ON e.id = mar.employee_id {$whereClause}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::connection()->prepare(
            "SELECT mar.*,
                    CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    e.employee_number,
                    d.name AS department_name,
                    u.username AS reviewer_username
             FROM manual_attendance_requests mar
             INNER JOIN employees e ON e.id = mar.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN users u ON u.id = mar.reviewed_by
             {$whereClause}
             ORDER BY mar.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return ['total' => $total, 'rows' => $stmt->fetchAll(), 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Approve a manual attendance request.
     */
    public function approve(string $requestId, string $reviewerUserId, string $remarks = ''): array
    {
        $req = $this->getRequest($requestId);
        if (!$req) {
            return ['success' => false, 'error' => 'Request not found.'];
        }
        if ($req['status'] !== 'Pending') {
            return ['success' => false, 'error' => 'Request is no longer pending.'];
        }

        Database::connection()->prepare(
            "UPDATE manual_attendance_requests
             SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW(), admin_remarks = ?
             WHERE id = ?"
        )->execute([$reviewerUserId, $remarks, $requestId]);

        // Apply to attendance records
        $this->applyApprovedRequest($req);

        $this->audit->log('MANUAL_ATTENDANCE_APPROVED', 'manual_attendance', $requestId, $req, ['remarks' => $remarks]);

        // Notify employee
        $userStmt = Database::connection()->prepare(
            "SELECT u.id FROM users u WHERE u.employee_id = ?"
        );
        $userStmt->execute([$req['employee_id']]);
        $userId = $userStmt->fetchColumn();
        if ($userId) {
            $typeLabel = $req['request_type'] === 'time_in' ? 'Time In' : 'Time Out';
            $this->notify->notify(
                $userId,
                'Manual Attendance Approved',
                "Your manual {$typeLabel} request for {$req['request_date']} has been approved.",
                'success'
            );
        }

        return ['success' => true];
    }

    /**
     * Reject a manual attendance request.
     */
    public function reject(string $requestId, string $reviewerUserId, string $remarks = ''): array
    {
        $req = $this->getRequest($requestId);
        if (!$req) {
            return ['success' => false, 'error' => 'Request not found.'];
        }
        if ($req['status'] !== 'Pending') {
            return ['success' => false, 'error' => 'Request is no longer pending.'];
        }

        Database::connection()->prepare(
            "UPDATE manual_attendance_requests
             SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW(), admin_remarks = ?
             WHERE id = ?"
        )->execute([$reviewerUserId, $remarks, $requestId]);

        $this->audit->log('MANUAL_ATTENDANCE_REJECTED', 'manual_attendance', $requestId, $req, ['remarks' => $remarks]);

        // Notify employee
        $userStmt = Database::connection()->prepare("SELECT id FROM users WHERE employee_id = ?");
        $userStmt->execute([$req['employee_id']]);
        $userId = $userStmt->fetchColumn();
        if ($userId) {
            $typeLabel = $req['request_type'] === 'time_in' ? 'Time In' : 'Time Out';
            $this->notify->notify(
                $userId,
                'Manual Attendance Rejected',
                "Your manual {$typeLabel} request for {$req['request_date']} was rejected. {$remarks}",
                'danger'
            );
        }

        return ['success' => true];
    }

    /**
     * Bulk approve/reject.
     */
    public function bulkAction(array $ids, string $action, string $reviewerUserId, string $remarks = ''): array
    {
        $results = ['approved' => 0, 'rejected' => 0, 'errors' => 0];
        foreach ($ids as $id) {
            $result = $action === 'approve'
                ? $this->approve($id, $reviewerUserId, $remarks)
                : $this->reject($id, $reviewerUserId, $remarks);
            if ($result['success']) {
                $results[$action === 'approve' ? 'approved' : 'rejected']++;
            } else {
                $results['errors']++;
            }
        }
        return $results;
    }

    /* ════════════════════════════════════════════════════════
     * Admin Direct Attendance Entry
     * ════════════════════════════════════════════════════════ */

    /**
     * Administrator creates a manual attendance record directly.
     */
    public function adminCreate(array $data, string $adminUserId): array
    {
        $employeeId = $data['employee_id']      ?? '';
        $date       = $data['attendance_date']  ?? '';
        $timeIn     = !empty($data['time_in'])  ? $date . ' ' . $data['time_in']  : null;
        $timeOut    = !empty($data['time_out']) ? $date . ' ' . $data['time_out'] : null;
        $status     = $data['attendance_status'] ?? 'present';
        $method     = $data['method']           ?? 'Manual Entry';
        $reason     = trim($data['reason']      ?? '');
        $remarks    = trim($data['admin_remarks'] ?? '');

        if (!$employeeId || !$date || !$reason) {
            return ['success' => false, 'error' => 'Employee, date and reason are required.'];
        }

        // Validate time order
        if ($timeIn && $timeOut && strtotime($timeOut) <= strtotime($timeIn)) {
            return ['success' => false, 'error' => 'Time Out must be after Time In.'];
        }

        $warnings = $this->validateAdminEntry($employeeId, $date, $data);

        $id = uuid_v4();
        Database::connection()->prepare(
            "INSERT INTO admin_manual_attendance
             (id, employee_id, attendance_date, time_in, time_out, attendance_status, method, reason, admin_remarks, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$id, $employeeId, $date, $timeIn, $timeOut, $status, $method, $reason, $remarks, $adminUserId]);

        // Also update attendance_summary
        $this->syncSummary($employeeId, $date, $timeIn, $timeOut, $status);

        $this->audit->log('ADMIN_MANUAL_ATTENDANCE_CREATED', 'manual_attendance', $id, null, $data);

        return ['success' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * List admin-created manual attendance records.
     */
    public function listAdminEntries(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]  = 'ama.employee_id = ?';
            $params[] = $filters['employee_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'ama.attendance_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'ama.attendance_date <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM admin_manual_attendance ama {$whereClause}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::connection()->prepare(
            "SELECT ama.*,
                    CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    e.employee_number,
                    u.username AS created_by_name
             FROM admin_manual_attendance ama
             INNER JOIN employees e ON e.id = ama.employee_id
             LEFT JOIN users u ON u.id = ama.created_by
             {$whereClause}
             ORDER BY ama.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return ['total' => $total, 'rows' => $stmt->fetchAll()];
    }

    /* ════════════════════════════════════════════════════════
     * Validation
     * ════════════════════════════════════════════════════════ */

    /**
     * Check for issues with an employee request.
     * Returns array of warning strings (non-blocking unless critical).
     */
    private function validateRequest(string $employeeId, string $type, string $date, string $time): array
    {
        $warnings = [];
        $db       = Database::connection();

        // Check for existing attendance record of this type
        $existing = $db->prepare(
            "SELECT id FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
        );
        $existing->execute([$employeeId, $date]);
        $summary = $existing->fetch();

        if ($type === 'time_in' && $summary && $summary['time_in'] ?? false) {
            $warnings[] = 'An existing Time In record already exists for this date.';
        }
        if ($type === 'time_out' && $summary && $summary['time_out'] ?? false) {
            $warnings[] = 'An existing Time Out record already exists for this date.';
        }

        // Check allowed hours warning
        $allowedFrom = $this->cfg->get('allowed_time_in_from', '06:00');
        $allowedTo   = $this->cfg->get('allowed_time_in_to',   '10:00');
        if ($type === 'time_in' && $time) {
            if ($time < $allowedFrom || $time > $allowedTo) {
                $warnings[] = "Requested time ({$time}) is outside the normal allowed window ({$allowedFrom}–{$allowedTo}).";
            }
        }

        return $warnings;
    }

    private function validateAdminEntry(string $employeeId, string $date, array $data): array
    {
        $warnings = [];

        // Check if attendance already exists for this date
        $stmt = Database::connection()->prepare(
            "SELECT id FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        if ($stmt->fetch()) {
            $warnings[] = 'An attendance record already exists for this employee on this date. It will be overwritten.';
        }

        // Out-of-hours warning
        $allowedFrom = $this->cfg->get('allowed_time_in_from', '06:00');
        $allowedTo   = $this->cfg->get('allowed_time_in_to',   '22:00');
        if (!empty($data['time_in'])) {
            if ($data['time_in'] < $allowedFrom || $data['time_in'] > $allowedTo) {
                $warnings[] = "Time In ({$data['time_in']}) is outside the configurable allowed window ({$allowedFrom}–{$allowedTo}). Please verify before saving.";
            }
        }

        return $warnings;
    }

    /* ════════════════════════════════════════════════════════
     * Internal Helpers
     * ════════════════════════════════════════════════════════ */

    private function applyApprovedRequest(array $req): void
    {
        $employeeId = $req['employee_id'];
        $date       = $req['request_date'];
        $time       = $req['requested_time'];
        $type       = $req['request_type'];

        $datetime = $date . ' ' . $time;

        // Upsert attendance_summary row
        $stmt = Database::connection()->prepare(
            "SELECT id, time_in, time_out FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        $existing = $stmt->fetch();

        if ($existing) {
            $col = $type === 'time_in' ? 'time_in' : 'time_out';
            Database::connection()->prepare(
                "UPDATE attendance_summary
                 SET {$col} = ?, day_status = 'present', updated_at = NOW()
                 WHERE employee_id = ? AND attendance_date = ?"
            )->execute([$datetime, $employeeId, $date]);
        } else {
            $col = $type === 'time_in' ? 'time_in' : 'time_out';
            Database::connection()->prepare(
                "INSERT INTO attendance_summary (id, employee_id, attendance_date, {$col}, day_status)
                 VALUES (?, ?, ?, ?, 'present')"
            )->execute([uuid_v4(), $employeeId, $date, $datetime]);
        }

        // Recalculate attendance metrics if both time_in and time_out are present
        $this->recalculateAttendanceMetrics($employeeId, $date);

        // Insert into raw attendance table
        Database::connection()->prepare(
            "INSERT IGNORE INTO attendance
             (id, employee_id, attendance_date, time_recorded, attendance_type, method, recorded_by)
             VALUES (?, ?, ?, ?, ?, 'manual', ?)"
        )->execute([
            uuid_v4(), $employeeId, $date, $datetime,
            $type === 'time_in' ? 'time_in' : 'time_out',
            current_user()['id'] ?? null,
        ]);
    }

    private function syncSummary(
        string  $employeeId,
        string  $date,
        ?string $timeIn,
        ?string $timeOut,
        string  $status
    ): void {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        $existing = $stmt->fetch();

        if ($existing) {
            Database::connection()->prepare(
                "UPDATE attendance_summary
                 SET time_in = ?, time_out = ?, day_status = ?, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$timeIn, $timeOut, $status, $existing['id']]);
        } else {
            Database::connection()->prepare(
                "INSERT INTO attendance_summary (id, employee_id, attendance_date, time_in, time_out, day_status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([uuid_v4(), $employeeId, $date, $timeIn, $timeOut, $status]);
        }

        // Recalculate metrics after sync
        if ($timeIn && $timeOut) {
            $this->recalculateAttendanceMetrics($employeeId, $date);
        }
    }

    /**
     * Recalculate attendance metrics (late, undertime, total hours) based on shift
     */
    private function recalculateAttendanceMetrics(string $employeeId, string $date): void
    {
        $db = Database::connection();

        // Get current attendance summary
        $stmt = $db->prepare(
            "SELECT time_in, time_out FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        $summary = $stmt->fetch();

        if (!$summary || !$summary['time_in'] || !$summary['time_out']) {
            return; // Cannot calculate without both times
        }

        // Get employee's shift information
        $stmt = $db->prepare(
            "SELECT s.time_in, s.time_out, s.grace_period_minutes, s.required_hours, s.lunch_break_minutes
             FROM shifts s
             INNER JOIN employees e ON e.shift_id = s.id
             WHERE e.id = ?"
        );
        $stmt->execute([$employeeId]);
        $shift = $stmt->fetch();

        if (!$shift) {
            return; // No shift assigned, cannot calculate
        }

        $timeIn = strtotime($summary['time_in']);
        $timeOut = strtotime($summary['time_out']);
        $shiftTimeIn = strtotime($date . ' ' . $shift['time_in']);
        $shiftTimeOut = strtotime($date . ' ' . $shift['time_out']);

        // Calculate total hours worked (excluding lunch break if applicable)
        $totalSeconds = $timeOut - $timeIn;
        $lunchMinutes = (int) $shift['lunch_break_minutes'];
        if ($lunchMinutes > 0 && $totalSeconds > ($lunchMinutes * 60)) {
            $totalSeconds -= ($lunchMinutes * 60);
        }
        $totalHours = round($totalSeconds / 3600, 2);

        // Calculate late minutes
        $lateMinutes = 0;
        $gracePeriod = (int) $shift['grace_period_minutes'];
        if ($timeIn > $shiftTimeIn) {
            $lateSeconds = $timeIn - $shiftTimeIn;
            $lateMinutes = (int) round($lateSeconds / 60);
            // Subtract grace period
            if ($lateMinutes > $gracePeriod) {
                $lateMinutes -= $gracePeriod;
            } else {
                $lateMinutes = 0;
            }
        }

        // Calculate undertime minutes
        $undertimeMinutes = 0;
        $requiredHours = (float) $shift['required_hours'];
        if ($totalHours < $requiredHours) {
            $undertimeMinutes = (int) round(($requiredHours - $totalHours) * 60);
        }

        // Update attendance summary with calculated metrics
        $isLate = $lateMinutes > 0 ? 1 : 0;
        $stmt = $db->prepare(
            "UPDATE attendance_summary
             SET total_hours = ?, late_minutes = ?, undertime_minutes = ?, is_late = ?
             WHERE employee_id = ? AND attendance_date = ?"
        );
        $stmt->execute([$totalHours, $lateMinutes, $undertimeMinutes, $isLate, $employeeId, $date]);
    }

    private function getRequest(string $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM manual_attendance_requests WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function getEmployeeName(string $employeeId): string
    {
        $stmt = Database::connection()->prepare(
            "SELECT CONCAT(first_name,' ',last_name) FROM employees WHERE id = ?"
        );
        $stmt->execute([$employeeId]);
        return (string) ($stmt->fetchColumn() ?: 'Employee');
    }

    /* ── Pending counts for dashboard ───────────────────────── */
    public function pendingCount(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Pending'")
            ->fetchColumn();
    }
}
