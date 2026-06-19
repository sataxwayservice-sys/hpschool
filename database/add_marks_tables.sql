-- Add Marks/Exam Tables
-- Run this SQL to add marks and examination functionality to the database

USE school_fees_system;

-- Exams Table
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_name` VARCHAR(100) NOT NULL,
  `exam_type` VARCHAR(50) NULL COMMENT 'Monthly, Quarterly, Half-Yearly, Annual, etc.',
  `exam_date` DATE NOT NULL,
  `academic_year` VARCHAR(20) NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`exam_id`),
  KEY `idx_exam_date` (`exam_date`),
  KEY `idx_active` (`is_active`),
  KEY `fk_exam_created_by` (`created_by`),
  CONSTRAINT `fk_exam_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exam Routine Table
CREATE TABLE IF NOT EXISTS `exam_routines` (
  `routine_id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `class_id` INT(11) NOT NULL,
  `section_id` INT(11) NOT NULL DEFAULT 0,
  `subject_id` INT(11) NOT NULL,
  `exam_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `room_no` VARCHAR(50) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`routine_id`),
  UNIQUE KEY `uk_exam_class_section_subject` (`exam_id`, `class_id`, `section_id`, `subject_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_class_section` (`class_id`, `section_id`),
  KEY `idx_exam_date` (`exam_date`),
  KEY `idx_active` (`is_active`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subjects Table
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` INT(11) NOT NULL AUTO_INCREMENT,
  `subject_name` VARCHAR(100) NOT NULL,
  `subject_code` VARCHAR(20) NULL,
  `max_marks` INT(11) NOT NULL DEFAULT 100,
  `pass_marks` INT(11) NOT NULL DEFAULT 33,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `uk_subject_code` (`subject_code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marks Table
CREATE TABLE IF NOT EXISTS `marks` (
  `mark_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `exam_id` INT(11) NOT NULL,
  `subject_id` INT(11) NOT NULL,
  `marks_obtained` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `remarks` VARCHAR(255) NULL,
  `created_by` INT(11) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` INT(11) NULL,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mark_id`),
  UNIQUE KEY `uk_student_exam_subject` (`student_id`, `exam_id`, `subject_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_subject` (`subject_id`),
  KEY `fk_mark_created_by` (`created_by`),
  KEY `fk_mark_updated_by` (`updated_by`),
  CONSTRAINT `fk_mark_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mark_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mark_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mark_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mark_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default subjects
INSERT INTO `subjects` (`subject_name`, `subject_code`, `max_marks`, `pass_marks`, `is_active`) VALUES
('English', 'ENG', 100, 33, 1),
('Mathematics', 'MATH', 100, 33, 1),
('Science', 'SCI', 100, 33, 1),
('Social Studies', 'SST', 100, 33, 1),
('Hindi', 'HINDI', 100, 33, 1),
('Computer Science', 'CS', 100, 33, 1),
('Physical Education', 'PE', 50, 17, 1),
('Art & Craft', 'ART', 50, 17, 1)
ON DUPLICATE KEY UPDATE subject_name = subject_name;

-- Insert sample exams for current academic year
INSERT INTO `exams` (`exam_name`, `exam_type`, `exam_date`, `academic_year`, `is_active`) VALUES
('Unit Test 1', 'Monthly', CURDATE(), CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1), 1),
('Mid Term Exam', 'Quarterly', DATE_ADD(CURDATE(), INTERVAL 2 MONTH), CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1), 1),
('Unit Test 2', 'Monthly', DATE_ADD(CURDATE(), INTERVAL 4 MONTH), CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1), 1),
('Final Exam', 'Annual', DATE_ADD(CURDATE(), INTERVAL 6 MONTH), CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1), 1)
ON DUPLICATE KEY UPDATE exam_name = exam_name;

-- Add marks/exam permissions to modules
INSERT INTO `modules` (`module_name`, `module_description`, `is_active`) VALUES
('Marks', 'Marks/Exam Management', 1)
ON DUPLICATE KEY UPDATE module_name = module_name;

-- Create Marksheet view for quick access
CREATE OR REPLACE VIEW vw_student_marksheet AS
SELECT
    m.mark_id,
    s.student_id,
    s.admission_no,
    s.student_name,
    c.class_name,
    sec.section_name,
    e.exam_id,
    e.exam_name,
    e.exam_date,
    sub.subject_id,
    sub.subject_name,
    sub.max_marks,
    sub.pass_marks,
    m.marks_obtained,
    m.remarks,
    ROUND((m.marks_obtained / sub.max_marks) * 100, 2) AS percentage,
    CASE
        WHEN (m.marks_obtained / sub.max_marks) * 100 >= 90 THEN 'A+'
        WHEN (m.marks_obtained / sub.max_marks) * 100 >= 80 THEN 'A'
        WHEN (m.marks_obtained / sub.max_marks) * 100 >= 70 THEN 'B+'
        WHEN (m.marks_obtained / sub.max_marks) * 100 >= 60 THEN 'B'
        WHEN (m.marks_obtained / sub.max_marks) * 100 >= 50 THEN 'C+'
        WHEN (m.marks_obtained / sub.max_marks) * 100 >= 40 THEN 'C'
        WHEN (m.marks_obtained / sub.marks) * 100 >= 33 THEN 'D'
        ELSE 'F'
    END AS grade,
    CASE
        WHEN m.marks_obtained >= sub.pass_marks THEN 'Pass'
        ELSE 'Fail'
    END AS result,
    m.created_at
FROM marks m
JOIN students s ON m.student_id = s.student_id
JOIN classes c ON s.class_id = c.class_id
JOIN sections sec ON s.section_id = sec.section_id
JOIN exams e ON m.exam_id = e.exam_id
JOIN subjects sub ON m.subject_id = sub.subject_id;

SELECT 'Marks/Exam tables created successfully!' AS message;
