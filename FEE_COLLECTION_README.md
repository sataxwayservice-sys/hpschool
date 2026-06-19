# Fee Collection System - Professional Layout

## Overview
A professional fee receipt collection system with a modern interface matching the uploaded design, featuring dual-panel fee management and comprehensive receipt generation.

## Files Created/Modified

### Main Files:
1. **`modules/fees/collect_complete.php`** - Main fee collection interface
2. **`api/search_student.php`** - Student search API (by admission number or name)
3. **`api/search_students_by_name.php`** - Multi-student search API
4. **`test_fee_collection.php`** - Diagnostic tool to verify system setup
5. **`test_receipt_tables.php`** - Optional receipt books setup tool

## Features

### Professional Interface:
- **Top Action Bar**: Save, Edit, Delete, Cancel, Clear, View Report, Back buttons
- **Two-Column Layout**:
  - Left: Student assignment and payment details
  - Right: Payment method and remarks
- **Dual Fee Panels**:
  - Payable Fee List (Green) - Fees being collected
  - Pending Fee List (Yellow) - Available fees to collect
- **Interactive Fee Management**: Click to move fees between pending and payable
- **Auto-Calculate Totals**: Instant calculation of amounts
- **Multiple Payment Methods**: Cash, Bank Transfer, UPI, Cheque

### Smart Search:
- Search by admission number (exact or partial match)
- Search by student name
- Multiple result selection when multiple matches found
- Real-time search with loading indicator

### Fee Management:
- View all pending fees for selected student
- Monthly fees shown separately by month/year
- One-time fees tracked separately
- Prevents duplicate fee collection
- Partial payment support
- Additional charges field

### Receipt Generation:
- Auto-generated receipt numbers
- Stores all payment details
- Links to student and fee structure
- Optional receipt book organization
- Redirect to receipt view after save

## How to Use

### Initial Setup:

1. **Run Diagnostic Test**:
   ```
   http://localhost:8080/account3/test_fee_collection.php
   ```
   This will check:
   - Active students exist
   - Fee heads are created
   - Fee structure is assigned to students
   - Database tables are properly configured

2. **Prerequisites** (if diagnostic shows issues):
   - Add students via Student Management
   - Create fee heads via Fee Heads Management
   - Assign fees to students via Fee Structure page

3. **Optional - Receipt Books** (for advanced organization):
   ```
   http://localhost:8080/account3/test_receipt_tables.php
   ```
   Run the SQL to create receipt_books table and add optional columns

### Collecting Fees:

1. **Access Fee Collection**:
   ```
   http://localhost:8080/account3/modules/fees/collect_complete.php
   ```

2. **Search for Student**:
   - Enter admission number or student name
   - Click Search or press Enter
   - If multiple matches, select from results table

3. **Student Information Displayed**:
   - Name, admission number, class, section, roll number
   - Father name and status
   - All in a blue info box

4. **Select Fees to Collect**:
   - Pending fees appear on the right (yellow header)
   - Click green checkmark (✓) to move to Payable list (left, green header)
   - Click red trash icon to remove from Payable list
   - Edit amounts in Payable list if needed (for partial payments)

5. **Enter Payment Details**:
   - Receipt Date (defaults to today)
   - Payment Type: Cash, Bank Transfer, UPI, or Cheque
   - Optional: Cheque/Reference Number
   - Optional: Bank Name
   - Optional: Cheque Date
   - Optional: Remarks
   - Optional: Additional Charge amount

6. **Save Receipt**:
   - Click green "Save" button at top
   - Confirm the dialog
   - System generates receipt and redirects to receipt view

### Key Features in Action:

**Auto-Calculate**:
- As you add fees to payable list, totals update automatically
- Charge amount is added to the net total
- Amount field updates in real-time

**Prevent Duplicates**:
- Monthly fees already collected for same month/year are hidden
- One-time fees already collected don't appear in pending list

**Partial Payments**:
- You can modify the payable amount (reduce it)
- Useful for installment payments

**Navigation**:
- Back button returns to dashboard
- Clear button resets the form
- Cancel button reloads the page
- View Report opens receipts list in new tab

## Database Structure

### Required Tables:
- `students` - Student information
- `fee_heads` - Fee types (monthly/one-time)
- `fee_structure` - Fee assignments to students
- `fee_receipts` - Receipt records
- `fee_receipt_details` - Line items for each receipt

### Optional Tables:
- `receipt_books` - For organizing receipts into books/series

### Dynamic Column Detection:
The system automatically detects which columns exist in `fee_receipts`:
- `receipt_book_id` - Links to receipt books (optional)
- `charge_amount` - Additional charges (optional)
- `bank_name` - Bank name for transfers (optional)
- `cheque_date` - Date on cheque (optional)

If columns don't exist, they're simply omitted from the INSERT query.

## API Endpoints

### `/api/search_student.php`
**Method**: POST
**Parameters**:
- `search_term` or `admission_no` - Search string

**Response**:
```json
{
  "success": true,
  "student": {
    "student_id": 1,
    "student_name": "John Doe",
    "admission_no": "ADM001",
    "class_name": "10th",
    "section_name": "A",
    ...
  }
}
```

### `/api/search_students_by_name.php`
**Method**: POST
**Parameters**:
- `search` - Search string

**Response**:
```json
{
  "success": true,
  "students": [
    {"student_id": 1, "student_name": "John Doe", ...},
    {"student_id": 2, "student_name": "Jane Doe", ...}
  ]
}
```

## Troubleshooting

### Issue: No students found
**Solution**: Add students via Student Management module

### Issue: No pending fees shown
**Solution**:
1. Create fee heads via Fee Heads Management
2. Assign fees to students via Fee Structure page

### Issue: Receipt books warning
**Solution**: Optional feature. Visit `test_receipt_tables.php` to add or ignore the warning

### Issue: Search not working
**Solution**: Check JavaScript console (F12) for errors, verify API endpoints exist

### Issue: Save button disabled
**Solution**:
1. Make sure a student is selected
2. Add at least one fee to the Payable Fee List
3. Button enables automatically when fees are added

### Issue: Error on save
**Solution**:
1. Check browser console (F12) for JavaScript errors
2. Check PHP error log for server errors
3. Verify database columns exist using diagnostic tool

## Design Philosophy

This system follows the professional design pattern shown in the uploaded image:

1. **Action-First Design**: All actions accessible from top button bar
2. **Visual Hierarchy**: Color-coded sections (green=payable, yellow=pending)
3. **Minimal Clicks**: Drag-and-drop style interaction with single-click movements
4. **Real-time Feedback**: Instant calculations and visual updates
5. **Error Prevention**: Disabled states, confirmations, and duplicate prevention
6. **Mobile Responsive**: Bootstrap 5 responsive grid system

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

Requires JavaScript enabled.

## Dependencies

- PHP 7.4+
- MySQL 5.7+
- Bootstrap 5.x
- jQuery 3.6+
- Bootstrap Icons

All dependencies are already included in the base system.

## Future Enhancements

Possible additions:
- Print receipt directly from save confirmation
- SMS notification to parents after collection
- Bulk fee collection for multiple students
- Advanced filtering in pending fees
- Fee collection history per student
- Export receipts to PDF/Excel
- Receipt cancellation workflow

## Support

For issues or questions:
1. Run diagnostic tool: `test_fee_collection.php`
2. Check browser console for JavaScript errors
3. Verify database structure
4. Check PHP error logs

## Credits

System designed to match professional fee collection interface with modern UI/UX best practices.
