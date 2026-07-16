<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;
use PDO;

final class DepartmentService
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
     * Get paginated list of departments with filters
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where = [];
        $params = [];

        // Search by name or code
        if (!empty($filters['q'])) {
            $where[] = '(d.name LIKE :q OR d.code LIKE :q OR d.location LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        // Filter by branch
        if (!empty($filters['branch_id'])) {
            $where[] = 'd.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) FROM departments d $whereClause";
        $db = Database::connection();
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get paginated data
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT 
                d.id,
                d.name,
                d.code,
                d.description,
                d.department_head,
                d.contact_number,
                d.email_address,
                d.location,
                d.branch_id,
                b.name AS branch_name,
                d.status,
                d.created_at,
                d.updated_at,
                (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.status = 'active') as employee_count
            FROM departments d
            LEFT JOIN branches b ON b.id = d.branch_id
            $whereClause
            ORDER BY d.name ASC
            LIMIT :offset, :limit
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $departments,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage))
        ];
    }

    /**
     * Get all active departments (for dropdowns)
     */
    public function getActiveDepartments(): array
    {
        $db = Database::connection();
        $sql = "
            SELECT id, name, code 
            FROM departments 
            WHERE status = 'active' 
            ORDER BY name ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get department by ID
     */
    public function find(string $id): ?array
    {
        $db = Database::connection();
        $sql = "
            SELECT 
                d.id,
                d.name,
                d.code,
                d.description,
                d.department_head,
                d.contact_number,
                d.email_address,
                d.location,
                d.branch_id,
                b.name AS branch_name,
                d.status,
                d.created_at,
                d.updated_at,
                (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.status = 'active') as employee_count
            FROM departments d
            LEFT JOIN branches b ON b.id = d.branch_id
            WHERE d.id = :id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);
        return $department ?: null;
    }

    /**
     * Create a new department
     */
    public function create(array $data): array
    {
        $this->validateDepartmentData($data);
        $this->checkDuplicate($data);

        $db = Database::connection();
        
        try {
            $db->beginTransaction();

            $id = uuid_v4();
            
            $sql = "
                INSERT INTO departments (
                    id, name, code, description, branch_id, 
                    department_head, contact_number, email_address, location, status
                ) VALUES (
                    :id, :name, :code, :description, :branch_id,
                    :department_head, :contact_number, :email_address, :location, :status
                )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'department_head' => $data['department_head'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'email_address' => $data['email_address'] ?? null,
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? 'active'
            ]);

            // Log audit trail
            if ($this->audit) {
                $this->audit->log('department_created', 'departments', $id, null, $data);
            }

            $db->commit();

            return $this->find($id);

        } catch (\PDOException $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to create department: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing department
     */
    public function update(string $id, array $data): array
    {
        $department = $this->find($id);
        if (!$department) {
            throw new InvalidArgumentException('Department not found');
        }

        $this->validateDepartmentData($data, $id);
        $this->checkDuplicate($data, $id);

        $db = Database::connection();
        
        try {
            $db->beginTransaction();

            $sql = "
                UPDATE departments SET
                    name = :name,
                    code = :code,
                    description = :description,
                    branch_id = :branch_id,
                    department_head = :department_head,
                    contact_number = :contact_number,
                    email_address = :email_address,
                    location = :location,
                    status = :status
                WHERE id = :id
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'department_head' => $data['department_head'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'email_address' => $data['email_address'] ?? null,
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? $department['status']
            ]);

            // Log audit trail
            if ($this->audit) {
                $this->audit->log('department_updated', 'departments', $id, $department, $data);
            }

            $db->commit();

            return $this->find($id);

        } catch (\PDOException $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to update department: ' . $e->getMessage());
        }
    }

    /**
     * Activate or deactivate a department
     */
    public function setStatus(string $id, string $status): array
    {
        if (!in_array($status, ['active', 'inactive'])) {
            throw new InvalidArgumentException('Invalid status');
        }

        $department = $this->find($id);
        if (!$department) {
            throw new InvalidArgumentException('Department not found');
        }

        $db = Database::connection();
        
        try {
            $db->beginTransaction();

            $sql = "UPDATE departments SET status = :status WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['status' => $status, 'id' => $id]);

            // Log audit trail
            if ($this->audit) {
                $action = $status === 'active' ? 'department_activated' : 'department_deactivated';
                $this->audit->log($action, 'departments', $id, $department, ['status' => $status]);
            }

            $db->commit();

            return $this->find($id);

        } catch (\PDOException $e) {
            $db->rollBack();
            throw new RuntimeException('Failed to update department status: ' . $e->getMessage());
        }
    }

    /**
     * Validate department data
     */
    private function validateDepartmentData(array $data, ?string $excludeId = null): void
    {
        // Required fields
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Department name is required');
        }

        // Validate email format if provided
        if (!empty($data['email_address']) && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        // Validate phone format if provided (basic validation)
        if (!empty($data['contact_number']) && !preg_match('/^[0-9\+\-\(\)\s]+$/', $data['contact_number'])) {
            throw new InvalidArgumentException('Invalid contact number format');
        }

        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
            throw new InvalidArgumentException('Invalid status value');
        }
    }

    /**
     * Check for duplicate department code or name
     */
    private function checkDuplicate(array $data, ?string $excludeId = null): void
    {
        $db = Database::connection();

        // Check duplicate code if provided
        if (!empty($data['code'])) {
            $sql = "SELECT id FROM departments WHERE code = :code";
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
                throw new InvalidArgumentException('Department code already exists');
            }
        }

        // Check duplicate name
        $sql = "SELECT id FROM departments WHERE name = :name";
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
            throw new InvalidArgumentException('Department name already exists');
        }
    }

    /**
     * Get department statistics
     */
    public function getStatistics(): array
    {
        $db = Database::connection();
        
        $sql = "
            SELECT 
                d.id,
                d.name,
                d.code,
                d.status,
                b.name AS branch_name,
                (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.status = 'active') as total_employees,
                (SELECT COUNT(*) FROM employees e 
                 INNER JOIN attendance_summary a ON a.employee_id = e.id 
                 WHERE e.department_id = d.id 
                 AND a.attendance_date = CURDATE() 
                 AND a.day_status = 'present') as present_today,
                (SELECT COUNT(*) FROM employees e 
                 INNER JOIN attendance_summary a ON a.employee_id = e.id 
                 WHERE e.department_id = d.id 
                 AND a.attendance_date = CURDATE() 
                 AND a.day_status = 'absent') as absent_today
            FROM departments d
            LEFT JOIN branches b ON b.id = d.branch_id
            WHERE d.status = 'active'
            ORDER BY d.name ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
