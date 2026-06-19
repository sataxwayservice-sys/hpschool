# Fix Database Error - Quick Guide

## Current Error:
```
Database error occurred. Please contact administrator.
```

---

## 🔍 Step 1: Run Diagnostic Tool

I've created a diagnostic tool to identify the exact issue:

**Visit:** `http://localhost:8080/account3/diagnose_database.php`

This will show you:
- ✅ PHP configuration
- ✅ Database constants
- ✅ Connection test
- ✅ Exact error message
- ✅ Solutions for your specific error

---

## 🚀 Step 2: Common Solutions

### Solution A: XAMPP MySQL Not Running

**Problem:** MySQL service is not started

**Fix:**
1. Open **XAMPP Control Panel**
2. Find **MySQL** row
3. Click **Start** button
4. Wait for it to turn green
5. Refresh your browser

---

### Solution B: Database Doesn't Exist

**Problem:** Database `school_fees_system` not created

**Fix:**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click **New** (left sidebar)
3. Database name: `school_fees_system`
4. Collation: `utf8mb4_general_ci`
5. Click **Create**
6. Go to **Import** tab
7. Choose file: `database/database.sql` (if you have it)
8. Click **Go**

---

### Solution C: Database Credentials Wrong

**Problem:** Username or password incorrect

**Fix:**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Check if you can login with:
   - Username: `root`
   - Password: (empty)
3. If not, update `config/database.php` with correct credentials

---

### Solution D: Wrong Database Name

**Problem:** Database exists but with different name

**Fix:**

**Option 1: Rename your database**
1. In phpMyAdmin, select your database
2. Go to Operations tab
3. Rename to: `school_fees_system`

**Option 2: Update config to match your database name**
1. Open `config/database.php`
2. Find line 25:
   ```php
   define('DB_NAME', 'school_fees_system');
   ```
3. Change to your actual database name:
   ```php
   define('DB_NAME', 'your_database_name');
   ```

---

## 🔧 Step 3: Manual Database Check

### Check MySQL Service Status

**Windows (XAMPP):**
1. Open XAMPP Control Panel
2. MySQL should show **running** with green background
3. If not running, click Start

**Check Port:**
- MySQL default port: `3306`
- If using different port, update in `config/database.php`

### Verify Database Exists

**Via phpMyAdmin:**
1. Visit: `http://localhost/phpmyadmin`
2. Look in left sidebar
3. Find `school_fees_system` database
4. If not found, create it (see Solution B above)

**Via XAMPP Shell:**
```bash
mysql -u root -p
SHOW DATABASES;
```
Look for `school_fees_system` in the list.

---

## 📋 Detailed Error Codes

The diagnostic tool will show one of these error codes:

### Error 2002: Can't connect to MySQL server
- **Cause:** MySQL service not running
- **Fix:** Start MySQL in XAMPP Control Panel

### Error 1049: Unknown database
- **Cause:** Database doesn't exist
- **Fix:** Create database in phpMyAdmin

### Error 1045: Access denied
- **Cause:** Wrong username/password
- **Fix:** Update credentials in config/database.php

### Error 2013: Lost connection
- **Cause:** MySQL crashed or port issue
- **Fix:** Restart MySQL, check port 3306

---

## 🎯 Quick Troubleshooting Checklist

Run through this checklist:

- [ ] XAMPP Control Panel → MySQL is **running** (green)
- [ ] phpMyAdmin accessible at `http://localhost/phpmyadmin`
- [ ] Database `school_fees_system` exists in phpMyAdmin
- [ ] Database has tables (run Import if empty)
- [ ] Username: `root`, Password: empty (default XAMPP)
- [ ] No other program using port 3306
- [ ] Ran `diagnose_database.php` tool

---

## 🔄 Fresh Installation Steps

If nothing works, start fresh:

### 1. Stop MySQL
```
XAMPP Control Panel → Stop MySQL
```

### 2. Backup Existing Database (if any)
```
phpMyAdmin → Export → Go
```

### 3. Drop and Recreate Database
```sql
DROP DATABASE IF EXISTS school_fees_system;
CREATE DATABASE school_fees_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### 4. Import Fresh Database
1. phpMyAdmin → Select `school_fees_system`
2. Import tab
3. Choose `database/database.sql`
4. Click Go

### 5. Restart MySQL
```
XAMPP Control Panel → Start MySQL
```

### 6. Test Connection
Visit: `http://localhost:8080/account3/diagnose_database.php`

---

## 📞 Still Having Issues?

### Run Diagnostic Tool Again
After trying fixes above, run:
```
http://localhost:8080/account3/diagnose_database.php
```

### Check Error Logs
1. XAMPP Control Panel → MySQL → Logs
2. Look for recent errors
3. Share error details for further help

### Common Issues

**Port 3306 already in use:**
```
Stop other MySQL instances
Or change port in XAMPP config
```

**XAMPP won't start MySQL:**
```
Check Windows Services
Look for conflicting MySQL installations
Run XAMPP as Administrator
```

---

## ✅ Success Indicators

You'll know it's working when:

1. ✅ `diagnose_database.php` shows "Connection Successful!"
2. ✅ You can visit `http://localhost:8080/account3/` without error
3. ✅ Login page loads correctly
4. ✅ Can login with admin credentials
5. ✅ Dashboard displays without errors

---

## 🎉 Next Steps After Fix

Once database connects successfully:

1. **Test Login:** Try logging in with admin credentials
2. **Check Dashboard:** Verify all modules load
3. **Test Features:** Add a student, generate receipt
4. **Configure System:** Update school settings

---

## 📖 Additional Resources

- **Diagnostic Tool:** [diagnose_database.php](diagnose_database.php)
- **Production Guide:** [PRODUCTION_DEPLOYMENT_GUIDE.md](PRODUCTION_DEPLOYMENT_GUIDE.md)
- **Troubleshooting:** [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
- **XAMPP Docs:** https://www.apachefriends.org/docs/

---

**Good luck! The diagnostic tool should pinpoint the exact issue.** 🔧
