-- ============================================================
-- Per-employee permission: allow assigning tasks to teammates
-- without promoting the employee to manager/hr/admin.
-- ============================================================

ALTER TABLE `employees`
  ADD COLUMN `can_assign_tasks` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`;
