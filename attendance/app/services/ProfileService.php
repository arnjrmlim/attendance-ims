<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * ProfileService
 *
 * Handles all operations for a user's own profile:
 *   - Loading their full profile data
 *   - Changing their password
 *   - Uploading / replacing / removing their profile picture
 */
final class ProfileService
{
    // Allowed MIME types for profile pictures
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // Maximum profile picture size (2 MB)
    private const MAX_BYTES = 2 * 1024 * 1024;

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    /**
     * Load the full profile for the authenticated user.
     * Returns user row joined with employee row (if linked).
     */
    public function findByUserId(string $userId): ?array
    {
        // Ensure the profile_picture column exists (self-healing for existing DBs)
        $this->ensureProfilePictureColumn();

        $stmt = Database::connection()->prepare(
            'SELECT
                u.id              AS user_id,
                u.username,
                u.email           AS user_email,
                u.full_name,
                u.status          AS user_status,
                u.role_id,
                u.employee_id,
                u.last_login,
                u.last_login_ip,
                u.password_changed_at,
                u.must_change_password,
                u.profile_picture,
                r.name            AS role_name,
                r.slug            AS role_slug,
                e.employee_number,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.suffix,
                e.photo           AS employee_photo,
                e.position,
                e.department_id,
                e.branch_id,
                e.contact_number,
                e.email           AS employee_email,
                d.name            AS department_name,
                b.name            AS branch_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN employees e ON e.id = u.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Add profile_picture and ensure employee_timeline ENUM contains the new
     * event types required by this service.  Safe to call on every request —
     * the INFORMATION_SCHEMA check means it only runs the ALTER once.
     */
    private function ensureProfilePictureColumn(): void
    {
        $db = Database::connection();

        // ── users.profile_picture ────────────────────────────────────
        $row = $db->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND COLUMN_NAME  = 'profile_picture'"
        )->fetchColumn();

        if ((int) $row === 0) {
            $db->exec(
                "ALTER TABLE `users`
                 ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL
                 COMMENT 'Relative path under uploads/avatars/'
                 AFTER `must_change_password`"
            );
        }

        // ── employee_timeline ENUM extension ─────────────────────────
        // Replace the whole ENUM so all new event types are present.
        // MariaDB / MySQL silently ignores values that are already in the ENUM,
        // so this is safe to run repeatedly.
        $db->exec(
            "ALTER TABLE `employee_timeline`
             MODIFY COLUMN `event_type` ENUM(
               'employee_created',
               'employee_updated',
               'employee_archived',
               'employee_restored',
               'employee_activated',
               'employee_deactivated',
               'account_created',
               'account_updated',
               'account_deleted',
               'role_changed',
               'password_changed',
               'profile_picture_updated',
               'status_changed',
               'department_changed',
               'branch_changed',
               'shift_changed',
               'position_changed',
               'photo_updated',
               'qr_code_regenerated',
               'imported',
               'attendance_milestone'
             ) NOT NULL"
        );
    }

    // ----------------------------------------------------------------
    // Change Password
    // ----------------------------------------------------------------

    /**
     * Change the authenticated user's password.
     *
     * Rules:
     *   - currentPassword must verify against stored hash
     *   - newPassword must not be empty
     *   - newPassword must not equal currentPassword
     *   - confirmPassword must equal newPassword
     *
     * After success:
     *   - Updates password_hash and password_changed_at
     *   - Sets must_change_password = 0
     *   - Regenerates session ID
     *   - Logs audit entry + employee timeline entry
     *
     * @throws InvalidArgumentException on validation failure
     */
    public function changePassword(string $userId, array $data): void
    {
        $current = trim((string) ($data['current_password'] ?? ''));
        $new     = (string) ($data['new_password'] ?? '');
        $confirm = (string) ($data['confirm_password'] ?? '');

        // Load current hash
        $stmt = Database::connection()->prepare(
            'SELECT password_hash, employee_id FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('User account not found.');
        }

        // Validate current password
        if (!password_verify($current, $row['password_hash'])) {
            throw new InvalidArgumentException('Current password is incorrect.');
        }

        // Validate new password not empty
        if ($new === '') {
            throw new InvalidArgumentException('Please enter a new password.');
        }

        // Validate new != current
        if (password_verify($new, $row['password_hash'])) {
            throw new InvalidArgumentException('New password cannot be the same as your current password.');
        }

        // Validate confirmation matches
        if ($new !== $confirm) {
            throw new InvalidArgumentException('The new password and confirmation password do not match.');
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);

        Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :hash,
                 password_changed_at = NOW(),
                 must_change_password = 0,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute(['hash' => $newHash, 'id' => $userId]);

        // Regenerate session ID for security, keep user logged in
        session_regenerate_id(true);

        // Audit + employee timeline
        $employeeId = $row['employee_id'] ?? null;
        (new AuditService())->log(
            'PASSWORD_CHANGED',
            'users',
            $userId,
            null,
            ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]
        );

        if ($employeeId) {
            $this->addTimelineEntry(
                $employeeId,
                'password_changed',
                null,
                null,
                'Password changed by user',
                $userId
            );
        }
    }

    // ----------------------------------------------------------------
    // Profile Picture
    // ----------------------------------------------------------------

    /**
     * Upload or replace the user's profile picture.
     *
     * Accepts JPG, JPEG, PNG, WEBP up to 2 MB.
     * Stores file under uploads/avatars/{uuid}.{ext}
     * Deletes the old picture if one existed.
     *
     * @throws InvalidArgumentException on validation failure
     * @throws RuntimeException on file system failure
     */
    public function uploadProfilePicture(string $userId): string
    {
        if (
            empty($_FILES['profile_picture']['name']) ||
            ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            throw new InvalidArgumentException('No image file was selected.');
        }

        $error = $_FILES['profile_picture']['error'] ?? UPLOAD_ERR_OK;
        if ($error !== UPLOAD_ERR_OK) {
            $msgs = [
                UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the maximum allowed size.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
            ];
            throw new RuntimeException($msgs[$error] ?? 'File upload failed.');
        }

        if ($_FILES['profile_picture']['size'] > self::MAX_BYTES) {
            throw new InvalidArgumentException('Profile picture must not exceed 2 MB.');
        }

        // Verify MIME type
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['profile_picture']['tmp_name']);
        finfo_close($finfo);

        if (!array_key_exists($mimeType, self::ALLOWED_MIME)) {
            throw new InvalidArgumentException('Profile picture must be JPG, PNG, or WEBP.');
        }

        $ext       = self::ALLOWED_MIME[$mimeType];
        $uploadDir = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR)
                   . DIRECTORY_SEPARATOR . 'avatars';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Could not create upload directory.');
        }

        $filename = uuid_v4() . '.' . $ext;
        $target   = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
            throw new RuntimeException('Unable to store profile picture.');
        }

        $relativePath = 'avatars/' . $filename;

        // Delete old picture
        $stmt = Database::connection()->prepare('SELECT profile_picture, employee_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if ($row && !empty($row['profile_picture'])) {
            $this->deleteFile($row['profile_picture']);
        }

        // Persist
        Database::connection()->prepare(
            'UPDATE users SET profile_picture = :path, updated_at = NOW() WHERE id = :id'
        )->execute(['path' => $relativePath, 'id' => $userId]);

        // Audit + timeline
        (new AuditService())->log(
            'PROFILE_PICTURE_UPDATED',
            'users',
            $userId,
            $row['profile_picture'] ?? null,
            $relativePath
        );

        $employeeId = $row['employee_id'] ?? null;
        if ($employeeId) {
            $this->addTimelineEntry(
                $employeeId,
                'profile_picture_updated',
                $row['profile_picture'] ?? null,
                $relativePath,
                'Profile picture updated',
                $userId
            );
        }

        return $relativePath;
    }

    /**
     * Remove the user's profile picture.
     *
     * Deletes the file and sets profile_picture = NULL.
     */
    public function removeProfilePicture(string $userId): void
    {
        $stmt = Database::connection()->prepare('SELECT profile_picture, employee_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['profile_picture'])) {
            return; // nothing to remove
        }

        $this->deleteFile($row['profile_picture']);

        Database::connection()->prepare(
            'UPDATE users SET profile_picture = NULL, updated_at = NOW() WHERE id = :id'
        )->execute(['id' => $userId]);

        (new AuditService())->log(
            'PROFILE_PICTURE_REMOVED',
            'users',
            $userId,
            $row['profile_picture'],
            null
        );

        $employeeId = $row['employee_id'] ?? null;
        if ($employeeId) {
            $this->addTimelineEntry(
                $employeeId,
                'profile_picture_updated',
                $row['profile_picture'],
                null,
                'Profile picture removed',
                $userId
            );
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function deleteFile(string $relativePath): void
    {
        $full = rtrim((string) config('upload_path'), DIRECTORY_SEPARATOR)
              . DIRECTORY_SEPARATOR
              . ltrim($relativePath, '/\\');
        if (file_exists($full)) {
            @unlink($full);
        }
    }

    private function addTimelineEntry(
        string  $employeeId,
        string  $eventType,
        ?string $previousValue,
        ?string $newValue,
        ?string $description,
        ?string $createdBy
    ): void {
        Database::connection()->prepare(
            'INSERT INTO employee_timeline
                (employee_id, event_type, previous_value, new_value, description, created_by, created_at)
             VALUES
                (:employee_id, :event_type, :previous_value, :new_value, :description, :created_by, NOW())'
        )->execute([
            'employee_id'    => $employeeId,
            'event_type'     => $eventType,
            'previous_value' => $previousValue,
            'new_value'      => $newValue,
            'description'    => $description,
            'created_by'     => $createdBy,
        ]);
    }
}
