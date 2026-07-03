-- ============================================================
-- Internal Team Chat (direct messages)
-- ============================================================

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id`    INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `message`      TEXT NOT NULL,
  `is_read`      TINYINT(1) DEFAULT 0,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_conversation` (`sender_id`, `recipient_id`, `id`),
  KEY `idx_recipient_unread` (`recipient_id`, `is_read`),
  FOREIGN KEY (`sender_id`)    REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recipient_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
