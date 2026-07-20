<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;
use PDO;

/**
 * ShiftService
 *
 * Manages work-shift definitions: CRUD, default-shift logic, employee
 * assignment, and schema self-healing for the two columns (description,
 * is_default) that were added after the initial migration.
 *
 * Design goals:
 *  - One shift can be flagged is_default = 1; all others are 0.
 *  - New employees not given an explicit shift_id receive the default shift.
 *  - Deleting a shift is blocked unless no employees are assigned, or the
 *    caller supplies a replacement_shift_id to reassign them first.
 *  - Overnight shifts (time_out < time_in) are supported via the overnight flag.
 *  - All mutations run inside transactions; AuditService is called when available.
 */
final class ShiftService
{
    private ?AuditService $audit = null;

    public function __construct()
    {
        $this->ensureSchema();

        if (class_exists('App\Services\AuditService')) {
            $this->audit = new AuditService();
        }
    }

    // ----------------------------------------------------------------
    // Schema self-heal
    // ----------------------------------------------------------------

    /**
     * Ensure the two extra columns exist on the shifts table.
     * Safe to call on every instantiation — runs at most once per column
     * per database (INFORMATION_SCHEMA check is cheap).
     */
    private function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $db = Database::connection();

        $needed = [
            'description' => "ALTER TABLE `shifts`
                              ADD COLUMN `description` TEXT DEFAULT NULL
                              AFTER `name`",
            'is_default'  => "ALTER TABLE `shifts`
                              ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0
                              AFTER `status`",
        ];

        foreach ($needed as $col => $ddl) {
            $exists = (int) $db->query(
                "SELECT COUNT(*)
                   FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'shifts'
                    AND COLUMN_NAME  = '{$col}'"
            )->fetchColumn();

            if ($exists === 0) {
                $db->exec($ddl);
            }
        }

        // Ensure the Default Office Hours shift exists if the table is empty
        $this->seedDefaultShift($db);
    }

    /**
     * Create the "Regular Office Hours" default shift if no shifts exist.
     */
    private function seedDefaultShift(PDO $db): void
    {
        $count = (int) $db->query("SELECT COUNT(*) FROM shifts")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $id = uuid_v4();
        $db->prepare(
            "INSERT INTO shifts
               (id, name, description, type, time_in, time_out,
                lunch_break_start, lunch_break_end, lunch_break_minutes,
                grace_period_minutes, required_hours, overnight, status, is_default)
             VALUES
               (:id, :name, :description, 'regular', '08:00:00', '17:00:00',
                '12:00:00', '13:00:00', 60,
                15, 8.00, 0, 'active', 1)"
        )->execute([
            'id'          => $id,
            'name'        => 'Regular Office Hours',
            'description' => 'Standard 8 AM – 5 PM office shift with a 1-hour lunch break.',
        ]);
    }

    // ----------------------------------------------------------------
    // Queries
    // ----------------------------------------------------------------

    /**
     * Paginated list with optional search / status filter.
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[]    = "(s.name LIKE :q OR s.description LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[]         = 's.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $where[]       = 's.type = :type';
            $params['type'] = $filters['type'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $db = Database::connection();

        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM shifts s $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                s.*,
                (SELECT COUNT(*)
                   FROM employees e
                  WHERE e.shift_id = s.id
                    AND e.status   = 'active') AS employee_count
            FROM shifts s
            $whereClause
            ORDER BY s.is_default DESC, s.name ASC
            LIMIT :offset, :limit
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Find a single shift by ID.
     */
    public function find(string $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.*,
                    (SELECT COUNT(*)
                       FROM employees e
                      WHERE e.shift_id = s.id
                        AND e.status   = 'active') AS employee_count
               FROM shifts s
              WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Return the current default shift (is_default = 1), or null.
     */
    public function findDefault(): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM shifts WHERE is_default = 1 AND status = 'active' LIMIT 1"
        );
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * All active shifts for dropdowns (includes is_default for UI hints).
     */
    public function getActiveShifts(): array
    {
        return Database::connection()
            ->query(
                "SELECT id, name, type, time_in, time_out,
                        lunch_break_start, lunch_break_end,
                        grace_period_minutes, required_hours,
                        overnight, is_default
                   FROM shifts
                  WHERE status = 'active'
                  ORDER BY is_default DESC, name ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Employees assigned to a shift (paginated).
     */
    public function getAssignedEmployees(string $shiftId, int $page = 1, int $perPage = 20): array
    {
        $db     = Database::connection();
        $offset = ($page - 1) * $perPage;

        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM employees WHERE shift_id = ? AND status = 'active'"
        );
        $countStmt->execute([$shiftId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT e.id, e.employee_number,
                    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                    e.position, e.employment_status,
                    d.name AS department_name,
                    b.name AS branch_name
               FROM employees e
               LEFT JOIN departments d ON d.id = e.department_id
               LEFT JOIN branches    b ON b.id = e.branch_id
              WHERE e.shift_id = ?
                AND e.status   = 'active'
              ORDER BY e.last_name, e.first_name
              LIMIT ?, ?"
        );
        $stmt->bindValue(1, $shiftId);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // ----------------------------------------------------------------
    // Mutations
    // ----------------------------------------------------------------

    /**
     * Create a new shift.
     *
     * @throws InvalidArgumentException on validation failure
     */
    public function create(array $data): string
    {
        $this->validate($data);
        $this->checkDuplicateName($data['name']);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $id         = uuid_v4();
            $isDefault  = !empty($data['is_default']) ? 1 : 0;
            $overnight  = $this->detectOvernight($data);

            if ($isDefault) {
                // Clear existing default before setting the new one
                $db->exec("UPDATE shifts SET is_default = 0");
            }

            $db->prepare(
                "INSERT INTO shifts
                   (id, name, description, type,
                    time_in, time_out,
                    lunch_break_start, lunch_break_end, lunch_break_minutes,
                    grace_period_minutes, required_hours,
                    overnight, status, is_default)
                 VALUES
                   (:id, :name, :description, :type,
                    :time_in, :time_out,
                    :lunch_break_start, :lunch_break_end, :lunch_break_minutes,
                    :grace_period_minutes, :required_hours,
                    :overnight, :status, :is_default)"
            )->execute([
                'id'                  => $id,
                'name'                => trim($data['name']),
                'description'         => trim($data['description'] ?? ''),
                'type'                => $data['type'] ?? 'regular',
                'time_in'             => $data['time_in'],
                'time_out'            => $data['time_out'],
                'lunch_break_start'   => $data['lunch_break_start']   ?: null,
                'lunch_break_end'     => $data['lunch_break_end']     ?: null,
                'lunch_break_minutes' => (int) ($data['lunch_break_minutes'] ?? 60),
                'grace_period_minutes'=> (int) ($data['grace_period_minutes'] ?? 15),
                'required_hours'      => (float) ($data['required_hours'] ?? 8.00),
                'overnight'           => $overnight,
                'status'              => $data['status'] ?? 'active',
                'is_default'          => $isDefault,
            ]);

            $this->audit?->log('SHIFT_CREATED', 'shifts', $id, null, $data);
            $db->commit();

            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to create shift: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing shift.
     *
     * @throws InvalidArgumentException if shift not found or validation fails
     */
    public function update(string $id, array $data): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Shift not found.');
        }

        $this->validate($data);
        $this->checkDuplicateName($data['name'], $id);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $isDefault = !empty($data['is_default']) ? 1 : 0;
            $overnight = $this->detectOvernight($data);

            if ($isDefault) {
                // Remove default flag from all other shifts
                $db->prepare("UPDATE shifts SET is_default = 0 WHERE id != ?")->execute([$id]);
            }

            $db->prepare(
                "UPDATE shifts SET
                    name                  = :name,
                    description           = :description,
                    type                  = :type,
                    time_in               = :time_in,
                    time_out              = :time_out,
                    lunch_break_start     = :lunch_break_start,
                    lunch_break_end       = :lunch_break_end,
                    lunch_break_minutes   = :lunch_break_minutes,
                    grace_period_minutes  = :grace_period_minutes,
                    required_hours        = :required_hours,
                    overnight             = :overnight,
                    status                = :status,
                    is_default            = :is_default
                 WHERE id = :id"
            )->execute([
                'id'                  => $id,
                'name'                => trim($data['name']),
                'description'         => trim($data['description'] ?? ''),
                'type'                => $data['type'] ?? $existing['type'],
                'time_in'             => $data['time_in'],
                'time_out'            => $data['time_out'],
                'lunch_break_start'   => $data['lunch_break_start']   ?: null,
                'lunch_break_end'     => $data['lunch_break_end']     ?: null,
                'lunch_break_minutes' => (int) ($data['lunch_break_minutes'] ?? 60),
                'grace_period_minutes'=> (int) ($data['grace_period_minutes'] ?? 15),
                'required_hours'      => (float) ($data['required_hours'] ?? 8.00),
                'overnight'           => $overnight,
                'status'              => $data['status'] ?? $existing['status'],
                'is_default'          => $isDefault,
            ]);

            $this->audit?->log('SHIFT_UPDATED', 'shifts', $id, $existing, $data);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to update shift: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a shift.
     *
     * Rules:
     *  - Cannot delete if employees are assigned, unless a replacement_shift_id is provided.
     *  - Cannot delete the only active shift in the system.
     *
     * @throws InvalidArgumentException on guard failures
     */
    public function delete(string $id, ?string $replacementShiftId = null): void
    {
        $shift = $this->find($id);
        if (!$shift) {
            throw new InvalidArgumentException('Shift not found.');
        }

        $db           = Database::connection();
        $employeeCount = (int) $shift['employee_count'];

        if ($employeeCount > 0) {
            if (!$replacementShiftId) {
                throw new InvalidArgumentException(
                    "This shift has {$employeeCount} employee(s) assigned. "
                    . "Please select a replacement shift before deleting."
                );
            }

            $replacement = $this->find($replacementShiftId);
            if (!$replacement) {
                throw new InvalidArgumentException('Replacement shift not found.');
            }
            if ($replacementShiftId === $id) {
                throw new InvalidArgumentException('Replacement shift must differ from the shift being deleted.');
            }
        }

        // Ensure at least one active shift remains after deletion
        $activeCount = (int) $db->query(
            "SELECT COUNT(*) FROM shifts WHERE status = 'active'"
        )->fetchColumn();

        if ($activeCount <= 1 && $shift['status'] === 'active') {
            throw new InvalidArgumentException(
                'Cannot delete the only active shift. Create another shift first.'
            );
        }

        $db->beginTransaction();
        try {
            // Reassign employees if needed
            if ($employeeCount > 0 && $replacementShiftId) {
                $db->prepare(
                    "UPDATE employees SET shift_id = ? WHERE shift_id = ?"
                )->execute([$replacementShiftId, $id]);
            }

            // If this was the default, promote the replacement (or any active shift)
            if ((int) $shift['is_default'] === 1) {
                $fallbackId = $replacementShiftId;
                if (!$fallbackId) {
                    $row = $db->query(
                        "SELECT id FROM shifts WHERE status = 'active' AND id != '$id' LIMIT 1"
                    )->fetch();
                    $fallbackId = $row['id'] ?? null;
                }
                if ($fallbackId) {
                    $db->prepare(
                        "UPDATE shifts SET is_default = 1 WHERE id = ?"
                    )->execute([$fallbackId]);
                }
            }

            $db->prepare("DELETE FROM shifts WHERE id = ?")->execute([$id]);

            $this->audit?->log('SHIFT_DELETED', 'shifts', $id, $shift, []);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to delete shift: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Activate or deactivate a shift.
     */
    public function setStatus(string $id, string $status): void
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Invalid status.');
        }

        $shift = $this->find($id);
        if (!$shift) {
            throw new InvalidArgumentException('Shift not found.');
        }

        // Prevent deactivating if employees are still assigned
        if ($status === 'inactive' && (int) $shift['employee_count'] > 0) {
            throw new InvalidArgumentException(
                'Cannot deactivate a shift that has employees assigned. Reassign employees first.'
            );
        }

        Database::connection()
            ->prepare("UPDATE shifts SET status = ? WHERE id = ?")
            ->execute([$status, $id]);

        $this->audit?->log('SHIFT_STATUS_CHANGED', 'shifts', $id, $shift, ['status' => $status]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Auto-detect whether the shift spans midnight.
     * If time_out <= time_in the shift is treated as overnight.
     */
    private function detectOvernight(array $data): int
    {
        // Allow explicit override
        if (isset($data['overnight'])) {
            return (int) $data['overnight'];
        }

        $in  = strtotime('1970-01-01 ' . ($data['time_in']  ?? '00:00'));
        $out = strtotime('1970-01-01 ' . ($data['time_out'] ?? '00:00'));

        return ($out !== false && $in !== false && $out <= $in) ? 1 : 0;
    }

    /**
     * Validate required fields and time logic.
     *
     * @throws InvalidArgumentException
     */
    private function validate(array $data): void
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Shift name is required.');
        }
        if (strlen(trim($data['name'])) > 80) {
            throw new InvalidArgumentException('Shift name must not exceed 80 characters.');
        }
        if (empty($data['time_in'])) {
            throw new InvalidArgumentException('Time In is required.');
        }
        if (empty($data['time_out'])) {
            throw new InvalidArgumentException('Time Out is required.');
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['time_in'])) {
            throw new InvalidArgumentException('Invalid Time In format (expected HH:MM).');
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['time_out'])) {
            throw new InvalidArgumentException('Invalid Time Out format (expected HH:MM).');
        }

        $in  = strtotime('1970-01-01 ' . $data['time_in']);
        $out = strtotime('1970-01-01 ' . $data['time_out']);

        // For non-overnight shifts time_in must be before time_out
        $isOvernightExplicit = isset($data['overnight']) && (int) $data['overnight'] === 1;
        if (!$isOvernightExplicit && $out !== false && $in !== false && $out <= $in) {
            // Auto-flag as overnight rather than rejecting — the detectOvernight() call handles this
        }

        if (isset($data['grace_period_minutes']) && (int) $data['grace_period_minutes'] < 0) {
            throw new InvalidArgumentException('Grace period cannot be negative.');
        }
        if (isset($data['required_hours']) && (float) $data['required_hours'] <= 0) {
            throw new InvalidArgumentException('Required hours must be greater than zero.');
        }
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Invalid status value.');
        }
        if (!in_array($data['type'] ?? 'regular', ['regular', 'night', 'flexible'], true)) {
            throw new InvalidArgumentException('Invalid shift type.');
        }
    }

    /**
     * @throws InvalidArgumentException on duplicate
     */
    private function checkDuplicateName(string $name, ?string $excludeId = null): void
    {
        $sql    = "SELECT id FROM shifts WHERE name = :name";
        $params = ['name' => trim($name)];

        if ($excludeId) {
            $sql         .= " AND id != :exclude";
            $params['exclude'] = $excludeId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            throw new InvalidArgumentException("A shift named \"{$name}\" already exists.");
        }
    }

    /**
     * Return summary statistics for the shifts listing header.
     */
    public function getStatistics(): array
    {
        $db  = Database::connection();
        $row = $db->query(
            "SELECT
                COUNT(*)                                             AS total,
                SUM(status  = 'active')                              AS active,
                SUM(status  = 'inactive')                            AS inactive,
                SUM(is_default = 1)                                  AS has_default,
                (SELECT COUNT(*)
                   FROM employees
                  WHERE shift_id IS NULL
                    AND status   = 'active')                         AS unassigned_employees
             FROM shifts"
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'total' => 0, 'active' => 0, 'inactive' => 0,
            'has_default' => 0, 'unassigned_employees' => 0,
        ];
    }
}
