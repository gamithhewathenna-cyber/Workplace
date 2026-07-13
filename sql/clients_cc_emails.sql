-- ============================================================
-- Add per-client CC emails (comma-separated) — used when sending
-- Client Follow-up reminder emails to this client.
-- ============================================================

ALTER TABLE `clients`
  ADD COLUMN `cc_emails` VARCHAR(500) NULL AFTER `email`;
