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
        $this->render('attendance/monitoring', [
            'title' => 'Attendance Monitoring',
            'rows' => (new AttendanceService())->monitor($_GET),
            'employees' => $directory->employees(),
            'departments' => $directory->departments(),
            'branches' => $directory->branches(),
            'shifts' => $directory->shifts(),
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
