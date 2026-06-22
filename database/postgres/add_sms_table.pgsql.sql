-- Automated conversion: add_sms_table.sql -> Postgres
-- Removed USE statement, adjusted types and removed MySQL-specific COLLATE/ENGINE

-- SMS Logs Table
CREATE TABLE IF NOT EXISTS sms_logs (
  sms_log_id serial,
  phone_number varchar(20) NOT NULL,
  message text NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'Pending', -- converted from enum
  error_message text NULL,
  sent_by integer NULL,
  sent_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sms_log_id)
);
CREATE INDEX idx_phone ON sms_logs (phone_number);
CREATE INDEX idx_status ON sms_logs (status);
CREATE INDEX idx_sent_at ON sms_logs (sent_at);
ALTER TABLE sms_logs
  ADD CONSTRAINT fk_sms_sent_by FOREIGN KEY (sent_by) REFERENCES users (user_id) ON DELETE SET NULL;

-- SMS Templates Table
CREATE TABLE IF NOT EXISTS sms_templates (
  template_id serial,
  template_name varchar(100) NOT NULL,
  template_content text NOT NULL,
  category varchar(50), -- comment removed
  is_active boolean NOT NULL DEFAULT true,
  created_by integer NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL,
  PRIMARY KEY (template_id)
);
CREATE INDEX idx_category ON sms_templates (category);
CREATE INDEX idx_active ON sms_templates (is_active);
ALTER TABLE sms_templates
  ADD CONSTRAINT fk_template_created_by FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL;

-- Note: INSERTs that used ON DUPLICATE KEY UPDATE should be converted to
-- INSERT ... ON CONFLICT DO NOTHING or proper ON CONFLICT ... DO UPDATE with a unique constraint.

INSERT INTO sms_templates (template_name, template_content, category, is_active) VALUES
('Fee Reminder', 'Dear Parent, Your ward''s fee is due. Please pay at the earliest. Thank you. - [SCHOOL_NAME]', 'Fee', true),
('Fee Payment Confirmation', 'Dear Parent, We have received Rs. [AMOUNT] as fee payment. Receipt No: [RECEIPT_NO]. Thank you. - [SCHOOL_NAME]', 'Fee', true),
('Holiday Notification', 'Dear Parent, School will remain closed on [DATE] due to [REASON]. - [SCHOOL_NAME]', 'Holiday', true),
('PTM Notice', 'Dear Parent, Parent-Teacher meeting scheduled on [DATE] at [TIME]. Your presence is mandatory. - [SCHOOL_NAME]', 'PTM', true),
('Exam Notification', 'Dear Parent, Exam Schedule: [EXAM_NAME] from [START_DATE] to [END_DATE]. - [SCHOOL_NAME]', 'Exam', true),
('Exam Result', 'Dear Parent, Your ward has secured [MARKS]% in [EXAM_NAME]. Congratulations! - [SCHOOL_NAME]', 'Exam', true),
('Attendance Alert', 'Dear Parent, Your ward was absent on [DATE]. Please contact school if this is an error. - [SCHOOL_NAME]', 'General', true),
('Birthday Wish', 'Happy Birthday [STUDENT_NAME]! Wishing you a wonderful year ahead. - [SCHOOL_NAME]', 'General', true);

-- Note about modules insert: replace ON DUPLICATE KEY UPDATE with ON CONFLICT when a unique constraint exists on module_name.
-- Example (requires unique index on module_name):
-- INSERT INTO modules (module_name, module_description, is_active) VALUES ('SMS', 'SMS Management', true)
-- ON CONFLICT (module_name) DO UPDATE SET module_description = EXCLUDED.module_description;

SELECT 'SMS tables created successfully!' AS message;
