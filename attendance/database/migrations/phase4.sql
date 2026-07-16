-- ============================================================
-- Attendance Management System - Phase 4 Migration
-- Employee Management Module
-- Run after phase3.sql
-- MySQL 5.7+ / MariaDB 10.x compatible
-- ============================================================

USE `attendance_db`;

START TRANSACTION;

-- ============================================================
-- Expand employees table with additional fields
-- ============================================================

ALTER TABLE `employees`
  ADD COLUMN `gender` ENUM('Male','Female','Other') DEFAULT NULL AFTER `suffix`,
  ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `gender`,
  ADD COLUMN `civil_status` ENUM('Single','Married','Widowed','Separated','Divorced') DEFAULT NULL AFTER `date_of_birth`,
  ADD COLUMN `nationality` VARCHAR(80) DEFAULT NULL AFTER `civil_status`,
  ADD COLUMN `alternate_mobile` VARCHAR(30) DEFAULT NULL AFTER `contact_number`,
  ADD COLUMN `home_address` VARCHAR(500) DEFAULT NULL AFTER `email`,
  ADD COLUMN `emergency_contact_name` VARCHAR(120) DEFAULT NULL AFTER `home_address`,
  ADD COLUMN `emergency_contact_number` VARCHAR(30) DEFAULT NULL AFTER `emergency_contact_name`,
  ADD COLUMN `emergency_contact_relationship` VARCHAR(50) DEFAULT NULL AFTER `emergency_contact_number`,
  ADD COLUMN `username` VARCHAR(50) DEFAULT NULL AFTER `email`,
  ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `username`,
  ADD COLUMN `employment_type` ENUM('Regular','Probationary','Contractual','Part-Time','Temporary','Intern') DEFAULT 'Probationary' AFTER `employment_status`,
  ADD COLUMN `immediate_supervisor_id` CHAR(36) DEFAULT NULL AFTER `position`,
  ADD COLUMN `created_by` CHAR(36) DEFAULT NULL AFTER `created_at`,
  ADD COLUMN `updated_by` CHAR(36) DEFAULT NULL AFTER `updated_at`,
  ADD INDEX `idx_employees_supervisor` (`immediate_supervisor_id`),
  ADD INDEX `idx_employees_username` (`username`),
  ADD CONSTRAINT `fk_emp_supervisor` FOREIGN KEY (`immediate_supervisor_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_emp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_emp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Update employment_status enum to include all required statuses
ALTER TABLE `employees`
  MODIFY COLUMN `employment_status` ENUM('Active','Inactive','Suspended','Resigned','Terminated','Retired') NOT NULL DEFAULT 'Active';

-- ============================================================
-- Table: employee_timeline
-- Tracks all employee-related events
-- ============================================================
CREATE TABLE IF NOT EXISTS `employee_timeline` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` CHAR(36) NOT NULL,
  `event_type` ENUM(
    'employee_created',
    'employee_updated',
    'employee_archived',
    'employee_restored',
    'employee_activated',
    'employee_deactivated',
    'status_changed',
    'department_changed',
    'branch_changed',
    'shift_changed',
    'position_changed',
    'photo_updated',
    'qr_code_regenerated',
    'imported',
    'attendance_milestone'
  ) NOT NULL,
  `previous_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timeline_employee` (`employee_id`),
  KEY `idx_timeline_type` (`event_type`),
  KEY `idx_timeline_created` (`created_at`),
  CONSTRAINT `fk_timeline_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timeline_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: employee_imports
-- Tracks employee import operations
-- ============================================================
CREATE TABLE IF NOT EXISTS `employee_imports` (
  `id` CHAR(36) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_report` LONGTEXT DEFAULT NULL,
  `imported_by` CHAR(36) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_imports_created_by` (`imported_by`),
  KEY `idx_imports_created` (`created_at`),
  CONSTRAINT `fk_imports_created_by` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Phase 4 permissions
-- ============================================================
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES
('View Employees',                   'employees.view',              'employees',  'View employee list and profiles'),
('Create Employees',                 'employees.create',            'employees',  'Add new employees'),
('Edit Employees',                   'employees.edit',              'employees',  'Edit employee information'),
('Delete Employees',                 'employees.delete',            'employees',  'Archive/restore employees'),
('Manage Employee Status',           'employees.status',            'employees',  'Activate/deactivate/suspend employees'),
('Import Employees',                 'employees.import',            'employees',  'Import employees from Excel'),
('Export Employees',                 'employees.export',            'employees',  'Export employee data'),
('View Employee Timeline',           'employees.timeline',          'employees',  'View employee activity timeline'),
('Manage Employee Photos',           'employees.photos',            'employees',  'Upload and manage employee photos'),
('Regenerate QR Codes',              'employees.qr_regenerate',    'employees',  'Regenerate employee QR codes');

-- Grant all Phase 4 permissions to administrator (role_id = 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`
WHERE `slug` IN (
    'employees.view','employees.create','employees.edit','employees.delete',
    'employees.status','employees.import','employees.export','employees.timeline',
    'employees.photos','employees.qr_regenerate'
);

-- HR role (role_id = 2)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` IN (
    'employees.view','employees.create','employees.edit','employees.delete',
    'employees.status','employees.import','employees.export','employees.timeline',
    'employees.photos','employees.qr_regenerate'
);

-- Employee role (role_id = 3) - limited access
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN ('employees.view');

-- ============================================================
-- Phase 4 marker
-- ============================================================
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `description`) VALUES
('phase4_installed_at', NOW(), 'string', 'system', 'Phase 4 migration timestamp'),
('employee_photo_max_size', '2097152', 'integer', 'employees', 'Maximum employee photo size in bytes (2MB)'),
('employee_photo_allowed_types', 'jpg,jpeg,png', 'string', 'employees', 'Allowed employee photo file types'),
('qr_code_size', '300', 'integer', 'employees', 'QR code image size in pixels'),
('qr_code_error_correction', 'M', 'string', 'employees', 'QR code error correction level (L, M, Q, H)');

COMMIT;
