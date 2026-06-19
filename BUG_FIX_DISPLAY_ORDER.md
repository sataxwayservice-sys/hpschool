# Bug Fix: display_order → class_order

## Issue Reported
User reported: **"Current Class * not showing"** on the Student Promotion page.

## Root Cause
The SQL queries in multiple files were using an incorrect column name: `display_order`

The actual column name in the `classes` table is: `class_order`

```sql
-- Database schema shows:
CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) NOT NULL,
  `class_order` int(11) NOT NULL,          ← Correct column name
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`class_id`)
);
```

## Impact
When the queries used `display_order`, they failed silently and returned empty arrays. This caused:
- Class dropdowns showing only "-- Select Class --" with no options
- Section dropdowns showing only "-- Select Section --" with no options
- Users unable to select classes/sections on affected pages

## Files Fixed (11 total)

### Fixed Manually (2 files)
1. `modules/students/promote.php`
2. `modules/students/generate_id_card.php`

### Fixed via Script (9 files)
3. `modules/fees/due.php`
4. `modules/fees/structure.php`
5. `modules/marks/generate_marksheet.php`
6. `modules/marks/index.php`
7. `modules/reports/class_wise_students.php`
8. `modules/reports/due_fees.php`
9. `modules/reports/fee_collection.php`
10. `modules/reports/student_list.php`
11. `modules/sms/index.php`

## The Fix
Changed all occurrences of:
```php
// BEFORE (incorrect)
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY display_order");

// AFTER (correct)
$classes = fetchAll("SELECT * FROM classes WHERE is_active = 1 ORDER BY class_order");
```

## Verification
To verify the fix works:
1. Go to: `http://localhost:8080/account3/modules/students/promote.php`
2. Check that the "Current Class" dropdown now shows all available classes
3. Check that the "Current Section" dropdown shows all available sections

## Testing Status
✅ Fixed in code
⏳ Awaiting user testing

## Related Issues
This issue was present in:
- Student promotion page
- ID card generation
- Fee structure assignment
- Due fees page
- Mark entry pages
- All report pages

All have been fixed in this session.

## Prevention
In future development:
1. Always verify column names against database schema
2. Test dropdown populations on new pages
3. Check browser console for SQL errors

---

**Fixed**: November 2, 2025
**Issue**: Classes not showing in dropdowns
**Status**: ✅ Resolved
