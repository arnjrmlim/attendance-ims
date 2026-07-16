<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CorrectionService;
use App\Services\DirectoryService;
use Throwable;

final class AttendanceCorrectionController extends BaseController
{
    public function index(): void
    {
        require_login();
        $ownOnly = has_role('employee');
        $this->render('corrections/index', [
            'title' => 'Attendance Corrections',
            'rows' => (new CorrectionService())->list($_GET, $ownOnly, current_user()['employee_id'] ?? null),
            'employees' => (new DirectoryService())->employees(),
            'types' => CorrectionService::TYPES,
            'ownOnly' => $ownOnly,
        ]);
    }

    public function store(): void
    {
        require_login();
        verify_csrf();
        try {
            $employeeId = has_role('employee') ? current_user()['employee_id'] : ($_POST['employee_id'] ?? '');
            (new CorrectionService())->create([
                'employee_id' => $employeeId,
                'attendance_id' => $_POST['attendance_id'] ?? null,
                'attendance_date' => $_POST['attendance_date'] ?? '',
                'correction_type' => $_POST['correction_type'] ?? '',
                'original_time_in' => $_POST['original_time_in'] ?? null,
                'original_time_out' => $_POST['original_time_out'] ?? null,
                'requested_time_in' => $_POST['requested_time_in'] ?? null,
                'requested_time_out' => $_POST['requested_time_out'] ?? null,
                'reason' => $_POST['reason'] ?? '',
                'attachment' => save_upload('attachment'),
            ]);
            flash('success', 'Correction request submitted.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('corrections');
    }

    public function approve(): void
    {
        $this->review('Approved');
    }

    public function reject(): void
    {
        $this->review('Rejected');
    }

    private function review(string $status): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        try {
            (new CorrectionService())->transition((string) ($_POST['id'] ?? ''), $status, (string) ($_POST['admin_remarks'] ?? ''));
            flash('success', 'Correction request ' . strtolower($status) . '.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('corrections');
    }
}
