-- Automated conversion: MySQL -> PostgreSQL (best-effort)
-- Review required: enums converted to varchar, tinyint -> boolean, AUTO_INCREMENT -> serial,
-- removed MySQL-specific options (ENGINE, CHARSET, AFTER, ON UPDATE). Manual review needed for
-- ON CONFLICT translations, DATE functions (CURDATE, DATE_ADD), and JSON/longtext usage.

BEGIN;

-- 1. USERS & AUTHENTICATION
CREATE TABLE users (
  user_id serial,
  username varchar(50) NOT NULL,
  email varchar(100) NOT NULL,
  password varchar(255) NOT NULL,
  password_encrypted text DEFAULT NULL,
  full_name varchar(100) NOT NULL,
  role varchar(50) NOT NULL DEFAULT 'clerk', -- converted from enum
  student_id integer DEFAULT NULL,
  mobile varchar(15) DEFAULT NULL,
  firebase_uid varchar(255) DEFAULT NULL,
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, -- ON UPDATE removed; consider trigger for auto-update
  last_login timestamp NULL DEFAULT NULL,
  PRIMARY KEY (user_id)
);
CREATE INDEX idx_username ON users (username);
CREATE INDEX idx_email ON users (email);
CREATE UNIQUE INDEX idx_student_id ON users (student_id);

-- user_permissions
CREATE TABLE user_permissions (
  permission_id serial,
  user_id integer NOT NULL,
  module_name varchar(50) NOT NULL,
  can_view boolean DEFAULT false,
  can_add boolean DEFAULT false,
  can_edit boolean DEFAULT false,
  can_delete boolean DEFAULT false,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (permission_id)
);
CREATE INDEX fk_user_perm ON user_permissions (user_id);
ALTER TABLE user_permissions
  ADD CONSTRAINT fk_user_perm FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE;

-- school_settings
CREATE TABLE school_settings (
  setting_id serial,
  school_name varchar(200) NOT NULL,
  school_address text,
  school_phone varchar(20) DEFAULT NULL,
  school_email varchar(100) DEFAULT NULL,
  school_logo varchar(255) DEFAULT NULL,
  login_logo varchar(255) DEFAULT NULL,
  banner_logo varchar(255) DEFAULT NULL,
  current_academic_year varchar(20) NOT NULL,
  admission_prefix varchar(10) DEFAULT 'STU',
  receipt_prefix varchar(10) DEFAULT 'REC',
  currency_symbol varchar(10) DEFAULT '₹',
  upi_id varchar(100) DEFAULT NULL,
  payment_recipient_name varchar(150) DEFAULT NULL,
  payment_note varchar(255) DEFAULT NULL,
  timezone varchar(50) DEFAULT 'Asia/Kolkata',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_id)
);

-- classes
CREATE TABLE classes (
  class_id serial,
  class_name varchar(50) NOT NULL,
  class_order integer NOT NULL,
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id)
);

-- sections
CREATE TABLE sections (
  section_id serial,
  section_name varchar(10) NOT NULL,
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (section_id)
);

-- students
CREATE TABLE students (
  student_id serial,
  school_id integer DEFAULT NULL,
  admission_no varchar(50) NOT NULL,
  student_name varchar(100) NOT NULL,
  date_of_birth date NOT NULL,
  gender varchar(10) NOT NULL,
  class_id integer NOT NULL,
  section_id integer NOT NULL,
  roll_no varchar(20) DEFAULT NULL,
  address text,
  father_name varchar(100) NOT NULL,
  mother_name varchar(100) DEFAULT NULL,
  contact_no varchar(15) NOT NULL,
  email varchar(100) DEFAULT NULL,
  admission_date date NOT NULL,
  photo varchar(255) DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'Active',
  firebase_synced boolean DEFAULT false,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id)
);
CREATE UNIQUE INDEX idx_admission_no ON students (admission_no);
CREATE INDEX fk_student_class ON students (class_id);
CREATE INDEX fk_student_section ON students (section_id);
CREATE INDEX idx_student_school_id ON students (school_id);
CREATE INDEX idx_status ON students (status);
ALTER TABLE students
  ADD CONSTRAINT fk_student_class FOREIGN KEY (class_id) REFERENCES classes (class_id),
  ADD CONSTRAINT fk_student_section FOREIGN KEY (section_id) REFERENCES sections (section_id);

-- student_applications
CREATE TABLE student_applications (
  application_id serial,
  user_id integer NOT NULL,
  student_name varchar(100) NOT NULL,
  father_name varchar(100) NOT NULL,
  mother_name varchar(100) DEFAULT NULL,
  gender varchar(10) NOT NULL DEFAULT 'Other',
  mobile varchar(15) NOT NULL,
  email varchar(100) NOT NULL,
  date_of_birth date NOT NULL,
  address text DEFAULT NULL,
  class_id integer NOT NULL,
  section_id integer DEFAULT NULL,
  profile_photo varchar(255) DEFAULT NULL,
  documents_json text DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'Pending',
  rejection_reason text DEFAULT NULL,
  reviewed_by integer DEFAULT NULL,
  reviewed_at timestamp NULL DEFAULT NULL,
  linked_student_id integer DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (application_id)
);
CREATE UNIQUE INDEX idx_student_application_user ON student_applications (user_id);
CREATE INDEX idx_student_application_status ON student_applications (status);
CREATE INDEX idx_student_application_class ON student_applications (class_id);
CREATE INDEX idx_student_application_email ON student_applications (email);
CREATE INDEX idx_student_application_mobile ON student_applications (mobile);
ALTER TABLE student_applications
  ADD CONSTRAINT fk_student_application_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE;

-- student_documents
CREATE TABLE student_documents (
  document_id serial,
  student_id integer NOT NULL,
  document_type varchar(50) NOT NULL,
  document_title varchar(200) NOT NULL,
  exam_id integer DEFAULT NULL,
  issue_date date NOT NULL,
  remarks text DEFAULT NULL,
  visible_to_student boolean NOT NULL DEFAULT false,
  payload_json text NOT NULL,
  document_hash char(64) NOT NULL,
  generated_by integer DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (document_id)
);
CREATE UNIQUE INDEX idx_student_document_hash ON student_documents (document_hash);
CREATE INDEX idx_student_document_student ON student_documents (student_id, visible_to_student, document_type);
CREATE INDEX idx_student_document_exam ON student_documents (exam_id);
CREATE INDEX idx_student_document_created ON student_documents (created_at);
ALTER TABLE student_documents
  ADD CONSTRAINT fk_student_document_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE;

-- (Many more tables follow in original schema; convert similarly)

COMMIT;

-- NOTE: This file is an automated conversion. Review the following manually:
-- 1) Enum columns were converted to varchar; consider creating PostgreSQL ENUM types if desired.
-- 2) Columns using ON UPDATE CURRENT_TIMESTAMP need triggers to auto-update.
-- 3) Function calls like CURDATE(), DATE_ADD(), CONCAT(YEAR(...)) in INSERTs must be adapted to Postgres equivalents.
-- 4) Check UNIQUE / INDEX constraints: some INSERTs use ON DUPLICATE KEY UPDATE; replace with ON CONFLICT clauses if needed.
