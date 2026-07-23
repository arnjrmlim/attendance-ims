<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AttendanceService;
use App\Services\DirectoryService;

final class AttendanceMonitoringController extends BaseController
{
    public function index(): void
    {
        require_role(['administrator', 'hr']);
        $directory = new DirectoryService();
        
        // Apply default date range if not provided by user
        $filters = $_GET;
        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $filters['start_date'] = date('Y-m-01'); // First day of current month
            $filters['end_date'] = date('Y-m-d');     // Today
        }
        
        $this->render('attendance/monitoring', [
            'title' => 'Attendance Monitoring',
            'rows' => (new AttendanceService())->monitor($filters),
            'departments' => $directory->departments(),
            'branches' => $directory->branches(),
            'shifts' => $directory->shifts(),
            'filters' => $filters,
        ]);
    }

    /** GET /attendance-monitoring/api/data - Real-time monitoring data */
    public function apiData(): void
    {
        require_role(['administrator', 'hr']);
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = (new AttendanceService())->monitor($_GET);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
