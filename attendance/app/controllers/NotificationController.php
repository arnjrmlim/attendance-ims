<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\NotificationService;

final class NotificationController extends BaseController
{
    public function index(): void
    {
        require_login();
        $this->render('notifications/index', [
            'title' => 'Notifications',
            'rows' => (new NotificationService())->list(current_user()['id']),
        ]);
    }

    public function markRead(): void
    {
        require_login();
        verify_csrf();
        Database::connection()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_user_id = ?')
            ->execute([$_POST['id'] ?? '', current_user()['id']]);
        redirect('notifications');
    }

    public function delete(): void
    {
        require_login();
        verify_csrf();
        Database::connection()->prepare('DELETE FROM notifications WHERE id = ? AND recipient_user_id = ?')
            ->execute([$_POST['id'] ?? '', current_user()['id']]);
        redirect('notifications');
    }
}
