-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2026 at 07:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `student_marks_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year_name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year_name`) VALUES
(1, '2026');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_students` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_marks` tinyint(1) NOT NULL DEFAULT 1,
  `can_import_csv` tinyint(1) NOT NULL DEFAULT 1,
  `can_backup_db` tinyint(1) NOT NULL DEFAULT 1,
  `can_maintenance_mode` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_admins` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_site_settings` tinyint(1) NOT NULL DEFAULT 1,
  `can_view_analytics` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `full_name`, `password_hash`, `is_active`, `can_manage_students`, `can_manage_marks`, `can_import_csv`, `can_backup_db`, `can_maintenance_mode`, `can_manage_admins`, `can_manage_site_settings`, `can_view_analytics`, `created_at`) VALUES
(1, 'rnsdev', 'System Administrator', '$2y$10$Set/sOBWHIAuJvM5aHxcFeQviLoWlNSbJhIVuUSVKVCg/yFGQcJk2', 1, 1, 1, 1, 1, 1, 1, 1, 1, '2026-04-22 14:57:13'),
(2, 'bagiii', NULL, '$2y$10$S4.8xz9e64GCbKfCESGOAuD2W8xUqfFJsXOhjj9SU8dXc8.4sFSUW', 1, 1, 1, 1, 1, 1, 0, 0, 1, '2026-04-22 16:14:57');

-- --------------------------------------------------------

--
-- Table structure for table `app_meta`
--

CREATE TABLE `app_meta` (
  `meta_key` varchar(100) NOT NULL,
  `meta_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_meta`
--

INSERT INTO `app_meta` (`meta_key`, `meta_value`, `updated_at`) VALUES
('schema_version', '2026.04.22.01', '2026-04-22 18:27:23');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`) VALUES
(1, 'Grade 10'),
(2, 'Grade 11');

-- --------------------------------------------------------

--
-- Table structure for table `database_backups`
--

CREATE TABLE `database_backups` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `exam_name` varchar(100) NOT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `imports_log`
--

CREATE TABLE `imports_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `imported_rows` int(11) NOT NULL DEFAULT 0,
  `created_students` int(11) NOT NULL DEFAULT 0,
  `updated_marks` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marks`
--

CREATE TABLE `marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `exam_name` varchar(100) NOT NULL DEFAULT 'Term Exam',
  `subject_name` varchar(100) NOT NULL,
  `marks_obtained` decimal(7,2) DEFAULT 0.00,
  `max_marks` decimal(7,2) NOT NULL DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marks`
--

INSERT INTO `marks` (`id`, `student_id`, `exam_name`, `subject_name`, `marks_obtained`, `max_marks`) VALUES
(1, 1, 'UNIT TEST 1', 'Unit test 1', 85.00, 100.00),
(2, 2, 'UNIT TEST 1', 'Unit test 1', 90.00, 100.00),
(3, 3, 'UNIT TEST 1', 'Unit test 1', 30.00, 100.00),
(4, 4, 'UNIT TEST 1', 'Unit test 1', 95.00, 100.00),
(5, 1, 'UNIT TEST 2', 'Unit test 2', 85.00, 100.00),
(6, 2, 'UNIT TEST 2', 'Unit test 2', 90.00, 100.00),
(7, 3, 'UNIT TEST 2', 'Unit test 2', 56.00, 100.00),
(8, 4, 'UNIT TEST 2', 'Unit test 2', 95.00, 100.00),
(9, 1, 'UNIT TEST 3', 'Unit test 3', 80.00, 100.00),
(10, 2, 'UNIT TEST 3', 'Unit test 3', 99.00, 100.00),
(11, 3, 'UNIT TEST 3', 'Unit test 3', 95.99, 100.00),
(12, 4, 'UNIT TEST 3', 'Unit test 3', 75.00, 100.00),
(13, 5, 'UNIT TEST 1', 'English', 85.50, 100.00),
(14, 6, 'UNIT TEST 1', 'English', 90.00, 100.00),
(15, 7, 'UNIT TEST 1', 'English', 76.50, 100.00),
(16, 8, 'UNIT TEST 1', 'English', 95.00, 100.00),
(17, 1, 'UNIT TEST 4', 'Unit test 2', 85.00, 100.00),
(18, 2, 'UNIT TEST 4', 'Unit test 2', 90.00, 100.00),
(19, 3, 'UNIT TEST 4', 'Unit test 2', 76.00, 100.00),
(20, 4, 'UNIT TEST 4', 'Unit test 2', 95.00, 100.00),
(21, 1, 'UNIT TEST 5', 'Unit test 2', 85.00, 100.00),
(22, 2, 'UNIT TEST 5', 'Unit test 2', 90.00, 100.00),
(23, 3, 'UNIT TEST 5', 'Unit test 2', 76.00, 100.00),
(24, 4, 'UNIT TEST 5', 'Unit test 2', 95.00, 100.00),
(25, 3, 'UNIT TEST 6', 'Unit test 5', 55.00, 100.00),
(26, 4, 'UNIT TEST 6', 'Unit test 5', 60.00, 100.00),
(27, 2, 'UNIT TEST 6', 'Unit test 5', 78.00, 100.00),
(28, 1, 'UNIT TEST 6', 'Unit test 5', 66.00, 100.00),
(29, 3, 'UNIT TEST 7', 'Unit test 5', 55.00, 100.00),
(30, 4, 'UNIT TEST 7', 'Unit test 5', 60.00, 100.00),
(31, 2, 'UNIT TEST 7', 'Unit test 5', 78.00, 100.00),
(32, 1, 'UNIT TEST 7', 'Unit test 5', 66.00, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `footer_text` varchar(255) NOT NULL DEFAULT 'Designed and Developed by ONYX',
  `footer_url` varchar(255) NOT NULL DEFAULT 'https://onyxrns.com',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `footer_brand_name` varchar(140) NOT NULL DEFAULT 'BV-BrightVision',
  `footer_copyright_owner` varchar(140) NOT NULL DEFAULT 'BV-BrightVision',
  `footer_rights_text` varchar(160) NOT NULL DEFAULT 'All rights reserved.',
  `footer_collab_text` varchar(255) NOT NULL DEFAULT 'Collaborated with apexinventives',
  `footer_link_1_label` varchar(50) NOT NULL DEFAULT 'Privacy',
  `footer_link_1_url` varchar(255) NOT NULL DEFAULT '#',
  `footer_link_2_label` varchar(50) NOT NULL DEFAULT 'Terms',
  `footer_link_2_url` varchar(255) NOT NULL DEFAULT '#',
  `footer_link_3_label` varchar(50) NOT NULL DEFAULT 'Contact',
  `footer_link_3_url` varchar(255) NOT NULL DEFAULT '#'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `footer_text`, `footer_url`, `updated_at`, `footer_brand_name`, `footer_copyright_owner`, `footer_rights_text`, `footer_collab_text`, `footer_link_1_label`, `footer_link_1_url`, `footer_link_2_label`, `footer_link_2_url`, `footer_link_3_label`, `footer_link_3_url`) VALUES
(1, 'ONYX', 'https://onyxrns.com', '2026-04-22 16:43:28', 'BV-StudentMonitor', 'BV-StudentMonitor', 'All rights reserved.', 'Collaborated with apexinventives', 'Privacy', '#', 'Terms', '#', 'Contact', '#');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_code` varchar(24) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_code`, `name`, `class_id`, `academic_year_id`) VALUES
(1, 'STU000001', 'John Doe', 1, 1),
(2, 'STU000002', 'Jane Smith', 1, 1),
(3, 'STU000003', 'Alex Johnson', 1, 1),
(4, 'STU000004', 'Emily Davis', 1, 1),
(5, 'STU000005', 'John Doe', 2, 1),
(6, 'STU000006', 'Jane Smith', 2, 1),
(7, 'STU000007', 'Alex Johnson', 2, 1),
(8, 'STU000008', 'Emily Davis', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_accounts`
--

CREATE TABLE `student_accounts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_plain` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_accounts`
--

INSERT INTO `student_accounts` (`id`, `student_id`, `username`, `password_hash`, `password_plain`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'johndoe0001', '$2y$10$h/a86Ik4asVX7TLLmL4Yduvet0CLp2YlusWm2XprcQiXVOzT6bpDe', 'b$wXNWuS@y', 1, NULL, '2026-04-23 08:30:08', '2026-04-23 08:30:08'),
(2, 2, 'janesmith0002', '$2y$10$ar1SmkLrJi8wotyrLPMtleG7Q5dbJdDDRt0D6a/02j2UNA35mwrZe', '#kwi7m5JAg', 1, NULL, '2026-04-23 08:30:08', '2026-04-23 08:30:08'),
(3, 3, 'alexjohnson0003', '$2y$10$IMMKMElEBn3IaRq7LgmWPeQyBWl68apf1B6R2gXGccXQXqIYvZPVW', '!nw4aPbNUV', 1, '2026-04-26 00:05:48', '2026-04-23 08:30:08', '2026-04-25 18:35:48'),
(4, 4, 'emilydavis0004', '$2y$10$kb74HLQuD3lQIeszuZCQHenNYmybiPB1p00JjJPZxoFsuNWlriw/i', 'PTWD3wGpFi', 1, '2026-04-23 18:06:57', '2026-04-23 08:30:08', '2026-04-23 12:36:57'),
(5, 5, 'johndoe0005', '$2y$10$/.OORyidhvq05GM3K8BxDeyqBFzhpAi4hwHawGLWYOACzWTw/yfmu', 'TzWHMhEQ8w', 1, NULL, '2026-04-23 08:30:08', '2026-04-23 08:30:08'),
(6, 6, 'janesmith0006', '$2y$10$3f8hmiNQQnSCrsR7pd3W2OFkgEs3163FS.lVWij14g5iq7t2QWsjK', 'tqmS#x4zwF', 1, NULL, '2026-04-23 08:30:08', '2026-04-23 08:30:08'),
(7, 7, 'alexjohnson0007', '$2y$10$gyZzDmQ2VDCbSDTq15/YJ.Ym/TkaZNYCiDb68/TnIVo8T5LNTnLPK', 'SH3Gmz9kPD', 1, NULL, '2026-04-23 08:30:08', '2026-04-23 08:30:08'),
(8, 8, 'emilydavis0008', '$2y$10$SMrg5KekvMQWBV8tg1u22.BJikkOsNYadreB71D4CH8ffLj4/CLR6', '$mB8t87ryd', 1, NULL, '2026-04-23 08:30:08', '2026-04-23 08:30:08');

-- --------------------------------------------------------

--
-- Table structure for table `student_code_counter`
--

CREATE TABLE `student_code_counter` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `next_number` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_code_counter`
--

INSERT INTO `student_code_counter` (`id`, `next_number`, `updated_at`) VALUES
(1, 9, '2026-04-25 04:07:17');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year_name` (`year_name`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `app_meta`
--
ALTER TABLE `app_meta`
  ADD PRIMARY KEY (`meta_key`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `class_name` (`class_name`);

--
-- Indexes for table `database_backups`
--
ALTER TABLE `database_backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_database_backups_created_at` (`created_at`),
  ADD KEY `idx_database_backups_created_by` (`created_by`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_exam_scope` (`exam_name`,`academic_year_id`,`class_id`),
  ADD KEY `idx_exams_class_year` (`class_id`,`academic_year_id`),
  ADD KEY `fk_exams_year` (`academic_year_id`);

--
-- Indexes for table `imports_log`
--
ALTER TABLE `imports_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_imports_log_created_at` (`created_at`),
  ADD KEY `idx_imports_log_admin_id` (`admin_id`);

--
-- Indexes for table `marks`
--
ALTER TABLE `marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_exam_subject` (`student_id`,`exam_name`,`subject_name`),
  ADD KEY `idx_marks_exam` (`exam_name`),
  ADD KEY `idx_marks_subject` (`subject_name`),
  ADD KEY `idx_marks_student_exam` (`student_id`,`exam_name`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_identity` (`name`,`class_id`,`academic_year_id`),
  ADD UNIQUE KEY `uq_students_code` (`student_code`),
  ADD KEY `idx_students_class_year` (`class_id`,`academic_year_id`),
  ADD KEY `idx_students_class_year_name` (`class_id`,`academic_year_id`,`name`),
  ADD KEY `idx_students_year_class_name` (`academic_year_id`,`class_id`,`name`),
  ADD KEY `idx_students_name` (`name`);

--
-- Indexes for table `student_accounts`
--
ALTER TABLE `student_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_student_accounts_student` (`student_id`),
  ADD KEY `idx_student_accounts_username_active` (`username`,`is_active`);

--
-- Indexes for table `student_code_counter`
--
ALTER TABLE `student_code_counter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_name` (`subject_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `database_backups`
--
ALTER TABLE `database_backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `imports_log`
--
ALTER TABLE `imports_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marks`
--
ALTER TABLE `marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `student_accounts`
--
ALTER TABLE `student_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `database_backups`
--
ALTER TABLE `database_backups`
  ADD CONSTRAINT `fk_database_backups_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `fk_exams_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_exams_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `imports_log`
--
ALTER TABLE `imports_log`
  ADD CONSTRAINT `fk_imports_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `marks`
--
ALTER TABLE `marks`
  ADD CONSTRAINT `fk_marks_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_students_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_accounts`
--
ALTER TABLE `student_accounts`
  ADD CONSTRAINT `fk_student_accounts_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
