-- Add SMS Logs Table
-- Run this SQL to add SMS functionality to the database

USE school_fees_system;

-- SMS Logs Table
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `sms_log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `phone_number` VARCHAR(20) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('Sent', 'Failed', 'Pending') NOT NULL DEFAULT 'Pending',
  `error_message` TEXT NULL,
  `sent_by` INT(11) NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sms_log_id`),
  KEY `idx_phone` (`phone_number`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `fk_sms_sent_by` (`sent_by`),
  CONSTRAINT `fk_sms_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMS Templates Table
CREATE TABLE IF NOT EXISTS `sms_templates` (
  `template_id` INT(11) NOT NULL AUTO_INCREMENT,
  `template_name` VARCHAR(100) NOT NULL,
  `template_content` TEXT NOT NULL,
  `category` VARCHAR(50) NULL COMMENT 'Fee, Exam, Holiday, PTM, General',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`template_id`),
  KEY `idx_category` (`category`),
  KEY `idx_active` (`is_active`),
  KEY `fk_template_created_by` (`created_by`),
  CONSTRAINT `fk_template_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default SMS templates
INSERT INTO `sms_templates` (`template_name`, `template_content`, `category`, `is_active`) VALUES
('Fee Reminder', 'Dear Parent, Your ward''s fee is due. Please pay at the earliest. Thank you. - [SCHOOL_NAME]', 'Fee', 1),
('Fee Payment Confirmation', 'Dear Parent, We have received Rs. [AMOUNT] as fee payment. Receipt No: [RECEIPT_NO]. Thank you. - [SCHOOL_NAME]', 'Fee', 1),
('Holiday Notification', 'Dear Parent, School will remain closed on [DATE] due to [REASON]. - [SCHOOL_NAME]', 'Holiday', 1),
('PTM Notice', 'Dear Parent, Parent-Teacher meeting scheduled on [DATE] at [TIME]. Your presence is mandatory. - [SCHOOL_NAME]', 'PTM', 1),
('Exam Notification', 'Dear Parent, Exam Schedule: [EXAM_NAME] from [START_DATE] to [END_DATE]. - [SCHOOL_NAME]', 'Exam', 1),
('Exam Result', 'Dear Parent, Your ward has secured [MARKS]% in [EXAM_NAME]. Congratulations! - [SCHOOL_NAME]', 'Exam', 1),
('Attendance Alert', 'Dear Parent, Your ward was absent on [DATE]. Please contact school if this is an error. - [SCHOOL_NAME]', 'General', 1),
('Birthday Wish', 'Happy Birthday [STUDENT_NAME]! Wishing you a wonderful year ahead. - [SCHOOL_NAME]', 'General', 1);

-- Add SMS permission to modules
INSERT INTO `modules` (`module_name`, `module_description`, `is_active`) VALUES
('SMS', 'SMS Management', 1)
ON DUPLICATE KEY UPDATE module_name = module_name;

-- Grant SMS permissions to super_admin and admin by default
-- You may need to run this after the modules table is populated
-- UPDATE role_permissions SET can_send = 1 WHERE role_id IN (1, 2) AND module_id = (SELECT module_id FROM modules WHERE module_name = 'SMS');

SELECT 'SMS tables created successfully!' AS message;
