-- Converted INSERTs from add_sms_table.sql for Postgres
-- Ensure module_name is unique before using ON CONFLICT
CREATE UNIQUE INDEX IF NOT EXISTS uk_modules_name ON modules (module_name);

INSERT INTO sms_templates (template_name, template_content, category, is_active) VALUES
('Fee Reminder', 'Dear Parent, Your ward''s fee is due. Please pay at the earliest. Thank you. - [SCHOOL_NAME]', 'Fee', true),
('Fee Payment Confirmation', 'Dear Parent, We have received Rs. [AMOUNT] as fee payment. Receipt No: [RECEIPT_NO]. Thank you. - [SCHOOL_NAME]', 'Fee', true),
('Holiday Notification', 'Dear Parent, School will remain closed on [DATE] due to [REASON]. - [SCHOOL_NAME]', 'Holiday', true),
('PTM Notice', 'Dear Parent, Parent-Teacher meeting scheduled on [DATE] at [TIME]. Your presence is mandatory. - [SCHOOL_NAME]', 'PTM', true),
('Exam Notification', 'Dear Parent, Exam Schedule: [EXAM_NAME] from [START_DATE] to [END_DATE]. - [SCHOOL_NAME]', 'Exam', true),
('Exam Result', 'Dear Parent, Your ward has secured [MARKS]% in [EXAM_NAME]. Congratulations! - [SCHOOL_NAME]', 'Exam', true),
('Attendance Alert', 'Dear Parent, Your ward was absent on [DATE]. Please contact school if this is an error. - [SCHOOL_NAME]', 'General', true),
('Birthday Wish', 'Happy Birthday [STUDENT_NAME]! Wishing you a wonderful year ahead. - [SCHOOL_NAME]', 'General', true);

-- Insert module record safely
INSERT INTO modules (module_name, module_description, is_active) VALUES
('SMS', 'SMS Management', true)
ON CONFLICT (module_name) DO UPDATE SET module_description = EXCLUDED.module_description, is_active = EXCLUDED.is_active;

SELECT 'SMS templates and module insert converted for Postgres' AS message;
