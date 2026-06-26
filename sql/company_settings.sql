CREATE TABLE IF NOT EXISTS `company_settings` (
  `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `company_settings` (`setting_key`, `setting_value`) VALUES
('company_name',    'My Company'),
('company_email',   ''),
('company_phone',   ''),
('company_address', ''),
('mail_from',       ''),
('company_logo',    ''),
('timezone',                  'Asia/Kuala_Lumpur'),
('datetime_offset_seconds',  '0'),
('smtp_host',                ''),
('smtp_port',                '587'),
('smtp_username',            ''),
('smtp_password',            ''),
('smtp_encryption',          'tls'),
('smtp_from_email',          ''),
('smtp_from_name',           '');
