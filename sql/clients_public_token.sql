-- ============================================================
-- Public, no-login access token per client — used to build a
-- link clients (and CC'd people) can open straight from the
-- reminder email to view and check off their pending follow-ups,
-- without ever seeing or logging into the portal itself.
-- ============================================================

ALTER TABLE `clients`
  ADD COLUMN `public_token` VARCHAR(64) NULL AFTER `cc_emails`,
  ADD UNIQUE KEY `uq_public_token` (`public_token`);
