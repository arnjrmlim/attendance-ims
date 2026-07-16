<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BranchService;
use Throwable;

final class BranchController extends BaseController
{
    private BranchService $service;

    public function __construct()
    {
        $this->service = new BranchService();
    }

    /**
     * Display branch list
     */
    public function index(): void
    {
        require_role(['administrator']);
        
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 15);
        
        $result = $this->service->list($_GET, $page, $perPage);
        
        $this->render('branches/index', [
            'title' => 'Branch Management',
            'branches' => $result['data'],
            'meta' => $result,
            'filters' => $_GET,
        ]);
    }

    /**
     * Display branch creation form
     */
    public function create(): void
    {
        require_role(['administrator']);
        
        $this->render('branches/create', [
            'title' => 'Add New Branch',
        ]);
    }

    /**
     * Store new branch
     */
    public function store(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        try {
            $this->service->create($_POST);
            flash('success', 'Branch created successfully.');
            redirect('branches');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('branches/create');
        }
    }

    /**
     * Display branch details
     */
    public function show(): void
    {
        require_role(['administrator']);
        
        $id = $_GET['id'] ?? '';
        $branch = $this->service->find($id);
        
        if (!$branch) {
            flash('error', 'Branch not found.');
            redirect('branches');
        }
        
        $this->render('branches/show', [
            'title' => 'Branch Details',
            'branch' => $branch,
        ]);
    }

    /**
     * Display branch edit form
     */
    public function edit(): void
    {
        require_role(['administrator']);
        
        $id = $_GET['id'] ?? '';
        $branch = $this->service->find($id);
        
        if (!$branch) {
            flash('error', 'Branch not found.');
            redirect('branches');
        }
        
        $this->render('branches/edit', [
            'title' => 'Edit Branch',
            'branch' => $branch,
        ]);
    }

    /**
     * Update branch
     */
    public function update(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        $id = $_POST['id'] ?? '';
        
        try {
            $this->service->update($id, $_POST);
            flash('success', 'Branch updated successfully.');
            redirect('branches/show?id=' . $id);
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('branches/edit?id=' . $id);
        }
    }

    /**
     * Activate branch
     */
    public function activate(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        $id = $_POST['id'] ?? '';
        
        try {
            $this->service->setStatus($id, 'active');
            flash('success', 'Branch activated successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        
        redirect('branches');
    }

    /**
     * Deactivate branch
     */
    public function deactivate(): void
    {
        require_role(['administrator']);
        verify_csrf();
        
        $id = $_POST['id'] ?? '';
        
        try {
            $this->service->setStatus($id, 'inactive');
            flash('success', 'Branch deactivated successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        
        redirect('branches');
    }
}
