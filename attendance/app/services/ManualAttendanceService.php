<?php

/**
 * ManualAttendanceService
 *
 * Handles:
 *   - Employee self-service manual attendance requests (all 6 types)
 *   - Administrator direct manual attendance entry (all 6 types)
 *   - Delegates ALL summary computation to AttendanceEngine
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ManualAttendanceService
{
    /** Reason stored for auto-approved employee self-service entries. */
    private const AUTO_REASON = 'Manual attendance entry';

    private AuditService        $audit;
    private NotificationService $notify;
    private SettingsService     $cfg;
    private AttendanceEngine    $engine;

    public function __construct()
    {
        $this->audit  = new AuditService();
        $this->notify = new NotificationService();
        $this->cfg    = new SettingsService();
        $this->engine = new AttendanceEngine();
    }

    /* ════════════════════════════════════════════════════════
     * Employee Self-Service Requests
     * ════════════════════════════════════════════════════════ */

    /**
     * Submit a manual attendance request (employee self-service).
     *
     * Supported types: time_in | break_out | break_in | time_out |
     *                  overtime_in | overtime_out
     *
     * Date and server time are always set by the server — employees cannot
     * choose when their entry is recorded.
     *
     * The entry is applied immediately (auto-approved) without admin review.
     *
     * @return array{success:bool, id?:string, warnings?:list<string>, error?:string}
     */
    public function submitRequest(array $data): array
    {
        $employeeId = $data['employee_id']  ?? '';
        $rawType    = $data['request_type'] ?? '';
        $type       = $this->engine->canonicalType($rawType);

        if (!in_array($type, AttendanceEngine::VALID_TYPES, true)) {
            return ['success' => false, 'error' => 'Invalid request type. Valid types: '
                . implode(', ', AttendanceEngine::VALID_TYPES)];
        }
        if (empty($employeeId)) {
            return ['success' => false, 'error' => 'Employee ID is required.'];
        }

        // Server controls date and time — never trust client input
        $date     = date('Y-m-d');
        $time     = date('H:i:s');
        $datetime = $date . ' ' . $time;

        $warnings = $this->validateRequestType($employeeId, $type, $date, $time);

        // Insert the request record (audit trail)
        $id = uuid_v4();
        Database::connection()->prepare(
            "INSERT INTO manual_attendance_requests
             (id, employee_id, request_type, request_date, requested_time,
              reason, reason_category, status, reviewed_at, admin_remarks)
             VALUES (?, ?, ?, ?, ?, ?, 'Other', 'Approved', NOW(),
                     'Auto-approved: attendance recorded immediately.')"
        )->execute([$id, $employeeId, $type, $date, $time, self::AUTO_REASON]);

        $this->audit->log('MANUAL_ATTENDANCE_REQUEST_SUBMITTED', 'manual_attendance', $id, null, [
            'employee_id' => $employeeId,
            'type'        => $type,
            'date'        => $date,
            'time'        => $time,
        ]);

        // Write the raw attendance tap and rebuild the summary via AttendanceEngine
        $this->engine->recordTap(
            $employeeId,
            $date,
            $datetime,
            $type,
            'manual',
            current_user()['id'] ?? null
        );

        $this->audit->log('MANUAL_ATTENDANCE_AUTO_APPROVED', 'manual_attendance', $id, null, [
            'employee_id' => $employeeId,
            'type'        => $type,
            'date'        => $date,
            'time'        => $time,
        ]);

        $typeLabel = AttendanceEngine::LABELS[$type] ?? $type;
        $empName   = $this->getEmployeeName($employeeId);

        $this->notify->notifyRoles(
            ['administrator', 'hr'],
            'Manual Attendance Recorded',
            "{$empName} recorded a manual {$typeLabel} for {$date}.",
            'info'
        );

        return ['success' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * List manual attendance requests (paginated).
     * Admin/HR see all; employees see only their own.
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

        return [
            'total'    => $total,
            'rows'     => $stmt->fetchAll(),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Approve a pending manual attendance request.
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

        // Write the approved tap through the engine
        $type     = $this->engine->canonicalType($req['request_type']);
        $datetime = $req['request_date'] . ' ' . $req['requested_time'];

        $this->engine->recordTap(
            $req['employee_id'],
            $req['request_date'],
            $datetime,
            $type,
            'manual',
            $reviewerUserId
        );

        $this->audit->log('MANUAL_ATTENDANCE_APPROVED', 'manual_attendance', $requestId, $req, ['remarks' => $remarks]);

        // Notify the employee
        $userStmt = Database::connection()->prepare("SELECT id FROM users WHERE employee_id = ?");
        $userStmt->execute([$req['employee_id']]);
        $userId = $userStmt->fetchColumn();
        if ($userId) {
            $typeLabel = AttendanceEngine::LABELS[$type] ?? $type;
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
     * Reject a pending manual attendance request.
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

        $userStmt = Database::connection()->prepare("SELECT id FROM users WHERE employee_id = ?");
        $userStmt->execute([$req['employee_id']]);
        $userId = $userStmt->fetchColumn();
        if ($userId) {
            $type      = $this->engine->canonicalType($req['request_type']);
            $typeLabel = AttendanceEngine::LABELS[$type] ?? $type;
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
     * Bulk approve or reject multiple requests.
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
     * Administrator creates a manual attendance entry directly.
     *
     * Accepts all 6 attendance types in separate time fields:
     *   time_in, break_out, break_in, time_out, overtime_in, overtime_out
     *
     * Each non-empty time field becomes a raw attendance row; the engine
     * then recalculates the official summary from all rows for that date.
     */
    public function adminCreate(array $data, string $adminUserId): array
    {
        $employeeId = trim($data['employee_id']     ?? '');
        $date       = trim($data['attendance_date'] ?? '');
        $method     = $data['method']               ?? 'Manual Entry';
        $reason     = trim($data['reason']          ?? '');
        $remarks    = trim($data['admin_remarks']   ?? '');
        $status     = $data['attendance_status']    ?? 'present';

        if (!$employeeId || !$date || !$reason) {
            return ['success' => false, 'error' => 'Employee, date and reason are required.'];
        }

        // Collect every provided time field into typed taps
        $taps = $this->extractTaps($data, $date);

        // Basic time-order validation
        $orderError = $this->validateTimeOrder($taps, $date);
        if ($orderError) {
            return ['success' => false, 'error' => $orderError];
        }

        $warnings = $this->buildAdminWarnings($employeeId, $date, $taps);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $id = uuid_v4();

            // Store the admin_manual_attendance record (canonical columns)
            $db->prepare(
                "INSERT INTO admin_manual_attendance
                 (id, employee_id, attendance_date, time_in, time_out,
                  attendance_status, method, reason, admin_remarks, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $id, $employeeId, $date,
                $taps[AttendanceEngine::TYPE_TIME_IN]  ?? null,
                $taps[AttendanceEngine::TYPE_TIME_OUT] ?? null,
                $status, $method, $reason, $remarks, $adminUserId,
            ]);

            // Write one raw attendance row per provided type, then rebuild once
            foreach ($taps as $type => $datetime) {
                $db->prepare(
                    "INSERT INTO attendance
                     (id, employee_id, attendance_date, time_recorded, attendance_type,
                      method, recorded_by, created_at)
                     VALUES (?, ?, ?, ?, ?, 'manual', ?, NOW())"
                )->execute([uuid_v4(), $employeeId, $date, $datetime, $type, $adminUserId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // One rebuild call covers all newly inserted taps
        $this->engine->rebuildSummary($employeeId, $date);

        $this->audit->log('ADMIN_MANUAL_ATTENDANCE_CREATED', 'manual_attendance', $id, null, $data);

        return ['success' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * Administrator updates an existing manual attendance entry.
     */
    public function adminUpdate(string $id, array $data, string $adminUserId): array
    {
        $db   = Database::connection();
        $stmt = $db->prepare('SELECT * FROM admin_manual_attendance WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            return ['success' => false, 'error' => 'Record not found.'];
        }

        $date    = trim($data['attendance_date']  ?? $existing['attendance_date']);
        $method  = $data['method']                ?? $existing['method'];
        $reason  = trim($data['reason']           ?? $existing['reason']);
        $remarks = trim($data['admin_remarks']    ?? $existing['admin_remarks'] ?? '');
        $status  = $data['attendance_status']     ?? $existing['attendance_status'];

        $taps = $this->extractTaps($data, $date);

        $orderError = $this->validateTimeOrder($taps, $date);
        if ($orderError) {
            return ['success' => false, 'error' => $orderError];
        }

        $warnings = $this->buildAdminWarnings($existing['employee_id'], $date, $taps);

        $db->beginTransaction();
        try {
            // Update the admin record
            $db->prepare(
                'UPDATE admin_manual_attendance
                 SET attendance_date = ?, time_in = ?, time_out = ?,
                     attendance_status = ?, method = ?, reason = ?,
                     admin_remarks = ?, updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                $date,
                $taps[AttendanceEngine::TYPE_TIME_IN]  ?? null,
                $taps[AttendanceEngine::TYPE_TIME_OUT] ?? null,
                $status, $method, $reason, $remarks, $id,
            ]);

            // Remove old manual raw rows for this employee + date, then re-insert
            $db->prepare(
                "DELETE FROM attendance
                  WHERE employee_id = ? AND attendance_date = ? AND method = 'manual'"
            )->execute([$existing['employee_id'], $date]);

            foreach ($taps as $type => $datetime) {
                $db->prepare(
                    "INSERT INTO attendance
                     (id, employee_id, attendance_date, time_recorded, attendance_type,
                      method, recorded_by, created_at)
                     VALUES (?, ?, ?, ?, ?, 'manual', ?, NOW())"
                )->execute([uuid_v4(), $existing['employee_id'], $date, $datetime, $type, $adminUserId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->engine->rebuildSummary($existing['employee_id'], $date);

        $this->audit->log('ADMIN_MANUAL_ATTENDANCE_UPDATED', 'manual_attendance', $id, $existing, $data);

        return ['success' => true, 'warnings' => $warnings];
    }

    /**
     * Administrator deletes a manual attendance entry and reverts the summary.
     */
    public function adminDelete(string $id, string $adminUserId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM admin_manual_attendance WHERE id = ?'
        );
        $stmt->execute([$id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            return ['success' => false, 'error' => 'Record not found.'];
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM admin_manual_attendance WHERE id = ?')->execute([$id]);

            // Remove manual raw rows for this date; non-manual rows (QR, RFID, PIN) are preserved
            $db->prepare(
                "DELETE FROM attendance
                  WHERE employee_id = ? AND attendance_date = ? AND method = 'manual'"
            )->execute([$existing['employee_id'], $existing['attendance_date']]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Rebuild from whatever non-manual rows remain (or delete summary if none)
        $this->engine->rebuildSummary($existing['employee_id'], $existing['attendance_date']);

        $this->audit->log('ADMIN_MANUAL_ATTENDANCE_DELETED', 'manual_attendance', $id, $existing, null);

        return ['success' => true];
    }

    /**
     * Paginated list of admin-created direct entries.
     */
    /**
     * Paginated list of admin-created direct entries.
     *
     * break_out / break_in / overtime_in / overtime_out do NOT exist on
     * admin_manual_attendance — that table only stores time_in / time_out as
     * metadata.  The canonical break and OT values live in attendance_summary,
     * computed by AttendanceEngine.  We LEFT JOIN the summary so the API
     * response always includes those columns.
     *
     * The JOIN columns are selected conditionally so that this query is safe
     * even on a database that has not yet run the upgrade migration.
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

        // Detect which optional columns exist on attendance_summary
        $db = Database::connection();
        $hasBreakOut  = $this->summaryColExists($db, 'break_out');
        $hasBreakIn   = $this->summaryColExists($db, 'break_in');
        $hasOtIn      = $this->summaryColExists($db, 'overtime_in');
        $hasOtOut     = $this->summaryColExists($db, 'overtime_out');
        $hasBreakMins = $this->summaryColExists($db, 'break_minutes');
        $hasOtMins    = $this->summaryColExists($db, 'overtime_minutes');

        $breakOutSel  = $hasBreakOut  ? 's.break_out'                       : 'NULL AS break_out';
        $breakInSel   = $hasBreakIn   ? 's.break_in'                        : 'NULL AS break_in';
        $otInSel      = $hasOtIn      ? 's.overtime_in'                     : 'NULL AS overtime_in';
        $otOutSel     = $hasOtOut     ? 's.overtime_out'                    : 'NULL AS overtime_out';
        $breakMinsSel = $hasBreakMins ? 'COALESCE(s.break_minutes,   0) AS break_minutes'    : '0 AS break_minutes';
        $otMinsSel    = $hasOtMins    ? 'COALESCE(s.overtime_minutes, 0) AS overtime_minutes' : '0 AS overtime_minutes';

        $stmt = $db->prepare(
            "SELECT
                ama.id,
                ama.employee_id,
                ama.attendance_date,
                ama.time_in,
                ama.time_out,
                ama.attendance_status,
                ama.method,
                ama.reason,
                ama.admin_remarks,
                ama.created_by,
                ama.created_at,
                CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                e.employee_number,
                u.username AS created_by_name,
                {$breakOutSel},
                {$breakInSel},
                {$otInSel},
                {$otOutSel},
                {$breakMinsSel},
                {$otMinsSel},
                s.total_hours,
                s.late_minutes,
                s.undertime_minutes,
                s.day_status
             FROM admin_manual_attendance ama
             INNER JOIN employees e  ON e.id  = ama.employee_id
             LEFT  JOIN users u      ON u.id  = ama.created_by
             LEFT  JOIN attendance_summary s
                     ON s.employee_id    = ama.employee_id
                    AND s.attendance_date = ama.attendance_date
             {$whereClause}
             ORDER BY ama.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return ['total' => $total, 'rows' => $stmt->fetchAll()];
    }

    /**
     * Check whether a column exists on attendance_summary.
     * Cached per request so the INFORMATION_SCHEMA query runs at most once per column.
     */
    private function summaryColExists(\PDO $db, string $column): bool
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $cache[$column] = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'attendance_summary'
                    AND COLUMN_NAME  = '{$column}'"
            )->fetchColumn() > 0;
        }
        return $cache[$column];
    }

    /** Public wrapper used by the JSON API. */
    public function listAdminEntriesPaged(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->listAdminEntries($filters, $page, $perPage);
    }

    /* ════════════════════════════════════════════════════════
     * Stats (for index page cards)
     * ════════════════════════════════════════════════════════ */

    public function getStats(?string $employeeId = null): array
    {
        $db = Database::connection();

        if ($employeeId) {
            $stmtP = $db->prepare("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Pending'  AND employee_id = ?");
            $stmtP->execute([$employeeId]); $pending    = (int) $stmtP->fetchColumn();

            $stmtA = $db->prepare("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Approved' AND employee_id = ?");
            $stmtA->execute([$employeeId]); $approved   = (int) $stmtA->fetchColumn();

            $stmtR = $db->prepare("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Rejected' AND employee_id = ?");
            $stmtR->execute([$employeeId]); $rejected   = (int) $stmtR->fetchColumn();

            $stmtT = $db->prepare("SELECT COUNT(*) FROM admin_manual_attendance WHERE employee_id = ?");
            $stmtT->execute([$employeeId]); $adminTotal = (int) $stmtT->fetchColumn();
        } else {
            $pending    = (int) $db->query("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Pending'")->fetchColumn();
            $approved   = (int) $db->query("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Approved'")->fetchColumn();
            $rejected   = (int) $db->query("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Rejected'")->fetchColumn();
            $adminTotal = (int) $db->query("SELECT COUNT(*) FROM admin_manual_attendance")->fetchColumn();
        }

        return [
            'pending'     => $pending,
            'approved'    => $approved,
            'rejected'    => $rejected,
            'admin_total' => $adminTotal,
        ];
    }

    /** Pending-request count for the dashboard badge. */
    public function pendingCount(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM manual_attendance_requests WHERE status = 'Pending'")
            ->fetchColumn();
    }

    /* ════════════════════════════════════════════════════════
     * Private helpers
     * ════════════════════════════════════════════════════════ */

    /**
     * Extract typed taps from admin form data.
     * Each field name matches an attendance type; value is a HH:MM time string.
     * Returns array<canonical_type, "Y-m-d H:i:s"> for non-empty fields only.
     */
    private function extractTaps(array $data, string $date): array
    {
        $taps = [];
        foreach (AttendanceEngine::VALID_TYPES as $type) {
            $val = trim($data[$type] ?? '');
            if ($val !== '') {
                $taps[$type] = $date . ' ' . $val . (strlen($val) === 5 ? ':00' : '');
            }
        }
        return $taps;
    }

    /**
     * Validate that the provided times follow the expected sequence:
     * time_in ≤ break_out ≤ break_in ≤ time_out ≤ overtime_in ≤ overtime_out
     */
    private function validateTimeOrder(array $taps, string $date): ?string
    {
        $seq = [
            AttendanceEngine::TYPE_TIME_IN,
            AttendanceEngine::TYPE_BREAK_OUT,
            AttendanceEngine::TYPE_BREAK_IN,
            AttendanceEngine::TYPE_TIME_OUT,
            AttendanceEngine::TYPE_OT_IN,
            AttendanceEngine::TYPE_OT_OUT,
        ];

        $labels = AttendanceEngine::LABELS;
        $prev   = null;
        $prevLbl = '';

        foreach ($seq as $type) {
            if (!isset($taps[$type])) {
                continue;
            }
            if ($prev !== null && strtotime($taps[$type]) < strtotime($prev)) {
                return "{$labels[$type]} must be after {$prevLbl}.";
            }
            $prev    = $taps[$type];
            $prevLbl = $labels[$type];
        }

        return null;
    }

    /**
     * Build non-blocking warnings for admin entries.
     */
    private function buildAdminWarnings(string $employeeId, string $date, array $taps): array
    {
        $warnings = [];

        $stmt = Database::connection()->prepare(
            "SELECT id FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        if ($stmt->fetch()) {
            $warnings[] = 'An attendance record already exists for this employee on this date. It will be merged.';
        }

        $allowedFrom = $this->cfg->get('allowed_time_in_from', '06:00');
        $allowedTo   = $this->cfg->get('allowed_time_in_to',   '22:00');

        if (isset($taps[AttendanceEngine::TYPE_TIME_IN])) {
            $t = substr($taps[AttendanceEngine::TYPE_TIME_IN], 11, 5);
            if ($t < $allowedFrom || $t > $allowedTo) {
                $warnings[] = "Time In ({$t}) is outside the configurable allowed window ({$allowedFrom}–{$allowedTo}).";
            }
        }

        return $warnings;
    }

    /**
     * Validate an employee self-service request type against existing summary.
     */
    private function validateRequestType(string $employeeId, string $type, string $date, string $time): array
    {
        $warnings = [];
        $summary  = $this->engine->getOfficialSummary($employeeId, $date);

        // Warn if the official slot is already filled (multiple taps are allowed;
        // the engine will pick the correct official value)
        $filledMap = [
            AttendanceEngine::TYPE_TIME_IN   => 'time_in',
            AttendanceEngine::TYPE_BREAK_OUT => 'break_out',
            AttendanceEngine::TYPE_BREAK_IN  => 'break_in',
            AttendanceEngine::TYPE_TIME_OUT  => 'time_out',
            AttendanceEngine::TYPE_OT_IN     => 'overtime_in',
            AttendanceEngine::TYPE_OT_OUT    => 'overtime_out',
        ];

        if ($summary && isset($filledMap[$type]) && !empty($summary[$filledMap[$type]])) {
            $label = AttendanceEngine::LABELS[$type] ?? $type;
            $warnings[] = "A {$label} record already exists for today. This entry will be processed — the earlier/later value will be used as the official record.";
        }

        // Time-window warning for time_in only
        if ($type === AttendanceEngine::TYPE_TIME_IN) {
            $allowedFrom = $this->cfg->get('allowed_time_in_from', '06:00');
            $allowedTo   = $this->cfg->get('allowed_time_in_to',   '10:00');
            if ($time < $allowedFrom || $time > $allowedTo) {
                $warnings[] = "Requested time ({$time}) is outside the normal allowed window ({$allowedFrom}–{$allowedTo}).";
            }
        }

        return $warnings;
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
}
