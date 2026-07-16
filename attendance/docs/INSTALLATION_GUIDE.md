# Installation Guide — Attendance Management System

Target environment: **Windows 10 / 11 + XAMPP (Apache, MySQL/MariaDB, PHP 8.3+)**,
running standalone on a branch's dedicated Attendance PC.

---

## 1. Requirements

| Component | Version |
|---|---|
| Windows | 10 or 11 |
| XAMPP | Latest, bundling PHP 8.3+ and MySQL/MariaDB |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip` (all enabled by default in XAMPP) |
| Composer | Optional but recommended (for `vlucas/phpdotenv`, `ramsey/uuid`, `phpmailer/phpmailer`) |
| Browser | Any modern browser (Chrome, Edge, Firefox) |

---

## 2. Get the Code onto the PC

Place the project so that the `public/` folder is reachable through Apache.
Recommended layout:

```
C:\xampp\htdocs\attendance-ims\attendance\   <- project root (this repo)
    app\
    config\
    cron\
    database\
    docs\
    public\        <- Apache document root should point here (or use the .htaccess front controller)
    routes\
    logs\           <- created automatically on first cron run
```

If you keep the whole `attendance-ims` folder under `htdocs`, the app is
reached at:

```
http://localhost/attendance-ims/attendance/public/
```

This matches the default `base_url` in `config/app.php`
(`/attendance-ims/attendance/public`). If you deploy elsewhere, update that
value to match.

---

## 3. Install PHP Dependencies (Composer)

From the project root (`attendance/`):

```cmd
composer install
```

This installs `vlucas/phpdotenv`, `ramsey/uuid`, and `phpmailer/phpmailer`,
and creates `.env` automatically from `.env.example` (see the
`post-install-cmd` script in `composer.json`).

> If Composer isn't available on the branch PC, the app still runs without
> `vendor/` — `public/index.php` and `cron/bootstrap.php` both fall back to
> a lightweight built-in autoloader. You'll only lose `.env` support and the
> optional PHPMailer path (the app's built-in SMTP client doesn't need it).

---

## 4. Create the Database

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or use the MySQL CLI.
3. Create a database:
   ```sql
   CREATE DATABASE attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Import the schema (creates every Phase 1 + 2 table):
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root attendance_db < C:\xampp\htdocs\attendance-ims\attendance\database\schema.sql
   ```
5. **Required for every install (fresh or upgrade):** apply the Phase 3
   migration. `schema.sql` does not yet include the Phase 3 tables
   (`manual_attendance_requests`, `admin_manual_attendance`, `email_logs`,
   `backup_logs`, `job_logs`) or the Phase 3 columns on `announcements` —
   those only exist in this migration file:
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root attendance_db < C:\xampp\htdocs\attendance-ims\attendance\database\migrations\phase3.sql
   ```
   This step is safe to re-run any time (`CREATE TABLE IF NOT EXISTS`,
   `ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE` throughout), so if in doubt,
   run it again.
6. (Optional) Load sample/demo data:
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root attendance_db < C:\xampp\htdocs\attendance-ims\attendance\database\seed.sql
   ```
   This creates three demo accounts (username / password `Admin@123456`
   for all): `admin` (Administrator), `hr_maria` (HR), `emp_jose` (Employee).
   **Change these passwords immediately in a production deployment.**

### Upgrading an existing Phase 1/2 install

If you already have a Phase 1/2 database (created before this Phase 3
work), don't re-run `schema.sql` (it's meant for fresh installs and will
fail on tables that already exist). Instead apply the incremental
migrations in order:

```cmd
C:\xampp\mysql\bin\mysql.exe -u root attendance_db < database\migrations\phase2.sql
C:\xampp\mysql\bin\mysql.exe -u root attendance_db < database\migrations\phase3.sql
```

### "Table doesn't exist" errors after following this guide

If you see an error like
`Base table or view not found: 'attendance_db.manual_attendance_requests'`
(or `email_logs`, `backup_logs`, `job_logs`, `admin_manual_attendance`),
it means step 5 above was skipped. Run the `phase3.sql` command shown
in step 5 — it's safe to run at any time, including on a database
that's already partially set up.

---

## 5. Configure the Environment

Copy `.env.example` to `.env` in the project root and adjust:

```cmd
copy .env.example .env
```

At minimum, set your database credentials if they differ from the XAMPP
defaults (`root` / no password). SMTP and backup paths can also be set
here, but it's usually easier to configure them from the UI once the app
is running (**Admin → Email Settings**, **Admin → System Settings**) since
those are stored in the database and take precedence.

> `.env` loading requires the two `Dotenv::createImmutable(...)->safeLoad()`
> lines described at the top of `.env.example` to be added to
> `public/index.php` and `cron/bootstrap.php`. Without them, the app still
> works using the defaults baked into `config/*.php`, but env-var overrides
> won't take effect until you add those two lines.

---

## 6. Configure Backup Storage

Create a folder **outside** `public/` to store database backups, e.g.:

```cmd
mkdir C:\xampp\attendance-backups
```

Set this path at **Admin → System Settings** (Backup Settings section) or
via `BACKUP_STORAGE_PATH` in `.env`. Make sure the Windows account running
Apache/Task Scheduler has write access to this folder.

---

## 7. First Login and Initial Setup

1. Visit `http://localhost/attendance-ims/attendance/public/login`.
2. Log in with the seeded `admin` account (or your own, if you skipped
   `seed.sql` and created a user manually).
3. Go to **Admin → System Settings** and set:
   - Company Name, Branch Name, Company Logo
   - Working Hours, Grace Period, Overtime Rules
   - Time Zone, Date/Time Format
   - Session Timeout, Maximum Login Attempts
4. Go to **Admin → Email Settings**, enter your SMTP details, and click
   **Send Test Email** to confirm delivery before relying on the monthly
   report job.
5. Go to **Admin → Backups** and click **Run Backup Now** once to confirm
   `mysqldump` is reachable and the backup folder is writable.

---

## 8. Register Background Jobs

Follow **`docs/WINDOWS_TASK_SCHEDULER.md`** to register the six scheduled
PHP scripts (`cron/*.php`) in Windows Task Scheduler. This is what turns
the app from "runs when someone opens a browser" into a self-maintaining
system: monthly email reports, daily backups, retrying failed emails,
cleanup, archiving, and health checks all run unattended.

---

## 9. Verify Everything Is Wired Up

- [ ] Login works for admin/HR/employee roles
- [ ] **Admin → Backups** shows a successful manual backup
- [ ] **Admin → Email Settings → Send Test Email** delivers
- [ ] **Manual Attendance → Request** (employee) creates a Pending request
- [ ] **Manual Attendance → Approvals** (admin) can approve/reject it
- [ ] **Admin → System Health** shows PHP/DB/disk/uptime info
- [ ] Task Scheduler tasks all show a successful "Last Run Result" after
      running each one manually once (`schtasks /Run /TN "..."`)
- [ ] `attendance/logs/cron.log` is being written to after each job runs

---

## 10. Common Issues

| Symptom | Likely Cause | Fix |
|---|---|---|
| Blank page / 500 error | PHP version < 8.3, or missing `pdo_mysql` extension | Check `php -v`, enable extensions in `php.ini`, restart Apache |
| "Page not found" on every route except `/` | `base_url` in `config/app.php` doesn't match your actual folder path | Update `base_url` to match where `public/` is served from |
| Backup fails with "mysqldump failed" | `mysqldump.exe` not found | Confirm XAMPP MySQL bin is at `C:\xampp\mysql\bin\`, or add it to PATH |
| Monthly report never sends | SMTP not configured / wrong credentials | Configure and test at **Admin → Email Settings**; check **Admin → Email Logs** for the exact error |
| Scheduled task doesn't run | MySQL service not running at trigger time, or wrong account permissions | Set MySQL to auto-start as a Windows service; run task as `SYSTEM` or a service account with folder write access |
