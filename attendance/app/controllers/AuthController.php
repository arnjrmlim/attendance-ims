<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\AuditService;

final class AuthController extends BaseController
{
    public function showLogin(): void
    {
        if (isset($_SESSION['user'])) {
            redirect('dashboard');
        }
        $this->render('auth/login', ['title' => 'Login']);
    }

    public function login(): void
    {
        verify_csrf();

        // Ensure profile_picture column exists before querying users table
        $this->ensureUserColumns();

        $stmt = Database::connection()->prepare(
            'SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.username = ? AND users.status = "active"'
        );
        $stmt->execute([trim((string) ($_POST['username'] ?? ''))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            (new AuditService())->log('LOGIN_FAILED', 'auth', null, null, ['username' => $_POST['username'] ?? null]);
            flash('error', 'Invalid username or password.');
            redirect('login');
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'              => $user['id'],
            'username'        => $user['username'],
            'role_slug'       => $user['role_slug'],
            'role_name'       => $user['role_name'],
            'employee_id'     => $user['employee_id'],
            'full_name'       => $user['full_name'] ?: $user['username'],
            'profile_picture' => $user['profile_picture'] ?? null,
        ];

        Database::connection()->prepare(
            'UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?'
        )->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);

        (new AuditService())->log('LOGIN_SUCCESS', 'auth', $user['id']);

        // Enforce password change on first login
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            flash('error', 'You must change your temporary password before continuing.');
            redirect('profile');
        }

        redirect('dashboard');
    }

    public function logout(): void
    {
        verify_csrf();
        (new AuditService())->log('LOGOUT', 'auth', current_user()['id'] ?? null);
        $_SESSION = [];
        session_destroy();
        redirect('login');
    }

    /**
     * Add any columns that were introduced after the initial schema was deployed.
     * Safe to call on every login — INFORMATION_SCHEMA check prevents repeated ALTERs.
     */
    private function ensureUserColumns(): void
    {
        $db = Database::connection();

        $exists = (int) $db->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND COLUMN_NAME  = 'profile_picture'"
        )->fetchColumn();

        if ($exists === 0) {
            $db->exec(
                "ALTER TABLE `users`
                 ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL
                 COMMENT 'Relative path under uploads/avatars/'
                 AFTER `must_change_password`"
            );
        }
    }
}
