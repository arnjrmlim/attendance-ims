<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\DirectoryService;
use InvalidArgumentException;
use RuntimeException;
use PDO;

final class EmployeeService
{
    private DirectoryService $directory;

    public function __construct()
    {
        $this->directory = new DirectoryService();
    }

    /**
     * Get paginated list of employees with filters
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $where = [];
        $params = [];

        // Search by employee number or name
        if (!empty($filters['q'])) {
            $where[] = '(e.employee_number LIKE :q OR e.first_name LIKE :q OR e.last_name LIKE :q OR CONCAT(e.first_name, " ", e.last_name) LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        // Filter by department
        if (!empty($filters['department_id'])) {
            $where[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        // Filter by branch
        if (!empty($filters['branch_id'])) {
            $where[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        // Filter by shift
        if (!empty($filters['shift_id'])) {
            $where[] = 'e.shift_id = :shift_id';
            $params['shift_id'] = $filters['shift_id'];
        }

        // Filter by employment status
        if (!empty($filters['employment_status'])) {
            $where[] = 'e.employment_status = :employment_status';
            $params['employment_status'] = $filters['employment_status'];
        }

        // Filter by employment type
        if (!empty($filters['employment_type'])) {
            $where[] = 'e.employment_type = :employment_type';
            $params['employment_type'] = $filters['employment_type'];
        }

        // Filter by status (active/inactive)
        if (!empty($filters['status'])) {
            $where[] = 'e.status = :status';
            $params['status'] = $filters['status'];
        }

        $whereClause = !empty($where) ? implode(' AND ', $where) : '1=1';
        
        // Get total count
        $countSql = 'SELECT COUNT(*) FROM employees e WHERE ' . $whereClause;
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Get pagination metadata
        $meta = pagination_meta($total, $page, $perPage);

        // Get employees
        $sql = 'SELECT e.*, 
                       d.name AS department_name, 
                       b.name AS branch_name, 
                       s.name AS shift_name,
                       CONCAT(e.first_name, " ", e.last_name) AS full_name,
                       u.username AS created_by_username
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN shifts s ON s.id = e.shift_id
                LEFT JOIN users u ON u.id = e.created_by
                WHERE ' . $whereClause . '
                ORDER BY e.created_at DESC
                LIMIT :offset, :per_page';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $meta['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'meta' => $meta,
        ];
    }

    /**
     * Get single employee by ID
     */
    public function find(string $id): ?array
    {
        $sql = 'SELECT e.*, 
                       d.name AS department_name, 
                       b.name AS branch_name, 
                       s.name AS shift_name,
                       sup.employee_number AS supervisor_number,
                       CONCAT(sup.first_name, " ", sup.last_name) AS supervisor_name,
                       CONCAT(e.first_name, " ", e.last_name) AS full_name
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN branches b ON b.id = e.branch_id
                LEFT JOIN shifts s ON s.id = e.shift_id
                LEFT JOIN employees sup ON sup.id = e.immediate_supervisor_id
                WHERE e.id = ?';
        
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get employee by employee number
     */
    public function findByEmployeeNumber(string $employeeNumber): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM employees WHERE employee_number = ?');
        ignore_user_abort(true);
        $stmt->execute([$employeeNumber]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create new employee
     */
    public function create(array $data): string
    {
        $this->validate($data);
        $this->checkDuplicates($data);

        $id = uuid_v4();
        $userId = current_user()['id'] ?? null;

        // Generate unique QR code value
        $qrValue = $this->generateUniqueQRValue();

        // Hash PIN if provided
        $pinHash = null;
        if (!empty($data['pin'])) {
            $pinHash = password_hash($data['pin'], PASSWORD_DEFAULT);
        }

        // Hash password if provided
        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Handle photo upload
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $photoPath = $this->uploadPhoto('photo');
        }

        // Check if username/password columns exist
        $hasUsernameColumn = false;
        $hasPasswordColumn = false;
        try {
            $stmt = Database::connection()->query("SHOW COLUMNS FROM employees LIKE 'username'");
            $hasUsernameColumn = $stmt->fetch() !== false;
            $stmt = Database::connection()->query("SHOW COLUMNS FROM employees LIKE 'password_hash'");
            $hasPasswordColumn = $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            // Columns don't exist or error occurred
        }

        // Build SQL dynamically based on available columns
        $columns = [
            'id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix',
            'gender', 'date_of_birth', 'civil_status', 'nationality',
            'photo', 'department_id', 'branch_id', 'shift_id', 'position',
            'employment_status', 'employment_type', 'contact_number', 'alternate_mobile',
            'email', 'home_address', 'emergency_contact_name', 'emergency_contact_number',
            'emergency_contact_relationship', 'date_hired', 'immediate_supervisor_id',
            'pin', 'pin_hash', 'qr_code_value', 'rfid_value', 'status',
            'created_by', 'created_at', 'updated_at'
        ];

        if ($hasUsernameColumn) {
            array_splice($columns, array_search('email', $columns) + 1, 0, 'username');
        }
        if ($hasPasswordColumn) {
            array_splice($columns, array_search($hasUsernameColumn ? 'username' : 'email', $columns) + 1, 0, 'password_hash');
        }

        $sql = 'INSERT INTO employees (' . implode(', ', $columns) . ') VALUES (';
        $placeholders = [];
        foreach ($columns as $col) {
            if ($col === 'created_at' || $col === 'updated_at') {
                $placeholders[] = 'NOW()';
            } else {
                $placeholders[] = ':' . $col;
            }
        }
        $sql .= implode(', ', $placeholders) . ')';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'id' => $id,
            'employee_number' => trim($data['employee_number']),
            'first_name' => trim($data['first_name']),
            'middle_name' => trim($data['middle_name'] ?? ''),
            'last_name' => trim($data['last_name']),
            'suffix' => trim($data['suffix'] ?? ''),
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'civil_status' => $data['civil_status'] ?? null,
            'nationality' => trim($data['nationality'] ?? ''),
            'photo' => $photoPath,
            'department_id' => $data['department_id'] ?: null,
            'branch_id' => $data['branch_id'] ?: null,
            'shift_id' => $data['shift_id'] ?: null,
            'position' => trim($data['position'] ?? ''),
            'employment_status' => $data['employment_status'] ?? 'Active',
            'employment_type' => $data['employment_type'] ?? 'Probationary',
            'contact_number' => trim($data['contact_number'] ?? ''),
            'alternate_mobile' => trim($data['alternate_mobile'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'home_address' => trim($data['home_address'] ?? ''),
            'emergency_contact_name' => trim($data['emergency_contact_name'] ?? ''),
            'emergency_contact_number' => trim($data['emergency_contact_number'] ?? ''),
            'emergency_contact_relationship' => trim($data['emergency_contact_relationship'] ?? ''),
            'date_hired' => $data['date_hired'] ?? null,
            'immediate_supervisor_id' => $data['immediate_supervisor_id'] ?: null,
            'pin' => $data['pin'] ?? null,
            'pin_hash' => $pinHash,
            'qr_code_value' => $qrValue,
            'rfid_value' => !empty(trim($data['rfid_value'] ?? '')) ? trim($data['rfid_value']) : null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $userId,
        ];

        if ($hasUsernameColumn) {
            $params['username'] = trim($data['username'] ?? '');
        }
        if ($hasPasswordColumn) {
            $params['password_hash'] = $passwordHash;
        }

        $stmt->execute($params);

        // Create user record if username and password provided
        if (!empty($data['username']) && !empty($data['password'])) {
            $this->createUserForEmployee($id, $data, $passwordHash);
        }

        // Add timeline entry
        $this->addTimelineEntry($id, 'employee_created', null, json_encode($data), 'Employee created', $userId);

        // Log audit
        (new AuditService())->log('EMPLOYEE_CREATED', 'employees', $id, null, $data);

        return $id;
    }

    /**
     * Update existing employee
     */
    public function update(string $id, array $data): void
    {
        $employee = $this->find($id);
        if (!$employee) {
            throw new InvalidArgumentException('Employee not found.');
        }

        $this->validate($data, $id);
        $this->checkDuplicates($data, $id);

        $userId = current_user()['id'] ?? null;
        $previousData = $employee;

        // Handle photo upload
        $photoPath = $employee['photo'];
        if (!empty($_FILES['photo']['name'])) {
            $photoPath = $this->uploadPhoto('photo');
            // Delete old photo
            if ($employee['photo']) {
                $this->deletePhoto($employee['photo']);
            }
            $this->addTimelineEntry($id, 'photo_updated', $employee['photo'], $photoPath, 'Profile photo updated', $userId);
        }

        // Handle PIN change
        $pinHash = $employee['pin_hash'];
        $pin = $employee['pin'];
        if (!empty($data['pin'])) {
            $pin = $data['pin'];
            $pinHash = password_hash($data['pin'], PASSWORD_DEFAULT);
        }

        // Handle password change
        $passwordHash = $employee['password_hash'] ?? null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Check if username/password columns exist
        $hasUsernameColumn = false;
        $hasPasswordColumn = false;
        try {
            $stmt = Database::connection()->query("SHOW COLUMNS FROM employees LIKE 'username'");
            $hasUsernameColumn = $stmt->fetch() !== false;
            $stmt = Database::connection()->query("SHOW COLUMNS FROM employees LIKE 'password_hash'");
            $hasPasswordColumn = $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            // Columns don't exist or error occurred
        }

        // Track changes for timeline
        $changes = [];
        if ($employee['department_id'] !== ($data['department_id'] ?? null)) {
            $changes[] = 'department_changed';
        }
        if ($employee['branch_id'] !== ($data['branch_id'] ?? null)) {
            $changes[] = 'branch_changed';
        }
        if ($employee['shift_id'] !== ($data['shift_id'] ?? null)) {
            $changes[] = 'shift_changed';
        }
        if ($employee['position'] !== ($data['position'] ?? null)) {
            $changes[] = 'position_changed';
        }
        if ($employee['employment_status'] !== ($data['employment_status'] ?? null)) {
            $changes[] = 'status_changed';
        }

        // Build SQL dynamically based on available columns
        $setClauses = [
            'employee_number = :employee_number',
            'first_name = :first_name',
            'middle_name = :middle_name',
            'last_name = :last_name',
            'suffix = :suffix',
            'gender = :gender',
            'date_of_birth = :date_of_birth',
            'civil_status = :civil_status',
            'nationality = :nationality',
            'photo = :photo',
            'department_id = :department_id',
            'branch_id = :branch_id',
            'shift_id = :shift_id',
            'position = :position',
            'employment_status = :employment_status',
            'employment_type = :employment_type',
            'contact_number = :contact_number',
            'alternate_mobile = :alternate_mobile',
            'email = :email',
            'home_address = :home_address',
            'emergency_contact_name = :emergency_contact_name',
            'emergency_contact_number = :emergency_contact_number',
            'emergency_contact_relationship = :emergency_contact_relationship',
            'date_hired = :date_hired',
            'immediate_supervisor_id = :immediate_supervisor_id',
            'pin = :pin',
            'pin_hash = :pin_hash',
            'rfid_value = :rfid_value',
            'status = :status',
            'updated_by = :updated_by',
            'updated_at = NOW()'
        ];

        if ($hasUsernameColumn) {
            array_splice($setClauses, array_search('email = :email', $setClauses) + 1, 0, 'username = :username');
        }
        if ($hasPasswordColumn) {
            array_splice($setClauses, array_search($hasUsernameColumn ? 'username = :username' : 'email = :email', $setClauses) + 1, 0, 'password_hash = :password_hash');
        }

        $sql = 'UPDATE employees SET ' . implode(', ', $setClauses) . ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $params = [
            'id' => $id,
            'employee_number' => trim($data['employee_number']),
            'first_name' => trim($data['first_name']),
            'middle_name' => trim($data['middle_name'] ?? ''),
            'last_name' => trim($data['last_name']),
            'suffix' => trim($data['suffix'] ?? ''),
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'civil_status' => $data['civil_status'] ?? null,
            'nationality' => trim($data['nationality'] ?? ''),
            'photo' => $photoPath,
            'department_id' => $data['department_id'] ?: null,
            'branch_id' => $data['branch_id'] ?: null,
            'shift_id' => $data['shift_id'] ?: null,
            'position' => trim($data['position'] ?? ''),
            'employment_status' => $data['employment_status'] ?? 'Active',
            'employment_type' => $data['employment_type'] ?? 'Probationary',
            'contact_number' => trim($data['contact_number'] ?? ''),
            'alternate_mobile' => trim($data['alternate_mobile'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'home_address' => trim($data['home_address'] ?? ''),
            'emergency_contact_name' => trim($data['emergency_contact_name'] ?? ''),
            'emergency_contact_number' => trim($data['emergency_contact_number'] ?? ''),
            'emergency_contact_relationship' => trim($data['emergency_contact_relationship'] ?? ''),
            'date_hired' => $data['date_hired'] ?? null,
            'immediate_supervisor_id' => $data['immediate_supervisor_id'] ?: null,
            'pin' => $pin,
            'pin_hash' => $pinHash,
            'rfid_value' => !empty(trim($data['rfid_value'] ?? '')) ? trim($data['rfid_value']) : null,
            'status' => $data['status'] ?? 'active',
            'updated_by' => $userId,
        ];

        if ($hasUsernameColumn) {
            $params['username'] = trim($data['username'] ?? '');
        }
        if ($hasPasswordColumn) {
            $params['password_hash'] = $passwordHash;
        }

        $stmt->execute($params);

        // Update or create user record
        if (!empty($data['username'])) {
            // Check if user record exists for this employee
            $existingUser = $this->findUserByEmployeeId($id);
            
            if ($existingUser) {
                // Update existing user
                $this->updateUserForEmployee($existingUser['id'], $data, $passwordHash);
            } else {
                // Create new user record - password is required for new users
                if (!empty($data['password'])) {
                    $this->createUserForEmployee($id, $data, $passwordHash);
                }
            }
        } else {
            // Username cleared - check if user exists and delete
            $existingUser = $this->findUserByEmployeeId($id);
            if ($existingUser) {
                $this->deleteUserForEmployee($existingUser['id'], $id);
            }
        }

        // Add timeline entries for changes
        foreach ($changes as $change) {
            $this->addTimelineEntry($id, $change, 
                json_encode($previousData), 
                json_encode($data), 
                ucfirst(str_replace('_', ' ', $change)), 
                $userId
            );
        }

        $this->addTimelineEntry($id, 'employee_updated', json_encode($previousData), json_encode($data), 'Employee updated', $userId);
        (new AuditService())->log('EMPLOYEE_UPDATED', 'employees', $id, json_encode($previousData), $data);
    }

    /**
     * Activate employee
     */
    public function activate(string $id): void
    {
        $employee = $this->find($id);
        if (!$employee) {
            throw new InvalidArgumentException('Employee not found.');
        }

        $userId = current_user()['id'] ?? null;
        $previousStatus = $employee['status'];

        $stmt = Database::connection()->prepare(
            'UPDATE employees SET status = "active", updated_by = :user_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['user_id' => $userId, 'id' => $id]);

        $this->addTimelineEntry($id, 'employee_activated', $previousStatus, 'active', 'Employee activated', $userId);
        (new AuditService())->log('EMPLOYEE_ACTIVATED', 'employees', $id, $previousStatus, 'active');
    }

    /**
     * Deactivate employee
     */
    public function deactivate(string $id): void
    {
        $employee = $this->find($id);
        if (!$employee) {
            throw new InvalidArgumentException('Employee not found.');
        }

        $userId = current_user()['id'] ?? null;
        $previousStatus = $employee['status'];

        $stmt = Database::connection()->prepare(
            'UPDATE employees SET status = "inactive", updated_by = :user_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['user_id' => $userId, 'id' => $id]);

        $this->addTimelineEntry($id, 'employee_deactivated', $previousStatus, 'inactive', 'Employee deactivated', $userId);
        (new AuditService())->log('EMPLOYEE_DEACTIVATED', 'employees', $id, $previousStatus, 'inactive');
    }

    /**
     * Change employee status
     */
    public function changeStatus(string $id, string $status): void
    {
        $validStatuses = ['Active', 'Inactive', 'Suspended', 'Resigned', 'Terminated', 'Retired'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('Invalid employment status.');
        }

        $employee = $this->find($id);
        if (!$employee) {
            throw new InvalidArgumentException('Employee not found.');
        }

        $userId = current_user()['id'] ?? null;
        $previousStatus = $employee['employment_status'];

        $stmt = Database::connection()->prepare(
            'UPDATE employees SET employment_status = :status, updated_by = :user_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'user_id' => $userId, 'id' => $id]);

        $this->addTimelineEntry($id, 'status_changed', $previousStatus, $status, "Status changed to {$status}", $userId);
        (new AuditService())->log('EMPLOYEE_STATUS_CHANGED', 'employees', $id, $previousStatus, $status);
    }

    /**
     * Regenerate QR code for employee
     */
    public function regenerateQRCode(string $id): string
    {
        $employee = $this->find($id);
        if (!$employee) {
            throw new InvalidArgumentException('Employee not found.');
        }

        $userId = current_user()['id'] ?? null;
        $previousQR = $employee['qr_code_value'];
        $newQR = $this->generateUniqueQRValue();

        $stmt = Database::connection()->prepare(
            'UPDATE employees SET qr_code_value = :qr_value, updated_by = :user_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['qr_value' => $newQR, 'user_id' => $userId, 'id' => $id]);

        $this->addTimelineEntry($id, 'qr_code_regenerated', $previousQR, $newQR, 'QR code regenerated', $userId);
        (new AuditService())->log('EMPLOYEE_QR_REGENERATED', 'employees', $id, $previousQR, $newQR);

        return $newQR;
    }

    /**
     * Get employee timeline
     */
    public function getTimeline(string $id): array
    {
        $sql = 'SELECT et.*, u.username AS created_by_username
                FROM employee_timeline et
                LEFT JOIN users u ON u.id = et.created_by
                WHERE et.employee_id = ?
                ORDER BY et.created_at DESC';
        
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /**
     * Get employee statistics
     */
    public function getStatistics(): array
    {
        $sql = 'SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = "inactive" THEN 1 ELSE 0 END) AS inactive,
                    SUM(CASE WHEN employment_status = "Suspended" THEN 1 ELSE 0 END) AS suspended,
                    SUM(CASE WHEN employment_status = "Resigned" THEN 1 ELSE 0 END) AS resigned,
                    SUM(CASE WHEN employment_status = "Active" THEN 1 ELSE 0 END) AS active_employment,
                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS recent_hires
                FROM employees';
        
        $stmt = Database::connection()->query($sql);
        return $stmt->fetch();
    }

    /**
     * Bulk activate employees
     */
    public function bulkActivate(array $ids): int
    {
        $userId = current_user()['id'] ?? null;
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $sql = "UPDATE employees SET status = 'active', updated_by = ?, updated_at = NOW() WHERE id IN ({$placeholders})";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$userId, ...$ids]);

        $count = $stmt->rowCount();
        foreach ($ids as $id) {
            $this->addTimelineEntry($id, 'employee_activated', null, 'active', 'Bulk activated', $userId);
        }

        (new AuditService())->log('EMPLOYEE_BULK_ACTIVATED', 'employees', null, null, ['count' => $count, 'ids' => $ids]);
        return $count;
    }

    /**
     * Bulk deactivate employees
     */
    public function bulkDeactivate(array $ids): int
    {
        $userId = current_user()['id'] ?? null;
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $sql = "UPDATE employees SET status = 'inactive', updated_by = ?, updated_at = NOW() WHERE id IN ({$placeholders})";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$userId, ...$ids]);

        $count = $stmt->rowCount();
        foreach ($ids as $id) {
            $this->addTimelineEntry($id, 'employee_deactivated', null, 'inactive', 'Bulk deactivated', $userId);
        }

        (new AuditService())->log('EMPLOYEE_BULK_DEACTIVATED', 'employees', null, null, ['count' => $count, 'ids' => $ids]);
        return $count;
    }

    /**
     * Validate employee data
     */
    private function validate(array $data, ?string $ignoreId = null): void
    {
        // Required fields
        if (empty($data['employee_number']) || empty($data['first_name']) || empty($data['last_name'])) {
            throw new InvalidArgumentException('Employee number, first name, and last name are required.');
        }

        // Validate employee number format
        if (!preg_match('/^[A-Z0-9\-_]+$/i', $data['employee_number'])) {
            throw new InvalidArgumentException('Employee number can only contain letters, numbers, hyphens, and underscores.');
        }

        // Validate email format
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format.');
        }

        // Validate username format
        if (!empty($data['username']) && !preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $data['username'])) {
            throw new InvalidArgumentException('Username must be 3-50 characters and contain only letters, numbers, underscores, and dots.');
        }

        // Validate mobile number
        if (!empty($data['contact_number']) && !preg_match('/^[0-9\+\-\s]+$/', $data['contact_number'])) {
            throw new InvalidArgumentException('Invalid mobile number format.');
        }

        // Validate dates
        if (!empty($data['date_of_birth']) && !strtotime($data['date_of_birth'])) {
            throw new InvalidArgumentException('Invalid date of birth.');
        }

        if (!empty($data['date_hired']) && !strtotime($data['date_hired'])) {
            throw new InvalidArgumentException('Invalid date hired.');
        }

        // Validate employment status
        $validStatuses = ['Active', 'Inactive', 'Suspended', 'Resigned', 'Terminated', 'Retired'];
        if (!empty($data['employment_status']) && !in_array($data['employment_status'], $validStatuses, true)) {
            throw new InvalidArgumentException('Invalid employment status.');
        }

        // Validate employment type
        $validTypes = ['Regular', 'Probationary', 'Contractual', 'Part-Time', 'Temporary', 'Intern'];
        if (!empty($data['employment_type']) && !in_array($data['employment_type'], $validTypes, true)) {
            throw new InvalidArgumentException('Invalid employment type.');
        }

        // Validate civil status
        $validCivilStatuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
        if (!empty($data['civil_status']) && !in_array($data['civil_status'], $validCivilStatuses, true)) {
            throw new InvalidArgumentException('Invalid civil status.');
        }

        // Validate gender
        $validGenders = ['Male', 'Female', 'Other'];
        if (!empty($data['gender']) && !in_array($data['gender'], $validGenders, true)) {
            throw new InvalidArgumentException('Invalid gender.');
        }

        // Character limits
        if (strlen($data['employee_number']) > 30) {
            throw new InvalidArgumentException('Employee number cannot exceed 30 characters.');
        }

        if (strlen($data['first_name']) > 80) {
            throw new InvalidArgumentException('First name cannot exceed 80 characters.');
        }

        if (strlen($data['last_name']) > 80) {
            throw new InvalidArgumentException('Last name cannot exceed 80 characters.');
        }

        if (!empty($data['email']) && strlen($data['email']) > 120) {
            throw new InvalidArgumentException('Email cannot exceed 120 characters.');
        }
    }

    /**
     * Check for duplicate records
     */
    private function checkDuplicates(array $data, ?string $ignoreId = null): void
    {
        // Check duplicate employee number
        $sql = 'SELECT COUNT(*) FROM employees WHERE employee_number = ?';
        $params = [$data['employee_number']];
        
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Employee number already exists.');
        }

        // Check duplicate email
        if (!empty($data['email'])) {
            $sql = 'SELECT COUNT(*) FROM employees WHERE email = ?';
            $params = [$data['email']];
            
            if ($ignoreId) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            
            if ((int) $stmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('Email address already exists.');
            }
        }

        // Check duplicate username (only if column exists)
        if (!empty($data['username'])) {
            $hasUsernameColumn = false;
            try {
                $stmt = Database::connection()->query("SHOW COLUMNS FROM employees LIKE 'username'");
                $hasUsernameColumn = $stmt->fetch() !== false;
            } catch (\Throwable $e) {
                // Column doesn't exist or error occurred
            }

            if ($hasUsernameColumn) {
                $sql = 'SELECT COUNT(*) FROM employees WHERE username = ?';
                $params = [$data['username']];
                
                if ($ignoreId) {
                    $sql .= ' AND id <> ?';
                    $params[] = $ignoreId;
                }

                $stmt = Database::connection()->prepare($sql);
                $stmt->execute($params);
                
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Username already exists.');
                }
            }
        }

        // Check duplicate PIN
        if (!empty($data['pin'])) {
            $sql = 'SELECT COUNT(*) FROM employees WHERE pin = ?';
            $params = [$data['pin']];
            
            if ($ignoreId) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            
            if ((int) $stmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('PIN already exists.');
            }
        }

        // Check duplicate RFID
        if (!empty($data['rfid_value'])) {
            $sql = 'SELECT COUNT(*) FROM employees WHERE rfid_value = ?';
            $params = [$data['rfid_value']];
            
            if ($ignoreId) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            
            if ((int) $stmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('RFID value already exists.');
            }
        }
    }

    /**
     * Generate unique QR code value
     */
    private function generateUniqueQRValue(): string
    {
        $maxAttempts = 10;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $qrValue = 'EMP-' . strtoupper(bin2hex(random_bytes(8)));
            
            $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM employees WHERE qr_code_value = ?');
            $stmt->execute([$qrValue]);
            
            if ((int) $stmt->fetchColumn() === 0) {
                return $qrValue;
            }
        }

        throw new RuntimeException('Failed to generate unique QR code value.');
    }

    /**
     * Upload employee photo
     */
    private function uploadPhoto(string $field): string
    {
        if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('No photo file uploaded.');
        }

        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Photo upload failed.');
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($_FILES[$field]['size'] > $maxSize) {
            throw new InvalidArgumentException('Photo must not exceed 2MB.');
        }

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES[$field]['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new InvalidArgumentException('Photo must be JPG or PNG format.');
        }

        $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $uploadDir = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'photos';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $name = uuid_v4() . '.' . $extension;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $name;
        
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            throw new RuntimeException('Unable to store photo.');
        }

        return 'photos/' . $name;
    }

    /**
     * Delete employee photo
     */
    private function deletePhoto(string $path): void
    {
        $fullPath = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Add timeline entry
     */
    private function addTimelineEntry(string $employeeId, string $eventType, ?string $previousValue, ?string $newValue, ?string $description, ?string $createdBy): void
    {
        $sql = 'INSERT INTO employee_timeline (employee_id, event_type, previous_value, new_value, description, created_by, created_at)
                VALUES (:employee_id, :event_type, :previous_value, :new_value, :description, :created_by, NOW())';
        
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'employee_id' => $employeeId,
            'event_type' => $eventType,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'description' => $description,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Export employees to CSV
     */
    public function exportToCSV(array $filters = []): string
    {
        $result = $this->list($filters, 1, 10000);
        $employees = $result['data'];

        $filename = 'employees_export_' . date('Y-m-d_His') . '.csv';
        $filepath = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $filename;
        
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0775, true);
        }

        $fp = fopen($filepath, 'w');
        
        // Header
        fputcsv($fp, [
            'Employee Number', 'First Name', 'Middle Name', 'Last Name', 'Suffix',
            'Gender', 'Date of Birth', 'Civil Status', 'Nationality',
            'Department', 'Branch', 'Position', 'Shift',
            'Employment Status', 'Employment Type', 'Date Hired',
            'Contact Number', 'Email', 'Home Address',
            'Emergency Contact Name', 'Emergency Contact Number', 'Emergency Contact Relationship',
            'Status'
        ]);

        // Data
        foreach ($employees as $emp) {
            fputcsv($fp, [
                $emp['employee_number'],
                $emp['first_name'],
                $emp['middle_name'] ?? '',
                $emp['last_name'],
                $emp['suffix'] ?? '',
                $emp['gender'] ?? '',
                $emp['date_of_birth'] ?? '',
                $emp['civil_status'] ?? '',
                $emp['nationality'] ?? '',
                $emp['department_name'] ?? '',
                $emp['branch_name'] ?? '',
                $emp['position'] ?? '',
                $emp['shift_name'] ?? '',
                $emp['employment_status'] ?? '',
                $emp['employment_type'] ?? '',
                $emp['date_hired'] ?? '',
                $emp['contact_number'] ?? '',
                $emp['email'] ?? '',
                $emp['home_address'] ?? '',
                $emp['emergency_contact_name'] ?? '',
                $emp['emergency_contact_number'] ?? '',
                $emp['emergency_contact_relationship'] ?? '',
                $emp['status'] ?? ''
            ]);
        }

        fclose($fp);

        (new AuditService())->log('EMPLOYEE_EXPORT', 'employees', null, null, ['format' => 'csv', 'count' => count($employees)]);

        return 'reports/' . $filename;
    }

    /**
     * Create user record for employee with robust error handling and validation
     * 
     * @param string $employeeId Employee ID
     * @param array $employeeData Employee data array
     * @param string $passwordHash Hashed password
     * @return void
     */
    private function createUserForEmployee(string $employeeId, array $employeeData, string $passwordHash): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $username = trim($employeeData['username'] ?? '');
        $email = trim($employeeData['email'] ?? '');
        $fullName = trim($employeeData['first_name'] . ' ' . $employeeData['last_name']);
        $roleId = 3; // Default to Employee role

        // Log the start of user creation attempt
        error_log("[$timestamp] Automatic user creation attempt for employee: $employeeId, username: $username, email: $email");

        try {
            // Step 1: Verify database connection
            $db = Database::connection();
            $dbNameStmt = $db->query("SELECT DATABASE()");
            $currentDb = $dbNameStmt->fetchColumn();
            error_log("[$timestamp] Current database: $currentDb");

            // Step 2: Check if users table exists with proper error logging
            try {
                $tableCheckStmt = $db->query("SHOW TABLES LIKE 'users'");
                $hasUsersTable = $tableCheckStmt->fetch() !== false;
                
                if (!$hasUsersTable) {
                    error_log("[$timestamp] Automatic user creation skipped: users table not found in database '$currentDb'");
                    return;
                }
            } catch (\Throwable $e) {
                error_log(sprintf(
                    "[$timestamp] Failed checking users table existence. Employee ID: %s, Username: %s, Email: %s, Exception: %s, SQLSTATE: %s, Error Code: %s",
                    $employeeId,
                    $username,
                    $email,
                    get_class($e),
                    $e->getCode(),
                    $e->getMessage()
                ));
                error_log("[$timestamp] Automatic user creation skipped because prerequisite validation failed. Reason: " . $e->getMessage());
                return;
            }

            // Step 3: Validate users table schema
            $requiredColumns = ['id', 'username', 'password_hash', 'role_id', 'employee_id', 'full_name', 'email', 'status', 'created_at', 'updated_at'];
            $missingColumns = [];
            
            try {
                foreach ($requiredColumns as $column) {
                    $columnCheckStmt = $db->query("SHOW COLUMNS FROM users LIKE '$column'");
                    if ($columnCheckStmt->fetch() === false) {
                        $missingColumns[] = $column;
                    }
                }
                
                if (!empty($missingColumns)) {
                    error_log(sprintf(
                        "[$timestamp] Automatic user creation skipped: missing columns in users table. Employee ID: %s, Username: %s, Missing columns: %s",
                        $employeeId,
                        $username,
                        implode(', ', $missingColumns)
                    ));
                    return;
                }
            } catch (\Throwable $e) {
                error_log(sprintf(
                    "[$timestamp] Failed validating users table schema. Employee ID: %s, Username: %s, Exception: %s, SQLSTATE: %s, Error Code: %s",
                    $employeeId,
                    $username,
                    get_class($e),
                    $e->getCode(),
                    $e->getMessage()
                ));
                return;
            }

            // Step 4: Validate role exists
            try {
                $roleCheckStmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
                $roleCheckStmt->execute([$roleId]);
                if ($roleCheckStmt->fetch() === false) {
                    error_log(sprintf(
                        "[$timestamp] Automatic user creation skipped: role_id %s not found in roles table. Employee ID: %s, Username: %s",
                        $roleId,
                        $employeeId,
                        $username
                    ));
                    return;
                }
            } catch (\Throwable $e) {
                error_log(sprintf(
                    "[$timestamp] Failed validating role. Employee ID: %s, Username: %s, Role ID: %s, Exception: %s, SQLSTATE: %s, Error Code: %s",
                    $employeeId,
                    $username,
                    $roleId,
                    get_class($e),
                    $e->getCode(),
                    $e->getMessage()
                ));
                return;
            }

            // Step 5: Prepare and execute user insertion
            $userIdForUser = uuid_v4();
            $userSql = 'INSERT INTO users (id, username, password_hash, role_id, employee_id, full_name, email, status, created_at, updated_at)
                        VALUES (:id, :username, :password_hash, :role_id, :employee_id, :full_name, :email, "active", NOW(), NOW())';
            
            $userStmt = $db->prepare($userSql);
            
            if ($userStmt === false) {
                error_log(sprintf(
                    "[$timestamp] Failed to prepare user INSERT statement. Employee ID: %s, Username: %s, SQL: %s",
                    $employeeId,
                    $username,
                    $userSql
                ));
                return;
            }

            $userParams = [
                'id' => $userIdForUser,
                'username' => $username,
                'password_hash' => $passwordHash,
                'role_id' => $roleId,
                'employee_id' => $employeeId,
                'full_name' => $fullName,
                'email' => $email,
            ];

            // Validate all required parameters exist
            $requiredParams = ['id', 'username', 'password_hash', 'role_id', 'employee_id', 'full_name', 'email'];
            foreach ($requiredParams as $param) {
                if (!array_key_exists($param, $userParams)) {
                    error_log(sprintf(
                        "[$timestamp] Missing required parameter for user insertion: %s. Employee ID: %s, Username: %s",
                        $param,
                        $employeeId,
                        $username
                    ));
                    return;
                }
            }

            $userStmt->execute($userParams);
            
            error_log(sprintf(
                "[$timestamp] Automatic user creation succeeded. Employee ID: %s, Username: %s, User ID: %s",
                $employeeId,
                $username,
                $userIdForUser
            ));

        } catch (\Throwable $e) {
            error_log(sprintf(
                "[$timestamp] Automatic user creation failed.\nEmployee ID: %s\nUsername: %s\nEmail: %s\nException: %s\nSQLSTATE: %s\nError Code: %s\nMessage: %s\nTrace: %s",
                $employeeId,
                $username,
                $email,
                get_class($e),
                $e->getCode(),
                $e->getCode(),
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
    }

    /**
     * Find user record by employee ID
     * 
     * @param string $employeeId Employee ID
     * @return array|null User record or null if not found
     */
    private function findUserByEmployeeId(string $employeeId): ?array
    {
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE employee_id = ?');
            $stmt->execute([$employeeId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            error_log(sprintf(
                "[$timestamp] Failed to find user by employee ID. Employee ID: %s, Exception: %s, SQLSTATE: %s, Message: %s",
                $employeeId,
                get_class($e),
                $e->getCode(),
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Update user record for employee with robust error handling
     * 
     * @param string $userId User ID
     * @param array $employeeData Employee data array
     * @param string|null $passwordHash Hashed password (null if not changed)
     * @return void
     */
    private function updateUserForEmployee(string $userId, array $employeeData, ?string $passwordHash): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $username = trim($employeeData['username'] ?? '');
        $email = trim($employeeData['email'] ?? '');
        $fullName = trim($employeeData['first_name'] . ' ' . $employeeData['last_name']);

        error_log("[$timestamp] Automatic user update attempt for user: $userId, username: $username, email: $email");

        try {
            $db = Database::connection();
            
            // Build update SQL dynamically based on whether password is being updated
            $userUpdateSql = 'UPDATE users SET username = :username, full_name = :full_name, email = :email';
            $userUpdateParams = [
                'id' => $userId,
                'username' => $username,
                'full_name' => $fullName,
                'email' => $email,
            ];
            
            if ($passwordHash !== null) {
                $userUpdateSql .= ', password_hash = :password_hash';
                $userUpdateParams['password_hash'] = $passwordHash;
            }
            
            $userUpdateSql .= ', updated_at = NOW() WHERE id = :id';
            
            $userUpdateStmt = $db->prepare($userUpdateSql);
            
            if ($userUpdateStmt === false) {
                error_log(sprintf(
                    "[$timestamp] Failed to prepare user UPDATE statement. User ID: %s, Username: %s, SQL: %s",
                    $userId,
                    $username,
                    $userUpdateSql
                ));
                return;
            }

            $userUpdateStmt->execute($userUpdateParams);
            
            error_log(sprintf(
                "[$timestamp] Automatic user update succeeded. User ID: %s, Username: %s",
                $userId,
                $username
            ));

        } catch (\Throwable $e) {
            error_log(sprintf(
                "[$timestamp] Automatic user update failed.\nUser ID: %s\nUsername: %s\nEmail: %s\nException: %s\nSQLSTATE: %s\nError Code: %s\nMessage: %s\nTrace: %s",
                $userId,
                $username,
                $email,
                get_class($e),
                $e->getCode(),
                $e->getCode(),
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
    }

    /**
     * Delete user record for employee with robust error handling
     * 
     * @param string $userId User ID
     * @param string $employeeId Employee ID (for logging)
     * @return void
     */
    private function deleteUserForEmployee(string $userId, string $employeeId): void
    {
        $timestamp = date('Y-m-d H:i:s');
        
        error_log("[$timestamp] Automatic user deletion attempt for user: $userId, employee: $employeeId");

        try {
            $db = Database::connection();
            $userDeleteSql = 'DELETE FROM users WHERE id = ?';
            $userDeleteStmt = $db->prepare($userDeleteSql);
            
            if ($userDeleteStmt === false) {
                error_log(sprintf(
                    "[$timestamp] Failed to prepare user DELETE statement. User ID: %s, Employee ID: %s, SQL: %s",
                    $userId,
                    $employeeId,
                    $userDeleteSql
                ));
                return;
            }
            
            $userDeleteStmt->execute([$userId]);
            
            error_log(sprintf(
                "[$timestamp] Automatic user deletion succeeded. User ID: %s, Employee ID: %s",
                $userId,
                $employeeId
            ));

        } catch (\Throwable $e) {
            error_log(sprintf(
                "[$timestamp] Automatic user deletion failed.\nUser ID: %s\nEmployee ID: %s\nException: %s\nSQLSTATE: %s\nError Code: %s\nMessage: %s\nTrace: %s",
                $userId,
                $employeeId,
                get_class($e),
                $e->getCode(),
                $e->getCode(),
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
    }
}
