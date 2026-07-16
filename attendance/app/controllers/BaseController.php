<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\NotificationService;

abstract class BaseController extends Controller
{
    protected function render(string $view, array $data = []): void
    {
        // Enforce first-login password change gate.
        // If the user must change their password, only allow the profile page.
        $user = current_user();
        if ($user && (int) ($user['must_change_password'] ?? 0) === 1) {
            // Allow access to the profile view so they can complete the change.
            // Block everything else.
            if ($view !== 'profile/index') {
                flash('error', 'Please change your temporary password before continuing.');
                // Avoid redirect loops – only redirect if not already heading to profile
                if (!str_contains($_SERVER['REQUEST_URI'] ?? '', '/profile')) {
                    redirect('profile');
                }
            }
        }

        $data['unreadNotifications'] = $user
            ? (new NotificationService())->unreadCount($user['id'])
            : 0;

        $this->view($view, $data);
    }
}
