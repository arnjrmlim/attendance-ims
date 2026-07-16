<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\NotificationService;

abstract class BaseController extends Controller
{
    protected function render(string $view, array $data = []): void
    {
        $user = current_user();
        $data['unreadNotifications'] = $user ? (new NotificationService())->unreadCount($user['id']) : 0;
        $this->view($view, $data);
    }
}
