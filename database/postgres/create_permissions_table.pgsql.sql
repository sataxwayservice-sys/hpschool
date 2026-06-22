-- Automated conversion of create_permissions_table.sql -> Postgres
-- Review required: ON UPDATE and enum handling

CREATE TABLE IF NOT EXISTS role_permissions (
  id serial,
  school_id integer NOT NULL DEFAULT 0,
  role_name varchar(50) NOT NULL,
  permissions text NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
CREATE UNIQUE INDEX idx_role_permissions_school_role ON role_permissions (school_id, role_name);
CREATE INDEX idx_role_permissions_school_id ON role_permissions (school_id);

-- Sample inserts (unchanged):
INSERT INTO role_permissions (school_id, role_name, permissions) VALUES
(0, 'admin', '["dashboard_view","students_view","students_add","students_edit","students_delete","classes_view","classes_add","sections_view","sections_add","fees_view","fees_add","fees_edit","fees_structure","marks_view","marks_add","exams_manage","reports_view","reports_export","settings_view","settings_edit","school_settings_view","academic_years_view","session_rollover_view","student_portal_view","recycle_bin_view"]');

INSERT INTO role_permissions (school_id, role_name, permissions) VALUES
(0, 'accountant', '["dashboard_view","students_view","fees_view","fees_add","fees_edit","fees_structure","reports_view","reports_export"]');

INSERT INTO role_permissions (school_id, role_name, permissions) VALUES
(0, 'clerk', '["dashboard_view","students_view","students_add","students_edit","fees_view","fees_add"]');

INSERT INTO role_permissions (school_id, role_name, permissions) VALUES
(0, 'teacher', '["dashboard_view","students_view","marks_view","marks_add","exams_manage","reports_view"]');

-- NOTE: Verify JSON string syntax and consider using a JSONB column if you want to store permissions as structured JSON.
