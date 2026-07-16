<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DirectoryService;
use App\Services\LeaveService;
use Throwable;

final class LeaveController extends BaseController
{
    public function index(): void
    {
        require_login();
        $ownOnly = has_role('employee');
        $service = new LeaveService();
        $this->render('leaves/index', [
            'title' => 'Leave Management',
            'rows' => $service->list($_GET, $ownOnly, current_user()['employee_id'] ?? null),
            'employees' => (new DirectoryService())->employees(),
            'types' => LeaveService::TYPES,
            'ownOnly' => $ownOnly,
        ]);
    }

    public function store(): void
    {
        require_login();
        verify_csrf();
        try {
            $employeeId = has_role('employee') ? current_user()['employee_id'] : ($_POST['employee_id'] ?? '');
            (new LeaveService())->create([
                'employee_id' => $employeeId,
                'leave_type' => $_POST['leave_type'] ?? '',
                'start_date' => $_POST['start_date'] ?? '',
                'end_date' => $_POST['end_date'] ?? '',
                'reason' => $_POST['reason'] ?? '',
                'attachment' => save_upload('attachment'),
            ]);
            flash('success', 'Leave request submitted.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('leaves');
    }

    public function approve(): void
    {
        $this->review('Approved');
    }

    public function reject(): void
    {
        $this->review('Rejected');
    }

    public function cancel(): void
    {
        require_login();
        verify_csrf();
        (new LeaveService())->cancel((string) ($_POST['id'] ?? ''));
        flash('success', 'Leave request cancelled.');
        redirect('leaves');
    }

    private function review(string $status): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        try {
            (new LeaveService())->transition((string) ($_POST['id'] ?? ''), $status, (string) ($_POST['admin_remarks'] ?? ''));
            flash('success', 'Leave request ' . strtolower($status) . '.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('leaves');
    }
}
