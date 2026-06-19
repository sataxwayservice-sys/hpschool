# Production Deployment Guide
## School Management System

This guide will help you deploy your School Management System to production environments, specifically InfinityFree hosting.

---

## Table of Contents
1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [InfinityFree Hosting Setup](#infinityfree-hosting-setup)
3. [Database Configuration](#database-configuration)
4. [File Upload](#file-upload)
5. [Post-Deployment Steps](#post-deployment-steps)
6. [Troubleshooting](#troubleshooting)
7. [Security Recommendations](#security-recommendations)

---

## Pre-Deployment Checklist

Before deploying to production, ensure you have:

- ✅ Tested all features on localhost
- ✅ Database backup ready (SQL export)
- ✅ All files compressed in a ZIP archive
- ✅ InfinityFree account credentials
- ✅ Database credentials from InfinityFree control panel
- ✅ Changed default encryption key in config.php

---

## InfinityFree Hosting Setup

### Step 1: Get InfinityFree Database Credentials

1. Log in to your **InfinityFree Control Panel**
2. Navigate to **MySQL Databases**
3. Note down these important details:
   ```
   Database Host: sqlXXX.infinityfree.com (e.g., sql301.infinityfree.com)
   Database Name: if0_XXXXXXXX_school (or similar)
   Database User: if0_XXXXXXXX
   Database Password: [Your password]
   ```

### Step 2: Create Database

1. In InfinityFree Control Panel → **MySQL Databases**
2. Click **Create New Database**
3. Enter database name: `school` (will become `if0_XXXXXXXX_school`)
4. Note the full database name provided

### Step 3: Import Database

1. Go to **phpMyAdmin** in InfinityFree control panel
2. Select your newly created database
3. Click **Import** tab
4. Upload your `database.sql` file
5. Click **Go** to import
6. Wait for success message

---

## Database Configuration

### Option 1: Auto-Detection (Recommended)

The system automatically detects InfinityFree hosting. Just update the credentials:

**Edit: `config/database.php`**

Find this section (around line 16-19):

```php
// PRODUCTION - Use environment variables or define below
define('DB_HOST', getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com'); // Change sqlXXX to your host
define('DB_USER', getenv('DB_USER') ?: 'if0_XXXXXXXX');            // Your InfinityFree username
define('DB_PASS', getenv('DB_PASS') ?: '');                        // Your database password
define('DB_NAME', getenv('DB_NAME') ?: 'if0_XXXXXXXX_school');     // Your database name
```

**Replace with YOUR credentials:**

```php
// PRODUCTION - Use environment variables or define below
define('DB_HOST', getenv('DB_HOST') ?: 'sql301.infinityfree.com'); // Your actual host
define('DB_USER', getenv('DB_USER') ?: 'if0_40314654');            // Your actual username
define('DB_PASS', getenv('DB_PASS') ?: 'YourPassword123');         // Your actual password
define('DB_NAME', getenv('DB_NAME') ?: 'if0_40314654_school');     // Your actual database name
```

### Option 2: Using Environment Variables (More Secure)

Create a `.env` file or use InfinityFree's environment variable settings:

```env
DB_HOST=sql301.infinityfree.com
DB_USER=if0_40314654
DB_PASS=YourPassword123
DB_NAME=if0_40314654_school
```

The system will automatically read these via `getenv()`.

---

## File Upload

### Step 1: Prepare Files

1. **Create ZIP archive** of all project files
2. **Exclude** these files/folders:
   - `.git/` (if exists)
   - `node_modules/` (if exists)
   - `vendor/` (if exists, you'll reinstall on server)
   - Any local testing files

### Step 2: Upload to InfinityFree

1. Log in to InfinityFree Control Panel
2. Go to **File Manager** or use **FTP**
3. Navigate to `htdocs/` folder
4. Upload your ZIP file
5. Extract the ZIP file
6. Delete the ZIP file after extraction

### Step 3: Set Folder Permissions

Set these folders to **writable (777 or 755)**:
```
assets/uploads/
assets/uploads/students/
assets/uploads/logos/
```

**Using File Manager:**
- Right-click folder → Permissions → Set to 755

---

## Post-Deployment Steps

### Step 1: Update APP_URL

**Edit: `config/config.php`** (line 14)

Change from:
```php
define('APP_URL', 'http://localhost:8080/account3');
```

To your actual domain:
```php
define('APP_URL', 'https://yoursite.infinityfree.com');
// OR
define('APP_URL', 'https://www.yourschool.com');
```

### Step 2: Update Security Settings

**Edit: `config/config.php`**

1. **Change Encryption Key** (line 50):
```php
define('ENCRYPTION_KEY', 'your-random-secret-key-change-this-now-2024');
```

2. **Enable HTTPS for cookies** (line 28):
```php
ini_set('session.cookie_secure', 1); // Change 0 to 1
```

3. **Disable error display** (lines 21-22):
```php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Change 1 to 0 for production
```

### Step 3: Test the Deployment

1. Visit your site: `https://yoursite.infinityfree.com`
2. You should be redirected to login page
3. Test login with your admin credentials
4. Test key features:
   - Dashboard loads
   - Add a student
   - Upload a photo
   - Generate receipt
   - Check reports

---

## Troubleshooting

### Error: "Database connection failed"

**Solution:**
1. Double-check database credentials in `config/database.php`
2. Verify database was imported successfully in phpMyAdmin
3. Check database host format (should be `sqlXXX.infinityfree.com`)
4. Ensure database name includes the `if0_XXXXXXXX_` prefix

**Test Connection:**
Create a file `test_db.php` in root:
```php
<?php
require_once 'config/config.php';
echo "Testing database connection...<br>";
$conn = getDbConnection();
if ($conn) {
    echo "✅ Connected successfully!";
} else {
    echo "❌ Connection failed!";
}
?>
```

Delete this file after testing.

---

### Error: "CURRENCY_SYMBOL already defined"

**Solution:** This has been fixed in the latest version. If you still see it:
1. Make sure you have the latest `config/config.php`
2. Clear your browser cache
3. Check for duplicate includes

---

### Error: "Permission denied" when uploading

**Solution:**
1. Set folder permissions to 755 or 777:
   ```
   assets/uploads/
   assets/uploads/students/
   assets/uploads/logos/
   ```
2. Using File Manager, right-click → Permissions → Check all boxes

---

### Photos not displaying

**Solution:**
1. Check `APP_URL` is correct in `config/config.php`
2. Verify upload folder permissions
3. Check if photos exist in `assets/uploads/students/`
4. Clear browser cache

---

### Theme colors not applying

**Solution:**
1. Ensure `assets/css/theme.php` is uploaded
2. Check database has `theme_*` columns in `school_settings` table
3. Run `database/add_theme_colors.sql` if columns are missing
4. Clear browser cache (Ctrl + Shift + R)

---

## Security Recommendations

### 1. Change Default Credentials

After deployment, immediately change:
- Admin username and password
- Database password
- Encryption key

### 2. Delete Unnecessary Files

Remove these files from production:
```
test_login.php
reset_password.php
check_paths.php
check_dashboard.php
fix_user_roles.php
setup.php
setup_reports_permissions.php
TROUBLESHOOTING.md
*.md files (except README.md if needed)
```

### 3. Protect Sensitive Directories

Create `.htaccess` files in:

**`config/.htaccess`:**
```apache
Deny from all
```

**`database/.htaccess`:**
```apache
Deny from all
```

**`includes/.htaccess`:**
```apache
Deny from all
```

### 4. Enable HTTPS

InfinityFree provides free SSL certificates:
1. Go to Control Panel → SSL Certificates
2. Enable free SSL
3. Force HTTPS in `.htaccess` (root folder):

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 5. Regular Backups

**Database Backup:**
- Export via phpMyAdmin weekly
- Download to local machine

**File Backup:**
- Download `assets/uploads/` folder regularly
- Keep local copies of uploaded photos

### 6. Disable Directory Listing

Add to root `.htaccess`:
```apache
Options -Indexes
```

---

## Environment Variables (Advanced)

For better security, use environment variables instead of hardcoding credentials.

### Using .htaccess (InfinityFree)

Add to root `.htaccess`:
```apache
SetEnv DB_HOST "sql301.infinityfree.com"
SetEnv DB_USER "if0_40314654"
SetEnv DB_PASS "YourPassword123"
SetEnv DB_NAME "if0_40314654_school"
```

The system will automatically read these via `getenv()` in `config/database.php`.

---

## Quick Deployment Checklist

- [ ] Uploaded all files to `htdocs/`
- [ ] Set folder permissions (755 for uploads)
- [ ] Updated database credentials in `config/database.php`
- [ ] Updated `APP_URL` in `config/config.php`
- [ ] Changed `ENCRYPTION_KEY` in `config/config.php`
- [ ] Disabled error display in `config/config.php`
- [ ] Enabled HTTPS cookie security
- [ ] Imported database via phpMyAdmin
- [ ] Tested login and basic features
- [ ] Deleted testing/setup files
- [ ] Created `.htaccess` protection for config folders
- [ ] Enabled SSL certificate
- [ ] Set up regular backups

---

## Support

If you encounter issues:

1. Check the error logs in InfinityFree Control Panel
2. Review `TROUBLESHOOTING.md` for common issues
3. Test database connection using the test script above
4. Verify all credentials are correct

---

## Common InfinityFree Limitations

Be aware of these InfinityFree hosting limitations:

- **File Upload Size:** Max 10MB (configurable in our system)
- **Execution Time:** 30 seconds max
- **Database Size:** Unlimited on free plan
- **Bandwidth:** Unlimited
- **No Email Sending:** Use third-party SMTP (Gmail, SendGrid)
- **Ads:** Free plan includes ads (upgrade to remove)

---

## Next Steps After Deployment

1. **Configure School Settings**
   - Update school name, address, phone
   - Upload school logo
   - Set theme colors
   - Configure academic year

2. **Create User Accounts**
   - Add staff users
   - Assign roles and permissions

3. **Set Up Classes and Sections**
   - Add all classes
   - Create sections
   - Set fee structures

4. **Test All Features**
   - Student admission
   - Fee collection
   - Receipt generation
   - Reports
   - SMS (if configured)

---

**Congratulations!** Your School Management System is now live on production! 🎉

For any questions or issues, refer to the troubleshooting section or contact support.
