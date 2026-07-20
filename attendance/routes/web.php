<?php

declare(strict_types=1);

use App\Controllers\AnnouncementController;
use App\Controllers\AttendanceCorrectionController;
use App\Controllers\AttendanceMonitoringController;
use App\Controllers\AuthController;
use App\Controllers\AuditController;
use App\Controllers\BackupController;
use App\Controllers\BranchController;
use App\Controllers\ShiftController;
use App\Controllers\CalendarController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentController;
use App\Controllers\EmailLogsController;
use App\Controllers\EmailScheduleController;
use App\Controllers\EmailSettingsController;
use App\Controllers\EmployeeController;
use App\Controllers\HolidayController;
use App\Controllers\LeaveController;
use App\Controllers\ManualAttendanceController;
use App\Controllers\NotificationController;
use App\Controllers\ReportController;
use App\Controllers\SearchController;
use App\Controllers\ProfileController;
use App\Controllers\SystemController;

$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

/* ── Profile Settings ────────────────────────────────────────────── */
$router->get('/profile', [ProfileController::class, 'index']);
$router->post('/profile/password', [ProfileController::class, 'changePassword']);
$router->post('/profile/picture', [ProfileController::class, 'uploadPicture']);
$router->post('/profile/picture/remove', [ProfileController::class, 'removePicture']);

$router->get('/dashboard', [DashboardController::class, 'index']);

/* ── Attendance Monitoring ───────────────────────────────────────────── */
$router->get('/attendance-monitoring', [AttendanceMonitoringController::class, 'index']);
$router->get('/attendance-monitoring/api/data', [AttendanceMonitoringController::class, 'apiData']);

$router->get('/leaves', [LeaveController::class, 'index']);
$router->post('/leaves', [LeaveController::class, 'store']);
$router->post('/leaves/approve', [LeaveController::class, 'approve']);
$router->post('/leaves/reject', [LeaveController::class, 'reject']);
$router->post('/leaves/cancel', [LeaveController::class, 'cancel']);

$router->get('/corrections', [AttendanceCorrectionController::class, 'index']);
$router->post('/corrections', [AttendanceCorrectionController::class, 'store']);
$router->post('/corrections/approve', [AttendanceCorrectionController::class, 'approve']);
$router->post('/corrections/reject', [AttendanceCorrectionController::class, 'reject']);

$router->get('/attendance-monitoring', [AttendanceMonitoringController::class, 'index']);
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/reports/export', [ReportController::class, 'export']);

$router->get('/notifications', [NotificationController::class, 'index']);
$router->post('/notifications/read', [NotificationController::class, 'markRead']);
$router->post('/notifications/delete', [NotificationController::class, 'delete']);

$router->get('/calendar', [CalendarController::class, 'index']);
$router->get('/holidays', [HolidayController::class, 'index']);
$router->post('/holidays', [HolidayController::class, 'store']);
$router->post('/holidays/update', [HolidayController::class, 'update']);
$router->post('/holidays/deactivate', [HolidayController::class, 'deactivate']);

$router->get('/audit', [AuditController::class, 'index']);
$router->get('/search', [SearchController::class, 'index']);

/* ── Phase 3: Manual Attendance (admin queue + employee self-service) ── */
$router->get('/manual-attendance', [ManualAttendanceController::class, 'index']);
$router->get('/manual-attendance/create', [ManualAttendanceController::class, 'create']);
$router->post('/manual-attendance/create', [ManualAttendanceController::class, 'store']);
$router->get('/manual-attendance/request', [ManualAttendanceController::class, 'requestForm']);
$router->post('/manual-attendance/request', [ManualAttendanceController::class, 'submitRequest']);

/* ── Manual Attendance JSON API ─────────────────────────────────────── */
$router->get('/manual-attendance/api/list',        [ManualAttendanceController::class, 'apiList']);
$router->get('/manual-attendance/api/admin-list',  [ManualAttendanceController::class, 'apiAdminList']);
$router->get('/manual-attendance/api/stats',       [ManualAttendanceController::class, 'apiStats']);
$router->post('/manual-attendance/api/store',      [ManualAttendanceController::class, 'apiStore']);
$router->post('/manual-attendance/api/update',     [ManualAttendanceController::class, 'apiUpdate']);
$router->post('/manual-attendance/api/delete',     [ManualAttendanceController::class, 'apiDelete']);
$router->post('/manual-attendance/api/request',    [ManualAttendanceController::class, 'apiRequest']);

/* ── Phase 3: Announcements ─────────────────────────────────────────── */
$router->get('/announcements', [AnnouncementController::class, 'index']);
$router->get('/announcements/create', [AnnouncementController::class, 'create']);
$router->post('/announcements', [AnnouncementController::class, 'store']);
$router->get('/announcements/edit', [AnnouncementController::class, 'edit']);
$router->post('/announcements/update', [AnnouncementController::class, 'update']);
$router->post('/announcements/archive', [AnnouncementController::class, 'archive']);
$router->post('/announcements/publish', [AnnouncementController::class, 'publish']);

/* ── Phase 3: Email Settings & Logs ─────────────────────────────────── */
$router->get('/email-settings', [EmailSettingsController::class, 'index']);
$router->post('/email-settings', [EmailSettingsController::class, 'save']);
$router->post('/email-settings/test', [EmailSettingsController::class, 'test']);

$router->get('/email-logs', [EmailLogsController::class, 'index']);
$router->post('/email-logs/resend', [EmailLogsController::class, 'resend']);
$router->post('/email-logs/delete', [EmailLogsController::class, 'delete']);

/* ── Phase 5: Email Scheduling ───────────────────────────────────────── */
$router->get('/email-schedule/check',     [EmailScheduleController::class, 'check']);
$router->get('/email-schedule/status',    [EmailScheduleController::class, 'status']);
$router->get('/email-schedule/test',      [EmailScheduleController::class, 'testPage']);
$router->get('/email-schedule/test-logs', [EmailScheduleController::class, 'testLogs']);
$router->post('/email-schedule/test-run', [EmailScheduleController::class, 'testRun']);

/* ── Phase 3: Database Backups ──────────────────────────────────────── */
$router->get('/backups', [BackupController::class, 'index']);
$router->post('/backups/run', [BackupController::class, 'run']);
$router->get('/backups/download', [BackupController::class, 'download']);
$router->post('/backups/restore', [BackupController::class, 'restore']);
$router->post('/backups/delete', [BackupController::class, 'delete']);

/* ── Phase 3: System Configuration, Health, Job Logs ────────────────── */
$router->get('/system/settings', [SystemController::class, 'settings']);
$router->post('/system/settings', [SystemController::class, 'saveSettings']);
$router->get('/system/health', [SystemController::class, 'health']);
$router->get('/system/job-logs', [SystemController::class, 'jobLogs']);

/* ── Phase 4: Employee Management ───────────────────────────────────── */
$router->get('/employees', [EmployeeController::class, 'index']);
$router->get('/employees/create', [EmployeeController::class, 'create']);
$router->post('/employees', [EmployeeController::class, 'store']);
$router->get('/employees/show', [EmployeeController::class, 'show']);
$router->get('/employees/edit', [EmployeeController::class, 'edit']);
$router->post('/employees/update', [EmployeeController::class, 'update']);
$router->post('/employees/activate', [EmployeeController::class, 'activate']);
$router->post('/employees/deactivate', [EmployeeController::class, 'deactivate']);
$router->post('/employees/change-status', [EmployeeController::class, 'changeStatus']);
$router->post('/employees/regenerate-qr', [EmployeeController::class, 'regenerateQR']);
$router->post('/employees/bulk-action', [EmployeeController::class, 'bulkAction']);
$router->get('/employees/export', [EmployeeController::class, 'export']);
$router->get('/employees/print-qr', [EmployeeController::class, 'printQR']);
$router->get('/employees/import', [EmployeeController::class, 'importForm']);
$router->post('/employees/import', [EmployeeController::class, 'import']);

/* ── Branch Management ───────────────────────────────────────────────── */
$router->get('/branches', [BranchController::class, 'index']);
$router->get('/branches/create', [BranchController::class, 'create']);
$router->post('/branches/store', [BranchController::class, 'store']);
$router->get('/branches/show', [BranchController::class, 'show']);
$router->get('/branches/edit', [BranchController::class, 'edit']);
$router->post('/branches/update', [BranchController::class, 'update']);
$router->post('/branches/activate', [BranchController::class, 'activate']);
$router->post('/branches/deactivate', [BranchController::class, 'deactivate']);

/* ── Department Management ───────────────────────────────────────────── */
$router->get('/departments', [DepartmentController::class, 'index']);
$router->get('/departments/create', [DepartmentController::class, 'create']);
$router->post('/departments/store', [DepartmentController::class, 'store']);
$router->get('/departments/show', [DepartmentController::class, 'show']);
$router->get('/departments/edit', [DepartmentController::class, 'edit']);
$router->post('/departments/update', [DepartmentController::class, 'update']);
$router->post('/departments/activate', [DepartmentController::class, 'activate']);
$router->post('/departments/deactivate', [DepartmentController::class, 'deactivate']);

/* ── Shift Management ────────────────────────────────────────────── */
$router->get('/shifts',              [ShiftController::class, 'index']);
$router->get('/shifts/create',       [ShiftController::class, 'create']);
$router->post('/shifts/store',       [ShiftController::class, 'store']);
$router->get('/shifts/show',         [ShiftController::class, 'show']);
$router->get('/shifts/edit',         [ShiftController::class, 'edit']);
$router->post('/shifts/update',      [ShiftController::class, 'update']);
$router->post('/shifts/delete',      [ShiftController::class, 'delete']);
$router->post('/shifts/activate',    [ShiftController::class, 'activate']);
$router->post('/shifts/deactivate',  [ShiftController::class, 'deactivate']);
$router->post('/shifts/set-default', [ShiftController::class, 'setDefault']);
$router->get('/shifts/employees',    [ShiftController::class, 'assignedEmployees']);
