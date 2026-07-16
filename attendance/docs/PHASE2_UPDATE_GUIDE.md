# Attendance Management System Phase 2 Update Guide

## Requirements

- Windows with XAMPP Apache, PHP 8.3 or newer, and MySQL/MariaDB
- Existing Phase 1 database named `attendance_db`
- PDO MySQL extension enabled

## New Installation

1. Copy the `attendance` folder into `C:\xampp\htdocs\attendance-ims`.
2. Create the database objects:
   ```sql
   SOURCE C:/xampp/htdocs/attendance-ims/attendance/database/schema.sql;
   SOURCE C:/xampp/htdocs/attendance-ims/attendance/database/seed.sql;
   ```
3. Visit `http://localhost/attendance-ims/attendance/public`.
4. Sign in with the seeded administrator account:
   - Username: `admin`
   - Password: `Admin@123456`

## Updating an Existing Phase 1 Installation

1. Back up the database.
2. Run:
   ```sql
   SOURCE C:/xampp/htdocs/attendance-ims/attendance/database/migrations/phase2.sql;
   ```
3. Run only the Phase 2 seed statements from `database/seed.sql` if sample leave, holiday and notification records are wanted.
4. Confirm Apache points to `attendance/public` or open `http://localhost/attendance-ims/attendance/public`.

## Phase 2 Modules

- Leave Management: employee request submission, admin/HR approval, rejection, cancellation, working-day calculation, holiday exclusion and overlap prevention.
- Attendance Corrections: employee correction requests, pending review workflow, approval and rejection audit logging.
- Attendance Monitoring: date, employee, department, branch and status filters.
- Reports: report filters, totals, print layout, CSV export and spreadsheet-compatible export.
- Notifications: unread badge, read/delete actions and workflow notifications.
- Calendar: month view showing attendance summaries and holidays.
- Holiday Management: regular, special, company and branch holiday records with duplicate-date prevention by branch scope.
- Audit Trail: immutable append-only activity log with previous/new values, username, computer, timestamp and IP address.
- Global Search: reusable search across employees, departments, leaves, corrections and notifications.

## Security Notes

- All database writes use PDO prepared statements.
- Forms include CSRF protection.
- Output is escaped with `e()`.
- Uploads are size-limited, extension-validated and renamed with randomized UUID filenames.
- Admin and HR actions are role-gated.
- Approval, rejection, cancellation, login and holiday changes are audit logged.

## Production Checklist

- Change seeded passwords immediately.
- Configure database credentials in environment variables or `config/database.php`.
- Restrict direct web access to `uploads`, `logs`, `database`, `app` and `config`.
- Enable HTTPS in production.
- Schedule database backups.
