-- ============================================================
-- Attendance Management System - Phase 3 Migration
-- Run after phase2.sql
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `attendance_db`;

START TRANSACTION;

-- ============================================================
-- Expand settings table (already exists in schema.sql)
-- Ensure all Phase 3 setting keys exist
-- ============================================================

-- Phase 3 system settings
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`) VALUES
-- Company
('company_name',        'My Company',           'string',  'company',  'Company name'),
('company_branch',      'Main Branch',          'string',  'company',  'Branch name'),
('company_logo',        '',                     'string',  'company',  'Logo path relative to public/'),
-- Attendance rules
('grace_period_minutes','15',                   'integer', 'attendance','Grace period in minutes before marking late'),
('work_hours_per_day',  '8',                    'decimal', 'attendance','Standard working hours per day'),
('overtime_threshold',  '30',                   'integer', 'attendance','Minutes after shift end before counting overtime'),
('late_deduction',      '1',                    'boolean', 'attendance','Deduct late minutes from salary'),
('allowed_time_in_from','06:00',                'time',    'attendance','Earliest allowed time-in'),
('allowed_time_in_to',  '10:00',                'time',    'attendance','Latest allowed time-in (warn if exceeded)'),
('method_pin',          '1',                    'boolean', 'attendance','Enable PIN attendance method'),
('method_qr',           '1',                    'boolean', 'attendance','Enable QR Code attendance method'),
('method_rfid',         '1',                    'boolean', 'attendance','Enable RFID attendance method'),
('method_manual',       '1',                    'boolean', 'attendance','Enable Manual attendance method'),
-- Date / time
('timezone',            'Asia/Manila',          'string',  'system',   'System timezone'),
('date_format',         'M d, Y',               'string',  'system',   'PHP date format for display'),
('time_format',         'h:i A',                'string',  'system',   'PHP time format for display'),
-- Security
('session_timeout',     '120',                  'integer', 'security', 'Session idle timeout in minutes'),
('max_login_attempts',  '5',                    'integer', 'security', 'Max failed logins before lockout'),
('lockout_minutes',     '30',                   'integer', 'security', 'Account lockout duration in minutes'),
-- Backup
('backup_enabled',      '1',                    'boolean', 'backup',   'Enable automatic backups'),
('backup_daily',        '1',                    'boolean', 'backup',   'Run daily backup'),
('backup_weekly',       '1',                    'boolean', 'backup',   'Run weekly backup'),
('backup_monthly',      '1',                    'boolean', 'backup',   'Run monthly backup'),
('backup_retention_days','30',                  'integer', 'backup',   'Days to keep old backups'),
('backup_compress',     '1',                    'boolean', 'backup',   'Compress backups with gzip/zip'),
('backup_path',         '',                     'string',  'backup',   'Absolute path to backup directory (blank = auto)'),
-- Email / SMTP
('smtp_host',           '',                     'string',  'email',    'SMTP server hostname'),
('smtp_port',           '587',                  'integer', 'email',    'SMTP port'),
('smtp_username',       '',                     'string',  'email',    'SMTP username'),
('smtp_password',       '',                     'string',  'email',    'SMTP password (stored encrypted)'),
('smtp_encryption',     'tls',                  'string',  'email',    'Encryption: tls or ssl'),
('smtp_from_name',      'Attendance System',    'string',  'email',    'Sender display name'),
('smtp_from_email',     '',                     'string',  'email',    'Sender email address'),
('email_report_recipient','',                   'string',  'email',    'Primary report recipient email'),
('email_report_cc',     '',                     'string',  'email',    'CC addresses (comma-separated)'),
('email_report_bcc',    '',                     'string',  'email',    'BCC addresses (comma-separated)'),
('email_retry_interval','60',                   'integer', 'email',    'Minutes between retry attempts'),
('email_max_retries',   '5',                    'integer', 'email',    'Maximum retry attempts before giving up'),
('email_report_enabled','1',                    'boolean', 'email',    'Enable automatic monthly email reports'),
('email_report_compress','0',                   'boolean', 'email',    'Compress report attachments into ZIP'),
-- Maintenance
('log_retention_days',  '90',                   'integer', 'maintenance','Days to retain audit and system logs'),
('session_cleanup_days','7',                    'integer', 'maintenance','Days to retain expired sessions'),
('archive_after_months','12',                   'integer', 'maintenance','Months before archiving old attendance records'),
-- Phase 3 marker
('phase3_installed_at', NOW(),                  'string',  'system',   'Phase 3 migration timestamp');

-- ============================================================
-- Table: manual_attendance_requests
-- Employee self-service time-in/time-out requests
-- ============================================================
CREATE TABLE IF NOT EXISTS `manual_attendance_requests` (
  `id`                CHAR(36)     NOT NULL,
  `employee_id`       CHAR(36)     NOT NULL,
  `request_type`      ENUM('time_in','time_out') NOT NULL,
  `request_date`      DATE         NOT NULL,
  `requested_time`    TIME         NOT NULL,
  `reason`            TEXT         NOT NULL,
  `reason_category`   ENUM(
      'Forgot QR Code',
      'Forgot PIN',
      'RFID Card Lost',
      'Device Unavailable',
      'Power Outage',
      'System Maintenance',
      'Other'
  ) NOT NULL DEFAULT 'Other',
  `status`            ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `reviewed_by`       CHAR(36)     DEFAULT NULL,
  `reviewed_at`       DATETIME     DEFAULT NULL,
  `admin_remarks`     TEXT         DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mar_employee`   (`employee_id`),
  KEY `idx_mar_date`       (`request_date`),
  KEY `idx_mar_status`     (`status`),
  CONSTRAINT `fk_mar_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mar_reviewer` FOREIGN KEY (`reviewed_by`)  REFERENCES `users`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: admin_manual_attendance
-- Administrator-created attendance records
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_manual_attendance` (
  `id`                CHAR(36)     NOT NULL,
  `employee_id`       CHAR(36)     NOT NULL,
  `attendance_date`   DATE         NOT NULL,
  `time_in`           DATETIME     DEFAULT NULL,
  `time_out`          DATETIME     DEFAULT NULL,
  `attendance_status` ENUM('present','absent','half_day','holiday','leave') NOT NULL DEFAULT 'present',
  `method`            ENUM('Manual Entry','PIN','QR Code','RFID','System Generated') NOT NULL DEFAULT 'Manual Entry',
  `reason`            TEXT         NOT NULL,
  `admin_remarks`     TEXT         DEFAULT NULL,
  `created_by`        CHAR(36)     NOT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ama_employee`  (`employee_id`),
  KEY `idx_ama_date`      (`attendance_date`),
  KEY `idx_ama_created_by`(`created_by`),
  CONSTRAINT `fk_ama_employee`   FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ama_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users`     (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: email_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id`            CHAR(36)     NOT NULL,
  `recipient`     VARCHAR(255) NOT NULL,
  `subject`       VARCHAR(255) NOT NULL,
  `report_period` VARCHAR(80)  DEFAULT NULL COMMENT 'e.g. June 2026',
  `body_preview`  TEXT         DEFAULT NULL,
  `attachment_path` VARCHAR(500) DEFAULT NULL,
  `status`        ENUM('sent','failed','queued','retrying') NOT NULL DEFAULT 'queued',
  `retry_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error`    TEXT         DEFAULT NULL,
  `sent_at`       DATETIME     DEFAULT NULL,
  `next_retry_at` DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_status`   (`status`),
  KEY `idx_email_created`  (`created_at`),
  KEY `idx_email_retry`    (`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: backup_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id`            CHAR(36)     NOT NULL,
  `filename`      VARCHAR(255) NOT NULL,
  `filepath`      VARCHAR(500) NOT NULL,
  `filesize`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `backup_type`   ENUM('daily','weekly','monthly','manual') NOT NULL DEFAULT 'manual',
  `trigger_type`  ENUM('automatic','manual') NOT NULL DEFAULT 'manual',
  `status`        ENUM('success','failed','in_progress') NOT NULL DEFAULT 'in_progress',
  `duration_seconds` SMALLINT UNSIGNED DEFAULT NULL,
  `error_message` TEXT         DEFAULT NULL,
  `verified`      TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`    CHAR(36)     DEFAULT NULL COMMENT 'NULL = cron job',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_backup_type`    (`backup_type`),
  KEY `idx_backup_status`  (`status`),
  KEY `idx_backup_created` (`created_at`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: job_logs  (background task execution log)
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_name`    VARCHAR(100)  NOT NULL,
  `status`      ENUM('running','success','failed') NOT NULL DEFAULT 'running',
  `output`      TEXT          DEFAULT NULL,
  `error`       TEXT          DEFAULT NULL,
  `started_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` DATETIME      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_name`   (`job_name`),
  KEY `idx_job_status` (`status`),
  KEY `idx_job_started`(`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Expand announcements table
-- ============================================================
ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `target_type` ENUM('all','department','employee') NOT NULL DEFAULT 'all' AFTER `branch_id`,
  ADD COLUMN IF NOT EXISTS `target_id`   CHAR(36) DEFAULT NULL COMMENT 'department_id or employee_id' AFTER `target_type`,
  ADD COLUMN IF NOT EXISTS `scheduled_at` DATETIME DEFAULT NULL AFTER `publish_at`;

-- ============================================================
-- Phase 3 permissions
-- ============================================================
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES
('Manage Manual Attendance',        'manual_attendance.manage',   'manual_attendance', 'Admin: create manual attendance records'),
('Request Manual Attendance',       'manual_attendance.request',  'manual_attendance', 'Employee: submit manual time-in/out requests'),
('Approve Manual Attendance',       'manual_attendance.approve',  'manual_attendance', 'Admin: approve/reject manual attendance requests'),
('View Email Logs',                 'email_logs.view',            'email',             'View email delivery logs'),
('Manage Email Settings',           'email_settings.manage',      'email',             'Configure SMTP and email report settings'),
('Manage Backups',                  'backup.manage',              'backup',            'Create, download, restore and delete backups'),
('View Backup Logs',                'backup_logs.view',           'backup',            'View backup history'),
('Manage System Settings',          'system.settings',            'system',            'Change system configuration'),
('View System Health',              'system.health',              'system',            'View system health dashboard'),
('Manage Announcements',            'announcements.manage',       'announcements',     'Create and manage announcements'),
('View Announcements',              'announcements.view',         'announcements',     'View announcements on dashboard'),
('View Job Logs',                   'job_logs.view',              'system',            'View background job execution history');

-- Grant all Phase 3 permissions to administrator (role_id = 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`
WHERE `slug` IN (
    'manual_attendance.manage','manual_attendance.request','manual_attendance.approve',
    'email_logs.view','email_settings.manage','backup.manage','backup_logs.view',
    'system.settings','system.health','announcements.manage','announcements.view','job_logs.view'
);

-- HR role (role_id = 2)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` IN (
    'manual_attendance.manage','manual_attendance.approve',
    'announcements.manage','announcements.view','system.health'
);

-- Employee role (role_id = 3)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN (
    'manual_attendance.request','announcements.view'
);

COMMIT;
