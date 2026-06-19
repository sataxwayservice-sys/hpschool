# Quick Fixes for Common Issues

## 🎨 CSS Not Loading ("Failed to load stylesheet")

### **Quick Fix - Clear Browser Cache**

**Method 1: Hard Refresh**
```
Press: Ctrl + F5 (Windows)
Or: Ctrl + Shift + R (Windows)
Or: Cmd + Shift + R (Mac)
```

**Method 2: Clear Cache in Browser**
```
1. Press F12 (open Developer Tools)
2. Right-click the Refresh button
3. Select "Empty Cache and Hard Reload"
```

**Method 3: Test Direct Access**
```
Open in new tab:
http://localhost/account3/assets/css/style.css

If you see CSS code, the file works!
If you see 404, there's a path issue.
```

---

### **Permanent Fix**

Add this to your `.htaccess` to prevent caching issues:

```apache
# Disable caching for development
<IfModule mod_headers.c>
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires 0
</IfModule>
```

---

## 🔐 "Invalid username or password"

### **Quick Fix - Use Reset Tool**

```
1. Open: http://localhost/account3/reset_password.php
2. See list of users
3. Enter username from the list
4. Set password: admin123
5. Click "Reset Password"
6. Login with new credentials
```

### **Quick Fix - Test Login**

```
1. Open: http://localhost/account3/test_login.php
2. Use the test form
3. It will tell you exactly what's wrong
```

---

## 🖼️ Missing Images

### **Default Avatar Missing**

If you see broken image icon in student form:

✅ **FIXED!** Created: `assets/images/default-avatar.svg`

The system now uses SVG avatar which always works.

---

## 🔍 Diagnostic Tools

### **Check All Paths**
```
http://localhost/account3/check_paths.php
```

Shows:
- ✅ All asset files status
- ✅ URLs to test
- ✅ What needs fixing

### **Test Login**
```
http://localhost/account3/test_login.php
```

Shows:
- ✅ All users in database
- ✅ Test credentials
- ✅ Exact error message

### **Reset Password**
```
http://localhost/account3/reset_password.php
```

Shows:
- ✅ All users list
- ✅ Easy password reset
- ✅ Instant fix

---

## 🚀 Complete Fresh Start

If everything is broken:

### **Step 1: Clear Database**
```
1. Open: http://localhost/phpmyadmin/
2. Select: school_fees_system
3. Click: Operations tab
4. Click: "Drop database" (at bottom)
5. Confirm
```

### **Step 2: Clear Browser**
```
1. Clear all cookies for localhost
2. Clear cache
3. Close all tabs
4. Close browser completely
```

### **Step 3: Restart XAMPP**
```
1. Stop Apache
2. Stop MySQL
3. Close XAMPP
4. Open XAMPP again
5. Start Apache
6. Start MySQL
```

### **Step 4: Run Setup**
```
1. Open: http://localhost/account3/setup.php
2. Import Database (Step 1)
3. Create Admin (Step 2)
4. Login!
```

---

## ⚡ One-Line Fixes

### Fix 1: CSS Not Loading
```
Hard refresh: Ctrl + F5
```

### Fix 2: Can't Login
```
Open: http://localhost/account3/reset_password.php
Reset password, then login
```

### Fix 3: Session Warnings
```
Already fixed! Sessions now managed in config.php
```

### Fix 4: Database Connection
```
Check MySQL is running in XAMPP
```

### Fix 5: Port 80 In Use
```
Stop Skype or change Apache port to 8080
```

---

## 🎯 Most Common Issue = Cache!

**90% of "CSS not loading" issues are browser cache!**

**Solution:**
```
1. Press Ctrl + Shift + Delete
2. Select "Cached images and files"
3. Select "All time"
4. Click "Clear data"
5. Close browser
6. Reopen and try again
```

---

## 📊 Quick Checklist

Before asking for help, verify:

- [ ] Apache is running (green in XAMPP)
- [ ] MySQL is running (green in XAMPP)
- [ ] Database `school_fees_system` exists in phpMyAdmin
- [ ] Cleared browser cache (Ctrl + F5)
- [ ] Tried incognito/private browsing
- [ ] Can access: http://localhost/account3/assets/css/style.css
- [ ] Checked error logs in browser console (F12)
- [ ] Used diagnostic tools (test_login.php, check_paths.php)

---

## 🛠️ Diagnostic Tools Quick Reference

| Tool | URL | Purpose |
|------|-----|---------|
| Path Check | `check_paths.php` | Test all asset URLs |
| Login Test | `test_login.php` | Debug login issues |
| Password Reset | `reset_password.php` | Reset any password |
| Setup | `setup.php` | Database import & admin setup |

---

## 💡 Pro Tips

1. **Always use Ctrl + F5** when testing changes
2. **Use incognito mode** for clean testing
3. **Check browser console (F12)** for errors
4. **Test direct file access** before debugging code
5. **Clear cache first** before assuming code issue

---

## 🎓 Understanding the Error

### "Failed to load stylesheet"

This error means:
- Browser tried to load CSS file
- Got 404 or other error
- **Usually**: File exists but browser cached 404
- **Rarely**: File actually missing or path wrong

**99% Fix:**
```
Ctrl + F5
```

That's it! Most of the time that's all you need.

---

**Created:** Now
**Last Updated:** Now
**Status:** Ready to use

---

*Having issues? Use the diagnostic tools first before debugging code!*
