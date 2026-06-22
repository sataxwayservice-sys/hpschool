-- Convert student_reminders to Postgres
CREATE TABLE IF NOT EXISTS student_reminders (
  reminder_id serial,
  student_id integer NOT NULL,
  reminder_text text NOT NULL,
  reminder_type varchar(20) DEFAULT 'Other',
  priority varchar(20) DEFAULT 'Medium',
  due_date date DEFAULT NULL,
  status varchar(20) DEFAULT 'Active',
  created_by integer NOT NULL,
  created_by_role varchar(50) DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL,
  resolved_at timestamp NULL,
  resolved_by integer DEFAULT NULL,
  PRIMARY KEY (reminder_id)
);
CREATE INDEX idx_student_status ON student_reminders (student_id, status);
CREATE INDEX idx_due_date ON student_reminders (due_date);
ALTER TABLE student_reminders
  ADD CONSTRAINT fk_student_reminder_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_student_reminder_created_by FOREIGN KEY (created_by) REFERENCES users (user_id),
  ADD CONSTRAINT fk_student_reminder_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (user_id);

-- NOTE: Enums converted to varchar; consider defining PG ENUM types for stricter constraints.
