-- ============================================================
-- Attendance Management System - Phase 1
-- Database Schema
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
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `email`      VARCHAR(120) DEFAULT NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_code` (`code`),
  KEY `idx_branches_status` (`status`)
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
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_departments_branch` (`branch_id`),
  KEY `idx_departments_status` (`status`),
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
  `photo`             VARCHAR(255) DEFAULT NULL,
  `department_id`     CHAR(36)     DEFAULT NULL,
  `branch_id`         CHAR(36)     DEFAULT NULL,
  `shift_id`          CHAR(36)     DEFAULT NULL,
  `position`          VARCHAR(100) DEFAULT NULL,
  `employment_status` ENUM('regular','probationary','contractual','part_time','resigned','terminated') NOT NULL DEFAULT 'probationary',
  `contact_number`    VARCHAR(30)  DEFAULT NULL,
  `email`             VARCHAR(120) DEFAULT NULL,
  `date_hired`        DATE         DEFAULT NULL,
  `pin`               VARCHAR(10)  DEFAULT NULL COMMENT 'Hashed PIN for attendance',
  `pin_hash`          VARCHAR(255) DEFAULT NULL,
  `qr_code_value`     VARCHAR(255) DEFAULT NULL COMMENT 'Unique QR payload',
  `rfid_value`        VARCHAR(100) DEFAULT NULL COMMENT 'RFID card number',
  `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_number`  (`employee_number`),
  UNIQUE KEY `uq_employee_qr`      (`qr_code_value`),
  UNIQUE KEY `uq_employee_rfid`    (`rfid_value`),
  KEY `idx_employees_department`   (`department_id`),
  KEY `idx_employees_branch`       (`branch_id`),
  KEY `idx_employees_shift`        (`shift_id`),
  KEY `idx_employees_status`       (`status`),
  KEY `idx_employees_name`         (`last_name`, `first_name`),
  CONSTRAINT `fk_emp_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_branch`     FOREIGN KEY (`branch_id`)     REFERENCES `branches`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_shift`      FOREIGN KEY (`shift_id`)      REFERENCES `shifts`      (`id`) ON DELETE SET NULL
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
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username`   (`username`),
  UNIQUE KEY `uq_users_employee`   (`employee_id`),
  KEY `idx_users_role`             (`role_id`),
  KEY `idx_users_status`           (`status`),
  CONSTRAINT `fk_user_role`       FOREIGN KEY (`role_id`)     REFERENCES `roles`     (`id`),
  CONSTRAINT `fk_user_employee`   FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: attendance
-- ============================================================
CREATE TABLE `attendance` (
  `id`              CHAR(36)     NOT NULL,
  `employee_id`     CHAR(36)     NOT NULL,
  `attendance_date` DATE         NOT NULL,
  `time_recorded`   DATETIME     NOT NULL,
  `attendance_type` ENUM('time_in','lunch_out','lunch_in','time_out') NOT NULL,
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
  UNIQUE KEY `uq_attendance_type`      (`employee_id`, `attendance_date`, `attendance_type`),
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
  `time_in`             DATETIME     DEFAULT NULL,
  `lunch_out`           DATETIME     DEFAULT NULL,
  `lunch_in`            DATETIME     DEFAULT NULL,
  `time_out`            DATETIME     DEFAULT NULL,
  `total_hours`         DECIMAL(5,2) DEFAULT NULL,
  `late_minutes`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `undertime_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `overtime_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Phase 2',
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
-- Table: holidays (Phase 2 placeholder)
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
-- Table: leave_balances (prepared architecture)
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
-- Table: announcements (Phase 2 placeholder)
-- ============================================================
CREATE TABLE `announcements` (
  `id`           CHAR(36)     NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `body`         TEXT         NOT NULL,
  `author_id`    CHAR(36)     DEFAULT NULL,
  `branch_id`    CHAR(36)     DEFAULT NULL COMMENT 'NULL = all branches',
  `pinned`       TINYINT(1)   NOT NULL DEFAULT 0,
  `publish_at`   DATETIME     DEFAULT NULL,
  `expire_at`    DATETIME     DEFAULT NULL,
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
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`      (`user_id`),
  KEY `idx_audit_module`    (`module`),
  KEY `idx_audit_action`    (`action`),
  KEY `idx_audit_created`   (`created_at`),
  KEY `idx_audit_record`    (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: sessions (DB-backed sessions for security)
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

COMMIT;
