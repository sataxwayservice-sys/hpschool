-- Convert ALTER TABLE to Postgres (remove AFTER clauses)
ALTER TABLE school_settings
  ADD COLUMN theme_primary_color varchar(7) DEFAULT '#0d6efd',
  ADD COLUMN theme_secondary_color varchar(7) DEFAULT '#6c757d',
  ADD COLUMN theme_success_color varchar(7) DEFAULT '#198754',
  ADD COLUMN theme_info_color varchar(7) DEFAULT '#0dcaf0',
  ADD COLUMN theme_warning_color varchar(7) DEFAULT '#ffc107',
  ADD COLUMN theme_danger_color varchar(7) DEFAULT '#dc3545',
  ADD COLUMN theme_preset varchar(50) DEFAULT 'default';

-- Update default record
UPDATE school_settings
SET
  theme_primary_color = '#0d6efd',
  theme_secondary_color = '#6c757d',
  theme_success_color = '#198754',
  theme_info_color = '#0dcaf0',
  theme_warning_color = '#ffc107',
  theme_danger_color = '#dc3545',
  theme_preset = 'default'
WHERE setting_id = 1;

-- Verification query
SELECT
  school_name,
  theme_primary_color,
  theme_secondary_color,
  theme_success_color,
  theme_info_color,
  theme_warning_color,
  theme_danger_color,
  theme_preset
FROM school_settings;

-- NOTE: Postgres ignores the MySQL AFTER clause. Columns are appended in the order above.
