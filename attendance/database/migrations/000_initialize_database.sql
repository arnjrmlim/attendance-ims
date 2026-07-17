-- ============================================================
-- Attendance Management System - Master Migration
-- Phase 1: Create Tables (without circular foreign keys)
-- Phase 2: Add Circular Foreign Keys via ALTER TABLE
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- Create Database
-- ============================================================
CREATE DATABASE IF NOT EXISTS `attendance_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `attendance_db`;

-- ============================================================
-- Drop existing tables in reverse dependency order
-- ============================================================
DROP TABLE IF EXISTS `employee_imports`;
DROP TABLE IF EXISTS `employee_timeline`;
DROP TABLE IF EXISTS `job_logs`;
DROP TABLE IF EXISTS `backup_logs`;
DROP TABLE IF EXISTS `email_logs`;
DROP TABLE IF EXISTS `admin_manual_attendance`;
DROP TABLE IF EXISTS `manual_attendance_requests`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `leave_balances`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `attendance_corrections`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `holidays`;
DROP TABLE IF EXISTS `attendance_summary`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `shifts`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `branches`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

-- ============================================================
-- Table: roles
-- ============================================================
CREATE TABLE `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL,
  `slug`        VARCHAR(50)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: permissions
-- ============================================================
CREATE TABLE `permissions` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL,
  `module`      VARCHAR(50)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: role_permissions
-- ============================================================
CREATE TABLE `role_permissions` (
  `role_id`       TINYINT UNSIGNED  NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: branches
-- ============================================================
CREATE TABLE `branches` (
  `id`         CHAR(36)     NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `code`       VARCHAR(20)  NOT NULL,
  `address`    VARCHAR(255) DEFAULT NULL,
  `city`       VARCHAR(100) DEFAULT NULL,
  `province`   VARCHAR(100) DEFAULT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `email`      VARCHAR(120) DEFAULT NULL,
  `branch_manager` VARCHAR(120) DEFAULT NULL,
  `time_zone`  VARCHAR(50)  DEFAULT 'Asia/Manila',
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_code` (`code`),
  UNIQUE KEY `uq_branches_name` (`name`),
  KEY `idx_branches_status` (`status`),
  KEY `idx_branches_manager` (`branch_manager`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: departments
-- ============================================================
CREATE TABLE `departments` (
  `id`         CHAR(36)     NOT NULL,
  `branch_id`  CHAR(36)     DEFAULT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `code`       VARCHAR(20)  DEFAULT NULL,
  `description`VARCHAR(255) DEFAULT NULL,
  `department_head` VARCHAR(120) DEFAULT NULL,
  `contact_number` VARCHAR(30) DEFAULT NULL,
  `email_address` VARCHAR(120) DEFAULT NULL,
  `location`   VARCHAR(255) DEFAULT NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_name` (`name`),
  UNIQUE KEY `uq_departments_code` (`code`),
  KEY `idx_departments_branch` (`branch_id`),
  KEY `idx_departments_status` (`status`),
  KEY `idx_departments_head` (`department_head`),
  CONSTRAINT `fk_dept_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: shifts
-- ============================================================
CREATE TABLE `shifts` (
  `id`                    CHAR(36)     NOT NULL,
  `name`                  VARCHAR(80)  NOT NULL,
  `type`                  ENUM('regular','night','flexible') NOT NULL DEFAULT 'regular',
  `time_in`               TIME         NOT NULL,
  `time_out`              TIME         NOT NULL,
  `lunch_break_start`     TIME         DEFAULT NULL,
  `lunch_break_end`       TIME         DEFAULT NULL,
  `lunch_break_minutes`   TINYINT UNSIGNED NOT NULL DEFAULT 60,
  `grace_period_minutes`  TINYINT UNSIGNED NOT NULL DEFAULT 15,
  `required_hours`        DECIMAL(4,2) NOT NULL DEFAULT 8.00,
  `overnight`             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = time_out is next day',
  `status`                ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shifts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: employees
-- ============================================================
CREATE TABLE `employees` (
  `id`                CHAR(36)     NOT NULL,
  `employee_number`   VARCHAR(30)  NOT NULL,
  `first_name`        VARCHAR(80)  NOT NULL,
  `middle_name`       VARCHAR(80)  DEFAULT NULL,
  `last_name`         VARCHAR(80)  NOT NULL,
  `suffix`            VARCHAR(10)  DEFAULT NULL,
  `gender`            ENUM('Male','Female','Other') DEFAULT NULL,
  `date_of_birth`     DATE         DEFAULT NULL,
  `civil_status`      ENUM('Single','Married','Widowed','Separated','Divorced') DEFAULT NULL,
  `nationality`       VARCHAR(80)  DEFAULT NULL,
  `photo`             VARCHAR(255) DEFAULT NULL,
  `department_id`     CHAR(36)     DEFAULT NULL,
  `branch_id`         CHAR(36)     DEFAULT NULL,
  `shift_id`          CHAR(36)     DEFAULT NULL,
  `position`          VARCHAR(100) DEFAULT NULL,
  `employment_status` ENUM('Active','Inactive','Suspended','Resigned','Terminated','Retired') NOT NULL DEFAULT 'Active',
  `employment_type`    ENUM('Regular','Probationary','Contractual','Part-Time','Temporary','Intern') DEFAULT 'Probationary',
  `contact_number`    VARCHAR(30)  DEFAULT NULL,
  `alternate_mobile`  VARCHAR(30)  DEFAULT NULL,
  `email`             VARCHAR(120) DEFAULT NULL,
  `home_address`       VARCHAR(500) DEFAULT NULL,
  `emergency_contact_name` VARCHAR(120) DEFAULT NULL,
  `emergency_contact_number` VARCHAR(30) DEFAULT NULL,
  `emergency_contact_relationship` VARCHAR(50) DEFAULT NULL,
  `date_hired`        DATE         DEFAULT NULL,
  `immediate_supervisor_id` CHAR(36) DEFAULT NULL,
  `username`          VARCHAR(50)  DEFAULT NULL,
  `password_hash`     VARCHAR(255) DEFAULT NULL,
  `pin`               VARCHAR(10)  DEFAULT NULL COMMENT 'Hashed PIN for attendance',
  `pin_hash`          VARCHAR(255) DEFAULT NULL,
  `qr_code_value`     VARCHAR(255) DEFAULT NULL COMMENT 'Unique QR payload',
  `rfid_value`        VARCHAR(100) DEFAULT NULL COMMENT 'RFID card number',
  `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by`        CHAR(36)     DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by`        CHAR(36)     DEFAULT NULL,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_number`  (`employee_number`),
  UNIQUE KEY `uq_employee_qr`      (`qr_code_value`),
  UNIQUE KEY `uq_employee_rfid`    (`rfid_value`),
  UNIQUE KEY `uq_employee_username` (`username`),
  KEY `idx_employees_department`   (`department_id`),
  KEY `idx_employees_branch`       (`branch_id`),
  KEY `idx_employees_shift`        (`shift_id`),
  KEY `idx_employees_status`       (`status`),
  KEY `idx_employees_supervisor`  (`immediate_supervisor_id`),
  KEY `idx_employees_username`     (`username`),
  KEY `idx_employees_name`         (`last_name`, `first_name`),
  CONSTRAINT `fk_emp_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_branch`     FOREIGN KEY (`branch_id`)     REFERENCES `branches`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_shift`      FOREIGN KEY (`shift_id`)      REFERENCES `shifts`      (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_supervisor` FOREIGN KEY (`immediate_supervisor_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
  -- Circular FKs added in Phase 2:
  -- fk_emp_created_by (employees.created_by → users.id)
  -- fk_emp_updated_by (employees.updated_by → users.id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE `users` (
  `id`               CHAR(36)     NOT NULL,
  `username`         VARCHAR(60)  NOT NULL,
  `password_hash`    VARCHAR(255) NOT NULL,
  `role_id`          TINYINT UNSIGNED NOT NULL,
  `employee_id`      CHAR(36)     DEFAULT NULL,
  `full_name`        VARCHAR(180) DEFAULT NULL,
  `email`            VARCHAR(120) DEFAULT NULL,
  `status`           ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
  `failed_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`     DATETIME     DEFAULT NULL,
  `last_login`       DATETIME     DEFAULT NULL,
  `last_login_ip`    VARCHAR(45)  DEFAULT NULL,
  `password_changed_at` DATETIME  DEFAULT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `profile_picture`  VARCHAR(255) DEFAULT NULL COMMENT 'Relative path under uploads/avatars/',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username`   (`username`),
  UNIQUE KEY `uq_users_employee`   (`employee_id`),
  KEY `idx_users_role`             (`role_id`),
  KEY `idx_users_status`           (`status`),
  CONSTRAINT `fk_user_role`       FOREIGN KEY (`role_id`)     REFERENCES `roles`     (`id`)
  -- Circular FK added in Phase 2:
  -- fk_user_employee (users.employee_id → employees.id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: attendance
-- ============================================================
CREATE TABLE `attendance` (
  `id`              CHAR(36)     NOT NULL,
  `employee_id`     CHAR(36)     NOT NULL,
  `attendance_date` DATE         NOT NULL,
  `time_recorded`   DATETIME     NOT NULL,
  `attendance_type` ENUM(
      'time_in',
      'break_out',
      'break_in',
      'time_out',
      'overtime_in',
      'overtime_out',
      'lunch_out',
      'lunch_in'
    ) NOT NULL,
  `method`          ENUM('pin','qr_code','rfid','manual') NOT NULL DEFAULT 'pin',
  `device_name`     VARCHAR(100) DEFAULT NULL,
  `ip_address`      VARCHAR(45)  DEFAULT NULL,
  `is_late`         TINYINT(1)   NOT NULL DEFAULT 0,
  `is_early_arrival`TINYINT(1)   NOT NULL DEFAULT 0,
  `is_undertime`    TINYINT(1)   NOT NULL DEFAULT 0,
  `minutes_late`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `minutes_undertime` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total_hours`     DECIMAL(5,2) DEFAULT NULL COMMENT 'Populated on time_out',
  `notes`           VARCHAR(255) DEFAULT NULL,
  `recorded_by`     CHAR(36)     DEFAULT NULL COMMENT 'user_id if manual entry',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- No unique key on (employee_id, attendance_date, attendance_type):
  -- multiple taps of the same type are allowed; engine picks official via earliest/latest rules.
  KEY `idx_attendance_employee`        (`employee_id`),
  KEY `idx_attendance_date`            (`attendance_date`),
  KEY `idx_attendance_type`            (`attendance_type`),
  KEY `idx_attendance_method`          (`method`),
  CONSTRAINT `fk_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: attendance_summary (daily computed record)
-- ============================================================
CREATE TABLE `attendance_summary` (
  `id`                  CHAR(36)     NOT NULL,
  `employee_id`         CHAR(36)     NOT NULL,
  `attendance_date`     DATE         NOT NULL,
  `time_in`             DATETIME     DEFAULT NULL COMMENT 'Official: earliest time_in',
  `break_out`           DATETIME     DEFAULT NULL COMMENT 'Official: earliest break_out',
  `break_in`            DATETIME     DEFAULT NULL COMMENT 'Official: latest break_in',
  `time_out`            DATETIME     DEFAULT NULL COMMENT 'Official: latest time_out',
  `overtime_in`         DATETIME     DEFAULT NULL COMMENT 'Official: earliest overtime_in',
  `overtime_out`        DATETIME     DEFAULT NULL COMMENT 'Official: latest overtime_out',
  -- Legacy aliases kept for backward compatibility
  `lunch_out`           DATETIME     DEFAULT NULL,
  `lunch_in`            DATETIME     DEFAULT NULL,
  `total_hours`         DECIMAL(5,2) DEFAULT NULL,
  `break_minutes`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `late_minutes`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `undertime_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `overtime_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_late`             TINYINT(1)   NOT NULL DEFAULT 0,
  `is_absent`           TINYINT(1)   NOT NULL DEFAULT 0,
  `is_holiday`          TINYINT(1)   NOT NULL DEFAULT 0,
  `day_status`          ENUM('present','absent','half_day','holiday','rest_day','leave') NOT NULL DEFAULT 'absent',
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_summary_employee_date` (`employee_id`, `attendance_date`),
  KEY `idx_summary_date`                (`attendance_date`),
  KEY `idx_summary_status`              (`day_status`),
  CONSTRAINT `fk_summary_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: holidays
-- ============================================================
CREATE TABLE `holidays` (
  `id`           CHAR(36)     NOT NULL,
  `name`         VARCHAR(120) NOT NULL,
  `holiday_date` DATE         NOT NULL,
  `branch_id`    CHAR(36)     DEFAULT NULL,
  `type`         ENUM('regular','special','company','branch') NOT NULL DEFAULT 'regular',
  `description`  VARCHAR(255) DEFAULT NULL,
  `is_recurring` TINYINT(1)   NOT NULL DEFAULT 0,
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_holidays_date_name` (`holiday_date`, `name`),
  KEY `idx_holidays_date` (`holiday_date`),
  KEY `idx_holidays_branch` (`branch_id`),
  KEY `idx_holidays_status` (`status`),
  CONSTRAINT `fk_holiday_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leave_requests
-- ============================================================
CREATE TABLE `leave_requests` (
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

-- ============================================================
-- Table: attendance_corrections
-- ============================================================
CREATE TABLE `attendance_corrections` (
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

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE `notifications` (
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

-- ============================================================
-- Table: leave_balances
-- ============================================================
CREATE TABLE `leave_balances` (
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

-- ============================================================
-- Table: announcements
-- ============================================================
CREATE TABLE `announcements` (
  `id`           CHAR(36)     NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `body`         TEXT         NOT NULL,
  `author_id`    CHAR(36)     DEFAULT NULL,
  `branch_id`    CHAR(36)     DEFAULT NULL COMMENT 'NULL = all branches',
  `target_type`  ENUM('all','department','employee') NOT NULL DEFAULT 'all',
  `target_id`    CHAR(36)     DEFAULT NULL COMMENT 'department_id or employee_id',
  `pinned`       TINYINT(1)   NOT NULL DEFAULT 0,
  `publish_at`   DATETIME     DEFAULT NULL,
  `expire_at`    DATETIME     DEFAULT NULL,
  `scheduled_at` DATETIME     DEFAULT NULL,
  `status`       ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_status`  (`status`),
  KEY `idx_announcements_branch`  (`branch_id`),
  KEY `idx_announcements_publish` (`publish_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: audit_logs
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        CHAR(36)     DEFAULT NULL,
  `username`       VARCHAR(60)  DEFAULT NULL COMMENT 'Snapshot at time of action',
  `action`         VARCHAR(100) NOT NULL,
  `module`         VARCHAR(60)  NOT NULL,
  `record_id`      VARCHAR(36)  DEFAULT NULL COMMENT 'Affected record UUID',
  `previous_value` LONGTEXT     DEFAULT NULL,
  `new_value`      LONGTEXT     DEFAULT NULL,
  `computer_name`  VARCHAR(100) DEFAULT NULL,
  `ip_address`     VARCHAR(45)  DEFAULT NULL,
  `user_agent`     VARCHAR(300) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`      (`user_id`),
  KEY `idx_audit_module`    (`module`),
  KEY `idx_audit_action`    (`action`),
  KEY `idx_audit_created`   (`created_at`),
  KEY `idx_audit_record`    (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: user_sessions (DB-backed sessions for security)
-- ============================================================
CREATE TABLE `user_sessions` (
  `id`         VARCHAR(128)  NOT NULL,
  `user_id`    CHAR(36)      DEFAULT NULL,
  `ip_address` VARCHAR(45)   DEFAULT NULL,
  `user_agent` VARCHAR(300)  DEFAULT NULL,
  `payload`    LONGTEXT      NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user`     (`user_id`),
  KEY `idx_sessions_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: settings
-- ============================================================
CREATE TABLE `settings` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(80)  NOT NULL,
  `value`       TEXT         DEFAULT NULL,
  `type`        ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `group`       VARCHAR(50)  NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`),
  KEY `idx_settings_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: manual_attendance_requests
-- Employee self-service time-in/time-out requests
-- ============================================================
CREATE TABLE `manual_attendance_requests` (
  `id`                CHAR(36)     NOT NULL,
  `employee_id`       CHAR(36)     NOT NULL,
  `request_type`      ENUM('time_in','break_out','break_in','time_out','overtime_in','overtime_out') NOT NULL,
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
CREATE TABLE `admin_manual_attendance` (
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
CREATE TABLE `email_logs` (
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
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_status`   (`status`),
  KEY `idx_email_created`  (`created_at`),
  KEY `idx_email_retry`    (`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: backup_logs
-- ============================================================
CREATE TABLE `backup_logs` (
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
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_backup_type`    (`backup_type`),
  KEY `idx_backup_status`  (`status`),
  KEY `idx_backup_created` (`created_at`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: job_logs (background task execution log)
-- ============================================================
CREATE TABLE `job_logs` (
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
-- Table: employee_timeline
-- Tracks all employee-related events
-- ============================================================
CREATE TABLE `employee_timeline` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` CHAR(36) NOT NULL,
  `event_type` ENUM(
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
CREATE TABLE `employee_imports` (
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
-- Phase 2: Add Circular Foreign Keys
-- ============================================================
-- These foreign keys create circular dependencies and must be added
-- after all tables exist.

-- employees.created_by → users.id
ALTER TABLE `employees`
ADD CONSTRAINT `fk_emp_created_by`
FOREIGN KEY (`created_by`)
REFERENCES `users` (`id`)
ON DELETE SET NULL;

-- employees.updated_by → users.id
ALTER TABLE `employees`
ADD CONSTRAINT `fk_emp_updated_by`
FOREIGN KEY (`updated_by`)
REFERENCES `users` (`id`)
ON DELETE SET NULL;

-- users.employee_id → employees.id
ALTER TABLE `users`
ADD CONSTRAINT `fk_user_employee`
FOREIGN KEY (`employee_id`)
REFERENCES `employees` (`id`)
ON DELETE SET NULL;

COMMIT;
