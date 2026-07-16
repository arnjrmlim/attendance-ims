-- ============================================================
-- Attendance Management System - Phase 5 Migration
-- Email Scheduling Feature
-- Run after previous migrations
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `attendance_db`;

START TRANSACTION;

-- Add email_schedule setting
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`)
VALUES ('email_schedule', 'manual', 'string', 'email', 'Email schedule: manual, 15th, end_of_month, or both');

-- Add email_timezone setting for local timezone support
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`)
VALUES ('email_timezone', 'UTC', 'string', 'email', 'Timezone for email scheduling (e.g., Asia/Manila, America/New_York)');

-- Add last_email_sent_date to track when last email was sent
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`)
VALUES ('last_email_sent_date', NULL, 'string', 'email', 'Last date when scheduled email was sent (YYYY-MM-DD)');

COMMIT;
