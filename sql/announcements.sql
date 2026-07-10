-- ============================================================
-- Company Announcements (broadcast to everyone)
-- ============================================================

CREATE TABLE IF NOT EXISTS `company_announcements` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_active`  TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`created_by`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tracks which employees have dismissed which announcement's dashboard banner
CREATE TABLE IF NOT EXISTS `announcement_dismissals` (
  `announcement_id` INT UNSIGNED NOT NULL,
  `employee_id`      INT UNSIGNED NOT NULL,
  `dismissed_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`, `employee_id`),
  FOREIGN KEY (`announcement_id`) REFERENCES `company_announcements`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
