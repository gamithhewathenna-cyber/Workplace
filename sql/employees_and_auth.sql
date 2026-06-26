-- ============================================================
-- employees_and_auth.sql
-- Run this in phpMyAdmin after modules_schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `employees` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(255) NOT NULL,
  `email`      VARCHAR(255) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `position`   VARCHAR(100) DEFAULT NULL,
  `role`       ENUM('employee','manager','admin','hr') DEFAULT 'employee',
  `status`     ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
