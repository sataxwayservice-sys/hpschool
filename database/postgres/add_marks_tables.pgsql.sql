-- Automated conversion of add_marks_tables.sql -> Postgres
-- Review required: function replacements (CURDATE, DATE_ADD), ON DUPLICATE KEY UPDATE -> ON CONFLICT

-- Exams Table
CREATE TABLE IF NOT EXISTS exams (
  exam_id serial,
  exam_name varchar(100) NOT NULL,
  exam_type varchar(50),
  exam_date date NOT NULL,
  academic_year varchar(20),
  description text,
  is_active boolean NOT NULL DEFAULT true,
  created_by integer,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL,
  PRIMARY KEY (exam_id)
);
CREATE INDEX idx_exam_date ON exams (exam_date);
CREATE INDEX idx_active_exams ON exams (is_active);

-- Exam Routine Table
CREATE TABLE IF NOT EXISTS exam_routines (
  routine_id serial,
  exam_id integer NOT NULL,
  class_id integer NOT NULL,
  section_id integer NOT NULL DEFAULT 0,
  subject_id integer NOT NULL,
  exam_date date NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  room_no varchar(50) DEFAULT NULL,
  notes varchar(255) DEFAULT NULL,
  display_order integer NOT NULL DEFAULT 0,
  is_active boolean NOT NULL DEFAULT true,
  created_by integer DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (routine_id)
);
CREATE UNIQUE INDEX uk_exam_class_section_subject ON exam_routines (exam_id, class_id, section_id, subject_id);

-- Subjects Table
CREATE TABLE IF NOT EXISTS subjects (
  subject_id serial,
  subject_name varchar(100) NOT NULL,
  subject_code varchar(20),
  max_marks integer NOT NULL DEFAULT 100,
  pass_marks integer NOT NULL DEFAULT 33,
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL,
  PRIMARY KEY (subject_id)
);
CREATE UNIQUE INDEX uk_subject_code ON subjects (subject_code);
CREATE INDEX idx_subject_active ON subjects (is_active);

-- Marks Table
CREATE TABLE IF NOT EXISTS marks (
  mark_id serial,
  student_id integer NOT NULL,
  exam_id integer NOT NULL,
  subject_id integer NOT NULL,
  marks_obtained numeric(5,2) NOT NULL DEFAULT 0.00,
  remarks varchar(255) NULL,
  created_by integer NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by integer NULL,
  updated_at timestamp NULL,
  PRIMARY KEY (mark_id)
);
CREATE UNIQUE INDEX uk_student_exam_subject ON marks (student_id, exam_id, subject_id);
CREATE INDEX idx_mark_student ON marks (student_id);
CREATE INDEX idx_mark_exam ON marks (exam_id);
CREATE INDEX idx_mark_subject ON marks (subject_id);

-- Note: INSERT statements using CURDATE()/DATE_ADD and ON DUPLICATE KEY UPDATE need manual rewriting for Postgres.

-- Example view creation (may require small syntax tweaks)
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
        WHEN (m.marks_obtained / sub.max_marks) * 100 >= 33 THEN 'D'
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

SELECT 'Marks/Exam tables converted for Postgres (review inserts and functions)' AS message;
