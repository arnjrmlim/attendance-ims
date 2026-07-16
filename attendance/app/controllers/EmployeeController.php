<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DirectoryService;
use App\Services\EmployeeService;
use Throwable;

final class EmployeeController extends BaseController
{
    private EmployeeService $service;
    private DirectoryService $directory;

    public function __construct()
    {
        $this->service = new EmployeeService();
        $this->directory = new DirectoryService();
    }

    /**
     * Display employee list
     */
    public function index(): void
    {
        require_role(['administrator', 'hr']);
        
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 15);
        
        $result = $this->service->list($_GET, $page, $perPage);
        
        $this->render('employees/index', [
            'title' => 'Employee Management',
            'employees' => $result['data'],
            'meta' => $result['meta'],
            'filters' => $_GET,
            'departments' => $this->directory->departments(),
            'branches' => $this->directory->branches(),
            'shifts' => $this->directory->shifts(),
            'statistics' => $this->service->getStatistics(),
        ]);
    }

    /**
     * Display employee creation form
     */
    public function create(): void
    {
        require_role(['administrator', 'hr']);
        
        $this->render('employees/create', [
            'title' => 'Add New Employee',
            'departments' => $this->directory->departments(),
            'branches' => $this->directory->branches(),
            'shifts' => $this->directory->shifts(),
            'supervisors' => $this->service->list([], 1, 1000)['data'],
        ]);
    }

    /**
     * Store new employee
     */
    public function store(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        try {
            $id = $this->service->create($_POST);
            flash('success', 'Employee created successfully.');
            redirect('employees/show?id=' . $id);
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('employees/create');
        }
    }

    /**
     * Display employee profile
     */
    public function show(): void
    {
        require_role(['administrator', 'hr']);
        
        $id = $_GET['id'] ?? '';
        $employee = $this->service->find($id);
        
        if (!$employee) {
            flash('error', 'Employee not found.');
            redirect('employees');
        }

        $this->render('employees/view', [
            'title' => 'Employee Profile - ' . e($employee['full_name']),
            'employee' => $employee,
            'timeline' => $this->service->getTimeline($id),
        ]);
    }

    /**
     * Display employee edit form
     */
    public function edit(): void
    {
        require_role(['administrator', 'hr']);
        
        $id = $_GET['id'] ?? '';
        $employee = $this->service->find($id);
        
        if (!$employee) {
            flash('error', 'Employee not found.');
            redirect('employees');
        }

        $this->render('employees/edit', [
            'title' => 'Edit Employee - ' . e($employee['full_name']),
            'employee' => $employee,
            'departments' => $this->directory->departments(),
            'branches' => $this->directory->branches(),
            'shifts' => $this->directory->shifts(),
            'supervisors' => $this->service->list([], 1, 1000)['data'],
        ]);
    }

    /**
     * Update employee
     */
    public function update(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        try {
            $id = $_POST['id'] ?? '';
            $this->service->update($id, $_POST);
            flash('success', 'Employee updated successfully.');
            redirect('employees/show?id=' . $_POST['id']);
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('employees/edit?id=' . ($_POST['id'] ?? ''));
        }
    }

    /**
     * Activate employee
     */
    public function activate(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        try {
            $this->service->activate($_POST['id'] ?? '');
            flash('success', 'Employee activated successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('employees');
    }

    /**
     * Deactivate employee
     */
    public function deactivate(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        try {
            $this->service->deactivate($_POST['id'] ?? '');
            flash('success', 'Employee deactivated successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('employees');
    }

    /**
     * Change employee status
     */
    public function changeStatus(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        try {
            $this->service->changeStatus($_POST['id'] ?? '', $_POST['status'] ?? '');
            flash('success', 'Employee status changed successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('employees/show?id=' . ($_POST['id'] ?? ''));
    }

    /**
     * Regenerate QR code
     */
    public function regenerateQR(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        try {
            $this->service->regenerateQRCode($_POST['id'] ?? '');
            flash('success', 'QR code regenerated successfully.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('employees/show?id=' . ($_POST['id'] ?? ''));
    }

    /**
     * Bulk actions
     */
    public function bulkAction(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];
        
        if (empty($ids) || !is_array($ids)) {
            flash('error', 'No employees selected.');
            redirect('employees');
        }

        try {
            $count = 0;
            
            switch ($action) {
                case 'activate':
                    $count = $this->service->bulkActivate($ids);
                    flash('success', "{$count} employee(s) activated.");
                    break;
                case 'deactivate':
                    $count = $this->service->bulkDeactivate($ids);
                    flash('success', "{$count} employee(s) deactivated.");
                    break;
                default:
                    flash('error', 'Invalid bulk action.');
            }
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        
        redirect('employees');
    }

    /**
     * Export employees
     */
    public function export(): void
    {
        require_role(['administrator', 'hr']);
        
        try {
            $format = $_GET['format'] ?? 'csv';
            $filepath = $this->service->exportToCSV($_GET);
            
            $fullPath = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filepath;
            
            if (!file_exists($fullPath)) {
                flash('error', 'Export file not found.');
                redirect('employees');
            }

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Content-Length: ' . filesize($fullPath));
            header('Pragma: public');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            
            readfile($fullPath);
            exit;
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('employees');
        }
    }

    /**
     * Display QR code for printing
     */
    public function printQR(): void
    {
        require_role(['administrator', 'hr']);
        
        $id = $_GET['id'] ?? '';
        $employee = $this->service->find($id);
        
        if (!$employee) {
            flash('error', 'Employee not found.');
            redirect('employees');
        }

        $this->render('employees/print-qr', [
            'title' => 'Print QR Code - ' . e($employee['full_name']),
            'employee' => $employee,
        ]);
    }

    /**
     * Display employee import form
     */
    public function importForm(): void
    {
        require_role(['administrator', 'hr']);
        
        $this->render('employees/import', [
            'title' => 'Import Employees',
        ]);
    }

    /**
     * Process employee import
     */
    public function import(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        
        flash('warning', 'Employee import feature will be implemented in a future update.');
        redirect('employees/import');
    }
}
