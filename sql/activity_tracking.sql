-- ============================================================
-- Presence / activity tracking: Active -> Away (15 min idle) ->
-- Auto Logout (configurable, 30-60 min idle). Tracked per daily
-- emp_login_log row alongside the existing on_time/late status.
-- ============================================================

ALTER TABLE `emp_login_log`
  ADD COLUMN IF NOT EXISTS `presence_status`   ENUM('active','away','logged_out') DEFAULT 'active' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `last_activity_at`  DATETIME NULL AFTER `presence_status`,
  ADD COLUMN IF NOT EXISTS `last_heartbeat_at` DATETIME NULL AFTER `last_activity_at`,
  ADD COLUMN IF NOT EXISTS `active_seconds`    INT UNSIGNED DEFAULT 0 AFTER `last_heartbeat_at`,
  ADD COLUMN IF NOT EXISTS `away_seconds`      INT UNSIGNED DEFAULT 0 AFTER `active_seconds`,
  ADD COLUMN IF NOT EXISTS `logout_at`         DATETIME NULL AFTER `away_seconds`,
  ADD COLUMN IF NOT EXISTS `logout_reason`     VARCHAR(50) NULL AFTER `logout_at`;

INSERT IGNORE INTO `company_settings` (`setting_key`, `setting_value`) VALUES
('daily_target_hours',        '6'),
('away_after_minutes',        '15'),
('auto_logout_after_minutes', '45');
