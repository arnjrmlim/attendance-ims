# Email Scheduling — Setup & Reference Guide

**Integrated Management Services, Inc. — Attendance Management Portal**

---

## Overview

The system sends bi-monthly attendance reports automatically:

| Send Date | Reporting Period | Example |
|---|---|---|
| **16th** of the month | 1st – 15th (same month) | July 16 → July 1–15 report |
| **1st** of the month | 16th – last day (previous month) | August 1 → July 16–31 report |

---

## Architecture — Single Pipeline

There is one canonical email pipeline (Pipeline A). The legacy `cron/send_monthly_report.php` (Pipeline B) is deprecated, guarded, and will not send emails as long as normal settings are applied.

```
Windows Task Scheduler
  │  (every hour via curl)
  ▼
EmailScheduleController::check()         [controllers/EmailScheduleController.php]
  ▼
EmailScheduleService::checkAndSendScheduled()
  │  reads: email_schedule, email_timezone, email_report_enabled
  │  checks: today's day (16 or 1) in correct timezone
  │  checks: periodAlreadySent() — duplicate prevention
  ▼
EmailScheduleService::executeReport()
  │  resolves: dateFrom / dateTo via resolvePeriod()
  │  generates: HTML body (summary stats, no per-employee table)
  │  generates: Excel attachment via AttendanceExcelReportService
  │  optionally: ZipArchive compression if email_report_compress=1
  ▼
EmailService::sendWithPeriod()
  │  queues: email_logs row with report_date_from / report_date_to
  │  sends:  To and Cc in visible headers
  │  sends:  Bcc in SMTP envelope ONLY (not visible in headers)
  ▼
EmailService::deliverSmtpWithBcc()       raw PHP socket SMTP
  ▼
SMTP server → recipient inbox
```

---

## Quick Start

### 1. Run Migrations

```bash
mysql -u root -p attendance_db < database/migrations/000_initialize_database.sql
mysql -u root -p attendance_db < database/migrations/000_seed_data.sql
mysql -u root -p attendance_db < database/migrations/phase5.sql
mysql -u root -p attendance_db < database/migrations/phase6_email_test_mode.sql
```

Or run the browser migration helper (existing install):
```
http://localhost/attendance-ims/attendance/public/migrate_email_test_mode.php
```

The self-healing schema in `EmailService` and `EmailScheduleService` will also add any missing `email_logs` columns automatically on first use.

### 2. Configure SMTP

**Admin → Email Settings** → fill in SMTP credentials → **Save** → **Send Test Email**.

### 3. Set the Schedule

In Email Settings → Email Schedule:

| Dropdown label | DB value | Actual trigger day | Report period |
|---|---|---|---|
| Manual Only | `manual` | Never | — |
| Mid-month Report (sent every 16th) | `15th` | **16th** | 1st–15th same month |
| End-of-month Report (sent every 1st) | `end_of_month` | **1st** | 16th–last day previous month |
| Both Reports (16th and 1st) | `both` | **16th and 1st** | Both halves |

> The DB values `15th` and `end_of_month` are legacy keys kept for backward compatibility. The trigger days are 16 and 1 respectively.

### 4. Configure Task Scheduler

Run the check every hour. The scheduler is idempotent — it only sends when today matches a trigger day AND the period has not already been sent.

```cmd
curl -s "http://localhost/attendance-ims/attendance/public/index.php?url=email-schedule/check"
```

Task Scheduler settings:
- Trigger: Daily, repeat every 1 hour indefinitely
- Action: Start a program → `curl.exe`
- Arguments: `-s "http://localhost/attendance-ims/attendance/public/index.php?url=email-schedule/check"`

---

## Period Resolution

The anchor date (today's date when the cron runs) maps to a reporting period:

```
Anchor day = 16  →  same month    1st to 15th
Anchor day =  1  →  previous month 16th to last day
Any other day    →  ad-hoc / test: best-fit half
```

PHP's `date('t', $timestamp)` calculates the actual last day, handling all month lengths and leap years automatically. No hardcoded values.

### Verified Scenarios

| Anchor Date | Report From | Report To | Notes |
|---|---|---|---|
| 2026-08-01 | 2026-07-16 | 2026-07-31 | ✅ |
| 2026-08-16 | 2026-08-01 | 2026-08-15 | ✅ |
| 2026-09-01 | 2026-08-16 | 2026-08-31 | ✅ |
| 2027-01-01 | 2026-12-16 | 2026-12-31 | ✅ Year boundary |
| 2025-03-01 | 2025-02-16 | 2025-02-28 | ✅ Non-leap February |
| 2028-03-01 | 2028-02-16 | 2028-02-29 | ✅ Leap year February |

---

## Email Format

### Subject Line

```
IMS – Attendance Report (July 2026 1–15)
IMS – Attendance Report (July 2026 16–31)
```

### Body

Clean summary card — no per-employee table:
- Period label and date range
- Trigger reason and timezone (with UTC offset)
- Generated timestamp
- Employees included count
- Attendance summary: Present · Late · Absent · Leave · Overtime hours · Undertime hours

### Attachment

Filename: `Attendance_Report_2026-07_01-15.xlsx` or `.zip` if compression is enabled.

Three sheets:
1. **Daily Attendance** — one row per employee per day, 21 columns matching the monitoring export
2. **Period Summary** — one row per employee with period totals
3. **Statistics** — aggregate metrics

---

## ZIP Compression

When **Compress to ZIP** is enabled in Email Settings:

1. `EmailScheduleService::executeReport()` reads `email_report_compress` setting
2. After Excel generation, `ZipArchive` wraps the `.xlsx` into a `.zip`
3. The `.xlsx` is deleted; the `.zip` is attached instead
4. The attachment filename becomes `Attendance_Report_2026-07_01-15.xlsx.zip`
5. Uses PHP's built-in `ZipArchive` — no third-party library required

---

## BCC Handling

BCC recipients receive the email but are **not visible in any email header**:

- **To:** and **Cc:** addresses appear in the visible message headers
- **Bcc:** addresses are sent only in the SMTP `RCPT TO` envelope commands
- Implemented in `EmailService::deliverSmtpWithBcc()` using separate `$envelopeRecipients` and `$headerTo`/`$headerCc` parameters

---

## Duplicate Prevention

Two-layer guard in `EmailScheduleService::periodAlreadySent()`:

**Layer 1 — `email_logs` query:**
```sql
SELECT COUNT(*) FROM email_logs
WHERE report_period LIKE '%2026-07-H2%'
  AND status = 'sent'
  AND (is_test_run = 0 OR is_test_run IS NULL)
```
The period key (`YYYY-MM-H1` or `YYYY-MM-H2`) is written into `body_preview` by `markPeriodSent()`.

**Layer 2 — `last_email_sent_date` setting (fallback):**
```php
$lastSent = $this->cfg->get('last_email_sent_date', '');
return $this->resolvePeriodKey($lastSent) === $periodKey;
```

| Scenario | Behavior |
|---|---|
| Cron runs every hour on send day | First run sends; all subsequent runs find the key in email_logs → skipped ✅ |
| Manual/test runs | `is_test_run=1` — never blocks production sends ✅ |
| Force send | Bypasses `periodAlreadySent()` entirely ✅ |

---

## Retry System

Failed emails are retried automatically by `cron/retry_failed_emails.php` (hourly).

**Retry with attachment regeneration (P5):**

`email_logs` now stores `report_date_from` and `report_date_to`. On retry:
1. `EmailService::resend()` reads these columns
2. Calls `AttendanceExcelReportService::generateForPeriod($dateFrom, $dateTo, $periodLabel)`
3. Attaches the freshly generated Excel to the retry send
4. Cleans up the file after delivery

This means retried emails always have a valid attachment, even though the original temp file was deleted after the first send attempt.

| Setting | DB key | Behavior |
|---|---|---|
| Retry Interval | `email_retry_interval` | Minutes before next retry attempt |
| Max Retries | `email_max_retries` | After this many failures, status becomes `failed` permanently |

Admins are notified via the notification system when emails reach permanent failure status.

---

## Safe Testing Mode

**Admin → Email Settings → Email Schedule Test** or `/email-schedule/test`

Administrator only.

### Quick-Pick Dates

| Button | Date | Resolves to |
|---|---|---|
| 16th | `YYYY-MM-16` | 1st–15th report for that month |
| 1st | `YYYY-MM-01` (next month) | 16th–last day report for current month |

### Run Modes

| Mode | Description |
|---|---|
| **Dry Run** | Resolves period, builds report, counts employees — does **not** send |
| **Normal Send** | Sends exactly as production would; duplicate guard active |
| **Force Send** | Sends immediately, bypassing duplicate guard |

---

## Manual Mode

When `email_schedule = manual`:

- `checkAndSendScheduled()` returns immediately with `skipped: true`
- No report is generated, no email is sent, no logs are written
- Task Scheduler still fires the HTTP request — it just gets a fast skip response
- Manual sends via the test page still work (they call `executeReport()` directly, bypassing `checkAndSendScheduled()`)
- Pipeline B (`send_monthly_report.php`) also exits immediately when `email_schedule = manual` ✅

---

## Pipeline B Status (Legacy Cron)

`cron/send_monthly_report.php` is **deprecated**. It is preserved as a fallback reference but will not send emails in normal operation because it now exits immediately when:

- `email_schedule = manual`, **OR**
- `email_report_enabled != 1`

If you are using the HTTP cron endpoint (Pipeline A), **disable or delete** the Pipeline B Task Scheduler task (`IMS - Monthly Report`) to avoid any possibility of conflict.

---

## Email Logs

| Column | Notes |
|---|---|
| `id` | UUID |
| `recipient` | Visible To address |
| `subject` | Email subject |
| `report_period` | e.g. `July 2026 (1–15)` |
| `body_preview` | First 500 chars + period key suffix |
| `attachment_path` | Original path (may no longer exist) |
| `status` | `sent` · `failed` · `queued` · `retrying` |
| `retry_count` | SMTP retry attempts |
| `is_test_run` | `1` = test/dry-run, `0` = production |
| `simulated_date` | Date used in test run |
| `report_date_from` | Period start — used to regenerate attachment on retry |
| `report_date_to` | Period end — used to regenerate attachment on retry |
| `last_error` | Last SMTP error |
| `sent_at` | Delivery timestamp |

---

## API Endpoints

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `GET` | `/email-schedule/check` | Admin or localhost | Production cron trigger |
| `GET` | `/email-schedule/status` | Admin | JSON with correct next-send dates |
| `GET` | `/email-schedule/test` | Admin | Safe testing UI |
| `POST` | `/email-schedule/test-run` | Admin | Execute test/dry-run/force |
| `GET` | `/email-schedule/test-logs` | Admin | JSON recent test entries |

### `/email-schedule/status` Response (fixed in P3)

```json
{
  "schedule": "both",
  "timezone": "Asia/Manila",
  "last_sent_date": "2026-07-16",
  "next_send_dates": [
    { "send_date": "2026-08-16", "label": "2026-08-16 — Mid-month report",     "period": "August 2026 (1–15)" },
    { "send_date": "2026-08-01", "label": "2026-08-01 — End-of-month report",  "period": "July 2026 (16–31)" }
  ],
  "current_date": "2026-07-21",
  "current_day": 21,
  "days_in_month": 31
}
```

---

## Settings Reference

| Key | Default | Description |
|---|---|---|
| `email_schedule` | `manual` | `manual` · `15th` · `end_of_month` · `both` |
| `email_timezone` | `UTC` | Timezone for send-date calculation |
| `last_email_sent_date` | `NULL` | Last date a production send succeeded |
| `email_report_enabled` | `0` | Must be `1` for any automatic sends |
| `email_report_recipient` | — | Primary To address |
| `email_report_cc` | — | CC addresses — appear in Cc: header |
| `email_report_bcc` | — | BCC addresses — envelope only, hidden from headers |
| `email_report_compress` | `0` | `1` = ZIP the Excel before attaching |
| `email_max_retries` | `5` | Max SMTP retry attempts before permanent failure |
| `email_retry_interval` | `60` | Minutes between retry attempts |

---

## Troubleshooting

**Email not sent on the 16th or 1st**
- Check `email_report_enabled = 1` in settings.
- Check `email_report_recipient` is set.
- Run a Dry Run from the test page and check the result panel.
- Check `/email-logs` for SMTP errors.

**Wrong period in the report**
- Confirm anchor date: day 16 → 1–15 same month; day 1 → 16–end previous month.
- Run a dry run for the exact date and check `date_from` / `date_to` in the result.

**Duplicate sends**
- The period key (`YYYY-MM-H1`/`H2`) is embedded in `body_preview` of the sent log row.
- Test runs (`is_test_run=1`) never block production sends.
- Use Force Send to intentionally re-send a period that was already sent.

**Retried email has no attachment**
- Check that `report_date_from` and `report_date_to` columns exist in `email_logs`.
- Run `migrate_email_test_mode.php` if the columns are missing.
- `EmailService::resend()` will regenerate the Excel from those stored dates.

**ZIP not being created**
- Ensure `email_report_compress = 1` in settings.
- Verify PHP's `ZipArchive` extension is enabled (`php -m | grep zip` or check `phpinfo()`).
- A failed ZIP silently falls back to attaching the raw `.xlsx`.

---

## Windows Task Scheduler — Recommended Setup

Only one task is needed:

```cmd
:: IMS Email Schedule — runs hourly, lets the app decide when to send
schtasks /Create /TN "IMS - Email Schedule Check" ^
  /TR "curl -s \"http://localhost/attendance-ims/attendance/public/index.php?url=email-schedule/check\"" ^
  /SC HOURLY /MO 1 /ST 00:00 /RU SYSTEM /RL HIGHEST /F
```

Remove or disable the old monthly-report task if it exists:

```cmd
schtasks /Delete /TN "IMS - Monthly Report" /F
```
