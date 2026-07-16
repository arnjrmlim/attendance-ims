# Attendance Management System (AMS)

A branch-based Attendance Management System built for Windows + XAMPP
(Apache, MySQL/MariaDB, PHP 8.3+). Designed to run unattended on a
branch's dedicated Attendance PC, with self-maintaining background
services for reporting, backups, and system health.

Built in three phases:

- **Phase 1** — Core attendance, authentication, roles, employees, and reporting foundation.
- **Phase 2** — Leave management, attendance corrections, holidays/calendar, notifications, audit trail, monitoring, search.
- **Phase 3** — Automation: manual attendance workflows, scheduled background jobs, automatic monthly email reports, database backups, system configuration/health, and announcements.

---

## Feature Overview

### Attendance & Workforce
- Attendance monitoring, calendar/holiday management, leave requests and approvals
- Attendance correction requests with admin approval workflow
- Full-text search across employees/records

### Phase 3 — Manual Attendance
- **Admin/HR manual entry**: create attendance records directly (with method, reason, remarks) when an employee forgets to clock in/out
- **Employee self-service**: submit a manual Time In / Time Out request with a reason; nothing changes until approved
- **Approval queue**: pending manual entries, corrections, and requests, with single/bulk approve/reject, search, and filters
- Validation rules prevent duplicate time-ins, time-out-before-time-in, and overlapping open sessions, with warnings for unusual entries

### Phase 3 — Automated Reporting
- Monthly attendance report auto-generated on the 1st of each month and emailed to the Main Branch (Excel/PDF/CSV, optional ZIP)
- SMTP configuration screen with connection test before saving
- Failed sends are saved locally and retried automatically once connectivity returns; admins are notified after repeated failures
- Full Email Logs screen: status, retry count, error message, manual resend, download

### Phase 3 — Backups & Database Maintenance
- Daily / weekly / monthly / manual backups via `mysqldump`, stored outside the public web root
- Backup history, download, restore, and delete, all audit-logged
- Scheduled maintenance: table optimize/repair, session/log cleanup, old-record archiving, and a database health check with disk/storage monitoring

### Phase 3 — System Administration
- System Configuration: company/branch info, attendance rules (grace period, working hours, overtime, late rules), time zone/date/time format, attendance-method toggles, security settings (session timeout, max login attempts)
- System Health dashboard: PHP/Apache/DB status, DB size, disk space, last backup, last email sent, failed jobs, uptime
- Announcements: create/edit/archive/schedule, targeted at everyone, a department, or an individual employee
- Notification system for both admins (failures, pending approvals, low storage) and employees (approvals, rejections, announcements)
- Expanded, read-only audit trail covering every Phase 3 action

---

## Tech Stack

- PHP 8.3+, plain MVC (no framework) — see `app/core/` for the minimal Router/Controller/Model/Database classes
- MySQL / MariaDB
- Bootstrap 5 (CDN) for UI
- `mysqldump`/`mysql` CLI for backup/restore
- Built-in SMTP client for email delivery (no external mail library required)
- Windows Task Scheduler for all background jobs (no cron/Linux dependency)

---

## Project Structure

```
attendance/
├── app/
│   ├── controllers/     # One controller per feature area
│   ├── services/        # Business logic (Backup, Email, Settings, Audit, ...)
│   ├── views/           # PHP view templates, grouped by feature
│   ├── core/            # Router, Controller, Model, Database
│   └── helpers/         # Global helper functions (auth, csrf, flash, etc.)
├── config/               # app.php, database.php
├── cron/                 # Background jobs — see docs/WINDOWS_TASK_SCHEDULER.md
├── database/
│   ├── schema.sql        # Full schema (fresh installs)
│   ├── seed.sql          # Demo data + accounts
│   └── migrations/       # phase2.sql (upgrade only), phase3.sql (required for every install)
├── docs/
│   ├── INSTALLATION_GUIDE.md
│   ├── WINDOWS_TASK_SCHEDULER.md
│   └── PHASE2_UPDATE_GUIDE.md
├── public/                # Apache document root (index.php front controller)
├── routes/web.php         # All HTTP routes
├── logs/                  # Cron logs + lock files (created automatically)
└── .env.example
```

---

## Getting Started

See **[`docs/INSTALLATION_GUIDE.md`](docs/INSTALLATION_GUIDE.md)** for full
setup instructions (XAMPP, database import, `.env`, backups, first login).

Then set up the scheduled jobs — see
**[`docs/WINDOWS_TASK_SCHEDULER.md`](docs/WINDOWS_TASK_SCHEDULER.md)**.

Quick start once XAMPP/MySQL are running:

```cmd
composer install
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE attendance_db CHARACTER SET utf8mb4"
C:\xampp\mysql\bin\mysql.exe -u root attendance_db < database\schema.sql
C:\xampp\mysql\bin\mysql.exe -u root attendance_db < database\migrations\phase3.sql
C:\xampp\mysql\bin\mysql.exe -u root attendance_db < database\seed.sql
```

> `database\migrations\phase3.sql` is **required**, not optional — it
> creates the Phase 3 tables (manual attendance, email logs, backups, job
> logs) that `schema.sql` doesn't yet include. See
> `docs/INSTALLATION_GUIDE.md` for details.

Visit `http://localhost/attendance-ims/attendance/public/` and log in with
the seeded admin account (`admin` / `Admin@123456` — **change this**).

---

## Roles

| Role | Access |
|---|---|
| **Administrator** | Full access: all Phase 3 admin modules (Backups, Email Settings/Logs, System Settings/Health/Job Logs), plus everything HR can do |
| **HR** | Manual attendance approvals, corrections, leave approvals, holidays, audit trail, reports |
| **Employee** | Self-service manual attendance requests, leave requests, attendance history, announcements, notifications |

Role checks are enforced server-side via `require_role()` /
`has_role()` (see `app/helpers/functions.php`) on every controller action —
UI links are hidden for unauthorized roles, but access is not solely
enforced by hiding the button.

---

## Security Notes

- CSRF tokens on every state-changing form (`csrf_field()` / `verify_csrf()`)
- Output escaped via `e()` (wraps `htmlspecialchars`) in every view
- Prepared statements (PDO) throughout — no raw SQL string interpolation of user input
- Every manual attendance action, approval/rejection, settings change, backup, and email event is written to the audit log, which is read-only from the UI
- Backups are stored outside `public/` so they are never web-accessible
- Session timeout and max login attempts are configurable at **Admin → System Settings**

---

## Support / Troubleshooting

Common setup issues and fixes are listed at the end of
`docs/INSTALLATION_GUIDE.md`. Background-job specific issues (Task
Scheduler, `job_logs`, lock files) are covered in
`docs/WINDOWS_TASK_SCHEDULER.md`.
