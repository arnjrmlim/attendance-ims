-- ============================================================
-- Migration: Update branches table with additional fields
-- ============================================================
-- This migration adds missing fields to support full branch management
-- ============================================================

USE `attendance_db`;

-- Add missing columns to branches table
ALTER TABLE `branches`
ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `address`,
ADD COLUMN `province` VARCHAR(100) DEFAULT NULL AFTER `city`,
ADD COLUMN `branch_manager` VARCHAR(120) DEFAULT NULL AFTER `email`,
ADD COLUMN `time_zone` VARCHAR(50) DEFAULT 'Asia/Manila' AFTER `branch_manager`;

-- Add unique constraint for branch names to prevent duplicates
ALTER TABLE `branches`
ADD UNIQUE KEY `uq_branches_name` (`name`);

-- Add index for better query performance on status and manager
ALTER TABLE `branches`
ADD INDEX `idx_branches_manager` (`branch_manager`);
