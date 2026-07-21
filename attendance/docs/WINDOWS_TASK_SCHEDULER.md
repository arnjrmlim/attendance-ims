# Windows Task Scheduler — Background Jobs Setup Guide

**Integrated Management Services, Inc. — Attendance Management Portal**

The IMS Attendance Portal's background services (`/cron/*.php`) are
plain PHP CLI scripts. They are **not** run by a web server request — they
must be triggered on a schedule by **Windows Task Scheduler**, using the
PHP binary that ships with XAMPP.

This guide walks through registering all six jobs used in Phase 3.

---

## 0. Prerequisites

1. XAMPP is installed at `C:\xampp` and the project lives at
   `C:\xampp\htdocs\attendance-ims\attendance`.
   Adjust every path below if your installation differs.
2. MySQL/MariaDB (via XAMPP) is running as a Windows service, or set to
   auto-start, so scheduled jobs can reach the database even when no one
   is logged into phpMyAdmin.
3. `php.exe` is reachable at `C:\xampp\php\php.exe`. Confirm with:
   ```cmd
   C:\xampp\php\php.exe -v
   ```
4. Each script writes its own execution record to the `job_logs` table
   (visible at **Admin → Job Logs**) and to `attendance/logs/cron.log`,
   so you can always verify a task actually ran.
5. Each script uses a `.lock` file (in `attendance/logs/`) to prevent two
   copies of the same job from overlapping if a run takes longer than
   expected — safe to schedule aggressively.

---

## 1. Jobs and Recommended Schedules

| # | Script | Purpose | Recommended Trigger |
|---|--------|---------|----------------------|
| 1 | `cron/send_monthly_report.php` | Builds and emails the previous month's attendance report (Excel/PDF/CSV, optionally ZIP) to the Main Branch | Monthly, Day 1, 12:00 AM |
| 2 | `cron/daily_backup.php` | Creates the daily database backup; automatically escalates to weekly (Sundays) and monthly (1st) backups | Daily, 1:00 AM |
| 3 | `cron/retry_failed_emails.php` | Resends any queued/failed emails (e.g. reports that couldn't send because the internet was down) | Every 1 hour |
| 4 | `cron/system_cleanup.php` | Deletes expired sessions, old audit/job logs, stale temp files; optimizes tables; publishes scheduled announcements | Daily, 2:00 AM |
| 5 | `cron/archive_records.php` | Moves old attendance rows into archive tables to keep live tables fast | Monthly, Day 2, 3:00 AM |
| 6 | `cron/database_health_check.php` | Checks table integrity/disk space, auto-repairs crashed tables, alerts admins on problems | Daily, 4:00 AM |

Stagger the daily jobs (1:00 AM → 4:00 AM) so backups finish before
maintenance and health checks run against a table set that isn't mid-optimize.

---

## 2. Creating a Task (GUI method)

Repeat these steps once per job in the table above.

1. Open **Task Scheduler** (`taskschd.msc` or search "Task Scheduler" in
   the Start Menu).
2. In the right-hand panel, click **Create Task…** (not "Create Basic
   Task" — the full dialog gives more control).
3. **General tab**
   - Name: `IMS - Daily Backup` (match the job, e.g. `IMS - Monthly Report`,
     `IMS - Retry Failed Emails`, `IMS - System Cleanup`,
     `IMS - Archive Records`, `IMS - Database Health Check`)
   - Select **Run whether user is logged on or not**
   - Check **Run with highest privileges**
   - Configure for: Windows 10 / Windows 11
4. **Triggers tab** → **New…**
   - Set according to the schedule table above (e.g. Daily at 1:00 AM, or
     Monthly on day 1 at 12:00 AM, or "Repeat task every: 1 hour" for the
     retry job — set "for a duration of: Indefinitely").
5. **Actions tab** → **New…**
   - Action: **Start a program**
   - Program/script:
     ```
     C:\xampp\php\php.exe
     ```
   - Add arguments:
     ```
     C:\xampp\htdocs\attendance-ims\attendance\cron\daily_backup.php
     ```
     (swap in the correct script filename for each task)
   - Start in (optional but recommended):
     ```
     C:\xampp\htdocs\attendance-ims\attendance\cron
     ```
6. **Conditions tab**
   - Uncheck **Start the task only if the computer is on AC power** if this
     is a desktop/branch PC that's always plugged in.
   - Check **Wake the computer to run this task** if the PC may sleep.
7. **Settings tab**
   - Check **Run task as soon as possible after a scheduled start is
     missed** (covers the PC being off at 1:00 AM).
   - Check **If the task fails, restart every:** 10 minutes, up to 3 times.
   - Uncheck **Stop the task if it runs longer than:** or set a generous
     limit (e.g. 1 hour) so a slow backup isn't killed mid-write.
8. Click **OK**, then enter the Windows account password when prompted
   (required for "Run whether user is logged on or not").

### Verifying it worked

- Right-click the task → **Run** to trigger it immediately.
- Check `attendance/logs/cron.log` for a new `[INFO]` line.
- Check **Admin → Job Logs** in the web app for a `success` row.
- For the monthly report and retry jobs, check **Admin → Email Logs**.
- For backups, check **Admin → Backups**.

---

## 3. Creating Tasks via Command Line (`schtasks`)

If you prefer scripting the setup (or need to replicate it across several
branch PCs), each task can be created from an elevated Command Prompt.
Adjust `/RU` to the account that should run the task (`SYSTEM` works well
for unattended servers; use a real account if the task needs a mapped
network drive).

```cmd
:: 1. Monthly attendance report — 1st of month, 12:00 AM
schtasks /Create /TN "IMS - Monthly Report" ^
  /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\attendance-ims\attendance\cron\send_monthly_report.php\"" ^
  /SC MONTHLY /D 1 /ST 00:00 /RU SYSTEM /RL HIGHEST /F

:: 2. Daily database backup — 1:00 AM
schtasks /Create /TN "IMS - Daily Backup" ^
  /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\attendance-ims\attendance\cron\daily_backup.php\"" ^
  /SC DAILY /ST 01:00 /RU SYSTEM /RL HIGHEST /F

:: 3. Retry failed emails — every hour
schtasks /Create /TN "IMS - Retry Failed Emails" ^
  /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\attendance-ims\attendance\cron\retry_failed_emails.php\"" ^
  /SC HOURLY /MO 1 /ST 00:00 /RU SYSTEM /RL HIGHEST /F

:: 4. System cleanup — 2:00 AM daily
schtasks /Create /TN "IMS - System Cleanup" ^
  /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\attendance-ims\attendance\cron\system_cleanup.php\"" ^
  /SC DAILY /ST 02:00 /RU SYSTEM /RL HIGHEST /F

:: 5. Archive old records — 2nd of month, 3:00 AM
schtasks /Create /TN "IMS - Archive Records" ^
  /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\attendance-ims\attendance\cron\archive_records.php\"" ^
  /SC MONTHLY /D 2 /ST 03:00 /RU SYSTEM /RL HIGHEST /F

:: 6. Database health check — 4:00 AM daily
schtasks /Create /TN "IMS - Database Health Check" ^
  /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\attendance-ims\attendance\cron\database_health_check.php\"" ^
  /SC DAILY /ST 04:00 /RU SYSTEM /RL HIGHEST /F
```

Useful follow-up commands:

```cmd
:: List all IMS tasks and their last run result
schtasks /Query /TN "IMS - Daily Backup" /V /FO LIST

:: Run a task immediately (for testing)
schtasks /Run /TN "IMS - Daily Backup"

:: Delete a task
schtasks /Delete /TN "IMS - Daily Backup" /F
```

> **Note on `SYSTEM` account:** running as `SYSTEM` avoids password-expiry
> issues but has no mapped network drives and no interactive desktop —
> that's fine here since these scripts only need PHP CLI + MySQL access.
> If your MySQL data directory or backup storage path lives on a network
> share, use a dedicated service account (`/RU domain\svc-ims`) instead.

---

## 4. Where Output Goes

| Output | Location |
|---|---|
| Human-readable run log | `attendance/logs/cron.log` (auto-rotates past 5 MB) |
| Structured job history | `job_logs` table → **Admin → Job Logs** page |
| Email delivery attempts | `email_logs` table → **Admin → Email Logs** page |
| Backup history | `backup_logs` table → **Admin → Backups** page |
| Lock files (overlap guard) | `attendance/logs/*.lock` |

---

## 5. Troubleshooting

- **Task shows "0x1" or similar failure code:** Open Command Prompt and
  run the exact `php.exe` command manually to see the PHP error directly.
- **"Access is denied" writing logs/backups:** Ensure the account running
  the task has write access to `attendance/logs/` and your configured
  backup storage folder.
- **Task runs but nothing changes:** Check `job_logs` — a `failed` row
  will include the error. Common cause: MySQL service not running when
  the task fired at (e.g.) 1:00 AM.
- **Monthly report never emails:** Verify SMTP settings at
  **Admin → Email Settings** and use **Send Test Email** first; if the
  network is down, the report is saved locally and `retry_failed_emails.php`
  will pick it up automatically once connectivity returns.
