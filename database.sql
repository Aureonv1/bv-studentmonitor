-- BrightVision Academy - Database Schema
-- Supports student login credentials and variable max marks per exam subject.

CREATE DATABASE IF NOT EXISTS `student_marks_portal`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `student_marks_portal`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Fresh restore safety: remove any partially imported or legacy tables first.
-- Without this, CREATE TABLE IF NOT EXISTS can leave an old table definition in
-- place and later foreign keys may fail against missing/incorrect indexes.
DROP TABLE IF EXISTS `student_accounts`;
DROP TABLE IF EXISTS `marks`;
DROP TABLE IF EXISTS `imports_log`;
DROP TABLE IF EXISTS `database_backups`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `student_code_counter`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `academic_years`;
DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `app_meta`;
DROP TABLE IF EXISTS `admins`;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `can_manage_students` TINYINT(1) NOT NULL DEFAULT 1,
  `can_manage_marks` TINYINT(1) NOT NULL DEFAULT 1,
  `can_import_csv` TINYINT(1) NOT NULL DEFAULT 1,
  `can_backup_db` TINYINT(1) NOT NULL DEFAULT 1,
  `can_maintenance_mode` TINYINT(1) NOT NULL DEFAULT 1,
  `can_manage_admins` TINYINT(1) NOT NULL DEFAULT 1,
  `can_manage_site_settings` TINYINT(1) NOT NULL DEFAULT 1,
  `can_view_analytics` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `footer_text` VARCHAR(255) NOT NULL DEFAULT 'ONYX',
  `footer_url` VARCHAR(255) NOT NULL DEFAULT 'https://onyxrns.com',
  `footer_brand_name` VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision',
  `footer_copyright_owner` VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision',
  `footer_rights_text` VARCHAR(160) NOT NULL DEFAULT 'All rights reserved.',
  `footer_collab_text` VARCHAR(255) NOT NULL DEFAULT 'Collaborated with apexinventives',
  `footer_link_1_label` VARCHAR(50) NOT NULL DEFAULT 'Privacy',
  `footer_link_1_url` VARCHAR(255) NOT NULL DEFAULT '#',
  `footer_link_2_label` VARCHAR(50) NOT NULL DEFAULT 'Terms',
  `footer_link_2_url` VARCHAR(255) NOT NULL DEFAULT '#',
  `footer_link_3_label` VARCHAR(50) NOT NULL DEFAULT 'Contact',
  `footer_link_3_url` VARCHAR(255) NOT NULL DEFAULT '#',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `app_meta` (
  `meta_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `meta_value` VARCHAR(255) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `academic_years` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `year_name` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_name` VARCHAR(100) NOT NULL,
  `academic_year_id` INT NULL,
  `class_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_exams_class_year` (`class_id`, `academic_year_id`),
  UNIQUE KEY `uq_exam_scope` (`exam_name`, `academic_year_id`, `class_id`),
  CONSTRAINT `fk_exams_year`
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exams_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_code` VARCHAR(24) NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `class_id` INT NULL,
  `academic_year_id` INT NULL,
  UNIQUE KEY `uq_student_identity` (`name`, `class_id`, `academic_year_id`),
  KEY `idx_students_class_year` (`class_id`, `academic_year_id`),
  KEY `idx_students_class_year_name` (`class_id`, `academic_year_id`, `name`),
  KEY `idx_students_year_class_name` (`academic_year_id`, `class_id`, `name`),
  KEY `idx_students_name` (`name`),
  CONSTRAINT `fk_students_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_year`
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_code_counter` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `next_number` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `username` VARCHAR(64) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_plain` VARCHAR(120) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_accounts_student` (`student_id`),
  KEY `idx_student_accounts_username_active` (`username`, `is_active`),
  CONSTRAINT `fk_student_accounts_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `marks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NULL,
  `exam_name` VARCHAR(100) NOT NULL DEFAULT 'Term Exam',
  `subject_name` VARCHAR(100) NOT NULL,
  `marks_obtained` DECIMAL(7,2) DEFAULT 0.00,
  `max_marks` DECIMAL(7,2) NOT NULL DEFAULT 100.00,
  UNIQUE KEY `uq_student_exam_subject` (`student_id`, `exam_name`, `subject_name`),
  KEY `idx_marks_exam` (`exam_name`),
  KEY `idx_marks_subject` (`subject_name`),
  KEY `idx_marks_student_exam` (`student_id`, `exam_name`),
  CONSTRAINT `fk_marks_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `imports_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `imported_rows` INT NOT NULL DEFAULT 0,
  `created_students` INT NOT NULL DEFAULT 0,
  `updated_marks` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_imports_log_created_at` (`created_at`),
  KEY `idx_imports_log_admin_id` (`admin_id`),
  CONSTRAINT `fk_imports_log_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `database_backups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file_name` VARCHAR(255) NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_database_backups_created_at` (`created_at`),
  KEY `idx_database_backups_created_by` (`created_by`),
  CONSTRAINT `fk_database_backups_admin`
    FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migration safety for older installs
ALTER TABLE `marks`
  ADD COLUMN IF NOT EXISTS `exam_name` VARCHAR(100) NOT NULL DEFAULT 'Term Exam' AFTER `student_id`;

ALTER TABLE `marks`
  MODIFY COLUMN `marks_obtained` DECIMAL(7,2) DEFAULT 0.00;

ALTER TABLE `marks`
  ADD COLUMN IF NOT EXISTS `max_marks` DECIMAL(7,2) NOT NULL DEFAULT 100.00 AFTER `marks_obtained`;

ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `can_manage_site_settings` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_manage_admins`;

ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `can_view_analytics` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_manage_site_settings`;

ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `student_code` VARCHAR(24) NULL AFTER `id`;

ALTER TABLE `students`
  ADD UNIQUE KEY IF NOT EXISTS `uq_students_code` (`student_code`);

UPDATE `students`
SET `student_code` = CONCAT('STU', LPAD(`id`, 6, '0'))
WHERE `student_code` IS NULL OR `student_code` = '';

INSERT INTO `student_code_counter` (`id`, `next_number`)
VALUES (1, 1)
ON DUPLICATE KEY UPDATE `next_number` = `next_number`;

UPDATE `student_code_counter`
SET `next_number` = GREATEST(
  `next_number`,
  (
    SELECT COALESCE(MAX(CAST(SUBSTRING(`student_code`, 4) AS UNSIGNED)), 0) + 1
    FROM `students`
    WHERE `student_code` REGEXP '^STU[0-9]+$'
  )
)
WHERE `id` = 1;

ALTER TABLE `students`
  ADD INDEX IF NOT EXISTS `idx_students_year_class_name` (`academic_year_id`, `class_id`, `name`);

ALTER TABLE `students`
  ADD INDEX IF NOT EXISTS `idx_students_name` (`name`);

ALTER TABLE `marks`
  ADD INDEX IF NOT EXISTS `idx_marks_student_exam` (`student_id`, `exam_name`);

ALTER TABLE `student_accounts`
  ADD COLUMN IF NOT EXISTS `password_plain` VARCHAR(120) NOT NULL DEFAULT '' AFTER `password_hash`;

ALTER TABLE `student_accounts`
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password_plain`;

ALTER TABLE `student_accounts`
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL AFTER `is_active`;

ALTER TABLE `student_accounts`
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `student_accounts`
  ADD INDEX IF NOT EXISTS `idx_student_accounts_username_active` (`username`, `is_active`);

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_text` VARCHAR(255) NOT NULL DEFAULT 'ONYX';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_url` VARCHAR(255) NOT NULL DEFAULT 'https://onyxrns.com';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_brand_name` VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_copyright_owner` VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_rights_text` VARCHAR(160) NOT NULL DEFAULT 'All rights reserved.';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_collab_text` VARCHAR(255) NOT NULL DEFAULT 'Collaborated with apexinventives';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_link_1_label` VARCHAR(50) NOT NULL DEFAULT 'Privacy';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_link_1_url` VARCHAR(255) NOT NULL DEFAULT '#';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_link_2_label` VARCHAR(50) NOT NULL DEFAULT 'Terms';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_link_2_url` VARCHAR(255) NOT NULL DEFAULT '#';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_link_3_label` VARCHAR(50) NOT NULL DEFAULT 'Contact';

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `footer_link_3_url` VARCHAR(255) NOT NULL DEFAULT '#';

INSERT INTO `admins` (
  `username`,
  `full_name`,
  `password_hash`,
  `is_active`,
  `can_manage_students`,
  `can_manage_marks`,
  `can_import_csv`,
  `can_backup_db`,
  `can_maintenance_mode`,
  `can_manage_admins`,
  `can_manage_site_settings`,
  `can_view_analytics`
)
VALUES (
  'rnsdev',
  'System Administrator',
  '$2y$10$i4NwEXIhertNmP5W2uV7vOpRgPDUCa5VRulEUeqn6fi9WYQGfUKU.',
  1,
  1,
  1,
  1,
  1,
  1,
  1,
  1,
  1
)
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `password_hash` = VALUES(`password_hash`),
  `is_active` = VALUES(`is_active`),
  `can_manage_students` = VALUES(`can_manage_students`),
  `can_manage_marks` = VALUES(`can_manage_marks`),
  `can_import_csv` = VALUES(`can_import_csv`),
  `can_backup_db` = VALUES(`can_backup_db`),
  `can_maintenance_mode` = VALUES(`can_maintenance_mode`),
  `can_manage_admins` = VALUES(`can_manage_admins`),
  `can_manage_site_settings` = VALUES(`can_manage_site_settings`),
  `can_view_analytics` = VALUES(`can_view_analytics`);

INSERT INTO `site_settings` (
  `id`,
  `footer_text`,
  `footer_url`,
  `footer_brand_name`,
  `footer_copyright_owner`,
  `footer_rights_text`,
  `footer_collab_text`,
  `footer_link_1_label`,
  `footer_link_1_url`,
  `footer_link_2_label`,
  `footer_link_2_url`,
  `footer_link_3_label`,
  `footer_link_3_url`
)
VALUES (
  1,
  'ONYX',
  'https://onyxrns.com',
  'BV-BrightVision',
  'BV-BrightVision',
  'All rights reserved.',
  'Collaborated with apexinventives',
  'Privacy',
  '#',
  'Terms',
  '#',
  'Contact',
  '#'
)
ON DUPLICATE KEY UPDATE
  `footer_text` = COALESCE(NULLIF(`footer_text`, ''), VALUES(`footer_text`)),
  `footer_url` = COALESCE(NULLIF(`footer_url`, ''), VALUES(`footer_url`)),
  `footer_brand_name` = COALESCE(NULLIF(`footer_brand_name`, ''), VALUES(`footer_brand_name`)),
  `footer_copyright_owner` = COALESCE(NULLIF(`footer_copyright_owner`, ''), VALUES(`footer_copyright_owner`)),
  `footer_rights_text` = COALESCE(NULLIF(`footer_rights_text`, ''), VALUES(`footer_rights_text`)),
  `footer_collab_text` = COALESCE(NULLIF(`footer_collab_text`, ''), VALUES(`footer_collab_text`)),
  `footer_link_1_label` = COALESCE(NULLIF(`footer_link_1_label`, ''), VALUES(`footer_link_1_label`)),
  `footer_link_1_url` = COALESCE(NULLIF(`footer_link_1_url`, ''), VALUES(`footer_link_1_url`)),
  `footer_link_2_label` = COALESCE(NULLIF(`footer_link_2_label`, ''), VALUES(`footer_link_2_label`)),
  `footer_link_2_url` = COALESCE(NULLIF(`footer_link_2_url`, ''), VALUES(`footer_link_2_url`)),
  `footer_link_3_label` = COALESCE(NULLIF(`footer_link_3_label`, ''), VALUES(`footer_link_3_label`)),
  `footer_link_3_url` = COALESCE(NULLIF(`footer_link_3_url`, ''), VALUES(`footer_link_3_url`));

INSERT INTO `app_meta` (`meta_key`, `meta_value`)
VALUES ('schema_version', '2026.04.22.01')
ON DUPLICATE KEY UPDATE
  `meta_value` = VALUES(`meta_value`);

SET FOREIGN_KEY_CHECKS = 1;
