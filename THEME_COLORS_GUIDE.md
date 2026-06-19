# School Theme Colors Guide

## Overview
The School Management System now includes a powerful theme customization feature that allows you to match the website colors to your school's branding!

---

## 🎨 Features

### 1. **8 Predefined Theme Presets**
Choose from professionally designed color schemes:

1. **Default (Bootstrap Blue)** - Classic blue theme
2. **School Green** - Fresh, education-focused green
3. **Royal Blue** - Professional royal blue
4. **Purple Academic** - Scholarly purple theme
5. **Orange Energy** - Vibrant and energetic
6. **Teal Modern** - Contemporary teal design
7. **Crimson Tradition** - Traditional crimson
8. **Navy Professional** - Corporate navy theme

### 2. **Custom Color Selection**
- Use color pickers to choose exactly the colors you want
- Customize 6 different color categories:
  - **Primary** - Main brand color (buttons, links, navbar)
  - **Secondary** - Supporting elements
  - **Success** - Success messages, confirmations
  - **Info** - Information highlights
  - **Warning** - Warnings, caution alerts
  - **Danger** - Errors, delete actions

### 3. **Live Preview**
- See colors change in real-time as you select them
- Preview buttons show exactly how colors will look
- Instant feedback before saving

### 4. **Automatic Theme Application**
- Colors automatically apply across entire website
- Affects buttons, alerts, cards, navigation, and more
- No coding required!

---

## 🚀 How to Change Theme Colors

### Method 1: Using Presets (Quick & Easy)

1. **Login** as Super Admin or Admin
2. Go to **Settings → School Settings**
3. Scroll down to **"Theme Colors & Branding"** section
4. Select a preset from the dropdown:
   - Default (Bootstrap Blue)
   - School Green
   - Royal Blue
   - Purple Academic
   - Orange Energy
   - Teal Modern
   - Crimson Tradition
   - Navy Professional
5. Click **"Save Settings"**
6. Refresh the page to see changes!

### Method 2: Custom Colors (Advanced)

1. Go to **Settings → School Settings**
2. Scroll to **"Theme Colors & Branding"**
3. Select **"Custom Colors"** from preset dropdown
4. Click on each color picker to choose your custom colors:
   - Primary Color
   - Secondary Color
   - Success Color
   - Info Color
   - Warning Color
   - Danger Color
5. Watch the **Live Preview** to see how it looks
6. Click **"Save Settings"**
7. Refresh to apply changes!

---

## 📊 Theme Preset Colors

### Default (Bootstrap Blue)
```
Primary:   #0d6efd (Blue)
Secondary: #6c757d (Gray)
Success:   #198754 (Green)
Info:      #0dcaf0 (Cyan)
Warning:   #ffc107 (Amber)
Danger:    #dc3545 (Red)
```

### School Green
```
Primary:   #28a745 (Green)
Secondary: #6c757d (Gray)
Success:   #20c997 (Teal)
Info:      #17a2b8 (Cyan)
Warning:   #ffc107 (Amber)
Danger:    #dc3545 (Red)
```

### Royal Blue
```
Primary:   #0056b3 (Royal Blue)
Secondary: #495057 (Dark Gray)
Success:   #28a745 (Green)
Info:      #007bff (Blue)
Warning:   #fd7e14 (Orange)
Danger:    #dc3545 (Red)
```

### Purple Academic
```
Primary:   #6f42c1 (Purple)
Secondary: #6c757d (Gray)
Success:   #198754 (Green)
Info:      #9b59b6 (Lavender)
Warning:   #f39c12 (Gold)
Danger:    #e74c3c (Red)
```

### Orange Energy
```
Primary:   #fd7e14 (Orange)
Secondary: #6c757d (Gray)
Success:   #20c997 (Teal)
Info:      #17a2b8 (Cyan)
Warning:   #ffc107 (Amber)
Danger:    #dc3545 (Red)
```

### Teal Modern
```
Primary:   #20c997 (Teal)
Secondary: #6c757d (Gray)
Success:   #28a745 (Green)
Info:      #17a2b8 (Cyan)
Warning:   #ffc107 (Amber)
Danger:    #dc3545 (Red)
```

### Crimson Tradition
```
Primary:   #dc143c (Crimson)
Secondary: #6c757d (Gray)
Success:   #198754 (Green)
Info:      #0dcaf0 (Cyan)
Warning:   #ffc107 (Amber)
Danger:    #8b0000 (Dark Red)
```

### Navy Professional
```
Primary:   #001f3f (Navy)
Secondary: #495057 (Dark Gray)
Success:   #198754 (Green)
Info:      #0dcaf0 (Cyan)
Warning:   #ffc107 (Amber)
Danger:    #dc3545 (Red)
```

---

## 🎯 What Gets Themed?

The theme colors affect:

### ✅ Navigation
- Top navigation bar
- Dropdown menus
- Active menu items

### ✅ Buttons
- All button types (primary, secondary, success, etc.)
- Hover states
- Focus states

### ✅ Alerts & Messages
- Success messages
- Error messages
- Info boxes
- Warnings

### ✅ Cards & Panels
- Card headers
- Dashboard cards
- Report cards

### ✅ Forms
- Input focus borders
- Select boxes
- Form validation

### ✅ Tables
- Table highlights
- Hover effects
- Active rows

### ✅ Links
- Text links
- Navigation links
- Footer links

### ✅ Badges & Labels
- Status badges
- Count badges
- Category labels

### ✅ Progress Bars
- Loading indicators
- Progress tracking

---

## 💡 Tips & Best Practices

### Color Selection Tips:
1. **Choose Contrasting Colors**: Primary and secondary should be easily distinguishable
2. **Test Readability**: Ensure text is readable on colored backgrounds
3. **Stay Consistent**: Use your school's official brand colors
4. **Consider Accessibility**: Use colors that work for colorblind users

### Recommended Combinations:
- **Primary**: Your school's main brand color
- **Secondary**: A neutral gray or complementary color
- **Success**: Green (universally recognized for success)
- **Danger**: Red (universally recognized for errors)
- **Warning**: Yellow/Amber (standard for warnings)
- **Info**: Blue/Cyan (standard for information)

### Testing Your Theme:
After changing colors, test:
1. Dashboard visibility
2. Button readability
3. Alert message clarity
4. Form elements
5. Print/PDF outputs
6. ID cards and receipts

---

## 🔧 Technical Details

### Database
Theme colors are stored in the `school_settings` table with the following columns:
- `theme_preset` - Selected preset name
- `theme_primary_color` - Hex color for primary
- `theme_secondary_color` - Hex color for secondary
- `theme_success_color` - Hex color for success
- `theme_info_color` - Hex color for info
- `theme_warning_color` - Hex color for warning
- `theme_danger_color` - Hex color for danger

### Files Involved
1. `database/add_theme_colors.sql` - Database migration
2. `modules/settings/school.php` - Settings page with color pickers
3. `assets/css/theme.php` - Dynamic CSS generator
4. `includes/header.php` - Includes dynamic theme CSS

### How It Works
1. Colors are saved in database via School Settings
2. `theme.php` reads colors from database
3. Generates custom CSS dynamically
4. CSS is included in every page header
5. Overrides Bootstrap default colors

---

## 🛠️ Troubleshooting

### Colors Not Changing?
1. Clear browser cache (Ctrl+F5)
2. Check if you're logged in as Admin/Super Admin
3. Verify settings were saved successfully
4. Try a different browser

### Some Elements Not Themed?
- This is normal - some elements use fixed colors for consistency
- Login page, print layouts, and PDFs have special styling

### Want to Reset to Default?
1. Go to Settings → School Settings
2. Select "Default (Bootstrap Blue)" from preset dropdown
3. Save Settings
4. Refresh page

---

## 📱 Browser Compatibility

| Browser | Support |
|---------|---------|
| Chrome | ✅ Full Support |
| Firefox | ✅ Full Support |
| Edge | ✅ Full Support |
| Safari | ✅ Full Support |
| Mobile | ✅ Responsive |

---

## 🎓 Examples

### Example 1: Match School Logo Colors
If your school logo is maroon and gold:
1. Set Primary to maroon (#8B0000)
2. Set Warning to gold (#FFD700)
3. Keep other colors standard
4. Save and refresh!

### Example 2: Fresh Modern Look
For a contemporary feel:
1. Choose "Teal Modern" preset
2. Or customize with:
   - Primary: #20c997 (Teal)
   - Info: #17a2b8 (Cyan)
3. Results in a fresh, modern interface

### Example 3: Traditional School
For classic academic feel:
1. Choose "Navy Professional" or "Purple Academic"
2. These evoke traditional education institutions
3. Professional and scholarly appearance

---

## ✅ Quick Reference

**Access**: Settings → School Settings → Theme Colors & Branding

**Who Can Change**: Super Admin, Admin

**Takes Effect**: Immediately after save + refresh

**Applies To**: All pages, buttons, alerts, navigation, cards, forms

**Presets**: 8 predefined + Custom option

**Colors**: 6 customizable (Primary, Secondary, Success, Info, Warning, Danger)

---

## 🆘 Need Help?

- Check this guide for common questions
- Refer to main [README.md](README.md) for system overview
- Contact system administrator

---

**Feature Added**: November 2, 2025
**Status**: ✅ Fully Functional
**Version**: 1.0

---

*Transform your school management system to match your school's unique identity!* 🎨✨
