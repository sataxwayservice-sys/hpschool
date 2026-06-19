# School Students and Fees Management System
## Complete Development Roadmap

---

## Table of Contents
1. [System Overview](#system-overview)
2. [Installation Guide](#installation-guide)
3. [Project Structure](#project-structure)
4. [Database Architecture](#database-architecture)
5. [Module Development Guide](#module-development-guide)
6. [Firebase Integration](#firebase-integration)
7. [Testing & Deployment](#testing--deployment)
8. [Maintenance & Updates](#maintenance--updates)

---

## System Overview

### Technology Stack
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript, jQuery
- **Backend**: PHP 8.x (Core)
- **Database**: MySQL 8.x
- **Cloud**: Firebase (Authentication, Realtime Database, Cloud Functions)
- **Libraries**:
  - DataTables (Table management)
  - TCPDF/FPDF (PDF generation)
  - PHPSpreadsheet (Excel export)
  - Kreait Firebase PHP SDK

### Key Features
1. Student admission and management
2. Individual fee structure assignment
3. Fee collection with receipt generation
4. Mark entry and mark sheet generation
5. Student promotion system
6. Comprehensive reporting (PDF/Excel/JSON)
7. Role-based access control
8. SMS alerts via Firebase
9. Real-time Firebase backup
10. Payment link generation

---

## Installation Guide

### Prerequisites
1. XAMPP/WAMP/LAMP installed
2. PHP 8.0 or higher
3. MySQL 5.7 or higher
4. Composer (for Firebase SDK)
5. Firebase account

### Step-by-Step Installation

#### Step 1: Clone/Copy Files
```bash
# Copy all files to XAMPP htdocs
c:\xampp\htdocs\account3\
```

#### Step 2: Configure Database
1. Open `config/database.php`
2. Update database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management');
```

#### Step 3: Install Composer Dependencies
```bash
cd c:\xampp\htdocs\account3
composer require kreait/firebase-php
composer require phpoffice/phpspreadsheet
composer require tecnickcom/tcpdf
```

#### Step 4: Firebase Setup
1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create a new project
3. Enable Authentication (Email/Password)
4. Enable Realtime Database
5. Download Service Account JSON from Project Settings
6. Save as `config/firebase-service-account.json`
7. Update `config/firebase_config.php` with your Firebase credentials

#### Step 5: Run Setup
1. Start Apache and MySQL in XAMPP
2. Open browser: `http://localhost/account3/setup.php`
3. Click "Import Database" (Step 1)
4. Create Super Admin account (Step 2)
5. Login with created credentials

---

## Project Structure

```
account3/
│
├── config/                      # Configuration files
│   ├── config.php              # Main configuration
│   ├── database.php            # Database connection & helpers
│   ├── firebase_config.php     # Firebase configuration
│   └── firebase-service-account.json  # Firebase credentials
│
├── includes/                    # Common includes
│   ├── header.php              # Header with navigation
│   ├── footer.php              # Footer
│   ├── functions.php           # Common utility functions
│   └── auth.php                # Authentication helpers
│
├── assets/                      # Static assets
│   ├── css/
│   │   └── style.css           # Custom styles
│   ├── js/
│   │   └── script.js           # Custom JavaScript
│   ├── images/                 # Images
│   └── uploads/                # User uploads
│       ├── students/           # Student photos
│       └── logos/              # School logos
│
├── modules/                     # Application modules
│   ├── auth/                   # Authentication
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── profile.php
│   │   └── change_password.php
│   │
│   ├── dashboard/              # Dashboard
│   │   └── index.php
│   │
│   ├── students/               # Student Management
│   │   ├── index.php           # List students
│   │   ├── add.php             # Add student
│   │   ├── edit.php            # Edit student
│   │   ├── view.php            # View details
│   │   ├── delete.php          # Delete student
│   │   └── promote.php         # Promote students
│   │
│   ├── fees/                   # Fee Management
│   │   ├── structure.php       # Fee structure
│   │   ├── collect.php         # Collect fee
│   │   ├── receipt.php         # View receipt
│   │   ├── receipts.php        # All receipts
│   │   ├── due.php             # Due fees
│   │   └── payment_link.php    # Generate payment link
│   │
│   ├── marks/                  # Marks Management
│   │   ├── entry.php           # Enter marks
│   │   ├── view.php            # View marks
│   │   └── marksheet.php       # Generate marksheet
│   │
│   ├── reports/                # Reports
│   │   ├── students.php        # Student reports
│   │   ├── fees.php            # Fee reports
│   │   ├── collection.php      # Collection reports
│   │   └── due.php             # Due reports
│   │
│   └── settings/               # Settings
│       ├── school.php          # School settings
│       ├── users.php           # User management
│       ├── classes.php         # Classes & sections
│       └── fee_heads.php       # Fee heads master
│
├── ajax/                        # AJAX handlers
│   ├── search_student.php
│   ├── get_fee_structure.php
│   └── send_sms.php
│
├── database/                    # Database files
│   └── school_management.sql   # Complete schema
│
├── vendor/                      # Composer dependencies
│
├── .htaccess                    # Apache configuration
├── index.php                    # Main entry point
├── setup.php                    # Installation wizard
└── DEVELOPMENT_ROADMAP.md       # This file
```

---

## Database Architecture

### Core Tables

#### 1. users
- User authentication and role management
- Roles: super_admin, admin, accountant, clerk, teacher
- Firebase UID mapping

#### 2. user_permissions
- Granular permissions for each module
- Tick/Untick access control (view, add, edit, delete)

#### 3. students
- Complete student information
- Links to class, section
- Status: Active/Inactive
- Photo upload support

#### 4. classes & sections
- Class master (Nursery to 12th)
- Section master (A, B, C, D)

#### 5. fee_heads
- Master fee heads (Admission, Tuition, Hostel, etc.)
- Types: One-time, Monthly, Optional

#### 6. fee_structure
- Individual student fee assignment
- Effective date range
- Can be modified by admin

#### 7. fee_receipts
- Fee collection records
- Receipt number generation
- Payment modes: Cash, Bank, UPI, Cheque

#### 8. fee_receipt_details
- Line items for each receipt
- Month/Year tracking for monthly fees

#### 9. fee_ledger
- Running balance for each student
- Debit/Credit entries
- Month-wise tracking

#### 10. subjects & class_subjects
- Subject master
- Class-subject-teacher mapping

#### 11. student_marks
- Mark entry by subject
- Exam type tracking
- Entered by teacher

#### 12. student_promotions
- Year-end promotion tracking
- From class → To class history

#### 13. sms_logs
- SMS tracking
- Firebase integration logs
- Status: Pending, Sent, Failed

#### 14. firebase_sync_log
- Track all Firebase sync operations
- Error logging

#### 15. payment_links
- Generate shareable payment links
- Expiry tracking
- Usage status

#### 16. activity_log
- Audit trail for all actions
- User tracking with IP address

---

## Module Development Guide

### Phase 1: Core Setup (COMPLETED ✓)
- [x] Database schema
- [x] Configuration files
- [x] Authentication system
- [x] Dashboard
- [x] Header/Footer/Navigation

### Phase 2: Student Management (NEXT)

#### Module: students/add.php
**Purpose**: Add new student with photo upload

**Features**:
- Form with all student fields
- Photo upload with crop/resize
- Auto-generate admission number
- Assign initial fee structure
- Firebase sync

**Development Steps**:
1. Create HTML form with Bootstrap
2. Handle file upload (image processing)
3. Insert into `students` table
4. Redirect to fee structure assignment
5. Log activity
6. Sync to Firebase

**Code Structure**:
```php
// students/add.php
- Validate form input
- Process photo upload
- Generate admission number
- Insert student record
- Redirect to fee structure setup
```

#### Module: students/index.php
**Purpose**: List all students with search and filters

**Features**:
- DataTables integration
- Search by admission no, name
- Filter by class, section, status
- Actions: View, Edit, Delete
- Export to Excel

#### Module: students/edit.php
**Purpose**: Edit student details

**Features**:
- Pre-fill form with existing data
- Update photo (optional)
- Update all fields
- Activity logging

#### Module: students/view.php
**Purpose**: View complete student profile

**Features**:
- Display all details
- Fee payment history
- Marks history
- Generated documents (ID card, certificates)

#### Module: students/promote.php
**Purpose**: Bulk student promotion

**Features**:
- Select class
- Choose students to promote
- Promote to next class
- Update fee structure if needed
- Generate promotion certificate

### Phase 3: Fee Management

#### Module: fees/structure.php
**Purpose**: Assign/Edit fee structure per student

**Features**:
- Select student
- Display all fee heads
- Assign amounts per head
- Optional fees (tick/untick)
- Effective from date
- Save structure

#### Module: fees/collect.php
**Purpose**: Collect fees and generate receipt

**Features**:
- Search student by admission number
- Display assigned fee structure
- Select months (for monthly fees)
- Enter amount paid
- Auto-calculate balance
- Select payment mode
- Generate receipt
- Send SMS alert
- Print receipt

**Development Flow**:
```
1. Search student → Load fee structure
2. Select fee heads & months
3. Calculate total
4. Enter payment details
5. Generate receipt number
6. Insert into fee_receipts + fee_receipt_details
7. Update fee_ledger
8. Send SMS via Firebase
9. Display printable receipt
```

#### Module: fees/receipt.php
**Purpose**: View and reprint receipts

**Features**:
- Display receipt details
- Reprint option
- Cancel receipt (admin only)

#### Module: fees/due.php
**Purpose**: Due fees report

**Features**:
- List students with pending fees
- Month-wise grouping
- Class-wise filter
- Send SMS reminders (bulk)
- Export to Excel

### Phase 4: Marks Management

#### Module: marks/entry.php
**Purpose**: Enter marks subject-wise

**Features**:
- Teacher login shows assigned subjects
- Select class & subject
- Display student list
- Enter marks for each student
- Validate max marks
- Save marks

#### Module: marks/view.php
**Purpose**: View entered marks

**Features**:
- Filter by class, subject, exam type
- Edit marks
- Delete marks

#### Module: marks/marksheet.php
**Purpose**: Generate mark sheet

**Features**:
- Select student
- Select exam type
- Generate PDF mark sheet
- Include school logo & seal
- Calculate total, percentage, grade
- Download/Print

### Phase 5: Reports Module

#### Module: reports/students.php
**Purpose**: Student reports

**Features**:
- List by class, section
- Active/Inactive filter
- Export to PDF/Excel
- Include photos (optional)

#### Module: reports/fees.php
**Purpose**: Fee collection reports

**Features**:
- Date range filter
- Class-wise summary
- Payment mode summary
- Collector-wise summary
- Export to Excel/PDF

#### Module: reports/collection.php
**Purpose**: Daily/Monthly collection report

**Features**:
- Date range selection
- Daily collection summary
- Receipt-wise details
- Total calculation
- Export options

#### Module: reports/due.php
**Purpose**: Due fees report

**Features**:
- Month-wise due report
- Student-wise outstanding
- SMS reminder option
- Export to Excel

### Phase 6: Settings Module

#### Module: settings/school.php
**Purpose**: School settings management

**Features**:
- Edit school name
- Upload/Change logos (login, banner, main)
- Academic year setup
- Admission & receipt prefix
- Currency settings
- Timezone

#### Module: settings/users.php
**Purpose**: User management

**Features**:
- Add new users
- Assign roles
- Set permissions (tick/untick per module)
- Activate/Deactivate users
- Reset password

**Permissions Table**:
```
User: John Doe (Accountant)
Module         | View | Add | Edit | Delete
Students       | ✓    | ✗   | ✓    | ✗
Fees           | ✓    | ✓   | ✓    | ✗
Reports        | ✓    | ✗   | ✗    | ✗
Settings       | ✗    | ✗   | ✗    | ✗
```

#### Module: settings/classes.php
**Purpose**: Manage classes & sections

**Features**:
- Add/Edit/Delete classes
- Add/Edit/Delete sections
- Display order management

#### Module: settings/fee_heads.php
**Purpose**: Manage fee heads

**Features**:
- Add/Edit/Delete fee heads
- Set type (One-time, Monthly, Optional)
- Display order
- Activate/Deactivate

### Phase 7: Additional Features

#### Student ID Card Generation
```php
// students/id_card.php
- Load student details with photo
- Generate ID card using TCPDF
- Include QR code with admission number
- Print front and back
- Download PDF
```

#### Admit Card Generation
```php
// students/admit_card.php
- Select exam type
- Generate admit card with photo
- Include exam schedule
- Download/Print
```

#### Certificates
```php
// students/certificate.php
- Promotion certificate
- Character certificate
- Bonafide certificate
- Custom templates
```

---

## Firebase Integration

### Setup Firebase Cloud Functions

#### 1. SMS Alerts Function
```javascript
// Firebase Cloud Function: sendSMS
const functions = require('firebase-functions');
const admin = require('firebase-admin');

exports.sendSMS = functions.https.onRequest(async (req, res) => {
    const { to, message } = req.body;

    // Integrate with SMS provider (Twilio, MSG91, etc.)
    // Send SMS
    // Log to Realtime Database

    return res.json({ success: true });
});
```

#### 2. Realtime Database Structure
```json
{
  "students": {
    "student_id": {
      "admission_no": "STU000001",
      "student_name": "John Doe",
      "class": "10th A",
      "status": "Active",
      "synced_at": "2024-01-01T10:00:00Z"
    }
  },
  "fee_receipts": {
    "receipt_id": {
      "receipt_no": "REC000001",
      "student_id": 1,
      "amount": 5000,
      "payment_date": "2024-01-01",
      "synced_at": "2024-01-01T10:00:00Z"
    }
  }
}
```

### PHP Firebase Integration

#### Auto-sync on Database Operations
```php
// After INSERT student
$studentData = [
    'admission_no' => $admissionNo,
    'student_name' => $studentName,
    'class' => $className,
    'synced_at' => date('c')
];

syncToFirebase('students/' . $studentId, $studentData);
```

#### Send SMS Alert
```php
// After fee receipt generation
$message = "Dear Parent, Fee of Rs. {$amount} received for {$studentName}. Receipt No: {$receiptNo}. Thank you!";

sendSMSViaFirebase($contactNo, $message);

// Log SMS
logSMS($studentId, $contactNo, $message, 'Receipt');
```

---

## Testing & Deployment

### Testing Checklist

#### 1. Authentication Testing
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Logout functionality
- [ ] Password reset
- [ ] Session timeout
- [ ] Role-based access

#### 2. Student Management Testing
- [ ] Add student with photo
- [ ] Edit student details
- [ ] Delete student
- [ ] View student profile
- [ ] Search students
- [ ] Filter by class/section
- [ ] Promote students

#### 3. Fee Management Testing
- [ ] Assign fee structure
- [ ] Collect fee (full payment)
- [ ] Collect fee (partial payment)
- [ ] Generate receipt
- [ ] Reprint receipt
- [ ] Cancel receipt
- [ ] Due fees report
- [ ] SMS alert sent

#### 4. Marks Management Testing
- [ ] Enter marks
- [ ] Edit marks
- [ ] Generate mark sheet
- [ ] Validate max marks

#### 5. Reports Testing
- [ ] Student report (PDF/Excel)
- [ ] Fee report (PDF/Excel)
- [ ] Collection report
- [ ] Due report
- [ ] Custom date range

#### 6. Settings Testing
- [ ] Update school settings
- [ ] Add user with permissions
- [ ] Edit permissions
- [ ] Add class/section
- [ ] Add fee head

#### 7. Firebase Testing
- [ ] Student sync to Firebase
- [ ] Receipt sync to Firebase
- [ ] SMS sent via Firebase
- [ ] Backup restore from Firebase

### Production Deployment

#### 1. Server Requirements
- PHP 8.0+
- MySQL 5.7+
- Apache/Nginx with mod_rewrite
- SSL Certificate (recommended)
- Min 2GB RAM
- Min 10GB Storage

#### 2. Pre-Deployment Steps
```bash
# 1. Update configuration for production
# config/config.php
error_reporting(0);
ini_set('display_errors', 0);

# 2. Update database credentials
# config/database.php

# 3. Set secure encryption key
define('ENCRYPTION_KEY', 'generate-strong-random-key');

# 4. Enable Firebase production config

# 5. Set proper file permissions
chmod 755 -R /var/www/html/account3
chmod 777 -R /var/www/html/account3/assets/uploads

# 6. Enable HTTPS
# Update .htaccess for HTTPS redirect
```

#### 3. Backup Strategy
```bash
# Daily MySQL backup
mysqldump -u root -p school_management > backup_$(date +%Y%m%d).sql

# Weekly Firebase backup (automatic via Firebase)

# Monthly full backup (files + database)
tar -czf backup_$(date +%Y%m%d).tar.gz /var/www/html/account3
```

---

## Maintenance & Updates

### Regular Maintenance Tasks

#### Daily
- Monitor error logs
- Check SMS delivery status
- Verify Firebase sync

#### Weekly
- Database backup
- Review activity logs
- Check disk space

#### Monthly
- Update student promotions (if applicable)
- Generate monthly reports
- Clear old activity logs (>6 months)

### Update Procedures

#### Adding New Fee Head
1. Go to Settings → Fee Heads
2. Click "Add New"
3. Enter fee head name
4. Select type (One-time/Monthly/Optional)
5. Set display order
6. Save

#### Adding New User
1. Go to Settings → User Management
2. Click "Add User"
3. Enter details (username, email, role)
4. Set permissions (tick/untick modules)
5. Save
6. User receives auto-generated password via email

#### Changing Academic Year
1. Go to Settings → School Settings
2. Update "Current Academic Year"
3. Promote all students (if year-end)
4. Update fee structures if needed

---

## Troubleshooting

### Common Issues

#### Issue: "Database connection failed"
**Solution**:
- Check MySQL is running
- Verify credentials in `config/database.php`
- Check database exists

#### Issue: "Photo upload not working"
**Solution**:
- Check folder permissions (777 for uploads/)
- Verify max upload size in php.ini
- Check file type is image

#### Issue: "Firebase sync failed"
**Solution**:
- Verify Firebase credentials
- Check internet connection
- Review Firebase console for errors

#### Issue: "Receipt not printing"
**Solution**:
- Check browser print settings
- Disable popup blocker
- Use Chrome/Firefox

---

## Future Enhancements

### Planned Features
1. Parent portal (view fees, marks online)
2. Online fee payment gateway integration
3. Attendance management
4. Timetable management
5. Library management
6. Transport management
7. Mobile app (Android/iOS)
8. WhatsApp notifications
9. Biometric integration
10. Hostel management

---

## Support & Documentation

### Getting Help
- Review this roadmap document
- Check database schema in `database/school_management.sql`
- Review code comments in each module
- Check Firebase documentation for cloud features

### Best Practices
1. Always backup before major changes
2. Test in staging before production
3. Use meaningful variable names
4. Comment complex logic
5. Log all important actions
6. Validate all user inputs
7. Use prepared statements for SQL
8. Keep Firebase credentials secure

---

## Conclusion

This roadmap provides a complete guide to developing and maintaining the School Management System. Follow the phases sequentially, test thoroughly, and deploy confidently.

**Next Steps**:
1. Complete setup using `setup.php`
2. Configure Firebase integration
3. Start with Student Management module
4. Test each module before proceeding
5. Deploy to production after full testing

Good luck with your development!

---

*Document Version: 1.0.0*
*Last Updated: <?php echo date('Y-m-d'); ?>*
*School Management System - Complete Solution*
