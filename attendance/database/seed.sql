-- ============================================================
-- Attendance Management System - Seed Data
-- Run AFTER schema.sql
-- ============================================================

USE `attendance_db`;

SET AUTOCOMMIT = 0;
START TRANSACTION;

-- ============================================================
-- Roles
-- ============================================================
INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Administrator', 'administrator', 'Full system access'),
(2, 'HR',            'hr',            'Human Resources - employee and attendance management'),
(3, 'Employee',      'employee',      'Can view own attendance only');

-- ============================================================
-- Permissions
-- ============================================================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES
-- Dashboard
('View Dashboard',           'dashboard.view',            'dashboard',   'Access dashboard'),
-- Users
('View Users',               'users.view',                'users',       'View user list'),
('Create Users',             'users.create',              'users',       'Add new user'),
('Edit Users',               'users.edit',                'users',       'Modify user'),
('Delete Users',             'users.delete',              'users',       'Remove user'),
-- Employees
('View Employees',           'employees.view',            'employees',   'View employee list'),
('Create Employees',         'employees.create',          'employees',   'Add employee'),
('Edit Employees',           'employees.edit',            'employees',   'Modify employee'),
('Deactivate Employees',     'employees.deactivate',      'employees',   'Deactivate employee'),
-- Departments
('View Departments',         'departments.view',          'departments', 'View departments'),
('Create Departments',       'departments.create',        'departments', 'Add department'),
('Edit Departments',         'departments.edit',          'departments', 'Modify department'),
-- Branches
('View Branches',            'branches.view',             'branches',    'View branches'),
('Create Branches',          'branches.create',           'branches',    'Add branch'),
('Edit Branches',            'branches.edit',             'branches',    'Modify branch'),
-- Shifts
('View Shifts',              'shifts.view',               'shifts',      'View shifts'),
('Create Shifts',            'shifts.create',             'shifts',      'Add shift'),
('Edit Shifts',              'shifts.edit',               'shifts',      'Modify shift'),
-- Attendance
('View All Attendance',      'attendance.view_all',       'attendance',  'View any employee attendance'),
('View Own Attendance',      'attendance.view_own',       'attendance',  'View own attendance only'),
('Record Attendance',        'attendance.record',         'attendance',  'Record attendance entry'),
('Edit Attendance',          'attendance.edit',           'attendance',  'Modify attendance records'),
-- Audit Logs
('View Audit Logs',          'audit.view',                'audit',       'Access audit trail'),
-- Reports
('View Reports',             'reports.view',              'reports',     'Access reports (Phase 2)'),
('Manage Leave Requests',    'leaves.manage',             'leaves',      'Approve, reject, cancel and search leave requests'),
('Create Own Leave Requests','leaves.create_own',         'leaves',      'Submit own leave requests'),
('Manage Corrections',       'corrections.manage',        'corrections', 'Review attendance correction requests'),
('Create Own Corrections',   'corrections.create_own',    'corrections', 'Submit attendance corrections'),
('View Monitoring',          'attendance.monitor',        'attendance',  'Access attendance monitoring'),
('Manage Holidays',          'holidays.manage',           'holidays',    'Create, edit and deactivate holidays'),
('View Notifications',       'notifications.view',        'notifications','View internal notifications'),
('Use Global Search',        'search.use',                'search',      'Use reusable global search'),
-- Settings
('Manage Settings',          'settings.manage',           'settings',    'Edit system settings');

-- ============================================================
-- Role Permissions
-- ============================================================
-- Administrator: all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- HR: most permissions except user management, branches, audit
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` IN (
  'dashboard.view',
  'employees.view','employees.create','employees.edit','employees.deactivate',
  'departments.view','departments.create','departments.edit',
  'shifts.view',
  'attendance.view_all','attendance.record','attendance.edit',
  'reports.view',
  'leaves.manage','corrections.manage','attendance.monitor','holidays.manage','notifications.view','search.use'
);

-- Employee: own attendance only
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN ('dashboard.view', 'attendance.view_own','leaves.create_own','corrections.create_own','notifications.view','search.use');

-- ============================================================
-- Branch (Main Branch)
-- ============================================================
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `status`) VALUES
('b1000000-0000-0000-0000-000000000001', 'Main Branch', 'MAIN', '123 Main Street, City', '(02) 8123-4567', 'main@company.com', 'active'),
('b2000000-0000-0000-0000-000000000002', 'North Branch', 'NORTH', '456 North Ave, City', '(02) 8234-5678', 'north@company.com', 'active');

-- ============================================================
-- Departments
-- ============================================================
INSERT INTO `departments` (`id`, `branch_id`, `name`, `code`, `description`, `status`) VALUES
('d1000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 'Human Resources',        'HR',  'HR Department',            'active'),
('d2000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 'Information Technology', 'IT',  'IT Department',            'active'),
('d3000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 'Finance & Accounting',   'FIN', 'Finance Department',       'active'),
('d4000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 'Operations',             'OPS', 'Operations Department',    'active'),
('d5000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 'Sales & Marketing',      'SMD', 'Sales & Marketing Dept',   'active');

-- ============================================================
-- Shifts
-- ============================================================
INSERT INTO `shifts` (`id`, `name`, `type`, `time_in`, `time_out`, `lunch_break_start`, `lunch_break_end`, `lunch_break_minutes`, `grace_period_minutes`, `required_hours`, `overnight`) VALUES
('s1000000-0000-0000-0000-000000000001', 'Morning Shift (7AM-4PM)',   'regular',  '07:00:00', '16:00:00', '12:00:00', '13:00:00', 60, 15, 8.00, 0),
('s2000000-0000-0000-0000-000000000001', 'Day Shift (8AM-5PM)',       'regular',  '08:00:00', '17:00:00', '12:00:00', '13:00:00', 60, 15, 8.00, 0),
('s3000000-0000-0000-0000-000000000001', 'Mid Shift (10AM-7PM)',      'regular',  '10:00:00', '19:00:00', '14:00:00', '15:00:00', 60, 15, 8.00, 0),
('s4000000-0000-0000-0000-000000000001', 'Night Shift (10PM-6AM)',    'night',    '22:00:00', '06:00:00', '02:00:00', '03:00:00', 60, 15, 8.00, 1),
('s5000000-0000-0000-0000-000000000001', 'Flexible Shift',            'flexible', '08:00:00', '17:00:00', '12:00:00', '13:00:00', 60, 30, 8.00, 0);

-- ============================================================
-- Employees
-- ============================================================
INSERT INTO `employees` (`id`, `employee_number`, `first_name`, `middle_name`, `last_name`, `suffix`, `department_id`, `branch_id`, `shift_id`, `position`, `employment_status`, `contact_number`, `email`, `date_hired`, `qr_code_value`, `rfid_value`, `status`) VALUES
('e1000000-0000-0000-0000-000000000001', 'EMP-0001', 'System',   NULL,      'Administrator', NULL, 'd1000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 's2000000-0000-0000-0000-000000000001', 'System Administrator', 'regular',      '09171234567', 'admin@company.com',    '2020-01-01', 'QR-EMP-0001-SYSTEM-ADM',  'RF-0001', 'active'),
('e2000000-0000-0000-0000-000000000001', 'EMP-0002', 'Maria',    'Santos',  'Reyes',         NULL, 'd1000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 's2000000-0000-0000-0000-000000000001', 'HR Manager',           'regular',      '09182345678', 'maria.reyes@company.com','2021-03-15', 'QR-EMP-0002-MARIA-REY',   'RF-0002', 'active'),
('e3000000-0000-0000-0000-000000000001', 'EMP-0003', 'Jose',     'Cruz',    'Garcia',        NULL, 'd2000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 's2000000-0000-0000-0000-000000000001', 'IT Specialist',        'regular',      '09193456789', 'jose.garcia@company.com','2022-06-01', 'QR-EMP-0003-JOSE-GAR',   'RF-0003', 'active'),
('e4000000-0000-0000-0000-000000000001', 'EMP-0004', 'Ana',      'Lopez',   'Torres',        NULL, 'd3000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 's2000000-0000-0000-0000-000000000001', 'Accountant',           'probationary', '09204567890', 'ana.torres@company.com', '2024-01-10', 'QR-EMP-0004-ANA-TOR',    'RF-0004', 'active'),
('e5000000-0000-0000-0000-000000000001', 'EMP-0005', 'Roberto',  'Mendoza', 'Dela Cruz',     NULL, 'd4000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 's1000000-0000-0000-0000-000000000001', 'Operations Supervisor','regular',      '09215678901', 'roberto.dc@company.com', '2019-08-20', 'QR-EMP-0005-ROBERTO-DC', 'RF-0005', 'active');

-- ============================================================
-- Update employee PINs (PIN: 1234 for all, hashed)
-- password_hash('1234', PASSWORD_BCRYPT)  -- pre-computed
-- ============================================================
UPDATE `employees` SET
  `pin` = '1234',
  `pin_hash` = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE `id` IN (
  'e1000000-0000-0000-0000-000000000001',
  'e2000000-0000-0000-0000-000000000001',
  'e3000000-0000-0000-0000-000000000001',
  'e4000000-0000-0000-0000-000000000001',
  'e5000000-0000-0000-0000-000000000001'
);

-- ============================================================
-- Users
-- Passwords (all): Admin@123456
-- password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost'=>12])
-- ============================================================
-- Passwords (all): Admin@123456
-- Hash generated via: password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO `users` (`id`, `username`, `password_hash`, `role_id`, `employee_id`, `full_name`, `email`, `status`) VALUES
('u1000000-0000-0000-0000-000000000001', 'admin',    '$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm', 1, 'e1000000-0000-0000-0000-000000000001', 'System Administrator', 'admin@company.com',     'active'),
('u2000000-0000-0000-0000-000000000001', 'hr_maria', '$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm', 2, 'e2000000-0000-0000-0000-000000000001', 'Maria Santos Reyes',   'maria.reyes@company.com','active'),
('u3000000-0000-0000-0000-000000000001', 'emp_jose', '$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm', 3, 'e3000000-0000-0000-0000-000000000001', 'Jose Cruz Garcia',     'jose.garcia@company.com','active');

-- ============================================================
-- Sample Attendance Records (last 5 working days)
-- ============================================================
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `time_recorded`, `attendance_type`, `method`, `device_name`, `ip_address`, `is_late`, `minutes_late`) VALUES
-- EMP-0003 today
(UUID(), 'e3000000-0000-0000-0000-000000000001', CURDATE(), CONCAT(CURDATE(),' 07:58:00'), 'time_in',   'pin', 'KIOSK-01', '127.0.0.1', 0, 0),
(UUID(), 'e3000000-0000-0000-0000-000000000001', CURDATE(), CONCAT(CURDATE(),' 12:01:00'), 'lunch_out', 'pin', 'KIOSK-01', '127.0.0.1', 0, 0),
(UUID(), 'e3000000-0000-0000-0000-000000000001', CURDATE(), CONCAT(CURDATE(),' 13:00:00'), 'lunch_in',  'pin', 'KIOSK-01', '127.0.0.1', 0, 0),
-- EMP-0004 today (late)
(UUID(), 'e4000000-0000-0000-0000-000000000001', CURDATE(), CONCAT(CURDATE(),' 08:25:00'), 'time_in',   'qr_code', 'KIOSK-01', '127.0.0.1', 1, 25),
-- EMP-0005 yesterday
(UUID(), 'e5000000-0000-0000-0000-000000000001', DATE_SUB(CURDATE(),INTERVAL 1 DAY), CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 06:58:00'), 'time_in',   'rfid', 'KIOSK-02', '127.0.0.1', 0, 0),
(UUID(), 'e5000000-0000-0000-0000-000000000001', DATE_SUB(CURDATE(),INTERVAL 1 DAY), CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 12:00:00'), 'lunch_out', 'rfid', 'KIOSK-02', '127.0.0.1', 0, 0),
(UUID(), 'e5000000-0000-0000-0000-000000000001', DATE_SUB(CURDATE(),INTERVAL 1 DAY), CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 13:01:00'), 'lunch_in',  'rfid', 'KIOSK-02', '127.0.0.1', 0, 0),
(UUID(), 'e5000000-0000-0000-0000-000000000001', DATE_SUB(CURDATE(),INTERVAL 1 DAY), CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 16:00:00'), 'time_out',  'rfid', 'KIOSK-02', '127.0.0.1', 0, 0);

-- ============================================================
-- Attendance Summary
-- ============================================================
INSERT INTO `attendance_summary` (`id`, `employee_id`, `attendance_date`, `time_in`, `lunch_out`, `lunch_in`, `time_out`, `total_hours`, `late_minutes`, `day_status`) VALUES
(UUID(), 'e3000000-0000-0000-0000-000000000001', CURDATE(),                             CONCAT(CURDATE(),' 07:58:00'), CONCAT(CURDATE(),' 12:01:00'), CONCAT(CURDATE(),' 13:00:00'), NULL, NULL, 0, 'present'),
(UUID(), 'e4000000-0000-0000-0000-000000000001', CURDATE(),                             CONCAT(CURDATE(),' 08:25:00'), NULL, NULL, NULL, NULL, 25, 'present'),
(UUID(), 'e5000000-0000-0000-0000-000000000001', DATE_SUB(CURDATE(),INTERVAL 1 DAY),   CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 06:58:00'), CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 12:00:00'), CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 13:01:00'), CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 16:00:00'), 8.05, 0, 'present');

-- ============================================================
-- Settings
-- ============================================================
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `description`) VALUES
('app_name',               'Integrated Management Services, Inc.', 'string',  'general',  'Application name'),
('app_version',            '1.0.0',                       'string',  'general',  'Application version'),
('branch_name',            'Main Branch',                 'string',  'general',  'Current branch name'),
('branch_id',              'b1000000-0000-0000-0000-000000000001', 'string', 'general', 'Current branch UUID'),
('timezone',               'Asia/Manila',                 'string',  'general',  'System timezone'),
('photo_max_size_kb',      '2048',                        'integer', 'upload',   'Maximum photo size in KB'),
('pagination_limit',       '25',                          'integer', 'ui',       'Records per page'),
-- Company
('company_name',           'Integrated Management Services, Inc.', 'string', 'company', 'Company name'),
('company_abbreviation',   'IMS',                          'string', 'company', 'Company abbreviation (short name)'),
('company_logo',           '',                            'string',  'company', 'Logo path relative to public/'),
('company_address',        '123 Main Street, City',        'string',  'company', 'Company address'),
('company_contact',        '(02) 8123-4567',               'string',  'company', 'Company contact number'),
('company_email',          'info@company.com',            'string',  'company', 'Company email'),
-- System
('system_name',            'Attendance System',           'string',  'system',  'System display name'),
('maintenance_mode',       '0',                           'boolean', 'system',  'Enable maintenance mode (admins only)'),
-- Security (future)
('password_change_days',   '90',                          'integer', 'security', 'Force password change every X days (0 = disabled)'),
('password_min_length',    '8',                           'integer', 'security', 'Minimum password length'),
('password_require_upper', '1',                           'boolean', 'security', 'Require uppercase letters'),
('password_require_number','1',                           'boolean', 'security', 'Require numbers'),
('password_require_special','1',                          'boolean', 'security', 'Require special characters'),
-- Reports
('report_show_logo',        '1',                           'boolean', 'reports', 'Show company logo on reports'),
('report_show_address',    '1',                           'boolean', 'reports', 'Show company address on reports'),
('report_show_generated_by','1',                           'boolean', 'reports', 'Show generated by on reports'),
('report_show_timestamp',  '1',                           'boolean', 'reports', 'Show generation timestamp on reports');

-- ============================================================
-- Phase 2 Seed Data
-- Run database/migrations/phase2.sql before this section when updating.
-- ============================================================
INSERT IGNORE INTO `holidays` (`id`, `name`, `holiday_date`, `branch_id`, `type`, `description`, `is_recurring`, `status`) VALUES
(UUID(), 'New Year''s Day', CONCAT(YEAR(CURDATE()), '-01-01'), NULL, 'regular', 'Regular annual holiday', 1, 'active'),
(UUID(), 'Company Foundation Day', CONCAT(YEAR(CURDATE()), '-07-15'), 'b1000000-0000-0000-0000-000000000001', 'company', 'Company holiday', 1, 'active');

INSERT IGNORE INTO `leave_requests` (`id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `number_of_days`, `reason`, `status`) VALUES
(UUID(), 'e3000000-0000-0000-0000-000000000001', 'Vacation Leave', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 2, 'Family event', 'Pending');

INSERT IGNORE INTO `notifications` (`id`, `recipient_user_id`, `title`, `message`, `type`) VALUES
(UUID(), 'u1000000-0000-0000-0000-000000000001', 'Phase 2 Installed', 'Reports, leave, corrections, notifications, holidays, calendar and audit enhancements are ready.', 'success');

-- ============================================================
-- Audit log entries for seed
-- ============================================================
INSERT INTO `audit_logs` (`user_id`, `username`, `action`, `module`, `record_id`, `new_value`, `computer_name`, `ip_address`) VALUES
('u1000000-0000-0000-0000-000000000001', 'admin', 'SEED_DATA_INSTALLED', 'system', NULL, CONCAT('{"version":"2.0.0","timestamp":"', NOW(), '"}'), 'SEED-SCRIPT', '127.0.0.1');

COMMIT;
SET AUTOCOMMIT = 1;
