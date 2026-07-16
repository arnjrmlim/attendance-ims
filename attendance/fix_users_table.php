<?php
/**
 * Fix users table - recreate if missing or corrupted
 */

// Load database config
$config = require __DIR__ . '/config/database.php';

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $db = new PDO($dsn, $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists
    $checkSql = "SHOW TABLES LIKE 'users'";
    $stmt = $db->query($checkSql);
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "Table exists, discarding tablespace and dropping it...\n";
        try {
            $discardSql = "ALTER TABLE users DISCARD TABLESPACE";
            $db->exec($discardSql);
        } catch (PDOException $e) {
            echo "Discard failed (expected): " . $e->getMessage() . "\n";
        }
        $dropSql = "DROP TABLE IF EXISTS users";
        $db->exec($dropSql);
    }
    
    // Create the table without foreign key first
    echo "Creating users table...\n";
    $createSql = "
    CREATE TABLE `users` (
      `id`               CHAR(36)     NOT NULL,
      `username`         VARCHAR(60)  NOT NULL,
      `password_hash`    VARCHAR(255) NOT NULL,
      `role_id`          TINYINT UNSIGNED NOT NULL,
      `employee_id`      CHAR(36)     DEFAULT NULL,
      `full_name`        VARCHAR(180) DEFAULT NULL,
      `email`            VARCHAR(120) DEFAULT NULL,
      `status`           ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
      `failed_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
      `locked_until`     DATETIME     DEFAULT NULL,
      `last_login`       DATETIME     DEFAULT NULL,
      `last_login_ip`    VARCHAR(45)  DEFAULT NULL,
      `password_changed_at` DATETIME  DEFAULT NULL,
      `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_users_username`   (`username`),
      UNIQUE KEY `uq_users_employee`   (`employee_id`),
      KEY `idx_users_role`             (`role_id`),
      KEY `idx_users_status`           (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($createSql);
    echo "Users table created successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
