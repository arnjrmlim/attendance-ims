-- ============================================================
-- Upgrade: Add profile_picture to users
--          Add new event types to employee_timeline
-- Safe to run on a database that was initialized from
-- 000_initialize_database.sql (column already exists, ALTER
-- will be skipped due to IF NOT EXISTS equivalent).
-- ============================================================

USE `attendance_db`;

-- ── users.profile_picture ────────────────────────────────────────
-- Add the column only when it is absent
SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'profile_picture'
);

SET @sql := IF(
    @col = 0,
    'ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL
     COMMENT ''Relative path under uploads/avatars/''
     AFTER `must_change_password`',
    'SELECT ''profile_picture column already exists – skipped'' AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── employee_timeline event_type ENUM extension ──────────────────
-- MariaDB does not support IF NOT EXISTS for ALTER TABLE MODIFY on
-- ENUMs directly, so we check whether the new values are present
-- first. The safest approach on MariaDB / MySQL 5.7+ is to replace
-- the whole ENUM definition; the server ignores values already in use.

ALTER TABLE `employee_timeline`
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
  ) NOT NULL;
