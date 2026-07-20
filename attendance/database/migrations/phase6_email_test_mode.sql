-- ============================================================
-- Phase 6: Safe Testing Mode for Email Schedule
-- Run after phase5.sql
-- Adds is_test_run and simulated_date columns to email_logs.
-- All operations are idempotent (IF NOT EXISTS guards).
-- ============================================================

USE `attendance_db`;

START TRANSACTION;

-- ── Add is_test_run to email_logs ─────────────────────────────────────────────
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'email_logs'
       AND COLUMN_NAME  = 'is_test_run'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE `email_logs` ADD COLUMN `is_test_run` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = test run, not a real scheduled send'' AFTER `retry_count`',
    'SELECT 1 -- is_test_run already exists'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── Add simulated_date to email_logs ──────────────────────────────────────────
SET @col_exists2 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'email_logs'
       AND COLUMN_NAME  = 'simulated_date'
);
SET @ddl2 = IF(@col_exists2 = 0,
    'ALTER TABLE `email_logs` ADD COLUMN `simulated_date` DATE DEFAULT NULL COMMENT ''Date simulated during a test run'' AFTER `is_test_run`',
    'SELECT 1 -- simulated_date already exists'
);
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ── Index for fast test-log queries ──────────────────────────────────────────
SET @idx_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'email_logs'
       AND INDEX_NAME   = 'idx_email_logs_test_run'
);
SET @ddl3 = IF(@idx_exists = 0,
    'ALTER TABLE `email_logs` ADD KEY `idx_email_logs_test_run` (`is_test_run`, `created_at`)',
    'SELECT 1 -- index already exists'
);
PREPARE stmt3 FROM @ddl3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

COMMIT;
