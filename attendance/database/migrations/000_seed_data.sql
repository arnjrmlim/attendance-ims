-- ============================================================
-- Attendance Management System - Seed Data
-- Run AFTER 000_initialize_database.sql
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
(3, 'Supervisor',    'supervisor',    'Department supervisor - can view team attendance'),
(4, 'Employee',      'employee',      'Can view own attendance only');

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
('Delete Employees',         'employees.delete',          'employees',   'Archive/restore employees'),
('Manage Employee Status',   'employees.status',          'employees',   'Activate/deactivate/suspend employees'),
('Import Employees',         'employees.import',          'employees',   'Import employees from Excel'),
('Export Employees',         'employees.export',          'employees',   'Export employee data'),
('View Employee Timeline',   'employees.timeline',        'employees',   'View employee activity timeline'),
('Manage Employee Photos',   'employees.photos',          'employees',   'Upload and manage employee photos'),
('Regenerate QR Codes',      'employees.qr_regenerate',  'employees',   'Regenerate employee QR codes'),
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
('Delete Shifts',            'shifts.delete',             'shifts',      'Delete shift'),
-- Attendance
('View All Attendance',      'attendance.view_all',       'attendance',  'View any employee attendance'),
('View Own Attendance',      'attendance.view_own',       'attendance',  'View own attendance only'),
('Record Attendance',        'attendance.record',         'attendance',  'Record attendance entry'),
('Edit Attendance',          'attendance.edit',           'attendance',  'Modify attendance records'),
('View Attendance Monitoring','attendance.monitor',        'attendance',  'Access attendance monitoring'),
-- Manual Attendance
('Manage Manual Attendance',  'manual_attendance.manage',  'manual_attendance', 'Admin: create manual attendance records'),
('Request Manual Attendance', 'manual_attendance.request', 'manual_attendance', 'Employee: submit manual time-in/out requests'),
('Approve Manual Attendance', 'manual_attendance.approve', 'manual_attendance', 'Admin: approve/reject manual attendance requests'),
-- Leave
('Manage Leave Requests',     'leaves.manage',             'leaves',      'Approve, reject, cancel and search leave requests'),
('Create Own Leave Requests','leaves.create_own',         'leaves',      'Submit own leave requests'),
-- Corrections
('Manage Corrections',       'corrections.manage',        'corrections', 'Review attendance correction requests'),
('Create Own Corrections',   'corrections.create_own',    'corrections', 'Submit attendance corrections'),
-- Holidays
('Manage Holidays',          'holidays.manage',           'holidays',    'Create, edit and deactivate holidays'),
-- Notifications
('View Notifications',       'notifications.view',        'notifications','View internal notifications'),
-- Announcements
('Manage Announcements',     'announcements.manage',     'announcements', 'Create and manage announcements'),
('View Announcements',       'announcements.view',       'announcements', 'View announcements on dashboard'),
-- Audit Logs
('View Audit Logs',          'audit.view',                'audit',       'Access audit trail'),
-- Reports
('View Reports',             'reports.view',              'reports',     'Access reports'),
-- Search
('Use Global Search',        'search.use',                'search',      'Use reusable global search'),
-- Settings
('Manage Settings',          'settings.manage',           'settings',    'Edit system settings'),
('Manage System Settings',   'system.settings',           'system',      'Change system configuration'),
('View System Health',       'system.health',             'system',      'View system health dashboard'),
-- Email
('View Email Logs',          'email_logs.view',           'email',       'View email delivery logs'),
('Manage Email Settings',    'email_settings.manage',     'email',       'Configure SMTP and email report settings'),
-- Backup
('Manage Backups',           'backup.manage',             'backup',      'Create, download, restore and delete backups'),
('View Backup Logs',         'backup_logs.view',          'backup',      'View backup history'),
-- Job Logs
('View Job Logs',            'job_logs.view',             'system',      'View background job execution history');

-- ============================================================
-- Role Permissions
-- ============================================================
-- Administrator: all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- HR: most permissions except user management, branches, audit, system health, backups
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` IN (
  'dashboard.view',
  'employees.view','employees.create','employees.edit','employees.delete','employees.status',
  'employees.import','employees.export','employees.timeline','employees.photos','employees.qr_regenerate',
  'departments.view','departments.create','departments.edit',
  'shifts.view','shifts.create','shifts.edit','shifts.delete',
  'attendance.view_all','attendance.record','attendance.edit','attendance.monitor',
  'manual_attendance.manage','manual_attendance.approve',
  'leaves.manage','leaves.create_own',
  'corrections.manage','corrections.create_own',
  'holidays.manage',
  'notifications.view',
  'announcements.manage','announcements.view',
  'reports.view',
  'search.use',
  'settings.manage'
);

-- Supervisor: limited to team management
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN (
  'dashboard.view',
  'employees.view',
  'attendance.view_all','attendance.monitor',
  'manual_attendance.approve',
  'leaves.manage',
  'corrections.manage',
  'notifications.view',
  'announcements.view',
  'search.use'
);

-- Employee: own attendance only
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions`
WHERE `slug` IN (
  'dashboard.view',
  'attendance.view_own',
  'leaves.create_own',
  'corrections.create_own',
  'manual_attendance.request',
  'notifications.view',
  'announcements.view',
  'search.use'
);

-- ============================================================
-- Branches
-- ============================================================
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `city`, `province`, `phone`, `email`, `branch_manager`, `time_zone`, `status`) VALUES
('b1000000-0000-0000-0000-000000000001', 'Main Branch', 'MAIN', '123 Main Street, City', 'Manila', 'Metro Manila', '(02) 8123-4567', 'main@company.com', NULL, 'Asia/Manila', 'active');

-- ============================================================
-- Departments
-- ============================================================
INSERT INTO `departments` (`id`, `branch_id`, `name`, `code`, `description`, `department_head`, `contact_number`, `email_address`, `location`, `status`) VALUES
('d1000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 'Administration', 'ADMIN', 'Administration Department', NULL, '(02) 8123-4567', 'admin@company.com', 'Main Building, 2nd Floor', 'active');

-- ============================================================
-- Shifts
-- ============================================================
INSERT INTO `shifts` (`id`, `name`, `description`, `type`, `time_in`, `time_out`, `lunch_break_start`, `lunch_break_end`, `lunch_break_minutes`, `grace_period_minutes`, `required_hours`, `overnight`, `status`, `is_default`) VALUES
('s0000000-0000-0000-0000-000000000001', 'Day Shift', 'Standard 8 AM – 5 PM office shift with a 1-hour lunch break.', 'regular', '08:00:00', '17:00:00', '12:00:00', '13:00:00', 60, 15, 8.00, 0, 'active', 1);

-- ============================================================
-- Employees
-- ============================================================
INSERT INTO `employees` (`id`, `employee_number`, `first_name`, `middle_name`, `last_name`, `suffix`, `gender`, `date_of_birth`, `civil_status`, `nationality`, `department_id`, `branch_id`, `shift_id`, `position`, `employment_status`, `employment_type`, `contact_number`, `alternate_mobile`, `email`, `home_address`, `emergency_contact_name`, `emergency_contact_number`, `emergency_contact_relationship`, `date_hired`, `immediate_supervisor_id`, `username`, `password_hash`, `pin`, `pin_hash`, `qr_code_value`, `rfid_value`, `status`) VALUES
('e1000000-0000-0000-0000-000000000001', 'ADMIN001', 'System', NULL, 'Administrator', NULL, 'Male', '1990-01-01', 'Single', 'Filipino', 'd1000000-0000-0000-0000-000000000001', 'b1000000-0000-0000-0000-000000000001', 's0000000-0000-0000-0000-000000000001', 'System Administrator', 'Active', 'Regular', '09171234567', NULL, 'admin@company.com', '123 Main St, Manila', 'Emergency Contact', '09181234567', 'Spouse', '2020-01-01', NULL, 'admin', '$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm', '1234', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'QR-ADMIN001-SYSTEM-ADM', 'RF-0001', 'active');

-- ============================================================
-- Users
-- Default password: Admin@123456
-- Hash generated via: password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost'=>12])
-- must_change_password = 0 for seed users so they can log in immediately
-- ============================================================
INSERT INTO `users` (`id`, `username`, `password_hash`, `role_id`, `employee_id`, `full_name`, `email`, `status`, `must_change_password`, `password_changed_at`) VALUES
('u1000000-0000-0000-0000-000000000001', 'admin', '$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm', 1, 'e1000000-0000-0000-0000-000000000001', 'System Administrator', 'admin@company.com', 'active', 0, NOW());

-- ============================================================
-- Settings
-- ============================================================
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `description`) VALUES
-- General
('app_name',               'Integrated Management Services, Inc.', 'string',  'general',  'Application name'),
('app_version',            '1.0.0',                       'string',  'general',  'Application version'),
('branch_name',            'Main Branch',                 'string',  'general',  'Current branch name'),
('branch_id',              'b1000000-0000-0000-0000-000000000001', 'string', 'general', 'Current branch UUID'),
-- Company
('company_name',           'My Company',                  'string',  'company',  'Company name'),
('company_abbreviation',  'IMS',                         'string',  'company',  'Company abbreviation (short name)'),
('company_logo',           '',                            'string',  'company',  'Logo path relative to public/'),
-- System
('timezone',              'Asia/Manila',                 'string',  'system',   'System timezone'),
-- Attendance
('grace_period_minutes',  '15',                          'integer', 'attendance','Grace period in minutes before marking late'),
('work_hours_per_day',    '8',                           'decimal', 'attendance','Standard working hours per day'),
('overtime_threshold',    '30',                          'integer', 'attendance','Minutes after shift end before counting overtime'),
('late_deduction',        '1',                           'boolean', 'attendance','Deduct late minutes from salary'),
('allowed_time_in_from',  '06:00',                       'time',    'attendance','Earliest allowed time-in'),
('allowed_time_in_to',    '10:00',                       'time',    'attendance','Latest allowed time-in (warn if exceeded)'),
('method_pin',            '1',                           'boolean', 'attendance','Enable PIN attendance method'),
('method_qr',             '1',                           'boolean', 'attendance','Enable QR Code attendance method'),
('method_rfid',           '1',                           'boolean', 'attendance','Enable RFID attendance method'),
('method_manual',         '1',                           'boolean', 'attendance','Enable Manual attendance method'),
('attendance_kiosk_mode', '1',                           'boolean', 'attendance','Enable kiosk mode (no login required)'),
('pin_length',            '4',                           'integer', 'attendance','Default PIN length'),
('allow_manual_entry',    '1',                           'boolean', 'attendance','Allow HR/Admin to manually add attendance'),
-- Leave
('exclude_weekends_from_leave', '1',                     'boolean', 'leave',    'Exclude Saturdays and Sundays from leave day calculation'),
-- Backup
('backup_enabled',        '1',                           'boolean', 'backup',   'Enable automatic backups'),
('backup_daily',          '1',                           'boolean', 'backup',   'Run daily backup'),
('backup_weekly',         '1',                           'boolean', 'backup',   'Run weekly backup'),
('backup_monthly',        '1',                           'boolean', 'backup',   'Run monthly backup'),
('backup_retention_days', '30',                          'integer', 'backup',   'Days to keep old backups'),
('backup_compress',       '1',                           'boolean', 'backup',   'Compress backups with gzip/zip'),
('backup_path',           '',                            'string',  'backup',   'Absolute path to backup directory (blank = auto)'),
-- Email / SMTP
('smtp_host',             '',                            'string',  'email',    'SMTP server hostname'),
('smtp_port',             '587',                         'integer', 'email',    'SMTP port'),
('smtp_username',         '',                            'string',  'email',    'SMTP username'),
('smtp_password',         '',                            'string',  'email',    'SMTP password (stored encrypted)'),
('smtp_encryption',       'tls',                         'string',  'email',    'Encryption: tls or ssl'),
('smtp_from_name',        'Attendance System',           'string',  'email',    'Sender display name'),
('smtp_from_email',       '',                            'string',  'email',    'Sender email address'),
('email_report_recipient','',                           'string',  'email',    'Primary report recipient email'),
('email_report_cc',       '',                            'string',  'email',    'CC addresses (comma-separated)'),
('email_report_bcc',      '',                            'string',  'email',    'BCC addresses (comma-separated)'),
('email_retry_interval',  '60',                          'integer', 'email',    'Minutes between retry attempts'),
('email_max_retries',     '5',                           'integer', 'email',    'Maximum retry attempts before giving up'),
('email_report_enabled',  '1',                           'boolean', 'email',    'Enable automatic monthly email reports'),
('email_report_compress', '0',                           'boolean', 'email',    'Compress report attachments into ZIP'),
('email_schedule',        'manual',                      'string',  'email',    'Email schedule: manual, 15th, end_of_month, or both'),
('email_timezone',        'UTC',                         'string',  'email',    'Timezone for email scheduling (e.g., Asia/Manila, America/New_York)'),
('last_email_sent_date',  NULL,                         'string',  'email',    'Last date when scheduled email was sent (YYYY-MM-DD)'),
-- Maintenance
('log_retention_days',    '90',                          'integer', 'maintenance','Days to retain audit and system logs'),
('session_cleanup_days',  '7',                           'integer', 'maintenance','Days to retain expired sessions'),
('archive_after_months',  '12',                          'integer', 'maintenance','Months before archiving old attendance records'),
-- Upload
('photo_max_size_kb',     '2048',                        'integer', 'upload',   'Maximum photo size in KB'),
-- UI
('pagination_limit',      '25',                          'integer', 'ui',       'Records per page'),
-- Employee
('employee_photo_max_size', '2097152',                   'integer', 'employees','Maximum employee photo size in bytes (2MB)'),
('employee_photo_allowed_types', 'jpg,jpeg,png',         'string',  'employees','Allowed employee photo file types'),
('qr_code_size',          '300',                         'integer', 'employees','QR code image size in pixels'),
('qr_code_error_correction', 'M',                        'string',  'employees','QR code error correction level (L, M, Q, H)');

COMMIT;
SET AUTOCOMMIT = 1;
