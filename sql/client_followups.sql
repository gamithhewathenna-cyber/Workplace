-- ============================================================
-- Client Follow-ups — a lightweight per-client to-do list
-- (no due date/priority/assignee — just quick action items)
-- ============================================================

CREATE TABLE IF NOT EXISTS `client_followups` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id`    INT UNSIGNED NOT NULL,
  `title`        VARCHAR(255) NOT NULL,
  `is_completed` TINYINT(1) DEFAULT 0,
  `completed_at` DATETIME DEFAULT NULL,
  `completed_by` INT UNSIGNED DEFAULT NULL,
  `created_by`   INT UNSIGNED NOT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`)    REFERENCES `clients`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`created_by`)   REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`completed_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
