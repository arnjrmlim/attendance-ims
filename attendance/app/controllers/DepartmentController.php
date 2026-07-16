<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DepartmentService;
use App\Services\BranchService;
use Throwable;

final class DepartmentController extends BaseController
{
    private DepartmentService $service;
    private BranchService $branchService;

    public function __construct()
    {
        $this->service = new DepartmentService();
        $this->branchService = new BranchService();
    }

    /**
     * Display department list
     */
    public function index(): void
    {
        require_role(['administrator']);
        
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 15);
        
        $result = $this->service->list($_GET, $page, $perPage);
        
        $this->render('departments/index', [
            'title' => 'Department Management',
            'departments' => $result['data'],
            'meta' => $result,
            'filters' => $_GET,
            'branches' => $this->branchService->getActiveBranches(),
        ]);
    }

    /**
     * Display department creation form
     */
    public function create(): void
    {
        require_role(['administrator']);
        
        $this->render('departments/create', [
            'title' => 'Add New Department',
            'branches' => $this->branchService->getActiveBranches(),
        ]);
    }

    /**
     * Store new department
     */
    public function store(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        try {
            $this->service->create($_POST);
            flash('success', 'Department created successfully.');
            redirect('departments');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('departments/create');
        }
    }

    /**
     * Display department details
     */
    public function show(): void
    {
        require_role(['administrator']);
        
        $id = $_GET['id'] ?? '';
        $department = $this->service->find($id);
        
        if (!$department) {
            flash('error', 'Department not found.');
            redirect('departments');
        }
        
        $this->render('departments/show', [
            'title' => 'Department Details',
            'department' => $department,
        ]);
    }

    /**
     * Display department edit form
     */
    public function edit(): void
    {
        require_role(['administrator']);
        
        $id = $_GET['id'] ?? '';
        $department = $this->service->find($id);
        
        if (!$department) {
            flash('error', 'Department not found.');
            redirect('departments');
        }
        
        $this->render('departments/edit', [
            'title' => 'Edit Department',
            'department' => $department,
            'branches' => $this->branchService->getActiveBranches(),
        ]);
    }

    /**
     * Update department
     */
    public function update(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        $id = $_POST['id'] ?? '';
        
        try {
            $this->service->update($id, $_POST);
            flash('success', 'Department updated successfully.');
            redirect('departments/show?id=' . $id);
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('departments/edit?id=' . $id);
        }
    }

    /**
     * Activate department
     */
    public function activate(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        $id = $_POST['id'] ?? '';
        
        try {
            $this->service->setStatus($id, 'active');
            flash('success', 'Department activated successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        
        redirect('departments');
    }

    /**
     * Deactivate department
     */
    public function deactivate(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        $id = $_POST['id'] ?? '';
        
        try {
            $this->service->setStatus($id, 'inactive');
            flash('success', 'Department deactivated successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        
        redirect('departments');
    }
}
