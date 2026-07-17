<?php

/**
 * ManualAttendanceController
 *
 * Classic page routes (render HTML):
 *   GET  /manual-attendance              — index page (shell only; data loaded via AJAX)
 *   GET  /manual-attendance/create       — admin create form page
 *   GET  /manual-attendance/request      — employee self-service request page
 *
 * JSON API routes (all return application/json):
 *   GET  /manual-attendance/api/list          — paginated request list
 *   GET  /manual-attendance/api/admin-list    — paginated admin-entry list
 *   GET  /manual-attendance/api/stats         — summary counts for stat cards
 *   POST /manual-attendance/api/store         — admin create entry
 *   POST /manual-attendance/api/update        — admin update entry
 *   POST /manual-attendance/api/delete        — admin delete entry
 *   POST /manual-attendance/api/request       — employee submit request
 *   POST /manual-attendance/api/approve       — approve single request
 *   POST /manual-attendance/api/reject        — reject single request
 *   POST /manual-attendance/api/bulk-action   — bulk approve / reject
 *
 * Legacy HTML-POST routes kept for non-JS fallback:
 *   POST /manual-attendance/create       — admin store (redirects)
 *   POST /manual-attendance/request      — employee submit (redirects)
 *   POST /manual-attendance/approve      — approve (redirects)
 *   POST /manual-attendance/reject       — reject (redirects)
 *   POST /manual-attendance/bulk-action  — bulk (redirects)
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

    /* ════════════════════════════════════════════════════════════
     * HTML page shells
     * ════════════════════════════════════════════════════════════ */

    public function index(): void
    {
        require_login();
        $dir = new DirectoryService();
        $this->render('manual_attendance/index', [
            'title'     => 'Manual Attendance',
            'isAdminHr' => has_role(['administrator', 'hr']),
            'employees' => has_role(['administrator', 'hr']) ? $dir->employees() : [],
        ]);
    }

    public function create(): void
    {
        require_role(['administrator', 'hr']);
        $dir = new DirectoryService();
        $this->render('manual_attendance/create', [
            'title'     => 'Create Manual Attendance',
            'employees' => $dir->employees(),
        ]);
    }

    public function requestForm(): void
    {
        require_login();
        $this->render('manual_attendance/request', [
            'title' => 'Manual Attendance',
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     * JSON API
     * ════════════════════════════════════════════════════════════ */

    /** GET /manual-attendance/api/stats */
    public function apiStats(): void
    {
        require_login();
        try {
            $user       = current_user();
            $employeeId = has_role('employee') ? ($user['employee_id'] ?? null) : null;
            $this->jsonOk($this->svc->getStats($employeeId));
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** GET /manual-attendance/api/list */
    public function apiList(): void
    {
        require_login();
        try {
            $user = current_user();
            $filters = [
                'date_from'   => $_GET['date_from']   ?? '',
                'date_to'     => $_GET['date_to']     ?? '',
                'status'      => $_GET['status']      ?? '',
                'employee_id' => $_GET['employee_id'] ?? '',
            ];
            // Non-admin/hr users always see only their own records
            if (!has_role(['administrator', 'hr'])) {
                $filters['employee_id'] = $user['employee_id'] ?? '';
            }
            $page    = max(1, (int) ($_GET['page']     ?? 1));
            $perPage = max(5,  (int) ($_GET['per_page'] ?? 20));
            $this->jsonOk($this->svc->listRequests($filters, $page, $perPage));
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** GET /manual-attendance/api/admin-list */
    public function apiAdminList(): void
    {
        require_role(['administrator', 'hr']);
        try {
            $filters = [
                'employee_id' => $_GET['employee_id'] ?? '',
                'date_from'   => $_GET['date_from']   ?? '',
                'date_to'     => $_GET['date_to']     ?? '',
            ];
            $page    = max(1, (int) ($_GET['page']     ?? 1));
            $perPage = max(5,  (int) ($_GET['per_page'] ?? 20));
            $this->jsonOk($this->svc->listAdminEntriesPaged($filters, $page, $perPage));
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** POST /manual-attendance/api/store */
    public function apiStore(): void
    {
        require_role(['administrator', 'hr']);
        $this->verifyAjaxCsrf();
        try {
            $result = $this->svc->adminCreate($_POST, current_user()['id']);
            if (!$result['success']) { $this->jsonError($result['error']); }
            $this->jsonOk([
                'id'       => $result['id'],
                'warnings' => $result['warnings'] ?? [],
                'message'  => 'Manual attendance saved successfully.',
            ]);
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** POST /manual-attendance/api/update */
    public function apiUpdate(): void
    {
        require_role(['administrator', 'hr']);
        $this->verifyAjaxCsrf();
        $id = trim($_POST['id'] ?? '');
        if ($id === '') { $this->jsonError('Record ID is required.'); }
        try {
            $result = $this->svc->adminUpdate($id, $_POST, current_user()['id']);
            if (!$result['success']) { $this->jsonError($result['error']); }
            $this->jsonOk([
                'warnings' => $result['warnings'] ?? [],
                'message'  => 'Manual attendance updated successfully.',
            ]);
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** POST /manual-attendance/api/delete */
    public function apiDelete(): void
    {
        require_role(['administrator', 'hr']);
        $this->verifyAjaxCsrf();
        $id = trim($_POST['id'] ?? '');
        if ($id === '') { $this->jsonError('Record ID is required.'); }
        try {
            $result = $this->svc->adminDelete($id, current_user()['id']);
            if (!$result['success']) { $this->jsonError($result['error']); }
            $this->jsonOk(['message' => 'Manual attendance deleted successfully.']);
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** POST /manual-attendance/api/request */
    public function apiRequest(): void
    {
        require_login();
        $this->verifyAjaxCsrf();
        $user = current_user();
        if (empty($user['employee_id'])) {
            $this->jsonError('Your account is not linked to an employee record.');
        }
        $data                = $_POST;
        $data['employee_id'] = $user['employee_id'];
        try {
            $result = $this->svc->submitRequest($data);
            if (!$result['success']) { $this->jsonError($result['error']); }
            $this->jsonOk([
                'id'       => $result['id'],
                'warnings' => $result['warnings'] ?? [],
                'message'  => 'Your manual attendance has been recorded.',
            ]);
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** POST /manual-attendance/api/approve */
    public function apiApprove(): void
    {
        require_role(['administrator', 'hr']);
        $this->verifyAjaxCsrf();
        $id      = trim($_POST['id'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');
        try {
            $result = $this->svc->approve($id, current_user()['id'], $remarks);
            if (!$result['success']) { $this->jsonError($result['error']); }
            $this->jsonOk(['message' => 'Request approved and attendance updated.']);
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** POST /manual-attendance/api/reject */
    public function apiReject(): void
    {
        require_role(['administrator', 'hr']);
        $this->verifyAjaxCsrf();
        $id      = trim($_POST['id'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');
        if ($remarks === '') { $this->jsonError('A reason for rejection is required.'); }
        try {
            $result = $this->svc->reject($id, current_user()['id'], $remarks);
            if (!$result['success']) { $this->jsonError($result['error']); }
            $this->jsonOk(['message' => 'Request rejected.']);
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /** POST /manual-attendance/api/bulk-action */
    public function apiBulkAction(): void
    {
        require_role(['administrator', 'hr']);
        $this->verifyAjaxCsrf();
        $ids     = array_filter(array_map('trim', (array) ($_POST['ids'] ?? [])));
        $action  = $_POST['action'] ?? '';
        $remarks = trim($_POST['admin_remarks'] ?? '');
        if (empty($ids))                                           { $this->jsonError('No records selected.'); }
        if (!in_array($action, ['approve', 'reject'], true))       { $this->jsonError('Invalid action.'); }
        if ($action === 'reject' && $remarks === '')                { $this->jsonError('A reason for rejection is required.'); }
        try {
            $result = $this->svc->bulkAction($ids, $action, current_user()['id'], $remarks);
            $label  = $action === 'approve' ? 'approved' : 'rejected';
            $this->jsonOk([
                'approved' => $result['approved'],
                'rejected' => $result['rejected'],
                'errors'   => $result['errors'],
                'message'  => "{$result[$label]} request(s) {$label}."
                            . ($result['errors'] > 0 ? " {$result['errors']} error(s)." : ''),
            ]);
        } catch (\Throwable $e) { $this->jsonError($e->getMessage()); }
    }

    /* ════════════════════════════════════════════════════════════
     * Legacy HTML-POST fallbacks (redirect-based, non-JS)
     * ════════════════════════════════════════════════════════════ */

    public function store(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $result = $this->svc->adminCreate($_POST, current_user()['id']);
        if (!$result['success']) {
            flash('error', $result['error']);
            redirect('manual-attendance/create');
        }
        flash('success', empty($result['warnings'])
            ? 'Manual attendance record created successfully.'
            : 'Saved with warnings: ' . implode(' | ', $result['warnings']));
        redirect('manual-attendance');
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

        $data                = $_POST;
        $data['employee_id'] = $user['employee_id'];
        $result              = $this->svc->submitRequest($data);

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

    public function approve(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $result = $this->svc->approve(
            trim($_POST['id'] ?? ''),
            current_user()['id'],
            trim($_POST['admin_remarks'] ?? '')
        );
        flash($result['success'] ? 'success' : 'error',
              $result['success'] ? 'Request approved and attendance updated.' : $result['error']);
        redirect('manual-attendance');
    }

    public function reject(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $result = $this->svc->reject(
            trim($_POST['id'] ?? ''),
            current_user()['id'],
            trim($_POST['admin_remarks'] ?? '')
        );
        flash($result['success'] ? 'success' : 'error',
              $result['success'] ? 'Request rejected.' : $result['error']);
        redirect('manual-attendance');
    }

    public function bulkAction(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $ids    = (array) ($_POST['ids'] ?? []);
        $action = $_POST['action'] ?? '';
        if (empty($ids) || !in_array($action, ['approve', 'reject'], true)) {
            flash('error', 'Invalid bulk action.');
            redirect('manual-attendance');
        }

        $result = $this->svc->bulkAction($ids, $action, current_user()['id'], trim($_POST['admin_remarks'] ?? ''));
        $label  = $action === 'approve' ? 'approved' : 'rejected';
        flash('success', "{$result[$label]} request(s) {$label}."
            . ($result['errors'] > 0 ? " {$result['errors']} error(s)." : ''));
        redirect('manual-attendance');
    }

    /* ════════════════════════════════════════════════════════════
     * Private helpers
     * ════════════════════════════════════════════════════════════ */

    /** Emit a 200 JSON success envelope and exit. */
    private function jsonOk(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, ...$data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Emit a 422 JSON error envelope and exit.
     *  Raw SQL / PDO exceptions are logged and replaced with a generic message. */
    private function jsonError(string $message, int $status = 422): never
    {
        $safe = $this->sanitiseError($message);
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $safe], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Replace raw SQL / PDO exception strings with a safe user-facing message.
     * The original message is written to the PHP error log for diagnostics.
     */
    private function sanitiseError(string $message): string
    {
        $isRaw = str_starts_with($message, 'SQLSTATE')
              || str_contains($message, 'Unknown column')
              || str_contains($message, 'Table ')
              || str_contains($message, 'Duplicate entry')
              || str_contains($message, 'PDOException')
              || str_contains($message, 'errno=');

        if ($isRaw) {
            error_log('[ManualAttendanceController] Database error: ' . $message);
            return 'Attendance could not be processed. Please contact the system administrator.';
        }

        return $message;
    }

    /**
     * Verify CSRF token sent either as POST field _csrf or as the
     * X-CSRF-Token request header (used by fetch() calls).
     */
    private function verifyAjaxCsrf(): void
    {
        $token = $_POST['_csrf']
              ?? $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? '';
        if (!hash_equals($_SESSION['_csrf'] ?? '', (string) $token)) {
            $this->jsonError('Invalid or expired security token. Reload the page and try again.', 403);
        }
    }
}
