# Email Scheduling — Setup & Reference Guide

**Integrated Management Services, Inc. — Attendance Management Portal**

## Overview

The system sends bi-monthly attendance reports automatically:

| Send Date | Reporting Period | Example |
|-----------|----------------|---------|
| **16th** of the month | 1st – 15th (same month) | July 16 → July 1–15 report |
| **1st** of the month | 16th – last day (previous month) | August 1 → July 16–31 report |

This gives HR at least one full day to review before reports are emailed.

---

## Quick Start

### 1. Run Migrations

```bash
# Base schema + seed data
mysql -u root -p attendance_db < database/migrations/000_initialize_database.sql
mysql -u root -p attendance_db < database/migrations/000_seed_data.sql

# Email scheduling settings
mysql -u root -p attendance_db < database/migrations/phase5.sql

# Safe testing mode columns on email_logs
mysql -u root -p attendance_db < database/migrations/phase6_email_test_mode.sql
```

Or use the browser migration helpers if the database already exists:
```
http://localhost/attendance-ims/attendance/public/migrate_shifts.php
http://localhost/attendance-ims/attendance/public/migrate_email_test_mode.php
```

### 2. Configure SMTP

**Admin → Email Settings** → fill in SMTP credentials → **Save** → **Send Test Email**.

### 3. Set the Schedule

In Email Settings → Email Schedule:

| Setting value | Behaviour |
|---|---|
| `manual` | No automatic sends |
| `15th` | Sends on the **16th** (first-half report) |
| `end_of_month` | Sends on the **1st** (second-half report) |
| `both` | Sends on **both** the 16th and the 1st |

> The setting names (`15th`, `end_of_month`) are legacy keys kept for backward compatibility.  
> The actual trigger days are now **16** and **1** respectively.

### 4. Set Up the Cron Job (Windows Task Scheduler)

Run the schedule check every hour. The scheduler is idempotent — it only sends when today matches a trigger day AND the period has not already been sent.

| Field | Value |
|---|---|
| Trigger | Daily, repeat every 1 hour (or just run at 12:00 AM daily) |
| Action | `curl -s "http://localhost/attendance-ims/attendance/public/index.php?url=email-schedule/check"` |

Alternatively, call it via PHP CLI:
```
"C:\xampp\php\php.exe" -r "file_get_contents('http://localhost/attendance-ims/attendance/public/index.php?url=email-schedule/check');"
```

---

## How Period Resolution Works

The scheduler maps the **send date** (today's date) to the correct **reporting period**:

```
Send date day = 16  →  period = YYYY-MM-01  to  YYYY-MM-15   (same month)
Send date day =  1  →  period = prev-YYYY-prev-MM-16  to  prev-YYYY-prev-MM-{last}
Ad-hoc day ≤ 15    →  period = YYYY-MM-01  to  YYYY-MM-15   (test convenience)
Ad-hoc day > 15    →  period = YYYY-MM-16  to  YYYY-MM-{last} (test convenience)
```

Month length is always calculated dynamically — no hardcoded 28/30/31 values.

---

## Email Format

### Subject Line

```
IMS – Attendance Report (July 2026 1–15)
IMS – Attendance Report (July 2026 16–31)
```

### Email Body

Clean summary only — no per-employee table. The email body contains:

- Period label and date range
- Trigger reason and timezone
- Generated timestamp
- Employees included count
- **Attendance Summary table**: Present · Late · Absent · Leave · Overtime Hours · Undertime Hours

The full detail is in the Excel attachment.

### Excel Attachment

Filename pattern:
```
Attendance_Report_2026-07_01-15.xlsx
Attendance_Report_2026-07_16-31.xlsx
```

The attachment uses the exact same data and format as **Reports → Attendance Monitoring → Download Excel**. There is a single shared generator (`AttendanceExcelReportService`) used by both.

**Sheets:**

| Sheet | Contents |
|---|---|
| Daily Attendance | One row per employee per day: Date · Day · Status · Leave Type · Time In/Out · Break In/Out · OT In/Out · Late · Break · OT · Undertime · Hours Worked · Shift |
| Period Summary | One row per employee with period totals: Days Present/Late/Absent/Leave · Total Hours · Total Late/Undertime/OT/Break minutes |
| Statistics | Aggregate metrics: employees, attendance rate, present/late/absent/leave counts, total late/undertime/overtime hours |

---

## Safe Testing Mode

**Admin → Email Settings → Email Schedule Test** (or `GET /email-schedule/test`)

Accessible to **Administrator** only. Never visible to HR, Supervisors, or Employees.

### Quick-Pick Dates

The test page pre-fills the two canonical send dates:

| Button | Date | Resolves to |
|---|---|---|
| 16th button | `YYYY-MM-16` | 1st–15th report for that month |
| 1st button | `YYYY-MM-01` (next month) | 16th–last day report for current month |

### Run Modes

| Mode | Description |
|---|---|
| **Dry Run** (default) | Builds the report and Excel file, resolves the period, counts employees — does **not** send the email |
| **Normal Send** | Sends exactly as production would; duplicate guard is active |
| **Force Send** | Sends immediately, bypassing the duplicate guard |

### Duplicate Guard

When **Normal Send** is used and the period was already sent, the UI shows:

> "Report for period `July 2026 (1–15)` was already sent."
>
> [ Cancel ] [ Resend Anyway (Force Send) ]

### Test Log

Every execution is logged to `email_logs` with `is_test_run = 1` and `simulated_date` set. The last 30 test entries appear in the **Recent Test Executions** panel at the bottom of the test page.

---

## API Endpoints

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `GET` | `/email-schedule/check` | Admin or localhost | Production cron trigger |
| `GET` | `/email-schedule/status` | Admin | JSON schedule status |
| `GET` | `/email-schedule/test` | Admin | Safe testing UI |
| `POST` | `/email-schedule/test-run` | Admin | Execute test/dry-run/force |
| `GET` | `/email-schedule/test-logs` | Admin | JSON recent test entries |

### POST `/email-schedule/test-run` Params

| Param | Required | Values |
|---|---|---|
| `simulated_date` | Yes | Any `YYYY-MM-DD` |
| `mode` | No | `dry_run` (default) · `normal` · `force_send` |
| `_csrf` | Yes | Auto-included by form |

### Response Example (Dry Run)

```json
{
  "success": true,
  "dry_run": true,
  "message": "Dry run completed. Email was NOT sent.",
  "recipient": "hr@company.com",
  "subject": "IMS – Attendance Report (July 2026 1–15)",
  "period_label": "July 2026 (1–15)",
  "date_from": "2026-07-01",
  "date_to": "2026-07-15",
  "attachment_name": "Attendance_Report_2026-07_01-15.xlsx",
  "employee_count": 12,
  "already_sent": false
}
```

---

## Shared Excel Service

`AttendanceExcelReportService` is the single source of truth for all Excel exports:

```
Reports → Attendance Monitoring → Download Excel
    └── ReportController::exportExcel()
            └── AttendanceExcelReportService::buildExcel()

Email attachment (scheduled or test)
    └── EmailScheduleService::executeReport()
            └── AttendanceExcelReportService::generateForPeriod()
                        └── AttendanceExcelReportService::buildExcel()
```

Both paths call `AttendanceService::monitor()` for data, so calculations are always identical.

---

## Database Schema — `email_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | `CHAR(36)` | UUID |
| `recipient` | `VARCHAR(255)` | To address |
| `subject` | `VARCHAR(255)` | Email subject |
| `report_period` | `VARCHAR(80)` | e.g. `July 2026 (1–15)` |
| `body_preview` | `TEXT` | First 500 chars of body |
| `attachment_path` | `VARCHAR(500)` | Path to Excel file |
| `status` | `ENUM` | `sent` · `failed` · `queued` · `retrying` |
| `retry_count` | `TINYINT` | SMTP retry count |
| `is_test_run` | `TINYINT(1)` | `1` = test · `0` = production |
| `simulated_date` | `DATE` | Date simulated during test |
| `last_error` | `TEXT` | Last SMTP error |
| `sent_at` | `DATETIME` | Delivery time |
| `next_retry_at` | `DATETIME` | Next retry time |
| `created_at` | `DATETIME` | Row creation |
| `updated_at` | `DATETIME` | Last update |

---

## Settings Reference

| Key | Default | Description |
|---|---|---|
| `email_schedule` | `manual` | `manual` · `15th` · `end_of_month` · `both` |
| `email_timezone` | `UTC` | Timezone for send-date calculation |
| `last_email_sent_date` | `NULL` | Last date a production send succeeded |
| `email_report_enabled` | `0` | Must be `1` to allow any sends |
| `email_report_recipient` | — | Primary To address |
| `email_report_cc` | — | CC address(es) |
| `email_report_bcc` | — | BCC address(es) |
| `email_report_compress` | `0` | ZIP the Excel attachment |
| `email_max_retries` | `5` | Max SMTP retry attempts |
| `email_retry_interval` | `60` | Minutes between retries |

---

## Troubleshooting

**Email not sent on the 16th or 1st**
- Check `email_report_enabled = 1` in settings.
- Check `email_report_recipient` is configured.
- Run a Dry Run from the test page — if it fails, check the error message.
- Check `/email-logs` for SMTP errors.

**Wrong period in report**
- Confirm the send date: day 16 → 1–15 same month; day 1 → 16–end previous month.
- Run a dry run for the exact date you expect; the result panel shows `date_from` / `date_to`.

**Duplicate send**
- The duplicate guard checks `email_logs` for `status=sent, is_test_run=0` with the matching period key.
- Test runs (`is_test_run=1`) never block production sends.
- Use Force Send if you intentionally need to re-send a period.

**Excel attachment missing or empty**
- PhpSpreadsheet must be installed: `composer require phpoffice/phpspreadsheet`
- Check `sys_get_temp_dir()` is writable on your server.
- A dry run will show `attachment_name` if Excel generation succeeded.
