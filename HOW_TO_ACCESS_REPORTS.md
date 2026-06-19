# How to Access Reports

## Quick Access Methods

### Method 1: Navigation Menu (Easiest)
1. Log in to the system
2. Look at the top navigation bar
3. Click on **"Reports"** menu
4. Select **"All Reports"** from the dropdown
5. Or select any specific report directly

The Reports menu should now be visible if you have access to Students or Fees modules.

### Method 2: Direct URL
Visit: `http://localhost:8080/account3/modules/reports/`

### Method 3: From Dashboard
Click on **Dashboard** → Navigate to **Reports** section

---

## Available Reports

### Student Reports
- **All Students List** - Complete student database
- **Class-wise Students** - Organized by class and section
- **Student Details Report** - Detailed information with parents

### Fee Reports
- **Fee Collection Report** - All receipts with filters
- **Due Fee Report** - Students with pending payments
- **Date-wise Collection** - Daily/Monthly summaries
- **Class-wise Fee Report** - Collections by class
- **Fee Head-wise Report** - By fee type (Tuition, Transport, etc.)
- **Payment Mode Report** - Cash, Bank, UPI, Cheque breakdown

### Academic Reports
- **Marks Report** - Student exam results
- **Mark Sheet Generation** - Print report cards
- **Performance Analysis** - Trends and analytics

### System Reports
- **Activity Log Report** - User actions and audit trail
- **User Management Report** - All system users
- **Summary Dashboard** - Overall statistics

---

## Features

✅ **Excel Export** - Download any report as Excel file
✅ **Print** - Print-friendly layouts
✅ **Filters** - Date range, class, section filtering
✅ **Search** - Quick search functionality
✅ **Sort** - Click column headers to sort

---

## Troubleshooting

### "Reports menu not showing"
**Solution**: The menu now shows if you have Students or Fees permissions. If still not visible:
1. Run the setup script: `http://localhost:8080/account3/setup_reports_permissions.php`
2. This will add reports permissions to all roles
3. Refresh the page

### "Access Denied" error
**Solution**: Your user account needs permissions. Contact your administrator to:
- Grant you "Reports" view permission, OR
- Grant you "Students" or "Fees" view permission

### "Page not found" error
**Solution**: Make sure you're using the correct URL:
- Correct: `http://localhost:8080/account3/modules/reports/`
- Incorrect: `http://localhost/account3/modules/reports/` (missing 8080 port)

---

## Setup Script (One-time)

If the Reports menu is still not accessible, run this one-time setup:

1. Open browser
2. Visit: `http://localhost:8080/account3/setup_reports_permissions.php`
3. Wait for success message
4. Refresh your dashboard
5. Reports menu should now appear

**Important**: Delete `setup_reports_permissions.php` after running it.

---

## Direct Links to Common Reports

### Quick Links (Bookmark these!)

**Student Reports:**
- All Students: `http://localhost:8080/account3/modules/reports/student_list.php`
- By Class: `http://localhost:8080/account3/modules/reports/class_wise_students.php`

**Fee Reports:**
- Collections: `http://localhost:8080/account3/modules/reports/fee_collection.php`
- Due Fees: `http://localhost:8080/account3/modules/reports/due_fees.php`
- By Date: `http://localhost:8080/account3/modules/reports/date_wise_collection.php`
- By Payment: `http://localhost:8080/account3/modules/reports/payment_mode_report.php`

---

**Need Help?** Contact your system administrator.
