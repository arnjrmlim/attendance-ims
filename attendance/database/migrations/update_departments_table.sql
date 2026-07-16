-- ============================================================
-- Migration: Update departments table with additional fields
-- ============================================================
-- This migration adds missing fields to support full department management
-- ============================================================

USE `attendance_db`;

-- Add missing columns to departments table
ALTER TABLE `departments`
ADD COLUMN `department_head` VARCHAR(120) DEFAULT NULL AFTER `description`,
ADD COLUMN `contact_number` VARCHAR(30) DEFAULT NULL AFTER `department_head`,
ADD COLUMN `email_address` VARCHAR(120) DEFAULT NULL AFTER `contact_number`,
ADD COLUMN `location` VARCHAR(255) DEFAULT NULL AFTER `email_address`;

-- Add unique constraint for department names to prevent duplicates
ALTER TABLE `departments`
ADD UNIQUE KEY `uq_departments_name` (`name`);

-- Add unique constraint for department codes to prevent duplicates
ALTER TABLE `departments`
ADD UNIQUE KEY `uq_departments_code` (`code`);

-- Add index for better query performance on department head
ALTER TABLE `departments`
ADD INDEX `idx_departments_head` (`department_head`);
