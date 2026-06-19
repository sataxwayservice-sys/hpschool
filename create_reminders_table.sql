-- Create student_reminders table
CREATE TABLE IF NOT EXISTS `student_reminders` (
  `reminder_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `reminder_text` text NOT NULL,
  `reminder_type` enum('Academic','Behavioral','Document','Fee','Medical','Other') DEFAULT 'Other',
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `due_date` date DEFAULT NULL,
  `status` enum('Active','Resolved') DEFAULT 'Active',
  `created_by` int(11) NOT NULL,
  `created_by_role` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`reminder_id`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create index for faster searches
CREATE INDEX idx_student_status ON student_reminders(student_id, status);
CREATE INDEX idx_due_date ON student_reminders(due_date);
