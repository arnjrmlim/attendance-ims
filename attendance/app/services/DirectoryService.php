<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class DirectoryService
{
    private BranchService $branchService;
    private DepartmentService $departmentService;

    public function __construct()
    {
        $this->branchService = new BranchService();
        $this->departmentService = new DepartmentService();
    }

    public function employees(): array
    {
        return Database::connection()
            ->query("SELECT id, employee_number, CONCAT(first_name, ' ', last_name) AS name FROM employees WHERE status = 'active' ORDER BY last_name, first_name")
            ->fetchAll();
    }

    public function departments(): array
    {
        return $this->departmentService->getActiveDepartments();
    }

    public function branches(): array
    {
        return $this->branchService->getActiveBranches();
    }

    public function shifts(): array
    {
        return Database::connection()->query(
            "SELECT id, name, time_in, time_out,
                    lunch_break_start, lunch_break_end,
                    grace_period_minutes, required_hours,
                    is_default
               FROM shifts
              WHERE status = 'active'
              ORDER BY is_default DESC, name ASC"
        )->fetchAll();
    }

    public function roles(): array
    {
        return Database::connection()->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
    }
}
