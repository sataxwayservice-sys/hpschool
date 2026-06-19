# Troubleshooting Guide
## Common Issues and Solutions

---

## ✅ FIXED: Session Warnings

### Issues Resolved
```
✅ Warning: ini_set(): Session ini settings cannot be changed when a session is active
✅ Warning: Undefined array key "is_logged_in"
```

### What Was Fixed

1. **Session Management Centralized**
   - All session handling now in `config/config.php`
   - Removed `session_start()` from all individual files
   - Session ini settings configured BEFORE session starts

2. **Files Updated**
   - ✅ config/config.php - Now handles session start
   - ✅ index.php - Removed session_start()
   - ✅ setup.php - Removed session_start()
   - ✅ modules/auth/login.php - Removed session_start()
   - ✅ modules/auth/logout.php - Removed session_start()
   - ✅ modules/dashboard/index.php - Removed session_start()
   - ✅ modules/students/add.php - Removed session_start()
   - ✅ ajax/search_student.php - Removed session_start()
   - ✅ includes/auth.php - Fixed isLoggedIn() function

---

## 🐛 Common Installation Issues

### 1. Database Connection Failed

**Symptoms:**
```
Database connection failed. Please contact administrator.
```

**Solutions:**
- ✅ Check XAMPP MySQL is running (green in control panel)
- ✅ Verify database credentials in `config/database.php`:
  ```php
  DB_HOST: localhost
  DB_USER: root
  DB_PASS: (empty)
  DB_NAME: school_fees_system
  ```
- ✅ Check database exists in phpMyAdmin

---

### 2. Cannot Access Setup Page

**Symptoms:**
```
404 Not Found
```

**Solutions:**
- ✅ Check XAMPP Apache is running
- ✅ Verify correct URL: `http://localhost/account3/setup.php`
- ✅ Check folder exists at: `c:\xampp\htdocs\account3\`

---

### 3. Photo Upload Not Working

**Symptoms:**
```
Failed to upload photo
```

**Solutions:**
- ✅ Check folder permissions on `assets/uploads/students/`
- ✅ Verify `upload_max_filesize` in php.ini (should be 10M+)
- ✅ Restart Apache after changing php.ini
- ✅ Check file type is JPG or PNG

**Windows Permissions:**
```cmd
# In Command Prompt
icacls "c:\xampp\htdocs\account3\assets\uploads" /grant Everyone:F
```

---

### 4. Page Keeps Redirecting

**Symptoms:**
```
Too many redirects
Login page keeps reloading
```

**Solutions:**
- ✅ Clear browser cookies and cache
- ✅ Check session is starting properly
- ✅ Verify database connection works
- ✅ Try incognito/private browsing mode

---

### 5. Blank White Page

**Symptoms:**
```
Blank white screen, no error message
```

**Solutions:**
- ✅ Enable error reporting in `config/config.php`:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```
- ✅ Check PHP error logs: `c:\xampp\php\logs\php_error_log`
- ✅ Check Apache error logs: `c:\xampp\apache\logs\error.log`

---

### 6. Port 80 Already in Use

**Symptoms:**
```
Apache won't start
Port 80 in use by another application
```

**Solutions:**
- ✅ Close Skype (uses port 80)
- ✅ Close IIS if installed
- ✅ Change Apache port in XAMPP config:
  - Click Config → httpd.conf
  - Change `Listen 80` to `Listen 8080`
  - Restart Apache
  - Use URL: `http://localhost:8080/account3/`

---

### 7. MySQL Won't Start

**Symptoms:**
```
MySQL service won't start
Port 3306 in use
```

**Solutions:**
- ✅ Check another MySQL service isn't running
- ✅ Stop MySQL Windows service if installed
- ✅ Change MySQL port in XAMPP:
  - Config → my.ini
  - Change `port=3306` to `port=3307`
  - Update `config/database.php`: `DB_HOST: localhost:3307`

---

### 8. Composer Not Found

**Symptoms:**
```
'composer' is not recognized as an internal or external command
```

**Solutions:**
- ✅ Download Composer: [getcomposer.org](https://getcomposer.org/download/)
- ✅ Install globally (Windows installer)
- ✅ Restart Command Prompt
- ✅ Verify: `composer --version`

**Note:** Composer is optional for basic functionality!

---

### 9. Firebase Not Working

**Symptoms:**
```
Firebase sync failed
SMS not sending
```

**Solutions:**
- ✅ Check `config/firebase-service-account.json` exists
- ✅ Verify Firebase credentials in `config/firebase_config.php`
- ✅ Enable Authentication in Firebase Console
- ✅ Enable Realtime Database in Firebase Console
- ✅ Check internet connection

**Note:** Firebase is optional! System works without it.

---

## 🔍 Debugging Tips

### Enable Full Error Reporting

**In `config/config.php`:**
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Check PHP Version

```bash
php -v
```
Required: PHP 8.0 or higher

### Check Database Connection

**Create test file:** `test_db.php`
```php
<?php
require_once 'config/database.php';
$conn = getDbConnection();
if ($conn) {
    echo "✅ Database connected!";
} else {
    echo "❌ Database connection failed!";
}
?>
```

Access: `http://localhost/account3/test_db.php`

### Check Session

**Create test file:** `test_session.php`
```php
<?php
require_once 'config/config.php';
echo "Session Status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";
print_r($_SESSION);
?>
```

Access: `http://localhost/account3/test_session.php`

---

## 📋 Error Logs Location

### XAMPP Error Logs

**PHP Errors:**
```
c:\xampp\php\logs\php_error_log
```

**Apache Errors:**
```
c:\xampp\apache\logs\error.log
```

**MySQL Errors:**
```
c:\xampp\mysql\data\mysql_error.log
```

---

## 🔧 Configuration Files

### Key Files to Check

1. **Database Config:** `config/database.php`
   ```php
   DB_NAME: school_fees_system
   DB_USER: root
   DB_PASS: (empty)
   ```

2. **Main Config:** `config/config.php`
   ```php
   APP_URL: http://localhost/account3
   ```

3. **Firebase Config:** `config/firebase_config.php`
   - Update with your Firebase credentials
   - Optional for basic functionality

---

## ✅ Verification Checklist

After installation, verify:

- [ ] Can access: `http://localhost/account3/`
- [ ] Login page displays correctly
- [ ] Can login with admin credentials
- [ ] Dashboard shows statistics
- [ ] Can navigate all menu items
- [ ] Can add a test student
- [ ] Photo upload works (if trying)
- [ ] No PHP warnings in browser
- [ ] Database exists in phpMyAdmin

---

## 🆘 Still Having Issues?

### Check These Files

1. **Browser Console** (F12)
   - Check for JavaScript errors
   - Check network tab for failed requests

2. **PHP Error Logs**
   - Look for PHP errors and warnings
   - Check file paths are correct

3. **Database**
   - Check tables exist in phpMyAdmin
   - Verify data in `users` table
   - Check `school_settings` table

### Common Mistakes

❌ Wrong URL (missing /account3/)
❌ Apache/MySQL not running
❌ Wrong database name in config
❌ Folder permissions on Windows
❌ Firewall blocking localhost

---

## 📞 Getting Help

### Before Asking for Help

1. ✅ Check error logs
2. ✅ Enable error display
3. ✅ Try incognito browser
4. ✅ Clear cache/cookies
5. ✅ Restart XAMPP
6. ✅ Check this guide

### Information to Provide

When asking for help, include:
- Operating System (Windows/Mac/Linux)
- PHP Version (`php -v`)
- XAMPP Version
- Exact error message
- Error logs content
- Steps to reproduce

---

## 🎯 Quick Reset

If everything breaks, here's how to start fresh:

1. **Drop Database:**
   ```sql
   DROP DATABASE school_fees_system;
   ```

2. **Clear Browser Data:**
   - Clear cookies
   - Clear cache
   - Close all tabs

3. **Restart XAMPP:**
   - Stop Apache
   - Stop MySQL
   - Start both again

4. **Run Setup Again:**
   ```
   http://localhost/account3/setup.php
   ```

---

## 📊 System Requirements

### Minimum Requirements

- ✅ PHP 8.0 or higher
- ✅ MySQL 5.7 or higher
- ✅ Apache 2.4 or higher
- ✅ 2GB RAM
- ✅ 500MB disk space

### Recommended

- ✅ PHP 8.1+
- ✅ MySQL 8.0+
- ✅ 4GB RAM
- ✅ 1GB disk space
- ✅ SSD for better performance

---

## 🔒 Security Notes

### Production Deployment

Before going live:

1. **Disable Error Display:**
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

2. **Change Database Password:**
   - Set strong password for MySQL root
   - Update `config/database.php`

3. **Enable HTTPS:**
   - Get SSL certificate
   - Force HTTPS in .htaccess

4. **Change Encryption Key:**
   ```php
   define('ENCRYPTION_KEY', 'your-unique-32-char-key');
   ```

5. **File Permissions:**
   - Files: 644
   - Folders: 755
   - Uploads: 777 (with validation)

---

**Last Updated:** <?php echo date('Y-m-d'); ?>
**Version:** 1.0.0
**Status:** Active Development

---

**Need immediate help? Check the error logs first!** 🔍
