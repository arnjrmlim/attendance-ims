<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;
use PDO;

final class BranchService
{
    private ?AuditService $audit = null;

    public function __construct()
    {
        // Only initialize AuditService if it exists (avoid circular dependency)
        if (class_exists('App\Services\AuditService')) {
            $this->audit = new AuditService();
        }
    }

    /**
     * Get paginated list of branches with filters
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where = [];
        $params = [];

        // Search by name or code
        if (!empty($filters['q'])) {
            $where[] = '(b.name LIKE :q OR b.code LIKE :q OR b.city LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $where[] = 'b.status = :status';
            $params['status'] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) FROM branches b $whereClause";
        $db = Database::connection();
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get paginated data
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT 
                b.id,
                b.name,
                b.code,
                b.address,
                b.city,
                b.province,
                b.phone,
                b.email,
                b.branch_manager,
                b.time_zone,
                b.status,
                b.created_at,
                b.updated_at,
                (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.id AND e.status = 'active') as employee_count
            FROM branches b
            $whereClause
            ORDER BY b.name ASC
            LIMIT :offset, :limit
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $branches,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage))
        ];
    }

    /**
     * Get all active branches (for dropdowns)
     */
    public function getActiveBranches(): array
    {
        $db = Database::connection();
        $sql = "
            SELECT id, name, code 
            FROM branches 
            WHERE status = 'active' 
            ORDER BY name ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get branch by ID
     */
    public function find(string $id): ?array
    {
        $db = Database::connection();
        $sql = "
            SELECT 
                b.id,
                b.name,
                b.code,
                b.address,
                b.city,
                b.province,
                b.phone,
                b.email,
                b.branch_manager,
                b.time_zone,
                b.status,
                b.created_at,
                b.updated_at,
                (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.id AND e.status = 'active') as employee_count
            FROM branches b
            WHERE b.id = :id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $branch ?: null;
    }

    /**
     * Create a new branch
     */
    public function create(array $data): array
    {
        $this->validateBranchData($data);
        $this->checkDuplicate($data);

        $db = Database::connection();
        
        try {
            $db->beginTransaction();

            $id = uuid_v4();
            
            $sql = "
                INSERT INTO branches (
                    id, name, code, address, city, province, 
                    phone, email, branch_manager, time_zone, status
                ) VALUES (
                    :id, :name, :code, :address, :city, :province,
                    :phone, :email, :branch_manager, :time_zone, :status
                )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'branch_manager' => $data['branch_manager'] ?? null,
                'time_zone' => $data['time_zone'] ?? 'Asia/Manila',
                'status' => $data['status'] ?? 'active'
            ]);

            // Log audit trail
            if ($this->audit) {
                $this->audit->log('branch_created', 'branches', $id, null, $data);
            }

            $db->commit();

            return $this->find($id);

        } catch (\PDOException $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to create branch: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing branch
     */
    public function update(string $id, array $data): array
    {
        $branch = $this->find($id);
        if (!$branch) {
            throw new InvalidArgumentException('Branch not found');
        }

        $this->validateBranchData($data, $id);
        $this->checkDuplicate($data, $id);

        $db = Database::connection();
        
        try {
            $db->beginTransaction();

            $sql = "
                UPDATE branches SET
                    name = :name,
                    code = :code,
                    address = :address,
                    city = :city,
                    province = :province,
                    phone = :phone,
                    email = :email,
                    branch_manager = :branch_manager,
                    time_zone = :time_zone,
                    status = :status
                WHERE id = :id
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'branch_manager' => $data['branch_manager'] ?? null,
                'time_zone' => $data['time_zone'] ?? 'Asia/Manila',
                'status' => $data['status'] ?? $branch['status']
            ]);

            // Log audit trail
            if ($this->audit) {
                $this->audit->log('branch_updated', 'branches', $id, $branch, $data);
            }

            $db->commit();

            return $this->find($id);

        } catch (\PDOException $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to update branch: ' . $e->getMessage());
        }
    }

    /**
     * Activate or deactivate a branch
     */
    public function setStatus(string $id, string $status): array
    {
        if (!in_array($status, ['active', 'inactive'])) {
            throw new InvalidArgumentException('Invalid status');
        }

        $branch = $this->find($id);
        if (!$branch) {
            throw new InvalidArgumentException('Branch not found');
        }

        $db = Database::connection();
        
        try {
            $db->beginTransaction();

            $sql = "UPDATE branches SET status = :status WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['status' => $status, 'id' => $id]);

            // Log audit trail
            if ($this->audit) {
                $action = $status === 'active' ? 'branch_activated' : 'branch_deactivated';
                $this->audit->log($action, 'branches', $id, $branch, ['status' => $status]);
            }

            $db->commit();

            return $this->find($id);

        } catch (\PDOException $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to update branch status: ' . $e->getMessage());
        }
    }

    /**
     * Validate branch data
     */
    private function validateBranchData(array $data, ?string $excludeId = null): void
    {
        // Required fields
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Branch name is required');
        }
        if (empty($data['code'])) {
            throw new InvalidArgumentException('Branch code is required');
        }

        // Validate email format if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        // Validate phone format if provided (basic validation)
        if (!empty($data['phone']) && !preg_match('/^[0-9\+\-\(\)\s]+$/', $data['phone'])) {
            throw new InvalidArgumentException('Invalid phone number format');
        }

        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
            throw new InvalidArgumentException('Invalid status value');
        }
    }

    /**
     * Check for duplicate branch code or name
     */
    private function checkDuplicate(array $data, ?string $excludeId = null): void
    {
        $db = Database::connection();

        // Check duplicate code
        $sql = "SELECT id FROM branches WHERE code = :code";
        if ($excludeId) {
            $sql .= " AND id != :id";
        }
        
        $stmt = $db->prepare($sql);
        $params = ['code' => $data['code']];
        if ($excludeId) {
            $params['id'] = $excludeId;
        }
        $stmt->execute($params);
        
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('Branch code already exists');
        }

        // Check duplicate name
        $sql = "SELECT id FROM branches WHERE name = :name";
        if ($excludeId) {
            $sql .= " AND id != :id";
        }
        
        $stmt = $db->prepare($sql);
        $params = ['name' => $data['name']];
        if ($excludeId) {
            $params['id'] = $excludeId;
        }
        $stmt->execute($params);
        
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('Branch name already exists');
        }
    }

    /**
     * Get branch statistics
     */
    public function getStatistics(): array
    {
        $db = Database::connection();
        
        $sql = "
            SELECT 
                b.id,
                b.name,
                b.code,
                b.status,
                (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.id AND e.status = 'active') as total_employees,
                (SELECT COUNT(*) FROM employees e 
                 INNER JOIN attendance_summary a ON a.employee_id = e.id 
                 WHERE e.branch_id = b.id 
                 AND a.attendance_date = CURDATE() 
                 AND a.day_status = 'present') as present_today,
                (SELECT COUNT(*) FROM employees e 
                 INNER JOIN attendance_summary a ON a.employee_id = e.id 
                 WHERE e.branch_id = b.id 
                 AND a.attendance_date = CURDATE() 
                 AND a.day_status = 'absent') as absent_today
            FROM branches b
            WHERE b.status = 'active'
            ORDER BY b.name ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
