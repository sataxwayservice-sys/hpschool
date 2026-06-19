# Installation Guide
## School Students and Fees Management System

---

## Quick Installation Checklist

Follow these steps to get your system up and running in **15 minutes**.

---

## Prerequisites

- [ ] XAMPP/WAMP installed (PHP 8.0+, MySQL 5.7+)
- [ ] Composer installed ([Download](https://getcomposer.org/download/))
- [ ] Firebase account created ([Console](https://console.firebase.google.com/))
- [ ] Web browser (Chrome/Firefox recommended)

---

## Installation Steps

### Step 1: Project Setup (2 minutes)

1. **Copy project files** to XAMPP htdocs:
   ```
   c:\xampp\htdocs\account3\
   ```

2. **Verify folder structure**:
   ```
   account3/
   ├── config/
   ├── includes/
   ├── modules/
   ├── assets/
   ├── database/
   └── setup.php
   ```

---

### Step 2: Install Dependencies (3 minutes)

1. **Open Command Prompt** in project directory:
   ```cmd
   cd c:\xampp\htdocs\account3
   ```

2. **Install Composer packages**:
   ```cmd
   composer install
   ```

   **OR** install individually:
   ```cmd
   composer require kreait/firebase-php
   composer require phpoffice/phpspreadsheet
   composer require tecnickcom/tcpdf
   ```

3. **Verify vendor folder** is created

---

### Step 3: Database Configuration (2 minutes)

1. **Start XAMPP**:
   - Start Apache
   - Start MySQL

2. **Open** `config/database.php`

3. **Update credentials** (if needed):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Your MySQL password
   define('DB_NAME', 'school_management');
   ```

4. **Save the file**

---

### Step 4: Firebase Setup (5 minutes)

#### A. Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click **"Add project"**
3. Enter project name: `school-management`
4. Click **Continue** → **Continue** → **Create project**

#### B. Enable Authentication

1. In Firebase Console, click **Authentication**
2. Click **Get started**
3. Enable **Email/Password** provider
4. Click **Save**

#### C. Enable Realtime Database

1. Click **Realtime Database** in sidebar
2. Click **Create Database**
3. Choose location (e.g., `asia-southeast1`)
4. Start in **Test mode** (for now)
5. Click **Enable**

#### D. Get Service Account

1. Click **⚙️ Settings** → **Project settings**
2. Click **Service accounts** tab
3. Click **Generate new private key**
4. Download JSON file
5. Rename to `firebase-service-account.json`
6. Save in: `c:\xampp\htdocs\account3\config\`

#### E. Get Firebase Config

1. In **Project settings** → **General**
2. Scroll to **Your apps**
3. Click **Web** icon (</>) to add web app
4. Register app with nickname: `School Management`
5. Copy the config values:
   ```javascript
   const firebaseConfig = {
     apiKey: "AIza...",
     authDomain: "school-management.firebaseapp.com",
     databaseURL: "https://school-management.firebaseio.com",
     projectId: "school-management",
     storageBucket: "school-management.appspot.com",
     messagingSenderId: "123456789",
     appId: "1:123456789:web:abc123"
   };
   ```

6. **Open** `config/firebase_config.php`

7. **Update values**:
   ```php
   define('FIREBASE_API_KEY', 'AIza...');
   define('FIREBASE_AUTH_DOMAIN', 'school-management.firebaseapp.com');
   define('FIREBASE_DATABASE_URL', 'https://school-management.firebaseio.com');
   define('FIREBASE_PROJECT_ID', 'school-management');
   define('FIREBASE_STORAGE_BUCKET', 'school-management.appspot.com');
   define('FIREBASE_MESSAGING_SENDER_ID', '123456789');
   define('FIREBASE_APP_ID', '1:123456789:web:abc123');
   ```

8. **Save the file**

---

### Step 5: Run Setup Wizard (3 minutes)

1. **Open browser**: `http://localhost/account3/setup.php`

2. **Step 1: Import Database**
   - Click **"Import Database Now"**
   - Wait for success message

3. **Step 2: Create Super Admin**
   - Fill in the form:
     - Full Name: `Admin User`
     - Username: `admin`
     - Email: `admin@school.com`
     - Mobile: `9876543210`
     - Password: `admin123` (min 6 characters)
     - Confirm Password: `admin123`
   - Click **"Create Super Admin"**

4. **Setup Complete!**
   - Click **"Go to Login"**

---

### Step 6: First Login

1. **URL**: `http://localhost/account3/`

2. **Login Credentials**:
   - Username: `admin` (or what you created)
   - Password: `admin123` (or what you created)

3. **Click Login**

4. **Welcome to Dashboard!**

---

## Post-Installation Configuration

### 1. School Settings

1. Go to **Settings** → **School Settings**
2. Update:
   - School Name
   - School Address
   - Contact Details
   - Upload School Logo
3. Click **Save**

### 2. Add Classes & Sections

Classes are pre-loaded (Nursery to 12th).

**To add more sections**:
1. Go to **Settings** → **Classes & Sections**
2. Click **Add Section**
3. Enter section name (e.g., `E`)
4. Click **Save**

### 3. Setup Fee Heads

Default fee heads are pre-loaded:
- Admission Fee
- Tuition Fee
- Hostel Fee
- Transport Fee
- Exam Fee
- Library Fee
- Sports Fee

**To add custom fee head**:
1. Go to **Settings** → **Fee Heads**
2. Click **Add Fee Head**
3. Enter details
4. Select type (One-time/Monthly/Optional)
5. Click **Save**

### 4. Add Staff Users

1. Go to **Settings** → **User Management**
2. Click **Add User**
3. Fill in details:
   - Full Name
   - Username
   - Email
   - Role (Admin/Accountant/Clerk/Teacher)
   - Mobile
4. Set permissions:
   - Tick/untick modules
   - Set View/Add/Edit/Delete rights
5. Click **Save**
6. User receives auto-generated password

---

## Verify Installation

### Test Checklist

- [ ] Can login to admin dashboard
- [ ] Dashboard shows statistics
- [ ] Navigation menu works
- [ ] Can access Settings
- [ ] Can add a test student
- [ ] Photo upload works
- [ ] Can view student list
- [ ] Firebase config is correct

---

## Troubleshooting

### Issue: "Database connection failed"

**Solution**:
1. Check MySQL is running in XAMPP Control Panel
2. Verify credentials in `config/database.php`
3. Check database name is `school_management`

### Issue: "Composer not found"

**Solution**:
1. Download Composer: [getcomposer.org](https://getcomposer.org/download/)
2. Install globally
3. Restart Command Prompt
4. Run `composer --version` to verify

### Issue: "Firebase sync not working"

**Solution**:
1. Verify `firebase-service-account.json` exists in `config/`
2. Check Firebase credentials in `config/firebase_config.php`
3. Ensure Firebase services are enabled in console
4. Check error logs in browser console

### Issue: "Photo upload not working"

**Solution**:
1. Check folder permissions:
   ```cmd
   icacls "c:\xampp\htdocs\account3\assets\uploads" /grant Everyone:F
   ```
2. Verify `upload_max_filesize` in `php.ini` (should be 10M or more)
3. Restart Apache

### Issue: "Setup page shows errors"

**Solution**:
1. Check `database/school_management.sql` file exists
2. Verify MySQL user has CREATE DATABASE privilege
3. Check PHP error logs in `c:\xampp\php\logs\php_error_log`

---

## Security Recommendations

### Before Going Live

1. **Change default password** for admin user

2. **Update encryption key** in `config/config.php`:
   ```php
   define('ENCRYPTION_KEY', 'your-unique-32-character-key-here');
   ```

3. **Disable error display** in `config/config.php`:
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

4. **Update Firebase rules** to production mode:
   ```json
   {
     "rules": {
       ".read": "auth != null",
       ".write": "auth != null"
     }
   }
   ```

5. **Set secure file permissions**:
   - Files: 644
   - Folders: 755
   - Uploads: 777 (with proper validation)

6. **Enable HTTPS** (SSL certificate)

7. **Regular backups**:
   - Daily database backup
   - Weekly Firebase backup
   - Monthly full backup

---

## Next Steps

### Immediate Tasks

1. ✅ Complete installation
2. ✅ Login to dashboard
3. ✅ Configure school settings
4. ✅ Add classes & sections (if needed)
5. ✅ Setup fee heads
6. ✅ Create staff users
7. ✅ Add first student

### Start Using

1. **Add Students**:
   - Go to Students → Add Student
   - Fill form with student details
   - Upload photo
   - Assign fee structure

2. **Collect Fees**:
   - Go to Fees → Collect Fee
   - Search student by admission number
   - Select fee heads and months
   - Enter payment details
   - Generate receipt

3. **Generate Reports**:
   - Go to Reports
   - Select report type
   - Apply filters
   - Export to PDF/Excel

---

## Need Help?

### Documentation

- **README.md** - Overview and features
- **DEVELOPMENT_ROADMAP.md** - Complete development guide
- **database/school_management.sql** - Database schema

### Common Issues

Refer to troubleshooting section above or check:
- Browser console for JavaScript errors
- PHP error logs in XAMPP logs folder
- MySQL error logs
- Firebase console for sync errors

---

## Support Contacts

For technical support:
- Review documentation files
- Check code comments
- Review Firebase documentation
- Check MySQL logs

---

## Congratulations! 🎉

Your School Management System is now installed and ready to use!

**Login now**: [http://localhost/account3/](http://localhost/account3/)

---

**Installation Time**: ~15 minutes
**System Status**: ✅ Ready to use
**Next**: Add your first student and start managing!
