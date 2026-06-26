-- ============================================================
-- Employee Portal Enhancement: Module 1 (TODO) + Module 2 (Leave)
-- Compatible with cPanel / PHP / MySQL
-- Run this AFTER your existing schema
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- MODULE 1: TODO / ONGOING PROJECTS
-- ============================================================

-- Daily Login Tracking
CREATE TABLE IF NOT EXISTS `emp_login_log` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`   INT UNSIGNED NOT NULL,
  `login_date`    DATE NOT NULL,
  `first_login`   DATETIME NOT NULL,
  `status`        ENUM('on_time','late','absent') DEFAULT 'on_time',
  `minutes_late`  SMALLINT UNSIGNED DEFAULT 0,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_emp_date` (`employee_id`, `login_date`),
  KEY `idx_date` (`login_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recurring Daily Checklist Templates
CREATE TABLE IF NOT EXISTS `checklist_templates` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(255) NOT NULL,
  `sort_order`  TINYINT UNSIGNED DEFAULT 0,
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily Checklist Instances (auto-generated per day)
CREATE TABLE IF NOT EXISTS `daily_checklist` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `template_id`    INT UNSIGNED NOT NULL,
  `employee_id`    INT UNSIGNED NOT NULL,
  `check_date`     DATE NOT NULL,
  `is_completed`   TINYINT(1) DEFAULT 0,
  `completed_at`   DATETIME DEFAULT NULL,
  `completed_by`   INT UNSIGNED DEFAULT NULL,
  KEY `idx_emp_date` (`employee_id`, `check_date`),
  FOREIGN KEY (`template_id`) REFERENCES `checklist_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clients
CREATE TABLE IF NOT EXISTS `clients` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(255) NOT NULL,
  `email`       VARCHAR(255) DEFAULT NULL,
  `phone`       VARCHAR(50)  DEFAULT NULL,
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Projects
CREATE TABLE IF NOT EXISTS `projects` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`               VARCHAR(255) NOT NULL,
  `client_id`          INT UNSIGNED DEFAULT NULL,
  `description`        TEXT DEFAULT NULL,
  `priority`           ENUM('low','medium','high','critical') DEFAULT 'medium',
  `status`             ENUM('active','on_hold','completed','cancelled') DEFAULT 'active',
  `deadline`           DATE DEFAULT NULL,
  `completion_percent` TINYINT UNSIGNED DEFAULT 0,
  `created_by`         INT UNSIGNED NOT NULL,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_status` (`status`),
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project Employees (many-to-many)
CREATE TABLE IF NOT EXISTS `project_employees` (
  `project_id`   INT UNSIGNED NOT NULL,
  `employee_id`  INT UNSIGNED NOT NULL,
  `assigned_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`project_id`, `employee_id`),
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assigned Tasks
CREATE TABLE IF NOT EXISTS `tasks` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`            VARCHAR(255) NOT NULL,
  `client_id`        INT UNSIGNED DEFAULT NULL,
  `project_id`       INT UNSIGNED DEFAULT NULL,
  `description`      TEXT DEFAULT NULL,
  `priority`         ENUM('low','medium','high','critical') DEFAULT 'medium',
  `due_date`         DATE DEFAULT NULL,
  `assigned_by`      INT UNSIGNED NOT NULL,
  `assigned_to`      INT UNSIGNED NOT NULL,
  `estimated_hours`  DECIMAL(5,2) DEFAULT NULL,
  `status`           ENUM('not_started','in_progress','waiting_client','under_review','completed','cancelled') DEFAULT 'not_started',
  `notes`            TEXT DEFAULT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`client_id`)  REFERENCES `clients`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task Attachments
CREATE TABLE IF NOT EXISTS `task_attachments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id`     INT UNSIGNED NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `filepath`    VARCHAR(500) NOT NULL,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time Tracking
CREATE TABLE IF NOT EXISTS `time_tracking` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id`      INT UNSIGNED NOT NULL,
  `employee_id`  INT UNSIGNED NOT NULL,
  `started_at`   DATETIME NOT NULL,
  `paused_at`    DATETIME DEFAULT NULL,
  `resumed_at`   DATETIME DEFAULT NULL,
  `finished_at`  DATETIME DEFAULT NULL,
  `total_seconds` INT UNSIGNED DEFAULT 0,
  `break_seconds` INT UNSIGNED DEFAULT 0,
  `status`       ENUM('running','paused','finished') DEFAULT 'running',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_emp_task` (`employee_id`, `task_id`),
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily Work Reports
CREATE TABLE IF NOT EXISTS `daily_reports` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`     INT UNSIGNED NOT NULL,
  `report_date`     DATE NOT NULL,
  `work_completed`  TEXT NOT NULL,
  `problems_faced`  TEXT DEFAULT NULL,
  `tomorrow_plan`   TEXT DEFAULT NULL,
  `submitted_at`    DATETIME NOT NULL,
  `reviewed_by`     INT UNSIGNED DEFAULT NULL,
  `reviewed_at`     DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_emp_date` (`employee_id`, `report_date`),
  KEY `idx_date` (`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MODULE 2: LEAVE MANAGEMENT
-- ============================================================

-- Leave Types
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(100) NOT NULL,
  `annual_quota`    TINYINT UNSIGNED DEFAULT 0,
  `requires_cert`   TINYINT(1) DEFAULT 0,
  `cert_threshold_days` TINYINT UNSIGNED DEFAULT 2,
  `carry_forward`   TINYINT(1) DEFAULT 0,
  `max_carry_days`  TINYINT UNSIGNED DEFAULT 0,
  `is_special`      TINYINT(1) DEFAULT 0,
  `is_active`       TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `leave_types` (`id`,`name`,`annual_quota`,`requires_cert`,`cert_threshold_days`,`carry_forward`,`max_carry_days`,`is_special`) VALUES
(1, 'Annual Leave',  14, 0, 0, 1, 5, 0),
(2, 'Medical Leave',  6, 1, 2, 0, 0, 0),
(3, 'Special Leave',  0, 0, 0, 0, 0, 1);

-- Leave Balances (per employee per year)
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`   INT UNSIGNED NOT NULL,
  `leave_type_id` INT UNSIGNED NOT NULL,
  `year`          YEAR NOT NULL,
  `entitled`      DECIMAL(5,2) DEFAULT 0.00,
  `used`          DECIMAL(5,2) DEFAULT 0.00,
  `carried_fwd`   DECIMAL(5,2) DEFAULT 0.00,
  `balance`       DECIMAL(5,2) GENERATED ALWAYS AS (`entitled` + `carried_fwd` - `used`) STORED,
  UNIQUE KEY `uq_emp_type_year` (`employee_id`, `leave_type_id`, `year`),
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leave Requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`     INT UNSIGNED NOT NULL,
  `leave_type_id`   INT UNSIGNED NOT NULL,
  `start_date`      DATE NOT NULL,
  `end_date`        DATE NOT NULL,
  `is_half_day`     TINYINT(1) DEFAULT 0,
  `half_day_type`   ENUM('morning','afternoon') DEFAULT NULL,
  `total_days`      DECIMAL(4,1) NOT NULL,
  `reason`          TEXT NOT NULL,
  `cert_filename`   VARCHAR(255) DEFAULT NULL,
  `cert_filepath`   VARCHAR(500) DEFAULT NULL,
  `status`          ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by`     INT UNSIGNED DEFAULT NULL,
  `approval_notes`  TEXT DEFAULT NULL,
  `request_date`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `decision_date`   DATETIME DEFAULT NULL,
  KEY `idx_emp_status` (`employee_id`, `status`),
  KEY `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public / Company Holidays
CREATE TABLE IF NOT EXISTS `holidays` (
  `id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`     VARCHAR(255) NOT NULL,
  `date`     DATE NOT NULL UNIQUE,
  `type`     ENUM('public','company') DEFAULT 'public',
  `year`     YEAR GENERATED ALWAYS AS (YEAR(`date`)) STORED,
  KEY `idx_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `type`        VARCHAR(50) NOT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `message`     TEXT NOT NULL,
  `link`        VARCHAR(500) DEFAULT NULL,
  `is_read`     TINYINT(1) DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default checklist templates
INSERT IGNORE INTO `checklist_templates` (`id`,`title`,`sort_order`,`is_active`,`created_by`) VALUES
(1,'Check all client websites',1,1,1),
(2,'Check all social media pages',2,1,1),
(3,'Check company emails',3,1,1),
(4,'Review support tickets',4,1,1),
(5,'Review hosting/server alerts',5,1,1),
(6,'Update project progress',6,1,1),
(7,'Check scheduled posts',7,1,1),
(8,'Morning team update',8,1,1);

SET FOREIGN_KEY_CHECKS = 1;
