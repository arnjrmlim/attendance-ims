-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: attendance_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_manual_attendance`
--

DROP TABLE IF EXISTS `admin_manual_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_manual_attendance` (
  `id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `attendance_status` enum('present','absent','half_day','holiday','leave') NOT NULL DEFAULT 'present',
  `method` enum('Manual Entry','PIN','QR Code','RFID','System Generated') NOT NULL DEFAULT 'Manual Entry',
  `reason` text NOT NULL,
  `admin_remarks` text DEFAULT NULL,
  `created_by` char(36) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ama_employee` (`employee_id`),
  KEY `idx_ama_date` (`attendance_date`),
  KEY `idx_ama_created_by` (`created_by`),
  CONSTRAINT `fk_ama_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_ama_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_manual_attendance`
--

LOCK TABLES `admin_manual_attendance` WRITE;
/*!40000 ALTER TABLE `admin_manual_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_manual_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` char(36) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `author_id` char(36) DEFAULT NULL,
  `branch_id` char(36) DEFAULT NULL COMMENT 'NULL = all branches',
  `target_type` enum('all','department','employee') NOT NULL DEFAULT 'all',
  `target_id` char(36) DEFAULT NULL COMMENT 'department_id or employee_id',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `publish_at` datetime DEFAULT NULL,
  `expire_at` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_status` (`status`),
  KEY `idx_announcements_branch` (`branch_id`),
  KEY `idx_announcements_publish` (`publish_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_recorded` datetime NOT NULL,
  `attendance_type` enum('time_in','break_out','break_in','time_out','overtime_in','overtime_out','lunch_out','lunch_in') NOT NULL,
  `method` enum('pin','qr_code','rfid','manual') NOT NULL DEFAULT 'pin',
  `device_name` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `is_early_arrival` tinyint(1) NOT NULL DEFAULT 0,
  `is_undertime` tinyint(1) NOT NULL DEFAULT 0,
  `minutes_late` smallint(5) unsigned NOT NULL DEFAULT 0,
  `minutes_undertime` smallint(5) unsigned NOT NULL DEFAULT 0,
  `total_hours` decimal(5,2) DEFAULT NULL COMMENT 'Populated on time_out',
  `notes` varchar(255) DEFAULT NULL,
  `recorded_by` char(36) DEFAULT NULL COMMENT 'user_id if manual entry',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attendance_employee` (`employee_id`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_type` (`attendance_type`),
  KEY `idx_attendance_method` (`method`),
  CONSTRAINT `fk_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES ('3438ad8b-9dac-4a72-8a3d-313f30465f7c','00abab26-c518-42d1-b592-edd590df440a','2026-07-20','2026-07-20 12:16:05','time_in','manual',NULL,NULL,0,0,0,0,0,NULL,NULL,'4e5bcc90-2e31-46bb-abd1-effd6e2044be','2026-07-20 12:16:05'),('3af5d037-330d-42c3-af89-02df181a79c8','e3000000-0000-0000-0000-000000000001','2026-07-20','2026-07-20 12:13:40','time_in','manual',NULL,NULL,0,0,0,0,0,NULL,NULL,'u3000000-0000-0000-0000-000000000001','2026-07-20 12:13:40'),('3febcde3-837a-11f1-a17e-00155d84e748','e3000000-0000-0000-0000-000000000001','2026-07-19','2026-07-19 07:58:00','time_in','pin','KIOSK-01','127.0.0.1',0,0,0,0,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('3fec068f-837a-11f1-a17e-00155d84e748','e3000000-0000-0000-0000-000000000001','2026-07-19','2026-07-19 12:01:00','lunch_out','pin','KIOSK-01','127.0.0.1',0,0,0,0,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('3fec0904-837a-11f1-a17e-00155d84e748','e3000000-0000-0000-0000-000000000001','2026-07-19','2026-07-19 13:00:00','lunch_in','pin','KIOSK-01','127.0.0.1',0,0,0,0,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('3fec0a37-837a-11f1-a17e-00155d84e748','e4000000-0000-0000-0000-000000000001','2026-07-19','2026-07-19 08:25:00','time_in','qr_code','KIOSK-01','127.0.0.1',1,0,0,25,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('3fec0b2a-837a-11f1-a17e-00155d84e748','e5000000-0000-0000-0000-000000000001','2026-07-18','2026-07-18 06:58:00','time_in','rfid','KIOSK-02','127.0.0.1',0,0,0,0,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('3fec0cea-837a-11f1-a17e-00155d84e748','e5000000-0000-0000-0000-000000000001','2026-07-18','2026-07-18 12:00:00','lunch_out','rfid','KIOSK-02','127.0.0.1',0,0,0,0,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('3fec0de6-837a-11f1-a17e-00155d84e748','e5000000-0000-0000-0000-000000000001','2026-07-18','2026-07-18 13:01:00','lunch_in','rfid','KIOSK-02','127.0.0.1',0,0,0,0,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('3fec0ee0-837a-11f1-a17e-00155d84e748','e5000000-0000-0000-0000-000000000001','2026-07-18','2026-07-18 16:00:00','time_out','rfid','KIOSK-02','127.0.0.1',0,0,0,0,0,NULL,NULL,NULL,'2026-07-19 22:00:50'),('873d940a-c72f-42b2-962d-2fe832ad6547','9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','2026-07-20','2026-07-20 12:10:20','time_in','manual',NULL,NULL,0,0,0,0,0,NULL,NULL,'4c60df00-5c28-4c26-b023-1ecf8597c185','2026-07-20 12:10:20');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_corrections`
--

DROP TABLE IF EXISTS `attendance_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_corrections` (
  `id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL,
  `attendance_id` char(36) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `correction_type` enum('Forgot Time In','Forgot Time Out','Incorrect Attendance','Wrong Attendance Method') NOT NULL,
  `original_time_in` datetime DEFAULT NULL,
  `original_time_out` datetime DEFAULT NULL,
  `requested_time_in` datetime DEFAULT NULL,
  `requested_time_out` datetime DEFAULT NULL,
  `reason` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` char(36) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_correction_employee` (`employee_id`),
  KEY `idx_correction_date` (`attendance_date`),
  KEY `idx_correction_status` (`status`),
  KEY `fk_correction_attendance` (`attendance_id`),
  KEY `fk_correction_approver` (`approved_by`),
  CONSTRAINT `fk_correction_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_correction_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_correction_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_corrections`
--

LOCK TABLES `attendance_corrections` WRITE;
/*!40000 ALTER TABLE `attendance_corrections` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_corrections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_summary`
--

DROP TABLE IF EXISTS `attendance_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_summary` (
  `id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL COMMENT 'Official: earliest time_in',
  `break_out` datetime DEFAULT NULL COMMENT 'Official: earliest break_out',
  `break_in` datetime DEFAULT NULL COMMENT 'Official: latest break_in',
  `time_out` datetime DEFAULT NULL COMMENT 'Official: latest time_out',
  `overtime_in` datetime DEFAULT NULL COMMENT 'Official: earliest overtime_in',
  `overtime_out` datetime DEFAULT NULL COMMENT 'Official: latest overtime_out',
  `lunch_out` datetime DEFAULT NULL,
  `lunch_in` datetime DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `break_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `late_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `undertime_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `overtime_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `is_absent` tinyint(1) NOT NULL DEFAULT 0,
  `is_holiday` tinyint(1) NOT NULL DEFAULT 0,
  `day_status` enum('present','absent','half_day','holiday','rest_day','leave') NOT NULL DEFAULT 'absent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_summary_employee_date` (`employee_id`,`attendance_date`),
  KEY `idx_summary_date` (`attendance_date`),
  KEY `idx_summary_status` (`day_status`),
  CONSTRAINT `fk_summary_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_summary`
--

LOCK TABLES `attendance_summary` WRITE;
/*!40000 ALTER TABLE `attendance_summary` DISABLE KEYS */;
INSERT INTO `attendance_summary` VALUES ('2e1b67e6-6667-4009-95c2-0acdf05010b4','00abab26-c518-42d1-b592-edd590df440a','2026-07-20','2026-07-20 12:16:05',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,1,0,0,1,0,0,'present','2026-07-20 12:16:06','2026-07-20 12:16:06'),('3feeff4e-837a-11f1-a17e-00155d84e748','e3000000-0000-0000-0000-000000000001','2026-07-19','2026-07-19 07:58:00',NULL,NULL,NULL,NULL,NULL,'2026-07-19 12:01:00','2026-07-19 13:00:00',NULL,0,0,0,0,0,0,0,'present','2026-07-19 22:00:50','2026-07-19 22:00:50'),('3fef3afe-837a-11f1-a17e-00155d84e748','e4000000-0000-0000-0000-000000000001','2026-07-19','2026-07-19 08:25:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,25,0,0,0,0,0,'present','2026-07-19 22:00:50','2026-07-19 22:00:50'),('3fef432e-837a-11f1-a17e-00155d84e748','e5000000-0000-0000-0000-000000000001','2026-07-18','2026-07-18 06:58:00',NULL,NULL,'2026-07-18 16:00:00',NULL,NULL,'2026-07-18 12:00:00','2026-07-18 13:01:00',8.05,0,0,0,0,0,0,0,'present','2026-07-19 22:00:50','2026-07-19 22:00:50'),('a1c0e685-103a-4753-8e3a-d2ed2649bd1e','9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','2026-07-20','2026-07-20 12:10:20',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,205,0,0,1,0,0,'present','2026-07-20 12:10:21','2026-07-20 12:10:21'),('a637a0f5-f93c-4970-b618-89442096fa80','e3000000-0000-0000-0000-000000000001','2026-07-20','2026-07-20 12:13:40',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,0,0,0,'present','2026-07-20 12:13:40','2026-07-20 12:13:40');
/*!40000 ALTER TABLE `attendance_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(36) DEFAULT NULL,
  `username` varchar(60) DEFAULT NULL COMMENT 'Snapshot at time of action',
  `action` varchar(100) NOT NULL,
  `module` varchar(60) NOT NULL,
  `record_id` varchar(36) DEFAULT NULL COMMENT 'Affected record UUID',
  `previous_value` longtext DEFAULT NULL,
  `new_value` longtext DEFAULT NULL,
  `computer_name` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_record` (`record_id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,'u1000000-0000-0000-0000-000000000001','admin','SEED_DATA_INSTALLED','system',NULL,NULL,'{\"version\":\"1.0.0\",\"timestamp\":\"2026-07-19 22:00:50\"}','SEED-SCRIPT','127.0.0.1',NULL,'2026-07-19 22:00:50'),(2,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGOUT','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:01:29'),(3,'u1000000-0000-0000-0000-000000000001','admin','LOGIN_SUCCESS','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:04:58'),(4,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_UPDATED','users','u1000000-0000-0000-0000-000000000001',NULL,'\"avatars\\/63188439-eec7-4263-8f65-5269bf198963.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:05:54'),(5,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_UPDATED','users','u1000000-0000-0000-0000-000000000001','\"avatars\\/63188439-eec7-4263-8f65-5269bf198963.jpg\"','\"avatars\\/profile_u1000000-0000-0000-0000-000000000001_20260719_7c3e88.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:20:04'),(6,'u1000000-0000-0000-0000-000000000001','admin','LOGOUT','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:20:08'),(7,'u1000000-0000-0000-0000-000000000001','admin','LOGIN_SUCCESS','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:20:15'),(8,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_UPDATED','users','u1000000-0000-0000-0000-000000000001','\"avatars\\/profile_u1000000-0000-0000-0000-000000000001_20260719_7c3e88.jpg\"','\"avatars\\/profile_u1000000-0000-0000-0000-000000000001_20260719_656194.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:20:33'),(9,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_REMOVED','users','u1000000-0000-0000-0000-000000000001','\"avatars\\/profile_u1000000-0000-0000-0000-000000000001_20260719_656194.jpg\"',NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:20:36'),(10,'u1000000-0000-0000-0000-000000000001','admin','LOGOUT','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:20:58'),(11,NULL,NULL,'LOGIN_FAILED','auth',NULL,NULL,'{\"username\":\"emp_jose\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:21:00'),(12,NULL,NULL,'LOGIN_FAILED','auth',NULL,NULL,'{\"username\":\"emp_jose\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:21:01'),(13,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGIN_SUCCESS','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:21:14'),(14,'u3000000-0000-0000-0000-000000000001','emp_jose','PROFILE_PICTURE_UPDATED','users','u3000000-0000-0000-0000-000000000001',NULL,'\"avatars\\/profile_u3000000-0000-0000-0000-000000000001_20260719_351a3d.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:21:20'),(15,'u3000000-0000-0000-0000-000000000001','emp_jose','PROFILE_PICTURE_REMOVED','users','u3000000-0000-0000-0000-000000000001','\"avatars\\/profile_u3000000-0000-0000-0000-000000000001_20260719_351a3d.jpg\"',NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:22:00'),(16,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGOUT','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:22:02'),(17,'u1000000-0000-0000-0000-000000000001','admin','LOGIN_SUCCESS','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:22:09'),(18,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_UPDATED','users','u1000000-0000-0000-0000-000000000001',NULL,'\"avatars\\/profile_u1000000-0000-0000-0000-000000000001_20260719_2431c0.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:23:35'),(19,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_REMOVED','users','u1000000-0000-0000-0000-000000000001','\"avatars\\/profile_u1000000-0000-0000-0000-000000000001_20260719_2431c0.jpg\"',NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:38:15'),(20,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_UPDATED','users','u1000000-0000-0000-0000-000000000001',NULL,'\"avatars\\/57ad8634-0a38-420b-85b0-6c9fe66882c2.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:38:18'),(21,'u1000000-0000-0000-0000-000000000001','admin','LOGOUT','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:38:21'),(22,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGIN_SUCCESS','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:38:31'),(23,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGOUT','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:39:27'),(24,'u1000000-0000-0000-0000-000000000001','admin','LOGIN_SUCCESS','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:39:34'),(25,'u1000000-0000-0000-0000-000000000001','admin','LOGOUT','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:53:25'),(26,'u1000000-0000-0000-0000-000000000001','admin','LOGIN_SUCCESS','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:53:35'),(27,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_UPDATED','users','u1000000-0000-0000-0000-000000000001','\"avatars\\/57ad8634-0a38-420b-85b0-6c9fe66882c2.jpg\"','\"avatars\\/1949c1d0-79fa-4eef-bf16-14a425ab4fe2.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:53:50'),(28,'u1000000-0000-0000-0000-000000000001','admin','LOGOUT','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 22:53:54'),(29,'u1000000-0000-0000-0000-000000000001','admin','LOGIN_SUCCESS','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:42:47'),(30,'u1000000-0000-0000-0000-000000000001','admin','LOGOUT','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:00'),(31,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGIN_SUCCESS','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:07'),(32,'u2000000-0000-0000-0000-000000000001','hr_maria','EMPLOYEE_UPDATED','employees','e3000000-0000-0000-0000-000000000001','\"{\\\"id\\\":\\\"e3000000-0000-0000-0000-000000000001\\\",\\\"employee_number\\\":\\\"EMP-0003\\\",\\\"first_name\\\":\\\"Jose\\\",\\\"middle_name\\\":\\\"Cruz\\\",\\\"last_name\\\":\\\"Garcia\\\",\\\"suffix\\\":null,\\\"gender\\\":\\\"Male\\\",\\\"date_of_birth\\\":\\\"1990-06-01\\\",\\\"civil_status\\\":\\\"Single\\\",\\\"nationality\\\":\\\"Filipino\\\",\\\"photo\\\":null,\\\"department_id\\\":\\\"d2000000-0000-0000-0000-000000000001\\\",\\\"branch_id\\\":\\\"b1000000-0000-0000-0000-000000000001\\\",\\\"shift_id\\\":\\\"s2000000-0000-0000-0000-000000000001\\\",\\\"position\\\":\\\"IT Specialist\\\",\\\"employment_status\\\":\\\"Active\\\",\\\"employment_type\\\":\\\"Regular\\\",\\\"contact_number\\\":\\\"09193456789\\\",\\\"alternate_mobile\\\":null,\\\"email\\\":\\\"jose.garcia@company.com\\\",\\\"home_address\\\":\\\"789 East St, Manila\\\",\\\"emergency_contact_name\\\":\\\"Maria Garcia\\\",\\\"emergency_contact_number\\\":\\\"09203456789\\\",\\\"emergency_contact_relationship\\\":\\\"Mother\\\",\\\"date_hired\\\":\\\"2022-06-01\\\",\\\"immediate_supervisor_id\\\":null,\\\"username\\\":\\\"emp_jose\\\",\\\"password_hash\\\":\\\"$2y$12$yEaM\\\\\\/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm\\\",\\\"pin\\\":\\\"1234\\\",\\\"pin_hash\\\":\\\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\\\\\/.og\\\\\\/at2.uheWG\\\\\\/igi\\\",\\\"qr_code_value\\\":\\\"QR-EMP-0003-JOSE-GAR\\\",\\\"rfid_value\\\":\\\"RF-0003\\\",\\\"status\\\":\\\"active\\\",\\\"created_by\\\":null,\\\"created_at\\\":\\\"2026-07-19 22:00:50\\\",\\\"updated_by\\\":null,\\\"updated_at\\\":\\\"2026-07-19 22:00:50\\\",\\\"department_name\\\":\\\"Information Technology\\\",\\\"branch_name\\\":\\\"Main Branch\\\",\\\"shift_name\\\":\\\"Day Shift (8AM-5PM)\\\",\\\"supervisor_number\\\":null,\\\"supervisor_name\\\":null,\\\"full_name\\\":\\\"Jose Garcia\\\",\\\"user_id\\\":\\\"u3000000-0000-0000-0000-000000000001\\\",\\\"user_role_id\\\":4,\\\"user_role_name\\\":\\\"Employee\\\",\\\"user_username\\\":\\\"emp_jose\\\",\\\"profile_picture\\\":null}\"','{\"_csrf\":\"98c4c9391a2471e434be140eb79e04288554dda490237e2cd277c9522f7d7b9c\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"emp_jose\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:22'),(33,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGOUT','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:28'),(34,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGIN_SUCCESS','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:30'),(35,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGIN_SUCCESS','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:32'),(36,'u3000000-0000-0000-0000-000000000001','emp_jose','PROFILE_PICTURE_UPDATED','users','u3000000-0000-0000-0000-000000000001',NULL,'\"avatars\\/profile_u30000000000_20260719_aa21fe85.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:36'),(37,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGOUT','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:39'),(38,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGIN_SUCCESS','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 23:43:46'),(39,'u2000000-0000-0000-0000-000000000001','hr_maria','SHIFT_CREATED','shifts','13b328f1-0b5b-47b7-be17-b53a48933b96',NULL,'{\"_csrf\":\"d4d408d85a084c7801538b9d347347d24c8bc4be5078c488e119ee708ba74871\",\"name\":\"Regulart Hours\",\"type\":\"regular\",\"description\":\"\",\"time_in\":\"08:30\",\"time_out\":\"17:30\",\"required_hours\":\"8.00\",\"lunch_break_start\":\"12:00\",\"lunch_break_end\":\"13:00\",\"lunch_break_minutes\":\"60\",\"grace_period_minutes\":\"15\",\"overnight\":\"0\",\"status\":\"active\",\"is_default\":\"1\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:05:48'),(40,'u2000000-0000-0000-0000-000000000001','hr_maria','EMPLOYEE_CREATED','employees','9fe6baed-eb5f-4a97-8f4f-6d60833f40a4',NULL,'{\"_csrf\":\"d4d408d85a084c7801538b9d347347d24c8bc4be5078c488e119ee708ba74871\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"username\":\"\",\"password\":\"\",\"role_id\":\"3\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"13b328f1-0b5b-47b7-be17-b53a48933b96\",\"pin\":\"342423\",\"rfid_value\":\"\",\"status\":\"active\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:09:43'),(41,'u2000000-0000-0000-0000-000000000001','hr_maria','ROLE_ASSIGNED','users','9fe6baed-eb5f-4a97-8f4f-6d60833f40a4',NULL,'{\"role_id\":\"3\",\"username\":\"ABE123\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:09:43'),(42,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGOUT','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:09:51'),(43,'4c60df00-5c28-4c26-b023-1ecf8597c185','ABE123','LOGIN_SUCCESS','auth','4c60df00-5c28-4c26-b023-1ecf8597c185',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:09:57'),(44,'4c60df00-5c28-4c26-b023-1ecf8597c185','ABE123','PASSWORD_CHANGED','users','4c60df00-5c28-4c26-b023-1ecf8597c185',NULL,'{\"ip\":\"::1\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:10:05'),(45,'4c60df00-5c28-4c26-b023-1ecf8597c185','ABE123','MANUAL_ATTENDANCE_REQUEST_SUBMITTED','manual_attendance','ae7a82f8-b90d-4230-a512-e678af1f8ed8',NULL,'{\"employee_id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"type\":\"time_in\",\"date\":\"2026-07-20\",\"time\":\"12:10:20\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:10:20'),(46,'4c60df00-5c28-4c26-b023-1ecf8597c185','ABE123','MANUAL_ATTENDANCE_AUTO_APPROVED','manual_attendance','ae7a82f8-b90d-4230-a512-e678af1f8ed8',NULL,'{\"employee_id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"type\":\"time_in\",\"date\":\"2026-07-20\",\"time\":\"12:10:20\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:10:21'),(47,'4c60df00-5c28-4c26-b023-1ecf8597c185','ABE123','LOGOUT','auth','4c60df00-5c28-4c26-b023-1ecf8597c185',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:10:30'),(48,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGIN_SUCCESS','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:10:38'),(49,'u2000000-0000-0000-0000-000000000001','hr_maria','EMPLOYEE_UPDATED','employees','9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','\"{\\\"id\\\":\\\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\\\",\\\"employee_number\\\":\\\"abe-123\\\",\\\"first_name\\\":\\\"abegail\\\",\\\"middle_name\\\":\\\"\\\",\\\"last_name\\\":\\\"lim\\\",\\\"suffix\\\":\\\"\\\",\\\"gender\\\":\\\"\\\",\\\"date_of_birth\\\":\\\"0000-00-00\\\",\\\"civil_status\\\":\\\"\\\",\\\"nationality\\\":\\\"\\\",\\\"photo\\\":null,\\\"department_id\\\":\\\"d3000000-0000-0000-0000-000000000001\\\",\\\"branch_id\\\":\\\"b1000000-0000-0000-0000-000000000001\\\",\\\"shift_id\\\":\\\"13b328f1-0b5b-47b7-be17-b53a48933b96\\\",\\\"position\\\":\\\"fesfe\\\",\\\"employment_status\\\":\\\"Active\\\",\\\"employment_type\\\":\\\"Regular\\\",\\\"contact_number\\\":\\\"324234324\\\",\\\"alternate_mobile\\\":\\\"\\\",\\\"email\\\":\\\"abegail@gmail.com\\\",\\\"home_address\\\":\\\"\\\",\\\"emergency_contact_name\\\":\\\"fesfes\\\",\\\"emergency_contact_number\\\":\\\"234234\\\",\\\"emergency_contact_relationship\\\":\\\"fesfesfes\\\",\\\"date_hired\\\":\\\"2026-01-01\\\",\\\"immediate_supervisor_id\\\":null,\\\"username\\\":\\\"ABE123\\\",\\\"password_hash\\\":\\\"$2y$10$F0zdsMjAoiMuI3sKePZ\\\\\\/a.UbGHnrkYIx0n181uBO\\\\\\/3HqcTcu96Il.\\\",\\\"pin\\\":\\\"342423\\\",\\\"pin_hash\\\":\\\"$2y$10$Rza6ALMSlCakoXlyry7xqeqJcpwLJFiY3v8MGL07a3sjI8VBTINRO\\\",\\\"qr_code_value\\\":\\\"EMP-637C441780909ACA\\\",\\\"rfid_value\\\":null,\\\"status\\\":\\\"active\\\",\\\"created_by\\\":\\\"u2000000-0000-0000-0000-000000000001\\\",\\\"created_at\\\":\\\"2026-07-20 12:09:43\\\",\\\"updated_by\\\":null,\\\"updated_at\\\":\\\"2026-07-20 12:09:43\\\",\\\"department_name\\\":\\\"Finance & Accounting\\\",\\\"branch_name\\\":\\\"Main Branch\\\",\\\"shift_name\\\":\\\"Regulart Hours\\\",\\\"supervisor_number\\\":null,\\\"supervisor_name\\\":null,\\\"full_name\\\":\\\"abegail lim\\\",\\\"user_id\\\":\\\"4c60df00-5c28-4c26-b023-1ecf8597c185\\\",\\\"user_role_id\\\":3,\\\"user_role_name\\\":\\\"Supervisor\\\",\\\"user_username\\\":\\\"ABE123\\\",\\\"profile_picture\\\":null}\"','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"username\":\"ABE123\",\"password\":\"\",\"role_id\":\"3\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s3000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"\",\"status\":\"active\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:12:03'),(50,'u2000000-0000-0000-0000-000000000001','hr_maria','EMPLOYEE_UPDATED','employees','e3000000-0000-0000-0000-000000000001','\"{\\\"id\\\":\\\"e3000000-0000-0000-0000-000000000001\\\",\\\"employee_number\\\":\\\"EMP-0003\\\",\\\"first_name\\\":\\\"Jose\\\",\\\"middle_name\\\":\\\"Cruz\\\",\\\"last_name\\\":\\\"Garcia\\\",\\\"suffix\\\":\\\"\\\",\\\"gender\\\":\\\"Male\\\",\\\"date_of_birth\\\":\\\"1990-06-01\\\",\\\"civil_status\\\":\\\"Single\\\",\\\"nationality\\\":\\\"Filipino\\\",\\\"photo\\\":null,\\\"department_id\\\":\\\"d2000000-0000-0000-0000-000000000001\\\",\\\"branch_id\\\":\\\"b1000000-0000-0000-0000-000000000001\\\",\\\"shift_id\\\":\\\"s2000000-0000-0000-0000-000000000001\\\",\\\"position\\\":\\\"IT Specialist\\\",\\\"employment_status\\\":\\\"Active\\\",\\\"employment_type\\\":\\\"Regular\\\",\\\"contact_number\\\":\\\"09193456789\\\",\\\"alternate_mobile\\\":\\\"\\\",\\\"email\\\":\\\"jose.garcia@company.com\\\",\\\"home_address\\\":\\\"789 East St, Manila\\\",\\\"emergency_contact_name\\\":\\\"Maria Garcia\\\",\\\"emergency_contact_number\\\":\\\"09203456789\\\",\\\"emergency_contact_relationship\\\":\\\"Mother\\\",\\\"date_hired\\\":\\\"2022-06-01\\\",\\\"immediate_supervisor_id\\\":null,\\\"username\\\":\\\"emp_jose\\\",\\\"password_hash\\\":\\\"$2y$10$gbdlJAkzr.BPXU81WFHaN.Auvcd3\\\\\\/WzMvcGCSXl8pnxdaqoPrNMtW\\\",\\\"pin\\\":\\\"1234\\\",\\\"pin_hash\\\":\\\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\\\\\/.og\\\\\\/at2.uheWG\\\\\\/igi\\\",\\\"qr_code_value\\\":\\\"QR-EMP-0003-JOSE-GAR\\\",\\\"rfid_value\\\":\\\"RF-0003\\\",\\\"status\\\":\\\"active\\\",\\\"created_by\\\":null,\\\"created_at\\\":\\\"2026-07-19 22:00:50\\\",\\\"updated_by\\\":\\\"u2000000-0000-0000-0000-000000000001\\\",\\\"updated_at\\\":\\\"2026-07-19 23:43:22\\\",\\\"department_name\\\":\\\"Information Technology\\\",\\\"branch_name\\\":\\\"Main Branch\\\",\\\"shift_name\\\":\\\"Day Shift (8AM-5PM)\\\",\\\"supervisor_number\\\":null,\\\"supervisor_name\\\":null,\\\"full_name\\\":\\\"Jose Garcia\\\",\\\"user_id\\\":\\\"u3000000-0000-0000-0000-000000000001\\\",\\\"user_role_id\\\":4,\\\"user_role_name\\\":\\\"Employee\\\",\\\"user_username\\\":\\\"emp_jose\\\",\\\"profile_picture\\\":\\\"avatars\\\\\\/profile_u30000000000_20260719_aa21fe85.jpg\\\"}\"','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"emp_jose\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:12:40'),(51,'u2000000-0000-0000-0000-000000000001','hr_maria','SHIFT_CREATED','shifts','c1928020-1487-439e-b776-33eea39a9cd3',NULL,'{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"name\":\"test\",\"type\":\"regular\",\"description\":\"\",\"time_in\":\"12:00\",\"time_out\":\"21:00\",\"required_hours\":\"8.00\",\"lunch_break_start\":\"15:00\",\"lunch_break_end\":\"13:00\",\"lunch_break_minutes\":\"60\",\"grace_period_minutes\":\"15\",\"overnight\":\"0\",\"status\":\"active\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:10'),(52,'u2000000-0000-0000-0000-000000000001','hr_maria','EMPLOYEE_UPDATED','employees','e3000000-0000-0000-0000-000000000001','\"{\\\"id\\\":\\\"e3000000-0000-0000-0000-000000000001\\\",\\\"employee_number\\\":\\\"EMP-0003\\\",\\\"first_name\\\":\\\"Jose\\\",\\\"middle_name\\\":\\\"Cruz\\\",\\\"last_name\\\":\\\"Garcia\\\",\\\"suffix\\\":\\\"\\\",\\\"gender\\\":\\\"Male\\\",\\\"date_of_birth\\\":\\\"1990-06-01\\\",\\\"civil_status\\\":\\\"Single\\\",\\\"nationality\\\":\\\"Filipino\\\",\\\"photo\\\":null,\\\"department_id\\\":\\\"d2000000-0000-0000-0000-000000000001\\\",\\\"branch_id\\\":\\\"b1000000-0000-0000-0000-000000000001\\\",\\\"shift_id\\\":\\\"s2000000-0000-0000-0000-000000000001\\\",\\\"position\\\":\\\"IT Specialist\\\",\\\"employment_status\\\":\\\"Active\\\",\\\"employment_type\\\":\\\"Regular\\\",\\\"contact_number\\\":\\\"09193456789\\\",\\\"alternate_mobile\\\":\\\"\\\",\\\"email\\\":\\\"jose.garcia@company.com\\\",\\\"home_address\\\":\\\"789 East St, Manila\\\",\\\"emergency_contact_name\\\":\\\"Maria Garcia\\\",\\\"emergency_contact_number\\\":\\\"09203456789\\\",\\\"emergency_contact_relationship\\\":\\\"Mother\\\",\\\"date_hired\\\":\\\"2022-06-01\\\",\\\"immediate_supervisor_id\\\":null,\\\"username\\\":\\\"emp_jose\\\",\\\"password_hash\\\":\\\"$2y$10$aQ4wuSx97shQj6irtiV3VOuPnokBsfZsFSV\\\\\\/cDGMQgT77TAbFdyYm\\\",\\\"pin\\\":\\\"1234\\\",\\\"pin_hash\\\":\\\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\\\\\/.og\\\\\\/at2.uheWG\\\\\\/igi\\\",\\\"qr_code_value\\\":\\\"QR-EMP-0003-JOSE-GAR\\\",\\\"rfid_value\\\":\\\"RF-0003\\\",\\\"status\\\":\\\"active\\\",\\\"created_by\\\":null,\\\"created_at\\\":\\\"2026-07-19 22:00:50\\\",\\\"updated_by\\\":\\\"u2000000-0000-0000-0000-000000000001\\\",\\\"updated_at\\\":\\\"2026-07-20 12:12:40\\\",\\\"department_name\\\":\\\"Information Technology\\\",\\\"branch_name\\\":\\\"Main Branch\\\",\\\"shift_name\\\":\\\"Day Shift (8AM-5PM)\\\",\\\"supervisor_number\\\":null,\\\"supervisor_name\\\":null,\\\"full_name\\\":\\\"Jose Garcia\\\",\\\"user_id\\\":\\\"u3000000-0000-0000-0000-000000000001\\\",\\\"user_role_id\\\":4,\\\"user_role_name\\\":\\\"Employee\\\",\\\"user_username\\\":\\\"emp_jose\\\",\\\"profile_picture\\\":\\\"avatars\\\\\\/profile_u30000000000_20260719_aa21fe85.jpg\\\"}\"','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"c1928020-1487-439e-b776-33eea39a9cd3\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:29'),(53,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGOUT','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:31'),(54,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGIN_SUCCESS','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:33'),(55,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGIN_SUCCESS','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:35'),(56,'u3000000-0000-0000-0000-000000000001','emp_jose','MANUAL_ATTENDANCE_REQUEST_SUBMITTED','manual_attendance','defc1016-554f-4282-ac6c-8040285b0298',NULL,'{\"employee_id\":\"e3000000-0000-0000-0000-000000000001\",\"type\":\"time_in\",\"date\":\"2026-07-20\",\"time\":\"12:13:40\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:40'),(57,'u3000000-0000-0000-0000-000000000001','emp_jose','MANUAL_ATTENDANCE_AUTO_APPROVED','manual_attendance','defc1016-554f-4282-ac6c-8040285b0298',NULL,'{\"employee_id\":\"e3000000-0000-0000-0000-000000000001\",\"type\":\"time_in\",\"date\":\"2026-07-20\",\"time\":\"12:13:40\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:40'),(58,'u3000000-0000-0000-0000-000000000001','emp_jose','LOGOUT','auth','u3000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:43'),(59,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGIN_SUCCESS','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:13:50'),(60,'u2000000-0000-0000-0000-000000000001','hr_maria','PASSWORD_CHANGED','users','u2000000-0000-0000-0000-000000000001',NULL,'{\"ip\":\"::1\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:14:06'),(61,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGIN_SUCCESS','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:14:09'),(62,'u2000000-0000-0000-0000-000000000001','hr_maria','EMPLOYEE_CREATED','employees','00abab26-c518-42d1-b592-edd590df440a',NULL,'{\"_csrf\":\"a944f14e0f4116dae77423f2044a44dcb2b07d10be7a369aaa5e81bed53162c5\",\"employee_number\":\"test123\",\"first_name\":\"test\",\"middle_name\":\"\",\"last_name\":\"test\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"23432\",\"alternate_mobile\":\"\",\"email\":\"testing@Dwad.con\",\"username\":\"test123\",\"password\":\"test123\",\"role_id\":\"4\",\"home_address\":\"\",\"emergency_contact_name\":\"esfse\",\"emergency_contact_number\":\"543\",\"emergency_contact_relationship\":\"fsees\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"dawddwa\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"1900-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"c1928020-1487-439e-b776-33eea39a9cd3\",\"pin\":\"65775675\",\"rfid_value\":\"\",\"status\":\"active\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:15:33'),(63,'u2000000-0000-0000-0000-000000000001','hr_maria','ROLE_ASSIGNED','users','00abab26-c518-42d1-b592-edd590df440a',NULL,'{\"role_id\":\"4\",\"username\":\"test123\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:15:33'),(64,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGOUT','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:15:38'),(65,'4e5bcc90-2e31-46bb-abd1-effd6e2044be','test123','LOGIN_SUCCESS','auth','4e5bcc90-2e31-46bb-abd1-effd6e2044be',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:15:40'),(66,'4e5bcc90-2e31-46bb-abd1-effd6e2044be','test123','MANUAL_ATTENDANCE_REQUEST_SUBMITTED','manual_attendance','e7fa8f52-3111-498d-8334-a394a980eecf',NULL,'{\"employee_id\":\"00abab26-c518-42d1-b592-edd590df440a\",\"type\":\"time_in\",\"date\":\"2026-07-20\",\"time\":\"12:16:05\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:16:05'),(67,'4e5bcc90-2e31-46bb-abd1-effd6e2044be','test123','MANUAL_ATTENDANCE_AUTO_APPROVED','manual_attendance','e7fa8f52-3111-498d-8334-a394a980eecf',NULL,'{\"employee_id\":\"00abab26-c518-42d1-b592-edd590df440a\",\"type\":\"time_in\",\"date\":\"2026-07-20\",\"time\":\"12:16:05\"}','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:16:06'),(68,'4e5bcc90-2e31-46bb-abd1-effd6e2044be','test123','LOGOUT','auth','4e5bcc90-2e31-46bb-abd1-effd6e2044be',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:16:08'),(69,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGIN_SUCCESS','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:16:13'),(70,'u2000000-0000-0000-0000-000000000001','hr_maria','PROFILE_PICTURE_UPDATED','users','u2000000-0000-0000-0000-000000000001',NULL,'\"avatars\\/profile_u20000000000_20260720_a0615fe3.jpg\"','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:16:56'),(71,'u2000000-0000-0000-0000-000000000001','hr_maria','LOGOUT','auth','u2000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:22:45'),(72,'u1000000-0000-0000-0000-000000000001','admin','LOGIN_SUCCESS','auth','u1000000-0000-0000-0000-000000000001',NULL,NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:22:52'),(73,'u1000000-0000-0000-0000-000000000001','admin','EMAIL_SETTINGS_UPDATED','email',NULL,NULL,'[\"smtp_host\",\"smtp_port\",\"smtp_username\",\"smtp_encryption\",\"smtp_from_name\",\"smtp_from_email\",\"email_report_recipient\",\"email_report_cc\",\"email_report_bcc\",\"email_retry_interval\",\"email_max_retries\",\"email_report_enabled\",\"email_schedule\",\"email_timezone\",\"email_report_compress\"]','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 12:25:54'),(74,'u1000000-0000-0000-0000-000000000001','admin','EMAIL_SETTINGS_UPDATED','email',NULL,NULL,'[\"smtp_host\",\"smtp_port\",\"smtp_username\",\"smtp_encryption\",\"smtp_from_name\",\"smtp_from_email\",\"email_report_recipient\",\"email_report_cc\",\"email_report_bcc\",\"email_retry_interval\",\"email_max_retries\",\"email_report_enabled\",\"email_schedule\",\"email_timezone\",\"email_report_compress\"]','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 13:11:09'),(75,'u1000000-0000-0000-0000-000000000001','admin','EMAIL_SETTINGS_UPDATED','email',NULL,NULL,'[\"smtp_host\",\"smtp_port\",\"smtp_username\",\"smtp_encryption\",\"smtp_from_name\",\"smtp_from_email\",\"email_report_recipient\",\"email_report_cc\",\"email_report_bcc\",\"email_retry_interval\",\"email_max_retries\",\"email_report_enabled\",\"email_schedule\",\"email_timezone\",\"email_report_compress\"]','Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 13:14:03'),(76,'u1000000-0000-0000-0000-000000000001','admin','PROFILE_PICTURE_REMOVED','users','u1000000-0000-0000-0000-000000000001','\"avatars\\/1949c1d0-79fa-4eef-bf16-14a425ab4fe2.jpg\"',NULL,'Aaron','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 13:18:07');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_logs`
--

DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_logs` (
  `id` char(36) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `filesize` bigint(20) unsigned NOT NULL DEFAULT 0,
  `backup_type` enum('daily','weekly','monthly','manual') NOT NULL DEFAULT 'manual',
  `trigger_type` enum('automatic','manual') NOT NULL DEFAULT 'manual',
  `status` enum('success','failed','in_progress') NOT NULL DEFAULT 'in_progress',
  `duration_seconds` smallint(5) unsigned DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` char(36) DEFAULT NULL COMMENT 'NULL = cron job',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_backup_type` (`backup_type`),
  KEY `idx_backup_status` (`status`),
  KEY `idx_backup_created` (`created_at`),
  KEY `fk_backup_user` (`created_by`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_logs`
--

LOCK TABLES `backup_logs` WRITE;
/*!40000 ALTER TABLE `backup_logs` DISABLE KEYS */;
INSERT INTO `backup_logs` VALUES ('588023d2-8a17-480a-9da6-43e9ecb2b94a','','',0,'manual','manual','in_progress',NULL,NULL,0,'u1000000-0000-0000-0000-000000000001','2026-07-20 13:19:06');
/*!40000 ALTER TABLE `backup_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` char(36) NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `branch_manager` varchar(120) DEFAULT NULL,
  `time_zone` varchar(50) DEFAULT 'Asia/Manila',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_code` (`code`),
  UNIQUE KEY `uq_branches_name` (`name`),
  KEY `idx_branches_status` (`status`),
  KEY `idx_branches_manager` (`branch_manager`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES ('b1000000-0000-0000-0000-000000000001','Main Branch','MAIN','123 Main Street, City','Manila','Metro Manila','(02) 8123-4567','main@company.com',NULL,'Asia/Manila','active','2026-07-19 22:00:50','2026-07-19 22:00:50'),('b2000000-0000-0000-0000-000000000002','North Branch','NORTH','456 North Ave, City','Quezon City','Metro Manila','(02) 8234-5678','north@company.com',NULL,'Asia/Manila','active','2026-07-19 22:00:50','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` char(36) NOT NULL,
  `branch_id` char(36) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `department_head` varchar(120) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email_address` varchar(120) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_name` (`name`),
  UNIQUE KEY `uq_departments_code` (`code`),
  KEY `idx_departments_branch` (`branch_id`),
  KEY `idx_departments_status` (`status`),
  KEY `idx_departments_head` (`department_head`),
  CONSTRAINT `fk_dept_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES ('d1000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','Human Resources','HR','HR Department',NULL,'(02) 8123-4567','hr@company.com','Main Building, 2nd Floor','active','2026-07-19 22:00:50','2026-07-19 22:00:50'),('d2000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','Information Technology','IT','IT Department',NULL,'(02) 8123-4568','it@company.com','Main Building, 3rd Floor','active','2026-07-19 22:00:50','2026-07-19 22:00:50'),('d3000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','Finance & Accounting','FIN','Finance Department',NULL,'(02) 8123-4569','finance@company.com','Main Building, 4th Floor','active','2026-07-19 22:00:50','2026-07-19 22:00:50'),('d4000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','Operations','OPS','Operations Department',NULL,'(02) 8123-4570','operations@company.com','Main Building, 1st Floor','active','2026-07-19 22:00:50','2026-07-19 22:00:50'),('d5000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','Sales & Marketing','SMD','Sales & Marketing Dept',NULL,'(02) 8123-4571','sales@company.com','Main Building, 5th Floor','active','2026-07-19 22:00:50','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_logs` (
  `id` char(36) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `report_period` varchar(80) DEFAULT NULL COMMENT 'e.g. June 2026',
  `body_preview` text DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `status` enum('sent','failed','queued','retrying') NOT NULL DEFAULT 'queued',
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_test_run` tinyint(1) NOT NULL DEFAULT 0,
  `simulated_date` date DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_status` (`status`),
  KEY `idx_email_created` (`created_at`),
  KEY `idx_email_retry` (`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
INSERT INTO `email_logs` VALUES ('015727c8-f47e-4e62-9761-60a591247b9d','jerome.lim@ims.com.ph','[AMS] Attendance Report — July 2026 (1–15)','July 2026 (1–15)','\r\n\r\n\r\n\r\nAttendance Report — July 2026 (1–15)\r\n\r\n  body{font-family:Arial,sans-serif;margin:20px;color:#333}\r\n  h1{color:#1a56db}\r\n  table{border-collapse:collapse;width:100%;margin-top:16px;font-size:13px}\r\n  th,td{border:1px solid #ddd;padding:7px 10px;text-align:left}\r\n  th{background:#1a56db;color:#fff}\r\n  tr:nth-child(even){background:#f9fafb}\r\n  .meta{background:#eff6ff;padding:14px 18px;margin:16px 0;border-radius:6px;font-size:14px}\r\n  .meta p{margin:4px 0}\r\n\r\n\r\n\r\nMy Company — Atten','C:\\Users\\aaron\\AppData\\Local\\Temp\\ams_reports\\attendance_report_July_2026__1___15_.xlsx','sent',0,0,NULL,NULL,'2026-07-20 13:11:57','2026-07-20 13:11:52','2026-07-20 13:11:52','2026-07-20 13:11:57'),('33b62fcb-21e5-488d-9260-f2bf440b55dc','jerome.lim@ims.com.ph','[AMS] Attendance Report — July 2026 (1–15)','July 2026 (1–15)','Trigger: 15th of the month (simulated) | TZ: UTC | Date: 2026-07-15 | TEST RUN',NULL,'sent',0,1,'2026-07-15',NULL,'2026-07-20 13:11:57',NULL,'2026-07-20 13:11:57','2026-07-20 13:11:57'),('3c258a5c-2ec4-400f-a803-c78e0a431f80','jerome.lim@ims.com.ph','[AMS] Attendance Report — July 2026 (16–31)','July 2026 (16–31)','\r\n\r\n\r\n\r\nAttendance Report — July 2026 (16–31)\r\n\r\n  body{font-family:Arial,sans-serif;margin:20px;color:#333}\r\n  h1{color:#1a56db}\r\n  table{border-collapse:collapse;width:100%;margin-top:16px;font-size:13px}\r\n  th,td{border:1px solid #ddd;padding:7px 10px;text-align:left}\r\n  th{background:#1a56db;color:#fff}\r\n  tr:nth-child(even){background:#f9fafb}\r\n  .meta{background:#eff6ff;padding:14px 18px;margin:16px 0;border-radius:6px;font-size:14px}\r\n  .meta p{margin:4px 0}\r\n\r\n\r\n\r\nMy Company — Atte','C:\\Users\\aaron\\AppData\\Local\\Temp\\ams_reports\\attendance_report_July_2026__16___31_.xlsx','sent',0,0,NULL,NULL,'2026-07-20 13:12:41','2026-07-20 13:12:36','2026-07-20 13:12:36','2026-07-20 13:12:41'),('420ae7c6-d812-4335-824d-465db647556b','jerome.lim@ims.com.ph','[AMS] Attendance Report — July 2026 (1–15)','July 2026 (1–15)','Trigger: 15th of the month (simulated) | TZ: UTC | Date: 2026-07-15 | TEST RUN',NULL,'queued',0,1,'2026-07-15',NULL,NULL,NULL,'2026-07-20 13:14:26','2026-07-20 13:14:26'),('65c1a12e-1677-4cef-a4e8-0a117859fb96','jerome.lim@ims.com.ph','[AMS] Attendance Report — July 2026 (16–31)','July 2026 (16–31)','Trigger: End of month (simulated) | TZ: UTC | Date: 2026-07-31 | TEST RUN',NULL,'queued',0,1,'2026-07-31',NULL,NULL,NULL,'2026-07-20 13:14:22','2026-07-20 13:14:22'),('a6bb3fa2-5263-4ce6-be80-460effaa11f4','jerome.lim@ims.com.ph','[AMS] Attendance Report — July 2026 (16–31)','July 2026 (16–31)','Trigger: End of month (simulated) | TZ: UTC | Date: 2026-07-31 | TEST RUN',NULL,'sent',0,1,'2026-07-31',NULL,'2026-07-20 13:12:41',NULL,'2026-07-20 13:12:41','2026-07-20 13:12:41');
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_imports`
--

DROP TABLE IF EXISTS `employee_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_imports` (
  `id` char(36) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `success_count` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_report` longtext DEFAULT NULL,
  `imported_by` char(36) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_imports_created_by` (`imported_by`),
  KEY `idx_imports_created` (`created_at`),
  CONSTRAINT `fk_imports_created_by` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_imports`
--

LOCK TABLES `employee_imports` WRITE;
/*!40000 ALTER TABLE `employee_imports` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_imports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_timeline`
--

DROP TABLE IF EXISTS `employee_timeline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_timeline` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` char(36) NOT NULL,
  `event_type` enum('employee_created','employee_updated','employee_archived','employee_restored','employee_activated','employee_deactivated','account_created','account_updated','account_deleted','role_changed','password_changed','profile_picture_updated','status_changed','department_changed','branch_changed','shift_changed','position_changed','photo_updated','qr_code_regenerated','imported','attendance_milestone') NOT NULL,
  `previous_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timeline_employee` (`employee_id`),
  KEY `idx_timeline_type` (`event_type`),
  KEY `idx_timeline_created` (`created_at`),
  KEY `fk_timeline_created_by` (`created_by`),
  CONSTRAINT `fk_timeline_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_timeline_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_timeline`
--

LOCK TABLES `employee_timeline` WRITE;
/*!40000 ALTER TABLE `employee_timeline` DISABLE KEYS */;
INSERT INTO `employee_timeline` VALUES (1,'e1000000-0000-0000-0000-000000000001','profile_picture_updated',NULL,'avatars/63188439-eec7-4263-8f65-5269bf198963.jpg','Profile picture updated','u1000000-0000-0000-0000-000000000001','2026-07-19 22:05:54'),(2,'e1000000-0000-0000-0000-000000000001','profile_picture_updated','avatars/63188439-eec7-4263-8f65-5269bf198963.jpg','avatars/profile_u1000000-0000-0000-0000-000000000001_20260719_7c3e88.jpg','Profile picture updated','u1000000-0000-0000-0000-000000000001','2026-07-19 22:20:04'),(3,'e1000000-0000-0000-0000-000000000001','profile_picture_updated','avatars/profile_u1000000-0000-0000-0000-000000000001_20260719_7c3e88.jpg','avatars/profile_u1000000-0000-0000-0000-000000000001_20260719_656194.jpg','Profile picture updated','u1000000-0000-0000-0000-000000000001','2026-07-19 22:20:33'),(4,'e1000000-0000-0000-0000-000000000001','profile_picture_updated','avatars/profile_u1000000-0000-0000-0000-000000000001_20260719_656194.jpg',NULL,'Profile picture removed','u1000000-0000-0000-0000-000000000001','2026-07-19 22:20:36'),(5,'e3000000-0000-0000-0000-000000000001','profile_picture_updated',NULL,'avatars/profile_u3000000-0000-0000-0000-000000000001_20260719_351a3d.jpg','Profile picture updated','u3000000-0000-0000-0000-000000000001','2026-07-19 22:21:20'),(6,'e3000000-0000-0000-0000-000000000001','profile_picture_updated','avatars/profile_u3000000-0000-0000-0000-000000000001_20260719_351a3d.jpg',NULL,'Profile picture removed','u3000000-0000-0000-0000-000000000001','2026-07-19 22:22:00'),(7,'e1000000-0000-0000-0000-000000000001','profile_picture_updated',NULL,'avatars/profile_u1000000-0000-0000-0000-000000000001_20260719_2431c0.jpg','Profile picture updated','u1000000-0000-0000-0000-000000000001','2026-07-19 22:23:35'),(8,'e1000000-0000-0000-0000-000000000001','profile_picture_updated','avatars/profile_u1000000-0000-0000-0000-000000000001_20260719_2431c0.jpg',NULL,'Profile picture removed','u1000000-0000-0000-0000-000000000001','2026-07-19 22:38:15'),(9,'e1000000-0000-0000-0000-000000000001','profile_picture_updated',NULL,'avatars/57ad8634-0a38-420b-85b0-6c9fe66882c2.jpg','Profile picture updated','u1000000-0000-0000-0000-000000000001','2026-07-19 22:38:18'),(10,'e1000000-0000-0000-0000-000000000001','profile_picture_updated','avatars/57ad8634-0a38-420b-85b0-6c9fe66882c2.jpg','avatars/1949c1d0-79fa-4eef-bf16-14a425ab4fe2.jpg','Profile picture updated','u1000000-0000-0000-0000-000000000001','2026-07-19 22:53:50'),(11,'e3000000-0000-0000-0000-000000000001','role_changed','{\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":null,\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"photo\":null,\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"09193456789\",\"alternate_mobile\":null,\"email\":\"jose.garcia@company.com\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":null,\"username\":\"emp_jose\",\"password_hash\":\"$2y$12$yEaM\\/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm\",\"pin\":\"1234\",\"pin_hash\":\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\/.og\\/at2.uheWG\\/igi\",\"qr_code_value\":\"QR-EMP-0003-JOSE-GAR\",\"rfid_value\":\"RF-0003\",\"status\":\"active\",\"created_by\":null,\"created_at\":\"2026-07-19 22:00:50\",\"updated_by\":null,\"updated_at\":\"2026-07-19 22:00:50\",\"department_name\":\"Information Technology\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Day Shift (8AM-5PM)\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"Jose Garcia\",\"user_id\":\"u3000000-0000-0000-0000-000000000001\",\"user_role_id\":4,\"user_role_name\":\"Employee\",\"user_username\":\"emp_jose\",\"profile_picture\":null}','{\"_csrf\":\"98c4c9391a2471e434be140eb79e04288554dda490237e2cd277c9522f7d7b9c\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"emp_jose\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Role changed','u2000000-0000-0000-0000-000000000001','2026-07-19 23:43:22'),(12,'e3000000-0000-0000-0000-000000000001','employee_updated','{\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":null,\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"photo\":null,\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"09193456789\",\"alternate_mobile\":null,\"email\":\"jose.garcia@company.com\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":null,\"username\":\"emp_jose\",\"password_hash\":\"$2y$12$yEaM\\/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm\",\"pin\":\"1234\",\"pin_hash\":\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\/.og\\/at2.uheWG\\/igi\",\"qr_code_value\":\"QR-EMP-0003-JOSE-GAR\",\"rfid_value\":\"RF-0003\",\"status\":\"active\",\"created_by\":null,\"created_at\":\"2026-07-19 22:00:50\",\"updated_by\":null,\"updated_at\":\"2026-07-19 22:00:50\",\"department_name\":\"Information Technology\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Day Shift (8AM-5PM)\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"Jose Garcia\",\"user_id\":\"u3000000-0000-0000-0000-000000000001\",\"user_role_id\":4,\"user_role_name\":\"Employee\",\"user_username\":\"emp_jose\",\"profile_picture\":null}','{\"_csrf\":\"98c4c9391a2471e434be140eb79e04288554dda490237e2cd277c9522f7d7b9c\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"emp_jose\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Employee updated','u2000000-0000-0000-0000-000000000001','2026-07-19 23:43:22'),(13,'e3000000-0000-0000-0000-000000000001','profile_picture_updated',NULL,'avatars/profile_u30000000000_20260719_aa21fe85.jpg','Profile picture updated','u3000000-0000-0000-0000-000000000001','2026-07-19 23:43:36'),(14,'9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','employee_created',NULL,'{\"_csrf\":\"d4d408d85a084c7801538b9d347347d24c8bc4be5078c488e119ee708ba74871\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"username\":\"\",\"password\":\"\",\"role_id\":\"3\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"13b328f1-0b5b-47b7-be17-b53a48933b96\",\"pin\":\"342423\",\"rfid_value\":\"\",\"status\":\"active\"}','Employee created','u2000000-0000-0000-0000-000000000001','2026-07-20 12:09:43'),(15,'9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','password_changed',NULL,NULL,'Password changed by user','4c60df00-5c28-4c26-b023-1ecf8597c185','2026-07-20 12:10:05'),(16,'9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','shift_changed','{\"id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"0000-00-00\",\"civil_status\":\"\",\"nationality\":\"\",\"photo\":null,\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"13b328f1-0b5b-47b7-be17-b53a48933b96\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":null,\"username\":\"ABE123\",\"password_hash\":\"$2y$10$F0zdsMjAoiMuI3sKePZ\\/a.UbGHnrkYIx0n181uBO\\/3HqcTcu96Il.\",\"pin\":\"342423\",\"pin_hash\":\"$2y$10$Rza6ALMSlCakoXlyry7xqeqJcpwLJFiY3v8MGL07a3sjI8VBTINRO\",\"qr_code_value\":\"EMP-637C441780909ACA\",\"rfid_value\":null,\"status\":\"active\",\"created_by\":\"u2000000-0000-0000-0000-000000000001\",\"created_at\":\"2026-07-20 12:09:43\",\"updated_by\":null,\"updated_at\":\"2026-07-20 12:09:43\",\"department_name\":\"Finance & Accounting\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Regulart Hours\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"abegail lim\",\"user_id\":\"4c60df00-5c28-4c26-b023-1ecf8597c185\",\"user_role_id\":3,\"user_role_name\":\"Supervisor\",\"user_username\":\"ABE123\",\"profile_picture\":null}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"username\":\"ABE123\",\"password\":\"\",\"role_id\":\"3\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s3000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"\",\"status\":\"active\"}','Shift changed','u2000000-0000-0000-0000-000000000001','2026-07-20 12:12:03'),(17,'9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','role_changed','{\"id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"0000-00-00\",\"civil_status\":\"\",\"nationality\":\"\",\"photo\":null,\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"13b328f1-0b5b-47b7-be17-b53a48933b96\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":null,\"username\":\"ABE123\",\"password_hash\":\"$2y$10$F0zdsMjAoiMuI3sKePZ\\/a.UbGHnrkYIx0n181uBO\\/3HqcTcu96Il.\",\"pin\":\"342423\",\"pin_hash\":\"$2y$10$Rza6ALMSlCakoXlyry7xqeqJcpwLJFiY3v8MGL07a3sjI8VBTINRO\",\"qr_code_value\":\"EMP-637C441780909ACA\",\"rfid_value\":null,\"status\":\"active\",\"created_by\":\"u2000000-0000-0000-0000-000000000001\",\"created_at\":\"2026-07-20 12:09:43\",\"updated_by\":null,\"updated_at\":\"2026-07-20 12:09:43\",\"department_name\":\"Finance & Accounting\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Regulart Hours\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"abegail lim\",\"user_id\":\"4c60df00-5c28-4c26-b023-1ecf8597c185\",\"user_role_id\":3,\"user_role_name\":\"Supervisor\",\"user_username\":\"ABE123\",\"profile_picture\":null}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"username\":\"ABE123\",\"password\":\"\",\"role_id\":\"3\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s3000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"\",\"status\":\"active\"}','Role changed','u2000000-0000-0000-0000-000000000001','2026-07-20 12:12:03'),(18,'9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','employee_updated','{\"id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"0000-00-00\",\"civil_status\":\"\",\"nationality\":\"\",\"photo\":null,\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"13b328f1-0b5b-47b7-be17-b53a48933b96\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":null,\"username\":\"ABE123\",\"password_hash\":\"$2y$10$F0zdsMjAoiMuI3sKePZ\\/a.UbGHnrkYIx0n181uBO\\/3HqcTcu96Il.\",\"pin\":\"342423\",\"pin_hash\":\"$2y$10$Rza6ALMSlCakoXlyry7xqeqJcpwLJFiY3v8MGL07a3sjI8VBTINRO\",\"qr_code_value\":\"EMP-637C441780909ACA\",\"rfid_value\":null,\"status\":\"active\",\"created_by\":\"u2000000-0000-0000-0000-000000000001\",\"created_at\":\"2026-07-20 12:09:43\",\"updated_by\":null,\"updated_at\":\"2026-07-20 12:09:43\",\"department_name\":\"Finance & Accounting\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Regulart Hours\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"abegail lim\",\"user_id\":\"4c60df00-5c28-4c26-b023-1ecf8597c185\",\"user_role_id\":3,\"user_role_name\":\"Supervisor\",\"user_username\":\"ABE123\",\"profile_picture\":null}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"9fe6baed-eb5f-4a97-8f4f-6d60833f40a4\",\"employee_number\":\"abe-123\",\"first_name\":\"abegail\",\"middle_name\":\"\",\"last_name\":\"lim\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"324234324\",\"alternate_mobile\":\"\",\"email\":\"abegail@gmail.com\",\"username\":\"ABE123\",\"password\":\"\",\"role_id\":\"3\",\"home_address\":\"\",\"emergency_contact_name\":\"fesfes\",\"emergency_contact_number\":\"234234\",\"emergency_contact_relationship\":\"fesfesfes\",\"department_id\":\"d3000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"fesfe\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2026-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s3000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"\",\"status\":\"active\"}','Employee updated','u2000000-0000-0000-0000-000000000001','2026-07-20 12:12:03'),(19,'e3000000-0000-0000-0000-000000000001','role_changed','{\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"photo\":null,\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":null,\"username\":\"emp_jose\",\"password_hash\":\"$2y$10$gbdlJAkzr.BPXU81WFHaN.Auvcd3\\/WzMvcGCSXl8pnxdaqoPrNMtW\",\"pin\":\"1234\",\"pin_hash\":\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\/.og\\/at2.uheWG\\/igi\",\"qr_code_value\":\"QR-EMP-0003-JOSE-GAR\",\"rfid_value\":\"RF-0003\",\"status\":\"active\",\"created_by\":null,\"created_at\":\"2026-07-19 22:00:50\",\"updated_by\":\"u2000000-0000-0000-0000-000000000001\",\"updated_at\":\"2026-07-19 23:43:22\",\"department_name\":\"Information Technology\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Day Shift (8AM-5PM)\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"Jose Garcia\",\"user_id\":\"u3000000-0000-0000-0000-000000000001\",\"user_role_id\":4,\"user_role_name\":\"Employee\",\"user_username\":\"emp_jose\",\"profile_picture\":\"avatars\\/profile_u30000000000_20260719_aa21fe85.jpg\"}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"emp_jose\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Role changed','u2000000-0000-0000-0000-000000000001','2026-07-20 12:12:40'),(20,'e3000000-0000-0000-0000-000000000001','employee_updated','{\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"photo\":null,\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":null,\"username\":\"emp_jose\",\"password_hash\":\"$2y$10$gbdlJAkzr.BPXU81WFHaN.Auvcd3\\/WzMvcGCSXl8pnxdaqoPrNMtW\",\"pin\":\"1234\",\"pin_hash\":\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\/.og\\/at2.uheWG\\/igi\",\"qr_code_value\":\"QR-EMP-0003-JOSE-GAR\",\"rfid_value\":\"RF-0003\",\"status\":\"active\",\"created_by\":null,\"created_at\":\"2026-07-19 22:00:50\",\"updated_by\":\"u2000000-0000-0000-0000-000000000001\",\"updated_at\":\"2026-07-19 23:43:22\",\"department_name\":\"Information Technology\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Day Shift (8AM-5PM)\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"Jose Garcia\",\"user_id\":\"u3000000-0000-0000-0000-000000000001\",\"user_role_id\":4,\"user_role_name\":\"Employee\",\"user_username\":\"emp_jose\",\"profile_picture\":\"avatars\\/profile_u30000000000_20260719_aa21fe85.jpg\"}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"emp_jose\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Employee updated','u2000000-0000-0000-0000-000000000001','2026-07-20 12:12:40'),(21,'e3000000-0000-0000-0000-000000000001','shift_changed','{\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"photo\":null,\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":null,\"username\":\"emp_jose\",\"password_hash\":\"$2y$10$aQ4wuSx97shQj6irtiV3VOuPnokBsfZsFSV\\/cDGMQgT77TAbFdyYm\",\"pin\":\"1234\",\"pin_hash\":\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\/.og\\/at2.uheWG\\/igi\",\"qr_code_value\":\"QR-EMP-0003-JOSE-GAR\",\"rfid_value\":\"RF-0003\",\"status\":\"active\",\"created_by\":null,\"created_at\":\"2026-07-19 22:00:50\",\"updated_by\":\"u2000000-0000-0000-0000-000000000001\",\"updated_at\":\"2026-07-20 12:12:40\",\"department_name\":\"Information Technology\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Day Shift (8AM-5PM)\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"Jose Garcia\",\"user_id\":\"u3000000-0000-0000-0000-000000000001\",\"user_role_id\":4,\"user_role_name\":\"Employee\",\"user_username\":\"emp_jose\",\"profile_picture\":\"avatars\\/profile_u30000000000_20260719_aa21fe85.jpg\"}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"c1928020-1487-439e-b776-33eea39a9cd3\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Shift changed','u2000000-0000-0000-0000-000000000001','2026-07-20 12:13:29'),(22,'e3000000-0000-0000-0000-000000000001','role_changed','{\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"photo\":null,\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":null,\"username\":\"emp_jose\",\"password_hash\":\"$2y$10$aQ4wuSx97shQj6irtiV3VOuPnokBsfZsFSV\\/cDGMQgT77TAbFdyYm\",\"pin\":\"1234\",\"pin_hash\":\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\/.og\\/at2.uheWG\\/igi\",\"qr_code_value\":\"QR-EMP-0003-JOSE-GAR\",\"rfid_value\":\"RF-0003\",\"status\":\"active\",\"created_by\":null,\"created_at\":\"2026-07-19 22:00:50\",\"updated_by\":\"u2000000-0000-0000-0000-000000000001\",\"updated_at\":\"2026-07-20 12:12:40\",\"department_name\":\"Information Technology\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Day Shift (8AM-5PM)\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"Jose Garcia\",\"user_id\":\"u3000000-0000-0000-0000-000000000001\",\"user_role_id\":4,\"user_role_name\":\"Employee\",\"user_username\":\"emp_jose\",\"profile_picture\":\"avatars\\/profile_u30000000000_20260719_aa21fe85.jpg\"}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"c1928020-1487-439e-b776-33eea39a9cd3\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Role changed','u2000000-0000-0000-0000-000000000001','2026-07-20 12:13:29'),(23,'e3000000-0000-0000-0000-000000000001','employee_updated','{\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"photo\":null,\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"shift_id\":\"s2000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":null,\"username\":\"emp_jose\",\"password_hash\":\"$2y$10$aQ4wuSx97shQj6irtiV3VOuPnokBsfZsFSV\\/cDGMQgT77TAbFdyYm\",\"pin\":\"1234\",\"pin_hash\":\"$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC\\/.og\\/at2.uheWG\\/igi\",\"qr_code_value\":\"QR-EMP-0003-JOSE-GAR\",\"rfid_value\":\"RF-0003\",\"status\":\"active\",\"created_by\":null,\"created_at\":\"2026-07-19 22:00:50\",\"updated_by\":\"u2000000-0000-0000-0000-000000000001\",\"updated_at\":\"2026-07-20 12:12:40\",\"department_name\":\"Information Technology\",\"branch_name\":\"Main Branch\",\"shift_name\":\"Day Shift (8AM-5PM)\",\"supervisor_number\":null,\"supervisor_name\":null,\"full_name\":\"Jose Garcia\",\"user_id\":\"u3000000-0000-0000-0000-000000000001\",\"user_role_id\":4,\"user_role_name\":\"Employee\",\"user_username\":\"emp_jose\",\"profile_picture\":\"avatars\\/profile_u30000000000_20260719_aa21fe85.jpg\"}','{\"_csrf\":\"da497acdb16e23ccc3489a685c0e162a07be794bd73970adbbeab267ef7ab2a5\",\"id\":\"e3000000-0000-0000-0000-000000000001\",\"employee_number\":\"EMP-0003\",\"first_name\":\"Jose\",\"middle_name\":\"Cruz\",\"last_name\":\"Garcia\",\"suffix\":\"\",\"gender\":\"Male\",\"date_of_birth\":\"1990-06-01\",\"civil_status\":\"Single\",\"nationality\":\"Filipino\",\"contact_number\":\"09193456789\",\"alternate_mobile\":\"\",\"email\":\"jose.garcia@company.com\",\"username\":\"emp_jose\",\"password\":\"\",\"role_id\":\"4\",\"home_address\":\"789 East St, Manila\",\"emergency_contact_name\":\"Maria Garcia\",\"emergency_contact_number\":\"09203456789\",\"emergency_contact_relationship\":\"Mother\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"IT Specialist\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"2022-06-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"c1928020-1487-439e-b776-33eea39a9cd3\",\"pin\":\"\",\"rfid_value\":\"RF-0003\",\"status\":\"active\"}','Employee updated','u2000000-0000-0000-0000-000000000001','2026-07-20 12:13:29'),(24,'e2000000-0000-0000-0000-000000000001','password_changed',NULL,NULL,'Password changed by user','u2000000-0000-0000-0000-000000000001','2026-07-20 12:14:06'),(25,'00abab26-c518-42d1-b592-edd590df440a','employee_created',NULL,'{\"_csrf\":\"a944f14e0f4116dae77423f2044a44dcb2b07d10be7a369aaa5e81bed53162c5\",\"employee_number\":\"test123\",\"first_name\":\"test\",\"middle_name\":\"\",\"last_name\":\"test\",\"suffix\":\"\",\"gender\":\"\",\"date_of_birth\":\"\",\"civil_status\":\"\",\"nationality\":\"\",\"contact_number\":\"23432\",\"alternate_mobile\":\"\",\"email\":\"testing@Dwad.con\",\"username\":\"test123\",\"password\":\"test123\",\"role_id\":\"4\",\"home_address\":\"\",\"emergency_contact_name\":\"esfse\",\"emergency_contact_number\":\"543\",\"emergency_contact_relationship\":\"fsees\",\"department_id\":\"d2000000-0000-0000-0000-000000000001\",\"branch_id\":\"b1000000-0000-0000-0000-000000000001\",\"position\":\"dawddwa\",\"employment_status\":\"Active\",\"employment_type\":\"Regular\",\"date_hired\":\"1900-01-01\",\"immediate_supervisor_id\":\"\",\"shift_id\":\"c1928020-1487-439e-b776-33eea39a9cd3\",\"pin\":\"65775675\",\"rfid_value\":\"\",\"status\":\"active\"}','Employee created','u2000000-0000-0000-0000-000000000001','2026-07-20 12:15:33'),(26,'e2000000-0000-0000-0000-000000000001','profile_picture_updated',NULL,'avatars/profile_u20000000000_20260720_a0615fe3.jpg','Profile picture updated','u2000000-0000-0000-0000-000000000001','2026-07-20 12:16:56'),(27,'e1000000-0000-0000-0000-000000000001','profile_picture_updated','avatars/1949c1d0-79fa-4eef-bf16-14a425ab4fe2.jpg',NULL,'Profile picture removed','u1000000-0000-0000-0000-000000000001','2026-07-20 13:18:07');
/*!40000 ALTER TABLE `employee_timeline` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` char(36) NOT NULL,
  `employee_number` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Divorced') DEFAULT NULL,
  `nationality` varchar(80) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `department_id` char(36) DEFAULT NULL,
  `branch_id` char(36) DEFAULT NULL,
  `shift_id` char(36) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employment_status` enum('Active','Inactive','Suspended','Resigned','Terminated','Retired') NOT NULL DEFAULT 'Active',
  `employment_type` enum('Regular','Probationary','Contractual','Part-Time','Temporary','Intern') DEFAULT 'Probationary',
  `contact_number` varchar(30) DEFAULT NULL,
  `alternate_mobile` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `home_address` varchar(500) DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_number` varchar(30) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `immediate_supervisor_id` char(36) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `pin` varchar(10) DEFAULT NULL COMMENT 'Hashed PIN for attendance',
  `pin_hash` varchar(255) DEFAULT NULL,
  `qr_code_value` varchar(255) DEFAULT NULL COMMENT 'Unique QR payload',
  `rfid_value` varchar(100) DEFAULT NULL COMMENT 'RFID card number',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` char(36) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_number` (`employee_number`),
  UNIQUE KEY `uq_employee_qr` (`qr_code_value`),
  UNIQUE KEY `uq_employee_rfid` (`rfid_value`),
  UNIQUE KEY `uq_employee_username` (`username`),
  KEY `idx_employees_department` (`department_id`),
  KEY `idx_employees_branch` (`branch_id`),
  KEY `idx_employees_shift` (`shift_id`),
  KEY `idx_employees_status` (`status`),
  KEY `idx_employees_supervisor` (`immediate_supervisor_id`),
  KEY `idx_employees_username` (`username`),
  KEY `idx_employees_name` (`last_name`,`first_name`),
  KEY `fk_emp_created_by` (`created_by`),
  KEY `fk_emp_updated_by` (`updated_by`),
  CONSTRAINT `fk_emp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_supervisor` FOREIGN KEY (`immediate_supervisor_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_emp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES ('00abab26-c518-42d1-b592-edd590df440a','test123','test','','test','','','0000-00-00','','',NULL,'d2000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','c1928020-1487-439e-b776-33eea39a9cd3','dawddwa','Active','Regular','23432','','testing@Dwad.con','','esfse','543','fsees','1900-01-01',NULL,'test123','$2y$10$tIeJLa6XdAH/j3cbSggJ3.bnz.AEnqQw0SvdGIw.0Zs8bplNrK9ii','65775675','$2y$10$j1zjGiPyeXmULcjhMThCl.VKuua35eMuDU5UvnnijsJDZMbLFwfvO','EMP-9CB08D04C9C6FD66',NULL,'active','u2000000-0000-0000-0000-000000000001','2026-07-20 12:15:33',NULL,'2026-07-20 12:15:33'),('9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','abe-123','abegail','','lim','','','0000-00-00','','',NULL,'d3000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','s3000000-0000-0000-0000-000000000001','fesfe','Active','Regular','324234324','','abegail@gmail.com','','fesfes','234234','fesfesfes','2026-01-01',NULL,'ABE123','$2y$10$F0zdsMjAoiMuI3sKePZ/a.UbGHnrkYIx0n181uBO/3HqcTcu96Il.','342423','$2y$10$Rza6ALMSlCakoXlyry7xqeqJcpwLJFiY3v8MGL07a3sjI8VBTINRO','EMP-637C441780909ACA',NULL,'active','u2000000-0000-0000-0000-000000000001','2026-07-20 12:09:43','u2000000-0000-0000-0000-000000000001','2026-07-20 12:12:03'),('e1000000-0000-0000-0000-000000000001','EMP-0001','System',NULL,'Administrator',NULL,'Male','1990-01-01','Single','Filipino',NULL,'d1000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','s2000000-0000-0000-0000-000000000001','System Administrator','Active','Regular','09171234567',NULL,'admin@company.com','123 Main St, Manila','Juan Dela Cruz','09181234567','Spouse','2020-01-01',NULL,'admin','$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm','1234','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','QR-EMP-0001-SYSTEM-ADM','RF-0001','active',NULL,'2026-07-19 22:00:50',NULL,'2026-07-19 22:00:50'),('e2000000-0000-0000-0000-000000000001','EMP-0002','Maria','Santos','Reyes',NULL,'Female','1985-03-15','Married','Filipino',NULL,'d1000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','s2000000-0000-0000-0000-000000000001','HR Manager','Active','Regular','09182345678',NULL,'maria.reyes@company.com','456 North Ave, QC','Pedro Reyes','09192345678','Spouse','2021-03-15',NULL,'hr_maria','$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm','1234','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','QR-EMP-0002-MARIA-REY','RF-0002','active',NULL,'2026-07-19 22:00:50',NULL,'2026-07-19 22:00:50'),('e3000000-0000-0000-0000-000000000001','EMP-0003','Jose','Cruz','Garcia','','Male','1990-06-01','Single','Filipino',NULL,'d2000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','c1928020-1487-439e-b776-33eea39a9cd3','IT Specialist','Active','Regular','09193456789','','jose.garcia@company.com','789 East St, Manila','Maria Garcia','09203456789','Mother','2022-06-01',NULL,'emp_jose','$2y$10$aQ4wuSx97shQj6irtiV3VOuPnokBsfZsFSV/cDGMQgT77TAbFdyYm','1234','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','QR-EMP-0003-JOSE-GAR','RF-0003','active',NULL,'2026-07-19 22:00:50','u2000000-0000-0000-0000-000000000001','2026-07-20 12:13:29'),('e4000000-0000-0000-0000-000000000001','EMP-0004','Ana','Lopez','Torres',NULL,'Female','1995-01-10','Single','Filipino',NULL,'d3000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','s2000000-0000-0000-0000-000000000001','Accountant','Active','Probationary','09204567890',NULL,'ana.torres@company.com','321 South St, Manila','Carlos Torres','09214567890','Father','2024-01-10','e2000000-0000-0000-0000-000000000001',NULL,NULL,'1234','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','QR-EMP-0004-ANA-TOR','RF-0004','active',NULL,'2026-07-19 22:00:50',NULL,'2026-07-19 22:00:50'),('e5000000-0000-0000-0000-000000000001','EMP-0005','Roberto','Mendoza','Dela Cruz',NULL,'Male','1980-08-20','Married','Filipino',NULL,'d4000000-0000-0000-0000-000000000001','b1000000-0000-0000-0000-000000000001','s1000000-0000-0000-0000-000000000001','Operations Supervisor','Active','Regular','09215678901',NULL,'roberto.dc@company.com','654 West St, Manila','Elena Dela Cruz','09225678901','Spouse','2019-08-20',NULL,NULL,NULL,'1234','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','QR-EMP-0005-ROBERTO-DC','RF-0005','active',NULL,'2026-07-19 22:00:50',NULL,'2026-07-19 22:00:50');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` char(36) NOT NULL,
  `name` varchar(120) NOT NULL,
  `holiday_date` date NOT NULL,
  `branch_id` char(36) DEFAULT NULL,
  `type` enum('regular','special','company','branch') NOT NULL DEFAULT 'regular',
  `description` varchar(255) DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_holidays_date_name` (`holiday_date`,`name`),
  KEY `idx_holidays_date` (`holiday_date`),
  KEY `idx_holidays_branch` (`branch_id`),
  KEY `idx_holidays_status` (`status`),
  CONSTRAINT `fk_holiday_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
INSERT INTO `holidays` VALUES ('3ff8df2e-837a-11f1-a17e-00155d84e748','New Year\'s Day','2026-01-01',NULL,'regular','Regular annual holiday',1,'active','2026-07-19 22:00:50','2026-07-19 22:00:50'),('3ff9509d-837a-11f1-a17e-00155d84e748','Company Foundation Day','2026-07-15','b1000000-0000-0000-0000-000000000001','company','Company holiday',1,'active','2026-07-19 22:00:50','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_logs`
--

DROP TABLE IF EXISTS `job_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) NOT NULL,
  `status` enum('running','success','failed') NOT NULL DEFAULT 'running',
  `output` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_name` (`job_name`),
  KEY `idx_job_status` (`status`),
  KEY `idx_job_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_logs`
--

LOCK TABLES `job_logs` WRITE;
/*!40000 ALTER TABLE `job_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_balances`
--

DROP TABLE IF EXISTS `leave_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_balances` (
  `id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL,
  `leave_type` varchar(80) NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `entitled_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `used_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leave_balance` (`employee_id`,`leave_type`,`year`),
  CONSTRAINT `fk_balance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_balances`
--

LOCK TABLES `leave_balances` WRITE;
/*!40000 ALTER TABLE `leave_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL,
  `leave_type` enum('Vacation Leave','Sick Leave','Emergency Leave','Maternity Leave','Paternity Leave','Bereavement Leave','Unpaid Leave') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `number_of_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `reason` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `approved_by` char(36) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leave_employee` (`employee_id`),
  KEY `idx_leave_dates` (`start_date`,`end_date`),
  KEY `idx_leave_status` (`status`),
  KEY `idx_leave_type` (`leave_type`),
  KEY `fk_leave_approver` (`approved_by`),
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES ('3ffbe93d-837a-11f1-a17e-00155d84e748','e3000000-0000-0000-0000-000000000001','Vacation Leave','2026-07-26','2026-07-27',2.00,'Family event',NULL,'Pending',NULL,NULL,NULL,'2026-07-19 22:00:50','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `manual_attendance_requests`
--

DROP TABLE IF EXISTS `manual_attendance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manual_attendance_requests` (
  `id` char(36) NOT NULL,
  `employee_id` char(36) NOT NULL,
  `request_type` enum('time_in','break_out','break_in','time_out','overtime_in','overtime_out') NOT NULL,
  `request_date` date NOT NULL,
  `requested_time` time NOT NULL,
  `reason` text NOT NULL,
  `reason_category` enum('Forgot QR Code','Forgot PIN','RFID Card Lost','Device Unavailable','Power Outage','System Maintenance','Other') NOT NULL DEFAULT 'Other',
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `reviewed_by` char(36) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mar_employee` (`employee_id`),
  KEY `idx_mar_date` (`request_date`),
  KEY `idx_mar_status` (`status`),
  KEY `fk_mar_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_mar_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mar_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `manual_attendance_requests`
--

LOCK TABLES `manual_attendance_requests` WRITE;
/*!40000 ALTER TABLE `manual_attendance_requests` DISABLE KEYS */;
INSERT INTO `manual_attendance_requests` VALUES ('ae7a82f8-b90d-4230-a512-e678af1f8ed8','9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','time_in','2026-07-20','12:10:20','Manual attendance entry','Other','Approved',NULL,'2026-07-20 12:10:20','Auto-approved: attendance recorded immediately.','2026-07-20 12:10:20','2026-07-20 12:10:20'),('defc1016-554f-4282-ac6c-8040285b0298','e3000000-0000-0000-0000-000000000001','time_in','2026-07-20','12:13:40','Manual attendance entry','Other','Approved',NULL,'2026-07-20 12:13:40','Auto-approved: attendance recorded immediately.','2026-07-20 12:13:40','2026-07-20 12:13:40'),('e7fa8f52-3111-498d-8334-a394a980eecf','00abab26-c518-42d1-b592-edd590df440a','time_in','2026-07-20','12:16:05','Manual attendance entry','Other','Approved',NULL,'2026-07-20 12:16:05','Auto-approved: attendance recorded immediately.','2026-07-20 12:16:05','2026-07-20 12:16:05');
/*!40000 ALTER TABLE `manual_attendance_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `recipient_user_id` char(36) NOT NULL,
  `title` varchar(160) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient` (`recipient_user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('0aabf60b-a228-4a16-81a3-0b1553b78a0c','u2000000-0000-0000-0000-000000000001','Manual Attendance Recorded','test test recorded a manual Time In for 2026-07-20.','info',0,'2026-07-20 12:16:06'),('19185663-99fd-4c00-a6a7-9c158888f321','u2000000-0000-0000-0000-000000000001','Manual Attendance Recorded','Jose Garcia recorded a manual Time In for 2026-07-20.','info',0,'2026-07-20 12:13:40'),('274e4628-1bad-489e-8bd6-7efdc033f88e','u1000000-0000-0000-0000-000000000001','Manual Attendance Recorded','abegail lim recorded a manual Time In for 2026-07-20.','info',0,'2026-07-20 12:10:21'),('3fff3d87-837a-11f1-a17e-00155d84e748','u1000000-0000-0000-0000-000000000001','Database Initialized','The database has been successfully initialized with the master migration script.','success',0,'2026-07-19 22:00:50'),('4fe714cc-2fcb-4518-b783-4483b917c473','u1000000-0000-0000-0000-000000000001','Manual Attendance Recorded','Jose Garcia recorded a manual Time In for 2026-07-20.','info',0,'2026-07-20 12:13:40'),('55403d1d-7ecb-4799-a72a-593a5d44fb58','u1000000-0000-0000-0000-000000000001','Manual Attendance Recorded','test test recorded a manual Time In for 2026-07-20.','info',0,'2026-07-20 12:16:06'),('fea4e84b-052e-4cef-9578-5ddba9dbdf5a','u2000000-0000-0000-0000-000000000001','Manual Attendance Recorded','abegail lim recorded a manual Time In for 2026-07-20.','info',0,'2026-07-20 12:10:21');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'View Dashboard','dashboard.view','dashboard','Access dashboard','2026-07-19 22:00:50'),(2,'View Users','users.view','users','View user list','2026-07-19 22:00:50'),(3,'Create Users','users.create','users','Add new user','2026-07-19 22:00:50'),(4,'Edit Users','users.edit','users','Modify user','2026-07-19 22:00:50'),(5,'Delete Users','users.delete','users','Remove user','2026-07-19 22:00:50'),(6,'View Employees','employees.view','employees','View employee list','2026-07-19 22:00:50'),(7,'Create Employees','employees.create','employees','Add employee','2026-07-19 22:00:50'),(8,'Edit Employees','employees.edit','employees','Modify employee','2026-07-19 22:00:50'),(9,'Delete Employees','employees.delete','employees','Archive/restore employees','2026-07-19 22:00:50'),(10,'Manage Employee Status','employees.status','employees','Activate/deactivate/suspend employees','2026-07-19 22:00:50'),(11,'Import Employees','employees.import','employees','Import employees from Excel','2026-07-19 22:00:50'),(12,'Export Employees','employees.export','employees','Export employee data','2026-07-19 22:00:50'),(13,'View Employee Timeline','employees.timeline','employees','View employee activity timeline','2026-07-19 22:00:50'),(14,'Manage Employee Photos','employees.photos','employees','Upload and manage employee photos','2026-07-19 22:00:50'),(15,'Regenerate QR Codes','employees.qr_regenerate','employees','Regenerate employee QR codes','2026-07-19 22:00:50'),(16,'View Departments','departments.view','departments','View departments','2026-07-19 22:00:50'),(17,'Create Departments','departments.create','departments','Add department','2026-07-19 22:00:50'),(18,'Edit Departments','departments.edit','departments','Modify department','2026-07-19 22:00:50'),(19,'View Branches','branches.view','branches','View branches','2026-07-19 22:00:50'),(20,'Create Branches','branches.create','branches','Add branch','2026-07-19 22:00:50'),(21,'Edit Branches','branches.edit','branches','Modify branch','2026-07-19 22:00:50'),(22,'View Shifts','shifts.view','shifts','View shifts','2026-07-19 22:00:50'),(23,'Create Shifts','shifts.create','shifts','Add shift','2026-07-19 22:00:50'),(24,'Edit Shifts','shifts.edit','shifts','Modify shift','2026-07-19 22:00:50'),(25,'View All Attendance','attendance.view_all','attendance','View any employee attendance','2026-07-19 22:00:50'),(26,'View Own Attendance','attendance.view_own','attendance','View own attendance only','2026-07-19 22:00:50'),(27,'Record Attendance','attendance.record','attendance','Record attendance entry','2026-07-19 22:00:50'),(28,'Edit Attendance','attendance.edit','attendance','Modify attendance records','2026-07-19 22:00:50'),(29,'View Attendance Monitoring','attendance.monitor','attendance','Access attendance monitoring','2026-07-19 22:00:50'),(30,'Manage Manual Attendance','manual_attendance.manage','manual_attendance','Admin: create manual attendance records','2026-07-19 22:00:50'),(31,'Request Manual Attendance','manual_attendance.request','manual_attendance','Employee: submit manual time-in/out requests','2026-07-19 22:00:50'),(32,'Approve Manual Attendance','manual_attendance.approve','manual_attendance','Admin: approve/reject manual attendance requests','2026-07-19 22:00:50'),(33,'Manage Leave Requests','leaves.manage','leaves','Approve, reject, cancel and search leave requests','2026-07-19 22:00:50'),(34,'Create Own Leave Requests','leaves.create_own','leaves','Submit own leave requests','2026-07-19 22:00:50'),(35,'Manage Corrections','corrections.manage','corrections','Review attendance correction requests','2026-07-19 22:00:50'),(36,'Create Own Corrections','corrections.create_own','corrections','Submit attendance corrections','2026-07-19 22:00:50'),(37,'Manage Holidays','holidays.manage','holidays','Create, edit and deactivate holidays','2026-07-19 22:00:50'),(38,'View Notifications','notifications.view','notifications','View internal notifications','2026-07-19 22:00:50'),(39,'Manage Announcements','announcements.manage','announcements','Create and manage announcements','2026-07-19 22:00:50'),(40,'View Announcements','announcements.view','announcements','View announcements on dashboard','2026-07-19 22:00:50'),(41,'View Audit Logs','audit.view','audit','Access audit trail','2026-07-19 22:00:50'),(42,'View Reports','reports.view','reports','Access reports','2026-07-19 22:00:50'),(43,'Use Global Search','search.use','search','Use reusable global search','2026-07-19 22:00:50'),(44,'Manage Settings','settings.manage','settings','Edit system settings','2026-07-19 22:00:50'),(45,'Manage System Settings','system.settings','system','Change system configuration','2026-07-19 22:00:50'),(46,'View System Health','system.health','system','View system health dashboard','2026-07-19 22:00:50'),(47,'View Email Logs','email_logs.view','email','View email delivery logs','2026-07-19 22:00:50'),(48,'Manage Email Settings','email_settings.manage','email','Configure SMTP and email report settings','2026-07-19 22:00:50'),(49,'Manage Backups','backup.manage','backup','Create, download, restore and delete backups','2026-07-19 22:00:50'),(50,'View Backup Logs','backup_logs.view','backup','View backup history','2026-07-19 22:00:50'),(51,'View Job Logs','job_logs.view','system','View background job execution history','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` tinyint(3) unsigned NOT NULL,
  `permission_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,44),(1,45),(1,46),(1,47),(1,48),(1,49),(1,50),(1,51),(2,1),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,22),(2,23),(2,24),(2,25),(2,27),(2,28),(2,29),(2,30),(2,32),(2,33),(2,34),(2,35),(2,36),(2,37),(2,38),(2,39),(2,40),(2,42),(2,43),(2,44),(3,1),(3,6),(3,25),(3,29),(3,32),(3,33),(3,35),(3,38),(3,40),(3,43),(4,1),(4,26),(4,31),(4,34),(4,36),(4,38),(4,40),(4,43);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrator','administrator','Full system access','2026-07-19 22:00:50'),(2,'HR','hr','Human Resources - employee and attendance management','2026-07-19 22:00:50'),(3,'Supervisor','supervisor','Department supervisor - can view team attendance','2026-07-19 22:00:50'),(4,'Employee','employee','Can view own attendance only','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(80) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `group` varchar(50) NOT NULL DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`),
  KEY `idx_settings_group` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'app_name','Attendance Management System','string','general','Application name','2026-07-19 22:00:50'),(2,'app_version','1.0.0','string','general','Application version','2026-07-19 22:00:50'),(3,'branch_name','Main Branch','string','general','Current branch name','2026-07-19 22:00:50'),(4,'branch_id','b1000000-0000-0000-0000-000000000001','string','general','Current branch UUID','2026-07-19 22:00:50'),(5,'company_name','My Company','string','company','Company name','2026-07-19 22:00:50'),(6,'company_branch','Main Branch','string','company','Branch name','2026-07-19 22:00:50'),(7,'company_logo','','string','company','Logo path relative to public/','2026-07-19 22:00:50'),(8,'timezone','Asia/Manila','string','system','System timezone','2026-07-19 22:00:50'),(9,'date_format','F j, Y','string','system','PHP date format for display','2026-07-19 22:00:50'),(10,'time_format','h:i A','string','system','PHP time format for display','2026-07-19 22:00:50'),(11,'session_timeout','480','integer','security','Session timeout in minutes (480 = 8h)','2026-07-19 22:00:50'),(12,'max_login_attempts','5','integer','security','Max failed login attempts before lockout','2026-07-19 22:00:50'),(13,'lockout_minutes','30','integer','security','Account lockout duration in minutes','2026-07-19 22:00:50'),(14,'grace_period_minutes','15','integer','attendance','Grace period in minutes before marking late','2026-07-19 22:00:50'),(15,'work_hours_per_day','8','','attendance','Standard working hours per day','2026-07-19 22:00:50'),(16,'overtime_threshold','30','integer','attendance','Minutes after shift end before counting overtime','2026-07-19 22:00:50'),(17,'late_deduction','1','boolean','attendance','Deduct late minutes from salary','2026-07-19 22:00:50'),(18,'allowed_time_in_from','06:00','','attendance','Earliest allowed time-in','2026-07-19 22:00:50'),(19,'allowed_time_in_to','10:00','','attendance','Latest allowed time-in (warn if exceeded)','2026-07-19 22:00:50'),(20,'method_pin','1','boolean','attendance','Enable PIN attendance method','2026-07-19 22:00:50'),(21,'method_qr','1','boolean','attendance','Enable QR Code attendance method','2026-07-19 22:00:50'),(22,'method_rfid','1','boolean','attendance','Enable RFID attendance method','2026-07-19 22:00:50'),(23,'method_manual','1','boolean','attendance','Enable Manual attendance method','2026-07-19 22:00:50'),(24,'attendance_kiosk_mode','1','boolean','attendance','Enable kiosk mode (no login required)','2026-07-19 22:00:50'),(25,'pin_length','4','integer','attendance','Default PIN length','2026-07-19 22:00:50'),(26,'allow_manual_entry','1','boolean','attendance','Allow HR/Admin to manually add attendance','2026-07-19 22:00:50'),(27,'exclude_weekends_from_leave','1','boolean','leave','Exclude Saturdays and Sundays from leave day calculation','2026-07-19 22:00:50'),(28,'backup_enabled','1','boolean','backup','Enable automatic backups','2026-07-19 22:00:50'),(29,'backup_daily','1','boolean','backup','Run daily backup','2026-07-19 22:00:50'),(30,'backup_weekly','1','boolean','backup','Run weekly backup','2026-07-19 22:00:50'),(31,'backup_monthly','1','boolean','backup','Run monthly backup','2026-07-19 22:00:50'),(32,'backup_retention_days','30','integer','backup','Days to keep old backups','2026-07-19 22:00:50'),(33,'backup_compress','1','boolean','backup','Compress backups with gzip/zip','2026-07-19 22:00:50'),(34,'backup_path','','string','backup','Absolute path to backup directory (blank = auto)','2026-07-19 22:00:50'),(35,'smtp_host','smtp.gmail.com','string','email','SMTP server hostname','2026-07-20 13:14:03'),(36,'smtp_port','587','integer','email','SMTP port','2026-07-20 13:14:03'),(37,'smtp_username','lim.jerome31.lj@gmail.com','string','email','SMTP username','2026-07-20 13:14:03'),(38,'smtp_password','ZN8GxfB1QbOvxLTG+X6wI3RWdiswbWs3YWY1a0MzVnhlQ3RMb1I5MFhaVlZMS0xrZHZxemM4UDJmaUE9','string','email','SMTP password (stored encrypted)','2026-07-20 12:25:54'),(39,'smtp_encryption','tls','string','email','Encryption: tls or ssl','2026-07-20 13:14:03'),(40,'smtp_from_name','Attendance System','string','email','Sender display name','2026-07-20 13:14:03'),(41,'smtp_from_email','lim.jerome31.lj@gmail.com','string','email','Sender email address','2026-07-20 13:14:03'),(42,'email_report_recipient','jerome.lim@ims.com.ph','string','email','Primary report recipient email','2026-07-20 13:14:03'),(43,'email_report_cc','','string','email','CC addresses (comma-separated)','2026-07-20 13:14:03'),(44,'email_report_bcc','','string','email','BCC addresses (comma-separated)','2026-07-20 13:14:03'),(45,'email_retry_interval','60','integer','email','Minutes between retry attempts','2026-07-20 13:14:03'),(46,'email_max_retries','5','integer','email','Maximum retry attempts before giving up','2026-07-20 13:14:03'),(47,'email_report_enabled','1','boolean','email','Enable automatic monthly email reports','2026-07-20 13:14:03'),(48,'email_report_compress','0','boolean','email','Compress report attachments into ZIP','2026-07-20 13:14:03'),(49,'email_schedule','both','string','email','Email schedule: manual, 15th, end_of_month, or both','2026-07-20 13:14:03'),(50,'email_timezone','UTC','string','email','Timezone for email scheduling (e.g., Asia/Manila, America/New_York)','2026-07-20 13:14:03'),(51,'last_email_sent_date',NULL,'string','email','Last date when scheduled email was sent (YYYY-MM-DD)','2026-07-19 22:00:50'),(52,'log_retention_days','90','integer','maintenance','Days to retain audit and system logs','2026-07-19 22:00:50'),(53,'session_cleanup_days','7','integer','maintenance','Days to retain expired sessions','2026-07-19 22:00:50'),(54,'archive_after_months','12','integer','maintenance','Months before archiving old attendance records','2026-07-19 22:00:50'),(55,'photo_max_size_kb','2048','integer','upload','Maximum photo size in KB','2026-07-19 22:00:50'),(56,'pagination_limit','25','integer','ui','Records per page','2026-07-19 22:00:50'),(57,'employee_photo_max_size','2097152','integer','employees','Maximum employee photo size in bytes (2MB)','2026-07-19 22:00:50'),(58,'employee_photo_allowed_types','jpg,jpeg,png','string','employees','Allowed employee photo file types','2026-07-19 22:00:50'),(59,'qr_code_size','300','integer','employees','QR code image size in pixels','2026-07-19 22:00:50'),(60,'qr_code_error_correction','M','string','employees','QR code error correction level (L, M, Q, H)','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shifts`
--

DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shifts` (
  `id` char(36) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('regular','night','flexible') NOT NULL DEFAULT 'regular',
  `time_in` time NOT NULL,
  `time_out` time NOT NULL,
  `lunch_break_start` time DEFAULT NULL,
  `lunch_break_end` time DEFAULT NULL,
  `lunch_break_minutes` tinyint(3) unsigned NOT NULL DEFAULT 60,
  `grace_period_minutes` tinyint(3) unsigned NOT NULL DEFAULT 15,
  `required_hours` decimal(4,2) NOT NULL DEFAULT 8.00,
  `overnight` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = time_out is next day',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shifts_status` (`status`),
  KEY `idx_shifts_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shifts`
--

LOCK TABLES `shifts` WRITE;
/*!40000 ALTER TABLE `shifts` DISABLE KEYS */;
INSERT INTO `shifts` VALUES ('13b328f1-0b5b-47b7-be17-b53a48933b96','Regulart Hours','','regular','08:30:00','17:30:00','12:00:00','13:00:00',60,15,8.00,0,'active',1,'2026-07-20 12:05:48','2026-07-20 12:05:48'),('c1928020-1487-439e-b776-33eea39a9cd3','test','','regular','12:00:00','21:00:00','15:00:00','13:00:00',60,15,8.00,0,'active',0,'2026-07-20 12:13:10','2026-07-20 12:13:10'),('s1000000-0000-0000-0000-000000000001','Morning Shift (7AM-4PM)',NULL,'regular','07:00:00','16:00:00','12:00:00','13:00:00',60,15,8.00,0,'active',0,'2026-07-19 22:00:50','2026-07-19 22:00:50'),('s2000000-0000-0000-0000-000000000001','Day Shift (8AM-5PM)',NULL,'regular','08:00:00','17:00:00','12:00:00','13:00:00',60,15,8.00,0,'active',0,'2026-07-19 22:00:50','2026-07-20 12:05:48'),('s3000000-0000-0000-0000-000000000001','Mid Shift (10AM-7PM)',NULL,'regular','10:00:00','19:00:00','14:00:00','15:00:00',60,15,8.00,0,'active',0,'2026-07-19 22:00:50','2026-07-19 22:00:50'),('s4000000-0000-0000-0000-000000000001','Night Shift (10PM-6AM)',NULL,'night','22:00:00','06:00:00','02:00:00','03:00:00',60,15,8.00,1,'active',0,'2026-07-19 22:00:50','2026-07-19 22:00:50'),('s5000000-0000-0000-0000-000000000001','Flexible Shift',NULL,'flexible','08:00:00','17:00:00','12:00:00','13:00:00',60,30,8.00,0,'active',0,'2026-07-19 22:00:50','2026-07-19 22:00:50');
/*!40000 ALTER TABLE `shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` char(36) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint(3) unsigned NOT NULL,
  `employee_id` char(36) DEFAULT NULL,
  `full_name` varchar(180) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `failed_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `profile_picture` varchar(255) DEFAULT NULL COMMENT 'Relative path under uploads/avatars/',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_employee` (`employee_id`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_user_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES ('4c60df00-5c28-4c26-b023-1ecf8597c185','ABE123','$2y$10$F0zdsMjAoiMuI3sKePZ/a.UbGHnrkYIx0n181uBO/3HqcTcu96Il.',3,'9fe6baed-eb5f-4a97-8f4f-6d60833f40a4','abegail lim','abegail@gmail.com','active',0,NULL,'2026-07-20 12:09:57','::1','2026-07-20 12:10:05',0,NULL,'2026-07-20 12:09:43','2026-07-20 12:12:03'),('4e5bcc90-2e31-46bb-abd1-effd6e2044be','test123','$2y$10$tIeJLa6XdAH/j3cbSggJ3.bnz.AEnqQw0SvdGIw.0Zs8bplNrK9ii',4,'00abab26-c518-42d1-b592-edd590df440a','test test','testing@Dwad.con','active',0,NULL,'2026-07-20 12:15:40','::1',NULL,1,NULL,'2026-07-20 12:15:33','2026-07-20 12:15:40'),('u1000000-0000-0000-0000-000000000001','admin','$2y$12$yEaM/kCQSTgDSRFxK9khVOmvc7vlLAkw3j36UBSTXi33yyryHvvwm',1,'e1000000-0000-0000-0000-000000000001','System Administrator','admin@company.com','active',0,NULL,'2026-07-20 12:22:52','::1','2026-07-19 22:00:50',0,NULL,'2026-07-19 22:00:50','2026-07-20 13:18:07'),('u2000000-0000-0000-0000-000000000001','hr_maria','$2y$10$LZONrV/sHQdNK/O1OU8hfek6AtLhNqz8QMi9rkChfc80eV5JUBbzS',2,'e2000000-0000-0000-0000-000000000001','Maria Santos Reyes','maria.reyes@company.com','active',0,NULL,'2026-07-20 12:16:13','::1','2026-07-20 12:14:06',0,'avatars/profile_u20000000000_20260720_a0615fe3.jpg','2026-07-19 22:00:50','2026-07-20 12:16:56'),('u3000000-0000-0000-0000-000000000001','emp_jose','$2y$10$aQ4wuSx97shQj6irtiV3VOuPnokBsfZsFSV/cDGMQgT77TAbFdyYm',4,'e3000000-0000-0000-0000-000000000001','Jose Garcia','jose.garcia@company.com','active',0,NULL,'2026-07-20 12:13:35','::1','2026-07-19 22:00:50',0,'avatars/profile_u30000000000_20260719_aa21fe85.jpg','2026-07-19 22:00:50','2026-07-20 12:13:35');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'attendance_db'
--

--
-- Dumping routines for database 'attendance_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-20 13:19:06
