-- Create role_permissions table
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL DEFAULT 0,
  `role_name` varchar(50) NOT NULL,
  `permissions` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_role_permissions_school_role` (`school_id`, `role_name`),
  KEY `idx_role_permissions_school_id` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert global default permissions for each role (school_id = 0)
-- Admin - Full access except user management
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'admin', '["dashboard_view","students_view","students_add","students_edit","students_delete","classes_view","classes_add","sections_view","sections_add","fees_view","fees_add","fees_edit","fees_structure","marks_view","marks_add","exams_manage","reports_view","reports_export","settings_view","settings_edit","school_settings_view","academic_years_view","session_rollover_view","student_portal_view","recycle_bin_view"]');

-- Accountant - Fees and reports
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'accountant', '["dashboard_view","students_view","fees_view","fees_add","fees_edit","fees_structure","reports_view","reports_export"]');

-- Clerk - Basic operations
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'clerk', '["dashboard_view","students_view","students_add","students_edit","fees_view","fees_add"]');

-- Teacher - Students and marks
INSERT INTO `role_permissions` (`school_id`, `role_name`, `permissions`) VALUES
(0, 'teacher', '["dashboard_view","students_view","marks_view","marks_add","exams_manage","reports_view"]');
