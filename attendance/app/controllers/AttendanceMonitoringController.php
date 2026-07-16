<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AttendanceService;
use App\Services\DirectoryService;

final class AttendanceMonitoringController extends BaseController
{
    public function index(): void
    {
        require_login();
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
}
