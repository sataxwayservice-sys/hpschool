-- =====================================================
-- Add Theme Color Columns to school_settings
-- =====================================================

-- Add theme color columns
ALTER TABLE `school_settings`
ADD COLUMN `theme_primary_color` VARCHAR(7) DEFAULT '#0d6efd' AFTER `timezone`,
ADD COLUMN `theme_secondary_color` VARCHAR(7) DEFAULT '#6c757d' AFTER `theme_primary_color`,
ADD COLUMN `theme_success_color` VARCHAR(7) DEFAULT '#198754' AFTER `theme_secondary_color`,
ADD COLUMN `theme_info_color` VARCHAR(7) DEFAULT '#0dcaf0' AFTER `theme_success_color`,
ADD COLUMN `theme_warning_color` VARCHAR(7) DEFAULT '#ffc107' AFTER `theme_info_color`,
ADD COLUMN `theme_danger_color` VARCHAR(7) DEFAULT '#dc3545' AFTER `theme_warning_color`,
ADD COLUMN `theme_preset` VARCHAR(50) DEFAULT 'default' AFTER `theme_danger_color`;

-- Update existing record with default colors
UPDATE `school_settings`
SET
    `theme_primary_color` = '#0d6efd',
    `theme_secondary_color` = '#6c757d',
    `theme_success_color` = '#198754',
    `theme_info_color` = '#0dcaf0',
    `theme_warning_color` = '#ffc107',
    `theme_danger_color` = '#dc3545',
    `theme_preset` = 'default'
WHERE `setting_id` = 1;

-- =====================================================
-- Predefined Theme Presets
-- =====================================================

/*
Available Theme Presets:

1. Default (Bootstrap Blue)
   Primary: #0d6efd
   Secondary: #6c757d
   Success: #198754
   Info: #0dcaf0
   Warning: #ffc107
   Danger: #dc3545

2. School Green
   Primary: #28a745 (Green)
   Secondary: #6c757d (Gray)
   Success: #20c997 (Teal)
   Info: #17a2b8 (Cyan)
   Warning: #ffc107 (Amber)
   Danger: #dc3545 (Red)

3. Royal Blue
   Primary: #0056b3 (Royal Blue)
   Secondary: #495057 (Dark Gray)
   Success: #28a745 (Green)
   Info: #007bff (Blue)
   Warning: #fd7e14 (Orange)
   Danger: #dc3545 (Red)

4. Purple Academic
   Primary: #6f42c1 (Purple)
   Secondary: #6c757d (Gray)
   Success: #198754 (Green)
   Info: #9b59b6 (Lavender)
   Warning: #f39c12 (Gold)
   Danger: #e74c3c (Red)

5. Orange Energy
   Primary: #fd7e14 (Orange)
   Secondary: #6c757d (Gray)
   Success: #20c997 (Teal)
   Info: #17a2b8 (Cyan)
   Warning: #ffc107 (Amber)
   Danger: #dc3545 (Red)

6. Teal Modern
   Primary: #20c997 (Teal)
   Secondary: #6c757d (Gray)
   Success: #28a745 (Green)
   Info: #17a2b8 (Cyan)
   Warning: #ffc107 (Amber)
   Danger: #dc3545 (Red)

7. Crimson Tradition
   Primary: #dc143c (Crimson)
   Secondary: #6c757d (Gray)
   Success: #198754 (Green)
   Info: #0dcaf0 (Cyan)
   Warning: #ffc107 (Amber)
   Danger: #8b0000 (Dark Red)

8. Navy Professional
   Primary: #001f3f (Navy)
   Secondary: #495057 (Dark Gray)
   Success: #198754 (Green)
   Info: #0dcaf0 (Cyan)
   Warning: #ffc107 (Amber)
   Danger: #dc3545 (Red)
*/

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
