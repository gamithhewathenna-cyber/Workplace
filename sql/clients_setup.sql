-- Clients table
-- Run this in phpMyAdmin if the clients table does not yet exist.

CREATE TABLE IF NOT EXISTS `clients` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`           VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255)  DEFAULT NULL,
  `email`          VARCHAR(255)  DEFAULT NULL,
  `phone`          VARCHAR(50)   DEFAULT NULL,
  `website`        VARCHAR(255)  DEFAULT NULL,
  `notes`          TEXT          DEFAULT NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If the clients table already existed with only id/name/is_active columns,
-- run these ALTER statements to add the extra fields:
--
-- ALTER TABLE `clients` ADD COLUMN `contact_person` VARCHAR(255) DEFAULT NULL AFTER `name`;
-- ALTER TABLE `clients` ADD COLUMN `email`          VARCHAR(255) DEFAULT NULL AFTER `contact_person`;
-- ALTER TABLE `clients` ADD COLUMN `phone`          VARCHAR(50)  DEFAULT NULL AFTER `email`;
-- ALTER TABLE `clients` ADD COLUMN `website`        VARCHAR(255) DEFAULT NULL AFTER `phone`;
-- ALTER TABLE `clients` ADD COLUMN `notes`          TEXT         DEFAULT NULL AFTER `website`;
-- ALTER TABLE `clients` ADD COLUMN `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `is_active`;
