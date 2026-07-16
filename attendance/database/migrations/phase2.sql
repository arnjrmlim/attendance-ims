-- ============================================================
-- Attendance Management System - Phase 2 Migration
-- Run after database/schema.sql and database/seed.sql
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `attendance_db`;

START TRANSACTION;

ALTER TABLE `holidays`
  ADD COLUMN `branch_id` CHAR(36) DEFAULT NULL AFTER `holiday_date`,
  ADD COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `is_recurring`,
  MODIFY COLUMN `type` ENUM('regular','special','company','branch') NOT NULL DEFAULT 'regular';

ALTER TABLE `holidays`
  ADD KEY `idx_holidays_branch` (`branch_id`),
  ADD KEY `idx_holidays_status` (`status`);

CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` CHAR(36) NOT NULL,
  `employee_id` CHAR(36) NOT NULL,
  `leave_type` ENUM('Vacation Leave','Sick Leave','Emergency Leave','Maternity Leave','Paternity Leave','Bereavement Leave','Unpaid Leave') NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `number_of_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `reason` TEXT NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `approved_by` CHAR(36) DEFAULT NULL,
  `approval_date` DATETIME DEFAULT NULL,
  `admin_remarks` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_employee` (`employee_id`),
  KEY `idx_leave_dates` (`start_date`, `end_date`),
  KEY `idx_leave_status` (`status`),
  KEY `idx_leave_type` (`leave_type`),
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_corrections` (
  `id` CHAR(36) NOT NULL,
  `employee_id` CHAR(36) NOT NULL,
  `attendance_id` CHAR(36) DEFAULT NULL,
  `attendance_date` DATE NOT NULL,
  `correction_type` ENUM('Forgot Time In','Forgot Time Out','Incorrect Attendance','Wrong Attendance Method') NOT NULL,
  `original_time_in` DATETIME DEFAULT NULL,
  `original_time_out` DATETIME DEFAULT NULL,
  `requested_time_in` DATETIME DEFAULT NULL,
  `requested_time_out` DATETIME DEFAULT NULL,
  `reason` TEXT NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` CHAR(36) DEFAULT NULL,
  `approval_date` DATETIME DEFAULT NULL,
  `admin_remarks` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_correction_employee` (`employee_id`),
  KEY `idx_correction_date` (`attendance_date`),
  KEY `idx_correction_status` (`status`),
  CONSTRAINT `fk_correction_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_correction_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_correction_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` CHAR(36) NOT NULL,
  `recipient_user_id` CHAR(36) NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient` (`recipient_user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id` CHAR(36) NOT NULL,
  `employee_id` CHAR(36) NOT NULL,
  `leave_type` VARCHAR(80) NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `entitled_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `used_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leave_balance` (`employee_id`, `leave_type`, `year`),
  CONSTRAINT `fk_balance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES
('Manage Leave Requests', 'leaves.manage', 'leaves', 'Approve, reject, cancel and search leave requests'),
('Create Own Leave Requests', 'leaves.create_own', 'leaves', 'Submit own leave requests'),
('Manage Attendance Corrections', 'corrections.manage', 'corrections', 'Review attendance correction requests'),
('Create Own Corrections', 'corrections.create_own', 'corrections', 'Submit attendance corrections'),
('View Attendance Monitoring', 'attendance.monitor', 'attendance', 'Access attendance monitoring'),
('Manage Holidays', 'holidays.manage', 'holidays', 'Create, edit and deactivate holidays'),
('View Notifications', 'notifications.view', 'notifications', 'View internal notifications'),
('Use Global Search', 'search.use', 'search', 'Use reusable global search');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` IN ('leaves.manage','corrections.manage','attendance.monitor','holidays.manage','notifications.view','search.use','audit.view');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN ('leaves.create_own','corrections.create_own','notifications.view','search.use');

INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`) VALUES
('exclude_weekends_from_leave', '1', 'boolean', 'leave', 'Exclude Saturdays and Sundays from leave day calculation'),
('phase2_installed_at', NOW(), 'string', 'system', 'Phase 2 migration timestamp');

COMMIT;
