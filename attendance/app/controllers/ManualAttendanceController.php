<?php

/**
 * ManualAttendanceController
 *
 * Routes:
 *   GET  /manual-attendance              — Admin approval queue + employee request list
 *   GET  /manual-attendance/create       — Admin: create manual attendance form
 *   POST /manual-attendance/create       — Admin: store manual attendance
 *   GET  /manual-attendance/request      — Employee: request form
 *   POST /manual-attendance/request      — Employee: submit request
 *   POST /manual-attendance/approve      — Admin: approve single
 *   POST /manual-attendance/reject       — Admin: reject single
 *   POST /manual-attendance/bulk-action  — Admin: bulk approve/reject
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DirectoryService;
use App\Services\ManualAttendanceService;

final class ManualAttendanceController extends BaseController
{
    private ManualAttendanceService $svc;

    public function __construct()
    {
        $this->svc = new ManualAttendanceService();
    }

    /* ── Approval Queue (Admin/HR) ─────────────────────────── */

    public function index(): void
    {
        require_login();
        $user = current_user();

        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to']   ?? '',
        ];

        // Employees only see their own
        if (has_role('employee')) {
            $filters['employee_id'] = $user['employee_id'];
        }

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $data    = $this->svc->listRequests($filters, $page);
        $adminEntries = [];
        if (has_role(['administrator', 'hr'])) {
            $adminEntries = $this->svc->listAdminEntries([], 1, 10)['rows'];
        }

        $this->render('manual_attendance/index', [
            'title'        => 'Manual Attendance',
            'rows'         => $data['rows'],
            'total'        => $data['total'],
            'page'         => $data['page'],
            'per_page'     => $data['per_page'],
            'filters'      => $filters,
            'adminEntries' => $adminEntries,
        ]);
    }

    /* ── Admin: Create Manual Attendance ───────────────────── */

    public function create(): void
    {
        require_role(['administrator', 'hr']);
        $dir = new DirectoryService();
        $this->render('manual_attendance/create', [
            'title'     => 'Create Manual Attendance',
            'employees' => $dir->employees(),
        ]);
    }

    public function store(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $user   = current_user();
        $result = $this->svc->adminCreate($_POST, $user['id']);

        if (!$result['success']) {
            flash('error', $result['error']);
            redirect('manual-attendance/create');
        }

        if (!empty($result['warnings'])) {
            flash('success', 'Manual attendance saved with warnings: ' . implode(' | ', $result['warnings']));
        } else {
            flash('success', 'Manual attendance record created successfully.');
        }

        redirect('manual-attendance');
    }

    /* ── Employee: Self-Service Request ────────────────────── */

    public function requestForm(): void
    {
        require_login();
        $this->render('manual_attendance/request', [
            'title' => 'Request Manual Attendance',
        ]);
    }

    public function submitRequest(): void
    {
        require_login();
        verify_csrf();

        $user = current_user();
        if (empty($user['employee_id'])) {
            flash('error', 'Your account is not linked to an employee record.');
            redirect('manual-attendance/request');
        }

        $data               = $_POST;
        $data['employee_id'] = $user['employee_id'];
        $result             = $this->svc->submitRequest($data);

        if (!$result['success']) {
            flash('error', $result['error']);
            redirect('manual-attendance/request');
        }

        $msg = 'Your manual attendance has been recorded.';
        if (!empty($result['warnings'])) {
            $msg .= ' Note: ' . implode(' | ', $result['warnings']);
        }
        flash('success', $msg);
        redirect('manual-attendance');
    }

    /* ── Approve ───────────────────────────────────────────── */

    public function approve(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id      = trim($_POST['id'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');
        $result  = $this->svc->approve($id, current_user()['id'], $remarks);

        if (!$result['success']) {
            flash('error', $result['error']);
        } else {
            flash('success', 'Request approved and attendance updated.');
        }
        redirect('manual-attendance');
    }

    /* ── Reject ─────────────────────────────────────────────── */

    public function reject(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id      = trim($_POST['id'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');
        $result  = $this->svc->reject($id, current_user()['id'], $remarks);

        if (!$result['success']) {
            flash('error', $result['error']);
        } else {
            flash('success', 'Request rejected.');
        }
        redirect('manual-attendance');
    }

    /* ── Bulk Action ────────────────────────────────────────── */

    public function bulkAction(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $ids     = (array) ($_POST['ids'] ?? []);
        $action  = $_POST['action'] ?? '';
        $remarks = trim($_POST['admin_remarks'] ?? '');

        if (empty($ids) || !in_array($action, ['approve', 'reject'], true)) {
            flash('error', 'Invalid bulk action.');
            redirect('manual-attendance');
        }

        $result = $this->svc->bulkAction($ids, $action, current_user()['id'], $remarks);
        $label  = $action === 'approve' ? 'approved' : 'rejected';
        flash('success', "{$result[$label]} request(s) {$label}." . ($result['errors'] > 0 ? " {$result['errors']} error(s)." : ''));
        redirect('manual-attendance');
    }
}
