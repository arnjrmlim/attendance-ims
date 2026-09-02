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

    public function cancel(): void
    {
        require_login();
        verify_csrf();
        try {
            (new CorrectionService())->cancel((string) ($_POST['id'] ?? ''));
            flash('success', 'Correction request cancelled.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('corrections');
    }

    public function attachment(): void
    {
        require_login();

        $request = (new CorrectionService())->find((string) ($_GET['id'] ?? ''));
        $this->authorizeAttachment($request);
        $this->streamAttachment($request['attachment'] ?? null);
    }

    private function review(string $status): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        try {
            (new CorrectionService())->transition((string) ($_POST['id'] ?? ''), $status, $status === 'Approved' ? '' : (string) ($_POST['admin_remarks'] ?? ''));
            flash('success', 'Correction request ' . strtolower($status) . '.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('corrections');
    }

    private function streamAttachment(?string $attachment): never
    {
        $filename = basename((string) $attachment);
        $path = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if ($filename === '' || !is_file($path)) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function authorizeAttachment(?array $request): void
    {
        $user = current_user();
        $canViewAll = has_role(['administrator', 'hr']);
        if (!$request || (!$canViewAll && ($user['employee_id'] ?? null) !== $request['employee_id'])) {
            http_response_code(403);
            exit('You do not have permission to view this attachment.');
        }
    }
}
