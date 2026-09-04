<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

final class CorrectionService
{
    public const TYPES = ['Forgot Time In', 'Forgot Time Out', 'Incorrect Attendance', 'Wrong Attendance Method', 'Official Business'];

    public function list(array $filters, bool $ownOnly = false, ?string $employeeId = null): array
    {
        $where = [];
        $params = [];
        if ($ownOnly && $employeeId) {
            $where[] = 'ac.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        if (!empty($filters['status'])) {
            $where[] = 'ac.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['correction_type'])) {
            $where[] = 'ac.correction_type = :correction_type';
            $params['correction_type'] = $filters['correction_type'];
        }
        if (!empty($filters['q'])) {
            $searchValue = '%' . $filters['q'] . '%';
            if ($ownOnly) {
                $where[] = 'ac.reason LIKE :q';
                $params['q'] = $searchValue;
            } else {
                $where[] = "(e.employee_number LIKE :q1 OR e.first_name LIKE :q2 OR e.last_name LIKE :q3 OR CONCAT_WS(' ', e.first_name, NULLIF(e.middle_name, ''), e.last_name) LIKE :q4 OR ac.reason LIKE :q5)";
                $params['q1'] = $searchValue;
                $params['q2'] = $searchValue;
                $params['q3'] = $searchValue;
                $params['q4'] = $searchValue;
                $params['q5'] = $searchValue;
            }
        }
        if (!empty($filters['start_date'])) {
            $where[] = 'ac.attendance_date >= :start_date';
            $params['start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[] = 'ac.attendance_date <= :end_date';
            $params['end_date'] = $filters['end_date'];
        }
        $sql = 'SELECT ac.*, e.employee_number, CONCAT(e.first_name, " ", e.last_name) AS employee_name
                FROM attendance_corrections ac INNER JOIN employees e ON e.id = ac.employee_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ac.created_at DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): string
    {
        if (empty($data['employee_id']) || empty($data['attendance_date']) || empty($data['reason'])) {
            throw new InvalidArgumentException('Employee, attendance date and reason are required.');
        }
        if (!in_array($data['correction_type'] ?? '', self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid correction type.');
        }
        if ($data['correction_type'] === 'Forgot Time In' && empty($data['requested_time_in'])) {
            throw new InvalidArgumentException('Requested time in is required for a Forgot Time In correction.');
        }
        if ($data['correction_type'] === 'Forgot Time Out' && empty($data['requested_time_out'])) {
            throw new InvalidArgumentException('Requested time out is required for a Forgot Time Out correction.');
        }
        if ($this->hasPendingSameType($data['employee_id'], $data['attendance_date'], $data['correction_type'], $data['attendance_id'] ?? null, $data['requested_time_in'] ?? null, $data['requested_time_out'] ?? null)) {
            throw new InvalidArgumentException('A pending correction of the same type with the same requested times already exists.');
        }
        $id = uuid_v4();
        $stmt = Database::connection()->prepare(
            'INSERT INTO attendance_corrections
            (id, employee_id, attendance_id, attendance_date, correction_type, original_time_in, original_time_out, requested_time_in, requested_time_out, reason, attachment)
            VALUES (:id, :employee_id, :attendance_id, :attendance_date, :correction_type, :original_time_in, :original_time_out, :requested_time_in, :requested_time_out, :reason, :attachment)'
        );
        $stmt->execute([
            'id' => $id,
            'employee_id' => $data['employee_id'],
            'attendance_id' => $data['attendance_id'] ?: null,
            'attendance_date' => $data['attendance_date'],
            'correction_type' => $data['correction_type'],
            'original_time_in' => $data['original_time_in'] ?: null,
            'original_time_out' => $data['original_time_out'] ?: null,
            'requested_time_in' => $data['requested_time_in'] ?: null,
            'requested_time_out' => $data['requested_time_out'] ?: null,
            'reason' => trim((string) $data['reason']),
            'attachment' => $data['attachment'] ?? null,
        ]);
        (new AuditService())->log('CORRECTION_SUBMITTED', 'corrections', $id, null, $data);
        (new NotificationService())->notifyRoles(['administrator', 'hr'], 'Attendance Correction Submitted', 'A correction request is pending review.', 'warning');
        
        // Send email notification after successful save
        (new EmailService())->sendCorrectionRequestNotification($data, $id);
        
        return $id;
    }

    public function transition(string $id, string $status, string $remarks): void
    {
        $before = $this->find($id);
        if (!$before) {
            throw new InvalidArgumentException('Correction request not found.');
        }
        $stmt = Database::connection()->prepare(
            'UPDATE attendance_corrections SET status = :status, approved_by = :approved_by, approval_date = NOW(), admin_remarks = :remarks WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'approved_by' => current_user()['id'] ?? null,
            'remarks' => $remarks,
            'id' => $id,
        ]);
        $after = $this->find($id);
        (new AuditService())->log('CORRECTION_' . strtoupper($status), 'corrections', $id, $before, $after);
        if (!empty($after['user_id'])) {
            (new NotificationService())->notify($after['user_id'], 'Correction ' . $status, 'Your correction request has been ' . strtolower($status) . '.', $status === 'Approved' ? 'success' : 'danger');
        }
    }

    public function cancel(string $id): void
    {
        $record = $this->find($id);
        if (!$record) {
            throw new InvalidArgumentException('Correction request not found.');
        }

        // Non-admin/hr users can only cancel their own requests.
        $user = current_user();
        if (!in_array($user['role_slug'] ?? '', ['administrator', 'hr'], true)
            && ($user['employee_id'] ?? null) !== $record['employee_id']) {
            throw new InvalidArgumentException('You are not authorised to cancel this correction request.');
        }

        $before = $record;
        $stmt = Database::connection()->prepare(
            "UPDATE attendance_corrections SET status = 'Cancelled', admin_remarks = '' WHERE id = ? AND status = 'Pending'"
        );
        $stmt->execute([$id]);
        (new AuditService())->log('CORRECTION_CANCELLED', 'corrections', $id, $before, $this->find($id));
    }

    public function find(string $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ac.*, e.employee_number, CONCAT(e.first_name, " ", e.last_name) AS employee_name, u.id AS user_id 
             FROM attendance_corrections ac
             INNER JOIN employees e ON e.id = ac.employee_id
             LEFT JOIN users u ON u.employee_id = ac.employee_id 
             WHERE ac.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function hasPending(string $employeeId, string $date, ?string $attendanceId): bool
    {
        $sql = "SELECT COUNT(*) FROM attendance_corrections WHERE status = 'Pending' AND employee_id = :employee_id AND attendance_date = :date";
        $params = ['employee_id' => $employeeId, 'date' => $date];
        if ($attendanceId) {
            $sql .= ' AND (attendance_id = :attendance_id OR attendance_id IS NULL)';
            $params['attendance_id'] = $attendanceId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Check if there's an exact duplicate pending correction.
     * Only blocks if correction type AND all requested times match exactly.
     * Different correction types are always allowed, even with same times.
     */
    private function hasPendingSameType(string $employeeId, string $date, string $correctionType, ?string $attendanceId, ?string $requestedTimeIn, ?string $requestedTimeOut): bool
    {
        $sql = "SELECT COUNT(*) FROM attendance_corrections 
                WHERE status = 'Pending' 
                AND employee_id = :employee_id 
                AND correction_type = :correction_type";
        $params = ['employee_id' => $employeeId, 'correction_type' => $correctionType];
        
        // Only check requested times if they are provided (not null or empty)
        // This allows different correction types to have overlapping times
        if (!empty($requestedTimeIn)) {
            $sql .= ' AND requested_time_in = :requested_time_in';
            $params['requested_time_in'] = $requestedTimeIn;
        }
        if (!empty($requestedTimeOut)) {
            $sql .= ' AND requested_time_out = :requested_time_out';
            $params['requested_time_out'] = $requestedTimeOut;
        }
        
        if ($attendanceId) {
            $sql .= ' AND (attendance_id = :attendance_id OR attendance_id IS NULL)';
            $params['attendance_id'] = $attendanceId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }
}
