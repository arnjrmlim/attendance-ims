<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AnnouncementService;
use App\Services\DashboardService;
use App\Services\NotificationService;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        require_login();
        $user    = current_user();
        $service = new DashboardService();
        $stats   = has_role('employee')
            ? $service->stats($user['employee_id'])
            : $service->stats();

        $notifications = (new NotificationService())->list($user['id']);

        // Visible announcements for this user
        $employeeId    = $user['employee_id'] ?? null;
        $departmentId  = null;
        if ($employeeId) {
            $stmt = \App\Core\Database::connection()->prepare(
                'SELECT department_id FROM employees WHERE id = ?'
            );
            $stmt->execute([$employeeId]);
            $row          = $stmt->fetch();
            $departmentId = $row['department_id'] ?? null;
        }
        $announcements = (new AnnouncementService())->visible($user['id'], $employeeId, $departmentId);

        $this->render('dashboard/index', [
            'title'         => 'Dashboard',
            'stats'         => $stats,
            'notifications' => array_slice($notifications, 0, 5),
            'announcements' => $announcements,
        ]);
    }
}
