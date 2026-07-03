-- ============================================================
-- Chat image attachments + retention support
-- ============================================================

ALTER TABLE `chat_messages`
  ADD COLUMN `attachment_path`       VARCHAR(255) NULL AFTER `message`,
  ADD COLUMN `attachment_expires_at` DATETIME     NULL AFTER `attachment_path`;
