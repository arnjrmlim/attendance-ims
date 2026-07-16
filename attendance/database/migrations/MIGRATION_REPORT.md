# Migration Report — Attendance Management System

## Status: Consolidated & Repaired

---

## Master Migration

| File | Purpose | Run Order |
|------|---------|-----------|
| `000_initialize_database.sql` | Create every table, index, FK, constraint | 1st |
| `000_seed_data.sql` | Insert roles, permissions, branches, departments, shifts, employees, users, settings | 2nd |
| `upgrade_users_profile_picture.sql` | Safe upgrade for existing DBs missing `profile_picture` column and extended `employee_timeline` ENUM | On existing DB only |

---

## Changes Made in This Repair Pass

### `000_initialize_database.sql`

| Change | Detail |
|--------|--------|
| **Added** `profile_picture` column to `users` table | `VARCHAR(255) DEFAULT NULL` — stores relative path under `uploads/avatars/` |
| **Extended** `employee_timeline.event_type` ENUM | Added: `account_created`, `account_updated`, `account_deleted`, `role_changed`, `password_changed`, `profile_picture_updated` |

### `000_seed_data.sql`

| Change | Detail |
|--------|--------|
| **Fixed** seed user INSERT | Now explicitly sets `must_change_password = 0` and `password_changed_at = NOW()` so seed accounts can log in immediately without being forced to a password change |

---

## Bug Fixes (PHP Code)

### Routing — Page Not Found after Employee Creation

**Root cause:** `employees/view.php` credential modal hard-coded the URL as  
`employees/view?id=...` but the registered route is `GET /employees/show`.

**Fix:** Both the "View Employee Profile" button and the "Close" button now use  
`url('employees/show?id=' . $credentials['employee_id'])`.

---

## New Features Implemented

### Employee Profile Page (`/employees/show`)

- Added `EmployeeService::getAttendanceSummary()` — queries `attendance_summary` for Present / Late / Absent / Overtime counts.
- `EmployeeController::show()` passes `$attendance` to the view.
- View rebuilt with all required sections:
  - Attendance Summary Cards
  - Personal Information
  - Employment Information
  - Contact Information
  - Account Information
  - Employee Timeline (with icons per event type)

### Profile Settings (`/profile`)

- **New:** `ProfileService` — handles password change, picture upload/remove, timeline logging.
- **New:** `ProfileController` — routes: `GET /profile`, `POST /profile/password`, `POST /profile/picture`, `POST /profile/picture/remove`.
- **New:** `app/views/profile/index.php` — My Profile page with Personal Info, Account Info, Change Password, Profile Picture upload/remove.
- **New routes** added to `routes/web.php`.
- **Navbar** updated: old standalone logout button replaced with a user dropdown containing Notifications, Profile Settings, and Logout.

### First-Login Password Change Enforcement

- `AuthController::login()` now reads `must_change_password` from the DB after successful credential verification.
- If `must_change_password = 1`, the user is redirected to `/profile` with an informational flash message.
- `BaseController::render()` enforces the gate on every rendered page — if `must_change_password = 1` in session, only `profile/index` is allowed to render.
- After a successful password change, `ProfileService::changePassword()` sets `must_change_password = 0` and `password_changed_at = NOW()`. `ProfileController` clears the flag from the live session immediately.
- `$_SESSION['user']` now includes `profile_picture` so the navbar avatar updates without a round-trip.

### Password Change Validation

| Rule | Error Message |
|------|---------------|
| Current password must match | "Current password is incorrect." |
| New password must not be empty | "Please enter a new password." |
| New password must differ from current | "New password cannot be the same as your current password." |
| Confirmation must match | "The new password and confirmation password do not match." |

No complexity requirements (internal system).

### Profile Picture

- Accepted types: JPG, JPEG, PNG, WEBP
- Maximum size: 2 MB
- Stored under `uploads/avatars/{uuid}.{ext}`
- Old picture automatically deleted on replace or remove
- MIME type verified with `finfo`, not just file extension

### Audit Logging

Every profile action generates an audit log entry:

| Action | `audit_logs.action` |
|--------|---------------------|
| Password changed | `PASSWORD_CHANGED` |
| Picture uploaded | `PROFILE_PICTURE_UPDATED` |
| Picture removed | `PROFILE_PICTURE_REMOVED` |

Plus `employee_timeline` entries for linked employees:

| Event | `event_type` |
|-------|-------------|
| Password changed | `password_changed` |
| Picture uploaded/removed | `profile_picture_updated` |

---

## Dependency Graph (Table Creation Order)

```
roles
permissions
role_permissions

branches
departments
shifts

employees          (FKs: departments, branches, shifts; circular FKs added via ALTER)
users              (FKs: roles; circular FK to employees added via ALTER)

attendance
attendance_summary
holidays

leave_requests
attendance_corrections
notifications
leave_balances
announcements

audit_logs
user_sessions
settings

manual_attendance_requests
admin_manual_attendance
email_logs
backup_logs
job_logs

employee_timeline
employee_imports

── Phase 2 (ALTER TABLE) ──
employees.created_by → users.id
employees.updated_by → users.id
users.employee_id   → employees.id
```

---

## How to Initialize a Fresh Database

```bash
# 1. Create empty database
mysql -u root -p -e "CREATE DATABASE attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Run master migration
mysql -u root -p attendance_db < database/migrations/000_initialize_database.sql

# 3. Run seed data
mysql -u root -p attendance_db < database/migrations/000_seed_data.sql
```

### Default Login
| Username | Password | Role |
|----------|----------|------|
| `admin` | `Admin@123456` | Administrator |
| `hr_maria` | `Admin@123456` | HR |
| `emp_jose` | `Admin@123456` | Employee |

---

## How to Upgrade an Existing Database

```bash
mysql -u root -p attendance_db < database/migrations/upgrade_users_profile_picture.sql
```

---

## Obsolete / Superseded Migration Files

The following files were part of incremental development and are **superseded** by `000_initialize_database.sql`. Do **not** run them on a database already initialized from the master migration — doing so will produce duplicate-column and duplicate-key errors.

| File | Why Obsolete |
|------|-------------|
| `phase2.sql` | Contained in `000_initialize_database.sql` |
| `phase3.sql` | Contained in `000_initialize_database.sql` |
| `phase4.sql` | Contained in `000_initialize_database.sql` |
| `phase5.sql` | Contained in `000_initialize_database.sql` |
| `update_branches_table.sql` | Contained in `000_initialize_database.sql` |
| `update_departments_table.sql` | Contained in `000_initialize_database.sql` |

These files are kept for reference only. They should not be executed in a production environment.
