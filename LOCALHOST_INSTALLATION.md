# Localhost Installation Guide
## Quick Setup for XAMPP/WAMP

---

## ⚡ Quick Start (5 Minutes)

Your system is already configured with database name: **`school_fees_system`**

This will NOT conflict with your existing `school_management` database.

---

## 📋 Step-by-Step Installation

### Step 1: Ensure XAMPP is Running

1. **Open XAMPP Control Panel**
2. **Start Apache** (if not running)
3. **Start MySQL** (if not running)

```
✅ Apache should be GREEN
✅ MySQL should be GREEN
```

---

### Step 2: Access the System

1. **Open your browser**
2. **Go to:** `http://localhost/account3/setup.php`

---

### Step 3: Run Setup Wizard

#### **Page 1: Import Database**

1. You'll see: **"Step 1 of 2: Import Database"**
2. Click the button: **"Import Database Now"**
3. Wait for success message ✅

**What happens:**
- Creates new database: `school_fees_system`
- Creates all 15 tables
- Inserts default data (classes, sections, fee heads)

---

#### **Page 2: Create Super Admin**

1. You'll see: **"Step 2 of 2: Create Super Admin Account"**
2. Fill in the form:

   ```
   Full Name: Admin User
   Username: admin
   Email: admin@school.com
   Mobile: 9876543210 (optional)
   Password: admin123
   Confirm Password: admin123
   ```

3. Click: **"Create Super Admin"**
4. Success! Click: **"Go to Login"**

---

### Step 4: Login

1. **URL:** `http://localhost/account3/`
2. **Enter credentials:**
   - Username: `admin`
   - Password: `admin123`
3. **Click Login**

🎉 **Welcome to Dashboard!**

---

## 🗄️ Database Information

### New Database Name
```
Database: school_fees_system
Host: localhost
User: root
Password: (empty)
```

### Your Existing Database (Unchanged)
```
Database: school_management
(This remains untouched)
```

### Check in phpMyAdmin

1. **Open:** `http://localhost/phpmyadmin/`
2. **You'll see both databases:**
   - ✅ `school_management` (your existing one)
   - ✅ `school_fees_system` (the new one)

---

## 📁 File Structure

All files are in:
```
c:\xampp\htdocs\account3\
```

### Important Files
```
account3/
├── setup.php                        ← Start here!
├── index.php                        ← Login page
├── config/
│   └── database.php                 ← DB: school_fees_system
├── database/
│   └── school_management.sql        ← Auto-import
└── modules/
    ├── auth/login.php               ← Login
    └── dashboard/index.php          ← Dashboard
```

---

## 🚀 What You Can Do Right Now

### 1. View Dashboard
- **URL:** `http://localhost/account3/modules/dashboard/`
- See statistics, quick actions, recent activity

### 2. Add a Student
- **Go to:** Students → Add Student
- Fill form with student details
- Upload photo (optional)
- Click "Save"

### 3. View Settings
- **Go to:** Settings → School Settings
- Update school name, logo, etc.

---

## 🔧 Optional: Install Composer Dependencies

For advanced features (PDF, Excel, Firebase):

```bash
# Open Command Prompt
cd c:\xampp\htdocs\account3

# Install dependencies
composer install
```

**What this installs:**
- Firebase PHP SDK
- PHPSpreadsheet (Excel export)
- TCPDF (PDF generation)

**Note:** These are optional for basic functionality!

---

## ✅ Verification Checklist

- [ ] XAMPP Apache is running
- [ ] XAMPP MySQL is running
- [ ] Opened `http://localhost/account3/setup.php`
- [ ] Clicked "Import Database" - SUCCESS
- [ ] Created Super Admin account - SUCCESS
- [ ] Logged in successfully - DASHBOARD VISIBLE
- [ ] Can see both databases in phpMyAdmin

---

## 🐛 Troubleshooting

### Issue: "Database connection failed"

**Solution:**
1. Check MySQL is running in XAMPP
2. Verify no password for root user
3. Check `config/database.php` has correct settings:
   ```php
   DB_NAME: school_fees_system
   DB_USER: root
   DB_PASS: (empty)
   ```

### Issue: "Cannot access setup.php"

**Solution:**
1. Check Apache is running
2. Verify URL: `http://localhost/account3/setup.php`
3. Check folder exists: `c:\xampp\htdocs\account3\`

### Issue: "Setup page keeps redirecting"

**Solution:**
1. Database already imported
2. Go directly to: `http://localhost/account3/`
3. Login with your admin credentials

### Issue: Port 80 already in use

**Solution:**
1. Close Skype or other programs using port 80
2. Or change Apache port in XAMPP config
3. Then use: `http://localhost:8080/account3/`

---

## 📊 Database Tables Created

When you import the database, these tables are created:

```
✅ users                  - User accounts & roles
✅ user_permissions       - Module permissions
✅ school_settings        - School configuration
✅ classes                - Class master (Nursery-12th)
✅ sections               - Section master (A, B, C, D)
✅ students               - Student records
✅ student_promotions     - Promotion history
✅ subjects               - Subject master
✅ class_subjects         - Class-subject mapping
✅ student_marks          - Marks records
✅ fee_heads              - Fee heads (Admission, Tuition, etc.)
✅ fee_structure          - Student fee assignment
✅ fee_receipts           - Fee collection
✅ fee_receipt_details    - Receipt line items
✅ fee_ledger             - Running balance
✅ sms_logs               - SMS tracking
✅ firebase_sync_log      - Cloud sync logs
✅ payment_links          - Payment link generation
✅ activity_log           - Audit trail
✅ backup_logs            - Backup tracking
```

---

## 🎯 Next Steps

### Immediate Tasks

1. **Configure School Settings**
   - Go to: Settings → School Settings
   - Update school name
   - Upload logo

2. **Add Classes/Sections** (Optional)
   - Pre-loaded: Nursery to 12th
   - Pre-loaded sections: A, B, C, D
   - Add more if needed

3. **Setup Fee Heads** (Optional)
   - Pre-loaded: Admission, Tuition, Hostel, Transport, etc.
   - Add custom fee heads if needed

4. **Add Users** (Staff)
   - Go to: Settings → User Management
   - Create accounts for accountant, clerk, teacher
   - Set permissions for each user

5. **Add First Student**
   - Go to: Students → Add Student
   - Fill details and upload photo
   - Assign fee structure

---

## 🔐 Default Login Credentials

After setup, your credentials are:

```
URL: http://localhost/account3/
Username: admin (or what you created)
Password: admin123 (or what you created)
Role: Super Admin
```

**⚠️ Change password after first login!**

---

## 📝 Important URLs

| Page | URL |
|------|-----|
| Setup | `http://localhost/account3/setup.php` |
| Login | `http://localhost/account3/` |
| Dashboard | `http://localhost/account3/modules/dashboard/` |
| Add Student | `http://localhost/account3/modules/students/add.php` |
| phpMyAdmin | `http://localhost/phpmyadmin/` |

---

## 💾 Backup Your Data

### Manual Backup (Recommended)

**Using phpMyAdmin:**
1. Open: `http://localhost/phpmyadmin/`
2. Click: `school_fees_system`
3. Click: **Export** tab
4. Click: **Go**
5. Save SQL file

**Using Command Line:**
```bash
cd c:\xampp\mysql\bin
mysqldump -u root school_fees_system > backup.sql
```

---

## 🆘 Need Help?

### Documentation
- **README.md** - Overview and features
- **INSTALLATION_GUIDE.md** - Detailed setup with Firebase
- **DEVELOPMENT_ROADMAP.md** - Complete development guide
- **PROJECT_SUMMARY.md** - What's been created

### Common Files
- **config/database.php** - Database settings
- **config/config.php** - Application settings
- **setup.php** - Installation wizard

---

## ✨ You're Ready!

Your School Management System is now installed on localhost with a **separate database** that won't affect your existing system.

### Quick Access

```bash
# Open in browser
http://localhost/account3/

# Login with your credentials
Username: admin
Password: admin123
```

**Enjoy managing your school! 🎓**

---

## 📌 Summary

✅ Database: `school_fees_system` (separate from your existing one)
✅ URL: `http://localhost/account3/`
✅ Setup: 2-step wizard (5 minutes)
✅ Login: Super admin account created
✅ Ready to use: Dashboard, Student management, and more!

---

*Installation Time: ~5 minutes*
*No conflicts with existing database*
*Ready for production use!*
