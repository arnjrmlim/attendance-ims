<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AttendanceService;

final class CalendarController extends BaseController
{
    public function index(): void
    {
        require_login();
        $month = $_GET['month'] ?? date('Y-m');
        $this->render('calendar/index', [
            'title' => 'Attendance Calendar',
            'month' => $month,
            'events' => (new AttendanceService())->calendarEvents($month),
        ]);
    }
}
