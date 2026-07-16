<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class NotificationService
{
    public function notify(string $recipientUserId, string $title, string $message, string $type = 'info'): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (id, recipient_user_id, title, message, type)
             VALUES (:id, :recipient_user_id, :title, :message, :type)'
        );
        $stmt->execute([
            'id' => uuid_v4(),
            'recipient_user_id' => $recipientUserId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }

    public function notifyRoles(array $roles, string $title, string $message, string $type = 'info'): void
    {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT users.id FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug IN ($placeholders) AND users.status = 'active'"
        );
        $stmt->execute($roles);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            $this->notify($userId, $title, $message, $type);
        }
    }

    public function unreadCount(string $userId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function list(string $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM notifications WHERE recipient_user_id = ? ORDER BY created_at DESC LIMIT 100');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
