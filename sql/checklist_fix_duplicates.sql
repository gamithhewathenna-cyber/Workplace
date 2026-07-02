-- ============================================================
-- Fix: Daily Checklist Duplicate Rows
-- Run this once on the production database.
-- ============================================================

-- 1) If any duplicate row was already marked complete, carry that
--    status over to the row we're about to keep (the earliest one).
UPDATE daily_checklist dc
JOIN (
    SELECT template_id, employee_id, check_date, MIN(id) AS keep_id
    FROM daily_checklist
    GROUP BY template_id, employee_id, check_date
) grp
  ON grp.template_id = dc.template_id
 AND grp.employee_id = dc.employee_id
 AND grp.check_date  = dc.check_date
 AND dc.id = grp.keep_id
SET dc.is_completed = 1
WHERE EXISTS (
    SELECT 1 FROM daily_checklist dc2
    WHERE dc2.template_id = dc.template_id
      AND dc2.employee_id = dc.employee_id
      AND dc2.check_date  = dc.check_date
      AND dc2.is_completed = 1
);

-- 2) Remove duplicate rows, keeping only the earliest (lowest id)
--    per template/employee/date combination.
DELETE dc1 FROM daily_checklist dc1
INNER JOIN daily_checklist dc2
  ON dc1.template_id = dc2.template_id
 AND dc1.employee_id = dc2.employee_id
 AND dc1.check_date  = dc2.check_date
 AND dc1.id > dc2.id;

-- 3) Prevent this from ever happening again: enforce one row per
--    template/employee/date at the database level. This is what the
--    existing `INSERT IGNORE` in generate_daily_checklist() needs to
--    actually be idempotent.
ALTER TABLE daily_checklist
  ADD UNIQUE KEY `uq_template_emp_date` (`template_id`, `employee_id`, `check_date`);
