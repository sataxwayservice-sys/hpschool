-- =====================================================
-- School Students and Fees Management System
-- Complete Database Schema
-- PHP Core + MySQL + Firebase Integration
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
-- =====================================================
-- 1. USERS & AUTHENTICATION
-- =====================================================

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `password_encrypted` text DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','accountant','clerk','teacher','parent','student') NOT NULL DEFAULT 'clerk',
  `student_id` int(11) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `firebase_uid` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  UNIQUE KEY `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Permissions Table
CREATE TABLE `user_permissions` (
  `permission_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  KEY `fk_user_perm` (`user_id`),
  CONSTRAINT `fk_user_perm` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 2. SCHOOL SETTINGS & CONFIGURATION
-- =====================================================

CREATE TABLE `school_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `school_name` varchar(200) NOT NULL,
  `school_address` text,
  `school_phone` varchar(20) DEFAULT NULL,
  `school_email` varchar(100) DEFAULT NULL,
  `school_logo` varchar(255) DEFAULT NULL,
  `login_logo` varchar(255) DEFAULT NULL,
  `banner_logo` varchar(255) DEFAULT NULL,
  `current_academic_year` varchar(20) NOT NULL,
  `admission_prefix` varchar(10) DEFAULT 'STU',
  `receipt_prefix` varchar(10) DEFAULT 'REC',
  `currency_symbol` varchar(10) DEFAULT '₹',
  `upi_id` varchar(100) DEFAULT NULL,
  `payment_recipient_name` varchar(150) DEFAULT NULL,
  `payment_note` varchar(255) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `school_settings` (`school_name`, `current_academic_year`, `admission_prefix`, `receipt_prefix`, `upi_id`, `payment_recipient_name`, `payment_note`)
VALUES ('School Dashboard', '2024-2025', 'STU', 'REC', NULL, 'School Dashboard', 'School fee payment');

-- =====================================================
-- 3. CLASSES & SECTIONS MASTER
-- =====================================================

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) NOT NULL,
  `class_order` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default classes
INSERT INTO `classes` (`class_name`, `class_order`) VALUES
('Nursery', 1), ('LKG', 2), ('UKG', 3), ('1st', 4), ('2nd', 5),
('3rd', 6), ('4th', 7), ('5th', 8), ('6th', 9), ('7th', 10),
('8th', 11), ('9th', 12), ('10th', 13), ('11th', 14), ('12th', 15);

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_name` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default sections
INSERT INTO `sections` (`section_name`) VALUES ('A'), ('B'), ('C'), ('D');

-- =====================================================
-- 4. STUDENT MANAGEMENT
-- =====================================================

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `admission_no` varchar(50) NOT NULL UNIQUE,
  `student_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `roll_no` varchar(20) DEFAULT NULL,
  `address` text,
  `father_name` varchar(100) NOT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `contact_no` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `admission_date` date NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `firebase_synced` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `idx_admission_no` (`admission_no`),
  KEY `fk_student_class` (`class_id`),
  KEY `fk_student_section` (`section_id`),
  KEY `idx_student_school_id` (`school_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  CONSTRAINT `fk_student_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `student_applications` (
  `application_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `father_name` varchar(100) NOT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL DEFAULT 'Other',
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `address` text DEFAULT NULL,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `documents_json` longtext DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `linked_student_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `idx_student_application_user` (`user_id`),
  KEY `idx_student_application_status` (`status`),
  KEY `idx_student_application_class` (`class_id`),
  KEY `idx_student_application_email` (`email`),
  KEY `idx_student_application_mobile` (`mobile`),
  CONSTRAINT `fk_student_application_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `student_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `document_type` enum('admit_card','transfer_certificate','character_certificate') NOT NULL,
  `document_title` varchar(200) NOT NULL,
  `exam_id` int(11) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `visible_to_student` tinyint(1) NOT NULL DEFAULT 0,
  `payload_json` longtext NOT NULL,
  `document_hash` char(64) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  UNIQUE KEY `idx_student_document_hash` (`document_hash`),
  KEY `idx_student_document_student` (`student_id`,`visible_to_student`,`document_type`),
  KEY `idx_student_document_exam` (`exam_id`),
  KEY `idx_student_document_created` (`created_at`),
  CONSTRAINT `fk_student_document_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Student Promotion History
CREATE TABLE `student_promotions` (
  `promotion_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `from_class_id` int(11) NOT NULL,
  `to_class_id` int(11) NOT NULL,
  `from_section_id` int(11) NOT NULL,
  `to_section_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `promotion_date` date NOT NULL,
  `remarks` text,
  `promoted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`promotion_id`),
  KEY `fk_promotion_student` (`student_id`),
  CONSTRAINT `fk_promotion_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 5. SUBJECTS & MARKS MANAGEMENT
-- =====================================================

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `max_marks` int(11) DEFAULT 100,
  `pass_marks` int(11) DEFAULT 33,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class-Subject Mapping
CREATE TABLE `class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cs_class` (`class_id`),
  KEY `fk_cs_subject` (`subject_id`),
  KEY `fk_cs_teacher` (`teacher_id`),
  CONSTRAINT `fk_cs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  CONSTRAINT `fk_cs_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exam routine
CREATE TABLE `exam_routines` (
  `routine_id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL DEFAULT 0,
  `subject_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_no` varchar(50) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`routine_id`),
  UNIQUE KEY `uk_exam_class_section_subject` (`exam_id`, `class_id`, `section_id`, `subject_id`),
  KEY `idx_exam` (`exam_id`),
  KEY `idx_class_section` (`class_id`, `section_id`),
  KEY `idx_exam_date` (`exam_date`),
  KEY `idx_active` (`is_active`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Student Marks
CREATE TABLE `student_marks` (
  `mark_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `exam_type` varchar(50) NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL,
  `max_marks` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `entered_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mark_id`),
  KEY `fk_mark_student` (`student_id`),
  KEY `fk_mark_subject` (`subject_id`),
  CONSTRAINT `fk_mark_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mark_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 6. FEE STRUCTURE MASTER
-- =====================================================

CREATE TABLE `fee_heads` (
  `fee_head_id` int(11) NOT NULL AUTO_INCREMENT,
  `fee_head_name` varchar(100) NOT NULL,
  `fee_type` enum('One-time','Monthly','Optional') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`fee_head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default fee heads
INSERT INTO `fee_heads` (`fee_head_name`, `fee_type`, `display_order`) VALUES
('Admission Fee', 'One-time', 1),
('Tuition Fee', 'Monthly', 2),
('Hostel Fee', 'Optional', 3),
('Transport Fee', 'Optional', 4),
('Exam Fee', 'Optional', 5),
('Library Fee', 'Optional', 6),
('Sports Fee', 'Optional', 7),
('Development Fee', 'Optional', 8);

-- Fee Structure (Individual Student)
CREATE TABLE `fee_structure` (
  `fee_structure_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `fee_head_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`fee_structure_id`),
  KEY `fk_fs_student` (`student_id`),
  KEY `fk_fs_fee_head` (`fee_head_id`),
  CONSTRAINT `fk_fs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fs_fee_head` FOREIGN KEY (`fee_head_id`) REFERENCES `fee_heads` (`fee_head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 7. FEE COLLECTION & RECEIPTS
-- =====================================================

CREATE TABLE `fee_receipts` (
  `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) NOT NULL UNIQUE,
  `student_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_mode` enum('Cash','Bank','UPI','Cheque') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `remarks` text,
  `is_cancelled` tinyint(1) DEFAULT 0,
  `firebase_synced` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`receipt_id`),
  UNIQUE KEY `idx_receipt_no` (`receipt_no`),
  KEY `fk_receipt_student` (`student_id`),
  KEY `fk_receipt_collector` (`collected_by`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_receipt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_receipt_collector` FOREIGN KEY (`collected_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fee Receipt Details (Individual Fee Heads)
CREATE TABLE `fee_receipt_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` int(11) NOT NULL,
  `fee_head_id` int(11) NOT NULL,
  `fee_month` varchar(20) DEFAULT NULL,
  `fee_year` varchar(10) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `fk_frd_receipt` (`receipt_id`),
  KEY `fk_frd_fee_head` (`fee_head_id`),
  CONSTRAINT `fk_frd_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `fee_receipts` (`receipt_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_frd_fee_head` FOREIGN KEY (`fee_head_id`) REFERENCES `fee_heads` (`fee_head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fee Ledger (Running Balance)
CREATE TABLE `fee_ledger` (
  `ledger_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `fee_head_id` int(11) NOT NULL,
  `month` varchar(20) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `debit` decimal(10,2) DEFAULT 0.00,
  `credit` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ledger_id`),
  KEY `fk_ledger_student` (`student_id`),
  KEY `fk_ledger_fee_head` (`fee_head_id`),
  KEY `idx_month_year` (`month`, `year`),
  CONSTRAINT `fk_ledger_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ledger_fee_head` FOREIGN KEY (`fee_head_id`) REFERENCES `fee_heads` (`fee_head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 8. SMS & NOTIFICATIONS
-- =====================================================

CREATE TABLE `sms_logs` (
  `sms_id` int(11) NOT NULL AUTO_INCREMENT,
  `mobile_number` varchar(15) NOT NULL,
  `message` text NOT NULL,
  `type` enum('Fee_Reminder','Receipt','Admission','General') NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  `firebase_response` text,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sms_id`),
  KEY `fk_sms_student` (`student_id`),
  CONSTRAINT `fk_sms_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 9. FIREBASE SYNC LOG
-- =====================================================

CREATE TABLE `firebase_sync_log` (
  `sync_id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `sync_status` enum('Pending','Success','Failed') NOT NULL DEFAULT 'Pending',
  `firebase_path` varchar(255) DEFAULT NULL,
  `error_message` text,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sync_id`),
  KEY `idx_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 10. PAYMENT LINKS
-- =====================================================

CREATE TABLE `payment_links` (
  `link_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `link_token` varchar(100) NOT NULL UNIQUE,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `expiry_date` date NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `idx_link_token` (`link_token`),
  KEY `fk_link_student` (`student_id`),
  CONSTRAINT `fk_link_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 11. PARENT PORTAL
-- =====================================================

CREATE TABLE `parent_student_links` (
  `link_id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relation` varchar(50) NOT NULL DEFAULT 'Parent',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `idx_parent_student` (`parent_user_id`, `student_id`),
  KEY `idx_parent_user` (`parent_user_id`),
  KEY `idx_student_id` (`student_id`),
  CONSTRAINT `fk_parent_link_parent` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_parent_link_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `parent_announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `publish_date` date DEFAULT NULL,
  `expire_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`),
  KEY `idx_active_dates` (`is_active`, `publish_date`, `expire_date`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 12. ACTIVITY LOG (Audit Trail)
-- =====================================================

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `fk_log_user` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 13. BACKUP LOGS
-- =====================================================

CREATE TABLE `backup_logs` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_type` enum('MySQL','Firebase','Both') NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `status` enum('Success','Failed') NOT NULL,
  `error_message` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`backup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- =====================================================
-- END OF SCHEMA
-- =====================================================
