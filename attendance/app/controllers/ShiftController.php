<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ShiftService;
use Throwable;

/**
 * ShiftController
 *
 * Handles all HTTP actions for the Shift Management module.
 * All write operations are restricted to Administrator and HR roles.
 * Read-only listing is also restricted (employees must not access this module).
 */
final class ShiftController extends BaseController
{
    private ShiftService $service;

    public function __construct()
    {
        $this->service = new ShiftService();
    }

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    /**
     * GET /shifts — paginated shift list with search/filter
     */
    public function index(): void
    {
        require_role(['administrator', 'hr']);

        $page    = max(1, (int) ($_GET['page']     ?? 1));
        $perPage = max(5,  (int) ($_GET['per_page'] ?? 15));

        $result = $this->service->list($_GET, $page, $perPage);

        $this->render('shifts/index', [
            'title'      => 'Shift Management',
            'shifts'     => $result['data'],
            'meta'       => $result,
            'filters'    => $_GET,
            'statistics' => $this->service->getStatistics(),
        ]);
    }

    /**
     * GET /shifts/employees — JSON list of employees for a given shift
     * Used by the "View Assigned Employees" modal (AJAX).
     */
    public function assignedEmployees(): void
    {
        require_role(['administrator', 'hr']);

        $shiftId = $_GET['shift_id'] ?? '';
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        if (!$shiftId) {
            http_response_code(400);
            echo json_encode(['error' => 'shift_id is required']);
            exit;
        }

        $shift  = $this->service->find($shiftId);
        if (!$shift) {
            http_response_code(404);
            echo json_encode(['error' => 'Shift not found']);
            exit;
        }

        $result = $this->service->getAssignedEmployees($shiftId, $page);

        header('Content-Type: application/json');
        echo json_encode([
            'shift'    => $shift,
            'employees'=> $result['data'],
            'meta'     => $result,
        ]);
        exit;
    }

    // ----------------------------------------------------------------
    // Create
    // ----------------------------------------------------------------

    /**
     * GET /shifts/create — show the create form
     */
    public function create(): void
    {
        require_role(['administrator', 'hr']);

        $this->render('shifts/form', [
            'title'  => 'Add New Shift',
            'shift'  => null,
            'action' => url('shifts/store'),
        ]);
    }

    /**
     * POST /shifts/store — persist a new shift
     */
    public function store(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        try {
            $id = $this->service->create($_POST);
            flash('success', 'Shift created successfully.');
            redirect('shifts/show?id=' . $id);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('shifts/create');
        }
    }

    // ----------------------------------------------------------------
    // Show
    // ----------------------------------------------------------------

    /**
     * GET /shifts/show — shift detail / employees tab
     */
    public function show(): void
    {
        require_role(['administrator', 'hr']);

        $id    = $_GET['id'] ?? '';
        $shift = $this->service->find($id);

        if (!$shift) {
            flash('error', 'Shift not found.');
            redirect('shifts');
        }

        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $employees = $this->service->getAssignedEmployees($id, $page);

        $this->render('shifts/show', [
            'title'     => 'Shift Details — ' . $shift['name'],
            'shift'     => $shift,
            'employees' => $employees['data'],
            'empMeta'   => $employees,
        ]);
    }

    // ----------------------------------------------------------------
    // Edit / Update
    // ----------------------------------------------------------------

    /**
     * GET /shifts/edit — pre-populated edit form
     */
    public function edit(): void
    {
        require_role(['administrator', 'hr']);

        $id    = $_GET['id'] ?? '';
        $shift = $this->service->find($id);

        if (!$shift) {
            flash('error', 'Shift not found.');
            redirect('shifts');
        }

        $this->render('shifts/form', [
            'title'  => 'Edit Shift — ' . $shift['name'],
            'shift'  => $shift,
            'action' => url('shifts/update'),
        ]);
    }

    /**
     * POST /shifts/update — apply edits
     */
    public function update(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id = $_POST['id'] ?? '';

        try {
            $this->service->update($id, $_POST);
            flash('success', 'Shift updated successfully.');
            redirect('shifts/show?id=' . $id);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('shifts/edit?id=' . $id);
        }
    }

    // ----------------------------------------------------------------
    // Delete
    // ----------------------------------------------------------------

    /**
     * POST /shifts/delete — delete with optional employee reassignment
     */
    public function delete(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id             = $_POST['id'] ?? '';
        $replacementId  = $_POST['replacement_shift_id'] ?? null;
        $replacementId  = ($replacementId === '' || $replacementId === null) ? null : $replacementId;

        try {
            $this->service->delete($id, $replacementId);
            flash('success', 'Shift deleted successfully.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('shifts');
    }

    // ----------------------------------------------------------------
    // Status toggles
    // ----------------------------------------------------------------

    /**
     * POST /shifts/activate
     */
    public function activate(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id = $_POST['id'] ?? '';

        try {
            $this->service->setStatus($id, 'active');
            flash('success', 'Shift activated.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('shifts');
    }

    /**
     * POST /shifts/deactivate
     */
    public function deactivate(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id = $_POST['id'] ?? '';

        try {
            $this->service->setStatus($id, 'inactive');
            flash('success', 'Shift deactivated.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('shifts');
    }

    /**
     * POST /shifts/set-default — mark a shift as the system default
     */
    public function setDefault(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id   = $_POST['id'] ?? '';
        $data = ['is_default' => 1];

        try {
            // Load existing, merge, re-save
            $shift = $this->service->find($id);
            if (!$shift) {
                throw new \InvalidArgumentException('Shift not found.');
            }
            $this->service->update($id, array_merge($shift, $data));
            flash('success', 'Default shift updated.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('shifts');
    }
}
