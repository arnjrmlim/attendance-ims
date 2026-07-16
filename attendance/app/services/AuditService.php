<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuditService
{
    public function log(string $action, string $module, ?string $recordId = null, mixed $previous = null, mixed $new = null): void
    {
        $user = current_user();
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs
            (user_id, username, action, module, record_id, previous_value, new_value, computer_name, ip_address, user_agent)
            VALUES (:user_id, :username, :action, :module, :record_id, :previous_value, :new_value, :computer_name, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'previous_value' => $previous === null ? null : json_encode($previous, JSON_THROW_ON_ERROR),
            'new_value' => $new === null ? null : json_encode($new, JSON_THROW_ON_ERROR),
            'computer_name' => gethostname() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
        ]);
    }
}
