<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

final class LeaveService
{
    public const TYPES = [
        'Vacation Leave',
        'Sick Leave',
        'Emergency Leave',
        'Maternity Leave',
        'Paternity Leave',
        'Bereavement Leave',
        'Unpaid Leave',
    ];

    public function list(array $filters, bool $ownOnly = false, ?string $employeeId = null): array
    {
        $where = [];
        $params = [];
        if ($ownOnly && $employeeId) {
            $where[] = 'lr.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        if (!empty($filters['status'])) {
            $where[] = 'lr.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['leave_type'])) {
            $where[] = 'lr.leave_type = :leave_type';
            $params['leave_type'] = $filters['leave_type'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(e.employee_number LIKE :q OR e.first_name LIKE :q OR e.last_name LIKE :q OR lr.reason LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        $sql = 'SELECT lr.*, e.employee_number, CONCAT(e.first_name, " ", e.last_name) AS employee_name
                FROM leave_requests lr INNER JOIN employees e ON e.id = lr.employee_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY lr.created_at DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): string
    {
        $this->validate($data);
        if ($this->hasOverlap($data['employee_id'], $data['start_date'], $data['end_date'])) {
            throw new InvalidArgumentException('Leave request overlaps an existing active leave request.');
        }
        $days = $this->workingDays($data['start_date'], $data['end_date'], $data['employee_id']);
        $id = uuid_v4();
        $stmt = Database::connection()->prepare(
            'INSERT INTO leave_requests (id, employee_id, leave_type, start_date, end_date, number_of_days, reason, attachment)
             VALUES (:id, :employee_id, :leave_type, :start_date, :end_date, :number_of_days, :reason, :attachment)'
        );
        $stmt->execute([
            'id' => $id,
            'employee_id' => $data['employee_id'],
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'number_of_days' => $days,
            'reason' => trim((string) $data['reason']),
            'attachment' => $data['attachment'] ?? null,
        ]);

        (new AuditService())->log('LEAVE_SUBMITTED', 'leaves', $id, null, $data);
        (new NotificationService())->notifyRoles(['administrator', 'hr'], 'Leave Request Submitted', 'A new leave request is pending review.', 'warning');
        return $id;
    }

    public function transition(string $id, string $status, string $remarks): void
    {
        $db = Database::connection();
        $before = $this->find($id);
        if (!$before) {
            throw new InvalidArgumentException('Leave request not found.');
        }
        $stmt = $db->prepare(
            'UPDATE leave_requests SET status = :status, approved_by = :approved_by, approval_date = NOW(), admin_remarks = :remarks WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'approved_by' => current_user()['id'] ?? null,
            'remarks' => $remarks,
            'id' => $id,
        ]);
        $after = $this->find($id);
        (new AuditService())->log('LEAVE_' . strtoupper($status), 'leaves', $id, $before, $after);
        if (!empty($after['user_id'])) {
            (new NotificationService())->notify($after['user_id'], 'Leave ' . $status, 'Your leave request has been ' . strtolower($status) . '.', $status === 'Approved' ? 'success' : 'danger');
        }
    }

    public function cancel(string $id): void
    {
        $before = $this->find($id);
        $stmt = Database::connection()->prepare("UPDATE leave_requests SET status = 'Cancelled' WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        (new AuditService())->log('LEAVE_CANCELLED', 'leaves', $id, $before, $this->find($id));
    }

    public function find(string $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT lr.*, u.id AS user_id FROM leave_requests lr
             LEFT JOIN users u ON u.employee_id = lr.employee_id WHERE lr.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function validate(array $data): void
    {
        if (empty($data['employee_id']) || empty($data['start_date']) || empty($data['end_date']) || empty($data['reason'])) {
            throw new InvalidArgumentException('Employee, dates and reason are required.');
        }
        if (!in_array($data['leave_type'] ?? '', self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid leave type.');
        }
        if ($data['end_date'] < $data['start_date']) {
            throw new InvalidArgumentException('End date cannot be earlier than start date.');
        }
    }

    private function hasOverlap(string $employeeId, string $start, string $end): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM leave_requests
             WHERE employee_id = ? AND status IN ('Pending','Approved') AND start_date <= ? AND end_date >= ?"
        );
        $stmt->execute([$employeeId, $end, $start]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function workingDays(string $start, string $end, string $employeeId): float
    {
        $startDate = new DateTimeImmutable($start);
        $endDate = (new DateTimeImmutable($end))->modify('+1 day');
        $days = 0;
        $holidays = $this->holidayMap($start, $end, $employeeId);

        foreach (new DatePeriod($startDate, new DateInterval('P1D'), $endDate) as $day) {
            $date = $day->format('Y-m-d');
            $isWeekend = in_array($day->format('N'), ['6', '7'], true);
            if (config('exclude_weekends_from_leave', true) && $isWeekend) {
                continue;
            }
            if (isset($holidays[$date])) {
                continue;
            }
            $days++;
        }
        return (float) $days;
    }

    private function holidayMap(string $start, string $end, string $employeeId): array
    {
        $stmt = Database::connection()->prepare('SELECT branch_id FROM employees WHERE id = ?');
        $stmt->execute([$employeeId]);
        $branchId = $stmt->fetchColumn();
        $stmt = Database::connection()->prepare(
            "SELECT holiday_date FROM holidays
             WHERE status = 'active' AND holiday_date BETWEEN :start AND :end AND (branch_id IS NULL OR branch_id = :branch_id)"
        );
        $stmt->execute(['start' => $start, 'end' => $end, 'branch_id' => $branchId]);
        return array_fill_keys($stmt->fetchAll(\PDO::FETCH_COLUMN), true);
    }
}
