-- ============================================================
-- Phase 7: Automatic Backup Configuration
-- Adds comprehensive automatic backup scheduling and configuration
-- ============================================================

USE `attendance_db`;

SET AUTOCOMMIT = 0;
START TRANSACTION;

-- ============================================================
-- Table: backup_schedules
-- Stores automatic backup configuration
-- ============================================================
CREATE TABLE IF NOT EXISTS `backup_schedules` (
  `id`                    CHAR(36)     NOT NULL,
  `enabled`               TINYINT(1)   NOT NULL DEFAULT 0,
  `frequency`             ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `backup_time`           TIME         NOT NULL DEFAULT '00:00:00',
  `weekly_day`            TINYINT      DEFAULT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `monthly_day`           TINYINT      DEFAULT NULL COMMENT '1-31, NULL for last day of month',
  `backup_directory`      VARCHAR(500) NOT NULL DEFAULT '/storage/backups/',
  `retention_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 7 COMMENT 'Number of backups to keep',
  `compress_backup`       TINYINT(1)   NOT NULL DEFAULT 1,
  `include_uploads`       TINYINT(1)   NOT NULL DEFAULT 0,
  `last_run_at`           DATETIME     DEFAULT NULL,
  `next_run_at`           DATETIME     DEFAULT NULL,
  `last_success_at`       DATETIME     DEFAULT NULL,
  `last_failure_at`       DATETIME     DEFAULT NULL,
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup_schedule` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Insert default backup schedule configuration
-- ============================================================
INSERT INTO `backup_schedules` (
  `id`, `enabled`, `frequency`, `backup_time`, `backup_directory`, 
  `retention_count`, `compress_backup`, `include_uploads`
) VALUES (
  's0000000-0000-0000-0000-000000000001', 
  0, 
  'daily', 
  '00:00:00', 
  '/storage/backups/', 
  7, 
  1, 
  0
) ON DUPLICATE KEY UPDATE `id` = `id`;

-- ============================================================
-- Update backup_logs table to add more detailed tracking
-- ============================================================
ALTER TABLE `backup_logs`
ADD COLUMN IF NOT EXISTS `uploads_included` TINYINT(1) NOT NULL DEFAULT 0 AFTER `trigger_type`,
ADD COLUMN IF NOT EXISTS `compression_used` TINYINT(1) NOT NULL DEFAULT 0 AFTER `uploads_included`;

COMMIT;
SET AUTOCOMMIT = 1;
