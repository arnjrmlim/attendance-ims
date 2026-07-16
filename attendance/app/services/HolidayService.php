<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

final class HolidayService
{
    public function list(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = 'h.name LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['type'])) {
            $where[] = 'h.type = :type';
            $params['type'] = $filters['type'];
        }
        $sql = 'SELECT h.*, b.name AS branch_name FROM holidays h LEFT JOIN branches b ON b.id = h.branch_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY h.holiday_date DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function store(array $data): void
    {
        $this->validate($data);
        if ($this->duplicate($data['holiday_date'], $data['branch_id'] ?: null, $data['id'] ?? null)) {
            throw new InvalidArgumentException('Holiday date already exists for this branch scope.');
        }
        $id = $data['id'] ?? uuid_v4();
        $stmt = Database::connection()->prepare(
            'INSERT INTO holidays (id, name, holiday_date, branch_id, type, description, is_recurring, status)
             VALUES (:id, :name, :holiday_date, :branch_id, :type, :description, :is_recurring, :status)
             ON DUPLICATE KEY UPDATE name = VALUES(name), holiday_date = VALUES(holiday_date), branch_id = VALUES(branch_id),
             type = VALUES(type), description = VALUES(description), is_recurring = VALUES(is_recurring), status = VALUES(status)'
        );
        $stmt->execute([
            'id' => $id,
            'name' => trim((string) $data['name']),
            'holiday_date' => $data['holiday_date'],
            'branch_id' => $data['branch_id'] ?: null,
            'type' => $data['type'],
            'description' => trim((string) ($data['description'] ?? '')),
            'is_recurring' => !empty($data['is_recurring']) ? 1 : 0,
            'status' => $data['status'] ?? 'active',
        ]);
        (new AuditService())->log('HOLIDAY_SAVED', 'holidays', $id, null, $data);
    }

    public function deactivate(string $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE holidays SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        (new AuditService())->log('HOLIDAY_DEACTIVATED', 'holidays', $id);
    }

    private function validate(array $data): void
    {
        if (empty($data['name']) || empty($data['holiday_date']) || !in_array($data['type'] ?? '', ['regular', 'special', 'company', 'branch'], true)) {
            throw new InvalidArgumentException('Holiday name, date and valid type are required.');
        }
    }

    private function duplicate(string $date, ?string $branchId, ?string $ignoreId): bool
    {
        $sql = 'SELECT COUNT(*) FROM holidays WHERE holiday_date = :date AND ';
        $sql .= $branchId ? 'branch_id = :branch_id' : 'branch_id IS NULL';
        $params = ['date' => $date];
        if ($branchId) {
            $params['branch_id'] = $branchId;
        }
        if ($ignoreId) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }
}
