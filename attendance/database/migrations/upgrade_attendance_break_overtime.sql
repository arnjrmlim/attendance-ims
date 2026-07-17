-- ============================================================
-- Upgrade: Break & Overtime Attendance Support
-- Version: 2 (complete + idempotent)
--
-- Run against an EXISTING attendance_db that was initialised
-- before the break/OT enhancement was added.
--
-- Safe to run multiple times — every DDL statement is guarded
-- by an INFORMATION_SCHEMA existence check.
-- ============================================================

USE `attendance_db`;

-- ────────────────────────────────────────────────────────────
-- 1. attendance.attendance_type — extend ENUM
--    Old: time_in | lunch_out | lunch_in | time_out
--    New: + break_out | break_in | overtime_in | overtime_out
--    lunch_out / lunch_in kept as legacy aliases.
-- ────────────────────────────────────────────────────────────
ALTER TABLE `attendance`
  MODIFY COLUMN `attendance_type`
    ENUM(
      'time_in',
      'break_out',
      'break_in',
      'time_out',
      'overtime_in',
      'overtime_out',
      'lunch_out',
      'lunch_in'
    ) NOT NULL;

-- ────────────────────────────────────────────────────────────
-- 2. attendance — drop the unique constraint that prevents
--    multiple taps of the same type per day.
-- ────────────────────────────────────────────────────────────
SET @idx_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'attendance'
      AND INDEX_NAME   = 'uq_attendance_type'
);
SET @drop_idx := IF(@idx_exists > 0,
    'ALTER TABLE `attendance` DROP INDEX `uq_attendance_type`',
    'SELECT ''uq_attendance_type already removed'' AS status'
);
PREPARE _s FROM @drop_idx; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ────────────────────────────────────────────────────────────
-- 3. attendance_summary — add break / overtime timestamp cols
-- ────────────────────────────────────────────────────────────

-- break_out
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance_summary' AND COLUMN_NAME='break_out');
SET @q := IF(@c=0,'ALTER TABLE `attendance_summary` ADD COLUMN `break_out` DATETIME DEFAULT NULL AFTER `time_in`','SELECT 1');
PREPARE _s FROM @q; EXECUTE _s; DEALLOCATE PREPARE _s;

-- break_in
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance_summary' AND COLUMN_NAME='break_in');
SET @q := IF(@c=0,'ALTER TABLE `attendance_summary` ADD COLUMN `break_in` DATETIME DEFAULT NULL AFTER `break_out`','SELECT 1');
PREPARE _s FROM @q; EXECUTE _s; DEALLOCATE PREPARE _s;

-- overtime_in
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance_summary' AND COLUMN_NAME='overtime_in');
SET @q := IF(@c=0,'ALTER TABLE `attendance_summary` ADD COLUMN `overtime_in` DATETIME DEFAULT NULL AFTER `time_out`','SELECT 1');
PREPARE _s FROM @q; EXECUTE _s; DEALLOCATE PREPARE _s;

-- overtime_out
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance_summary' AND COLUMN_NAME='overtime_out');
SET @q := IF(@c=0,'ALTER TABLE `attendance_summary` ADD COLUMN `overtime_out` DATETIME DEFAULT NULL AFTER `overtime_in`','SELECT 1');
PREPARE _s FROM @q; EXECUTE _s; DEALLOCATE PREPARE _s;

-- break_minutes
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance_summary' AND COLUMN_NAME='break_minutes');
SET @q := IF(@c=0,'ALTER TABLE `attendance_summary` ADD COLUMN `break_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `overtime_out`','SELECT 1');
PREPARE _s FROM @q; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ────────────────────────────────────────────────────────────
-- 4. admin_manual_attendance — add updated_at if missing
--    (present in master migration but absent in phase3.sql
--    which some older databases may have used)
-- ────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_manual_attendance' AND COLUMN_NAME='updated_at');
SET @q := IF(@c=0,'ALTER TABLE `admin_manual_attendance` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP','SELECT 1');
PREPARE _s FROM @q; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ────────────────────────────────────────────────────────────
-- 5. Back-fill break_out / break_in from legacy lunch columns
-- ────────────────────────────────────────────────────────────
UPDATE `attendance_summary`
   SET `break_out` = COALESCE(`break_out`, `lunch_out`),
       `break_in`  = COALESCE(`break_in`,  `lunch_in`)
 WHERE (`lunch_out` IS NOT NULL OR `lunch_in` IS NOT NULL)
   AND (`break_out` IS NULL AND `break_in` IS NULL);

-- ────────────────────────────────────────────────────────────
-- 6. manual_attendance_requests.request_type — extend ENUM
--    Old: time_in | time_out
--    New: + break_out | break_in | overtime_in | overtime_out
--    This is the root cause of blank Type badges: break/OT values
--    were rejected by strict mode and stored as empty string.
-- ────────────────────────────────────────────────────────────
ALTER TABLE `manual_attendance_requests`
  MODIFY COLUMN `request_type`
    ENUM('time_in','break_out','break_in','time_out','overtime_in','overtime_out') NOT NULL;

-- ────────────────────────────────────────────────────────────
-- 7. Verification — show column list so the caller can confirm
-- ────────────────────────────────────────────────────────────
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'attendance_summary'
 ORDER BY ORDINAL_POSITION;
