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
('b1000000-0000-0000-0000-000000000001', 'Main Branch', 'MAIN', '123 Main Street, City', '(02) 8123-4567', 'main@company.com', 'active');

-- ============================================================
-- Departments
-- ============================================================
INSERT INTO `departments` (`id`, `branch_id`, `name`, `code`, `description`, `status`) VALUES
('d1000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 'Administration', 'ADMIN', 'Administration Department', 'active');

-- ============================================================
-- Shifts
-- ============================================================
INSERT INTO `shifts` (`id`, `name`, `type`, `time_in`, `time_out`, `lunch_break_start`, `lunch_break_end`, `lunch_break_minutes`, `grace_period_minutes`, `required_hours`, `overnight`) VALUES
('s2000000-0000-0000-0000-000000000001', 'Day Shift (8AM-5PM)', 'regular', '08:00:00', '17:00:00', '12:00:00', '13:00:00', 60, 15, 8.00, 0);

-- ============================================================
-- Employees
-- ============================================================
INSERT INTO `employees` (`id`, `employee_number`, `first_name`, `middle_name`, `last_name`, `suffix`, `department_id`, `branch_id`, `shift_id`, `position`, `employment_status`, `contact_number`, `email`, `date_hired`, `qr_code_value`, `rfid_value`, `status`) VALUES
('e1000000-0000-0000-0000-000000000001', 'ADMIN001', 'System', NULL, 'Administrator', NULL, 'd1000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 's2000000-0000-0000-0000-000000000001', 'System Administrator', 'regular', '09171234567', 'admin@company.com', '2020-01-01', 'QR-ADMIN001-SYSTEM-ADM', 'RF-0001', 'active');

-- ============================================================
-- Users
-- Default password: Admin@123456
-- password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost'=>12])
-- ============================================================
INSERT INTO `users` (`id`, `username`, `password_hash`, `role_id`, `employee_id`, `full_name`, `email`, `status`) VALUES
('u1000000-0000-0000-0000-000000000001', 'admin', '$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm', 1, 'e1000000-0000-0000-0000-000000000001', 'System Administrator', 'admin@company.com', 'active');

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

COMMIT;
SET AUTOCOMMIT = 1;
