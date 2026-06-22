-- Converted INSERTs from add_marks_tables.sql for Postgres
-- Review before running

-- Ensure unique constraints exist to support ON CONFLICT behavior
CREATE UNIQUE INDEX IF NOT EXISTS uk_subject_code ON subjects (subject_code);
CREATE UNIQUE INDEX IF NOT EXISTS uk_exams_name ON exams (exam_name);

-- Insert default subjects (use subject_code as conflict target)
INSERT INTO subjects (subject_name, subject_code, max_marks, pass_marks, is_active) VALUES
('English', 'ENG', 100, 33, true),
('Mathematics', 'MATH', 100, 33, true),
('Science', 'SCI', 100, 33, true),
('Social Studies', 'SST', 100, 33, true),
('Hindi', 'HINDI', 100, 33, true),
('Computer Science', 'CS', 100, 33, true),
('Physical Education', 'PE', 50, 17, true),
('Art & Craft', 'ART', 50, 17, true)
ON CONFLICT (subject_code) DO NOTHING;

-- Insert sample exams for current academic year
-- Convert CURDATE() -> CURRENT_DATE, DATE_ADD(...) -> CURRENT_DATE + INTERVAL 'N month'
-- Convert CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1) -> (EXTRACT(YEAR FROM CURRENT_DATE)::int::text || '-' || (EXTRACT(YEAR FROM CURRENT_DATE)::int + 1)::text)
INSERT INTO exams (exam_name, exam_type, exam_date, academic_year, is_active) VALUES
('Unit Test 1', 'Monthly', CURRENT_DATE, (EXTRACT(YEAR FROM CURRENT_DATE)::int::text || '-' || (EXTRACT(YEAR FROM CURRENT_DATE)::int + 1)::text), true),
('Mid Term Exam', 'Quarterly', (CURRENT_DATE + INTERVAL '2 month'), (EXTRACT(YEAR FROM CURRENT_DATE)::int::text || '-' || (EXTRACT(YEAR FROM CURRENT_DATE)::int + 1)::text), true),
('Unit Test 2', 'Monthly', (CURRENT_DATE + INTERVAL '4 month'), (EXTRACT(YEAR FROM CURRENT_DATE)::int::text || '-' || (EXTRACT(YEAR FROM CURRENT_DATE)::int + 1)::text), true),
('Final Exam', 'Annual', (CURRENT_DATE + INTERVAL '6 month'), (EXTRACT(YEAR FROM CURRENT_DATE)::int::text || '-' || (EXTRACT(YEAR FROM CURRENT_DATE)::int + 1)::text), true)
ON CONFLICT (exam_name) DO NOTHING;

-- Add marks/exam permissions to modules (ensure unique index exists)
CREATE UNIQUE INDEX IF NOT EXISTS uk_modules_name ON modules (module_name);
INSERT INTO modules (module_name, module_description, is_active) VALUES
('Marks', 'Marks/Exam Management', true)
ON CONFLICT (module_name) DO NOTHING;

-- Notes:
-- - This conversion assumes modules.subject_code and exams.exam_name should be unique.
-- - Adjust conflict targets if different uniqueness semantics are required.
