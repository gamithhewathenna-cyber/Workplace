-- ============================================================
-- Weekly task archiving: completed tasks are hidden from the active
-- task list/board every Monday at 4PM, but never deleted.
-- ============================================================

ALTER TABLE `tasks`
  ADD COLUMN `archived_at` DATETIME NULL AFTER `status`;
