-- ============================================================
-- Per-Employee Checklist Assignment
-- Run this AFTER modules_schema.sql
-- ============================================================

ALTER TABLE `checklist_templates`
  ADD COLUMN `scope` ENUM('all','specific') NOT NULL DEFAULT 'all' AFTER `is_active`;

CREATE TABLE IF NOT EXISTS `checklist_template_assignees` (
  `template_id`  INT UNSIGNED NOT NULL,
  `employee_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`template_id`, `employee_id`),
  FOREIGN KEY (`template_id`) REFERENCES `checklist_templates`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
