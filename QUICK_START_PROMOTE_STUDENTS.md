# Quick Start Guide: Promote Students

## Overview
The Student Promotion System allows you to batch-promote students from one class/section to another in just 3 simple steps.

---

## 🚀 Quick Access

**Direct URL:**
```
http://localhost:8080/account3/modules/students/promote.php
```

**Via Navigation:**
Students → Promote Students

---

## 📋 Step-by-Step Guide

### Step 1: Select Current Class/Section
1. Click **Students → Promote Students** from the top menu
2. Select the **current class** (where students are now)
3. Select the **current section** (where students are now)
4. Click **"Load Students"** button

**Example:**
- Current Class: Class 9
- Current Section: A
- This loads all students currently in Class 9-A

---

### Step 2: Select Students to Promote
1. Review the list of students loaded
2. **Option A**: Click **"Select All"** to select everyone
3. **Option B**: Click individual checkboxes to select specific students
4. Watch the **counter badge** show how many students selected

**Table Shows:**
- Admission Number
- Student Name
- Roll Number
- Gender
- Father Name

**Selection Controls:**
- ✅ **Select All** - Selects all students in the list
- ❌ **Deselect All** - Clears all selections
- 🔢 **Badge** - Shows count (e.g., "15 selected")

---

### Step 3: Choose Destination & Promote
1. Select the **destination class** (where to promote students)
2. Select the **destination section** (where to promote students)
3. Click the green **"Promote Selected Students"** button
4. **Confirm** the action in the dialog box

**Example:**
- Promote To Class: Class 10
- Promote To Section: A
- This moves selected students from Class 9-A to Class 10-A

**Confirmation Dialog Shows:**
```
Are you sure you want to promote 15 student(s) from
Class 9 - A to Class 10 - A?
```

---

## ✅ What Happens After Promotion

### Immediate Changes:
1. ✅ Students are moved to new class
2. ✅ Students are moved to new section
3. ⚠️ **Roll numbers are cleared** (set to NULL)
4. ✅ Activity is logged for each student
5. ✅ Success message shows count

### Success Message Example:
```
✅ Successfully promoted 15 student(s)!
```

### If Errors Occur:
```
✅ Successfully promoted 14 student(s)! (1 failed)
```

---

## ⚠️ Important Notes

### Roll Numbers
**Roll numbers are automatically cleared after promotion.**

Why? Students need new roll numbers for their new class.

**What to do:**
1. Go to Students → View Students
2. Filter by the new class/section
3. Manually assign new roll numbers
4. Or use the "Edit Student" feature to set roll numbers individually

### You Cannot:
- ❌ Promote to the same class AND section (validation error)
- ❌ Promote without selecting any students (validation error)
- ❌ Promote without selecting destination (validation error)

### You Can:
- ✅ Promote entire class at once (Select All)
- ✅ Promote selected students only
- ✅ Promote to same class but different section
- ✅ Promote to different class and different section

---

## 🎯 Common Use Cases

### End of Academic Year - Promote Entire Class
**Scenario:** All Class 9 students move to Class 10

**Steps:**
1. Select Current: Class 9, Section A
2. Load Students
3. Click "Select All"
4. Select Destination: Class 10, Section A
5. Promote
6. Repeat for other sections (B, C, etc.)

---

### Transfer Students to Different Section
**Scenario:** Move 5 students from Section A to Section B (same class)

**Steps:**
1. Select Current: Class 10, Section A
2. Load Students
3. Manually select the 5 students
4. Select Destination: Class 10, Section B
5. Promote

---

### Promote Selected High Performers
**Scenario:** Promote only passing students to next class

**Steps:**
1. Select Current: Class 9, Section A
2. Load Students
3. Manually check boxes for passing students
4. Select Destination: Class 10, Section A
5. Promote
6. Failing students remain in Class 9

---

## 🔍 Verification

### How to Verify Promotion Worked:
1. Go to **Students → View Students**
2. Filter by **new class and section**
3. You should see the promoted students listed
4. Their roll numbers will be blank (NULL)

### Check Activity Log:
1. Go to **Profile** (top right menu)
2. Scroll to **Recent Activity**
3. You'll see log entries like:
   ```
   Student Promoted: Promoted student: John Doe (ID: 123)
   ```

---

## 🛠️ Troubleshooting

### Problem: "No students found"
**Cause:** No active students in selected class/section
**Solution:** Check if students exist in that class, verify they're marked as "Active"

### Problem: Can't click "Promote" button
**Cause:** No students selected
**Solution:** Select at least one student by clicking checkboxes

### Problem: Error "source and destination cannot be same"
**Cause:** Trying to promote to the same class AND section
**Solution:** Change either the class or section (or both)

### Problem: Loading spinner doesn't stop
**Cause:** AJAX endpoint error or network issue
**Solution:** Check browser console for errors, refresh the page

---

## 💡 Pro Tips

### Tip 1: Promote in Batches
If you have many students, promote one section at a time for better control.

### Tip 2: Print Student List First
Before promoting, go to Reports → Student List and print/export the current class roster for your records.

### Tip 3: Assign Roll Numbers Later
Don't worry about roll numbers during promotion. Assign them later in bulk using the student list.

### Tip 4: Use Select All Carefully
Double-check your selection before clicking "Select All" - make sure you want to promote everyone.

### Tip 5: Check Success Count
After promotion, verify the success count matches your selection count. If there's a mismatch, check which students failed.

---

## 🔐 Permissions Required

**Who can promote students:**
- Users with **Students module "Edit" permission**
- Super Admin (has all permissions)
- Admin (usually has this permission)

**Who cannot promote students:**
- Accountants (typically view-only)
- Clerks (typically view-only)
- Teachers (typically view-only)

**To check your permissions:**
1. Ask your administrator
2. Try accessing the page - you'll get an error if you don't have permission

---

## 📊 Technical Details (For Admins)

### Database Changes:
```sql
UPDATE students
SET class_id = [new_class_id],
    section_id = [new_section_id],
    roll_no = NULL
WHERE student_id = [student_id]
```

### Transaction Safety:
- Uses database transactions
- If any error occurs, all changes are rolled back
- Ensures data integrity

### Activity Logging:
- Each promotion is logged separately
- Includes student name and ID
- Timestamp and user ID recorded
- Available in activity_logs table

---

## 🎬 Video Walkthrough (Conceptual)

1. **[0:00]** Login to system
2. **[0:10]** Click Students → Promote Students
3. **[0:15]** Select Class 9, Section A
4. **[0:20]** Click "Load Students"
5. **[0:25]** Click "Select All" (or select specific students)
6. **[0:30]** Choose Class 10, Section A as destination
7. **[0:35]** Click "Promote Selected Students"
8. **[0:40]** Confirm in dialog box
9. **[0:45]** Success! Message shows "Successfully promoted 25 student(s)!"
10. **[0:50]** Verify by going to View Students, filter Class 10-A

**Total Time:** Under 1 minute!

---

## ✅ Checklist Before Promoting

- [ ] Have you loaded the correct current class/section?
- [ ] Have you selected the right students?
- [ ] Have you chosen the correct destination class/section?
- [ ] Have you verified source and destination are different?
- [ ] Are you ready to reassign roll numbers later?
- [ ] Have you saved a backup of student list (optional)?

If all checked, you're ready to promote! ✨

---

## 📞 Need Help?

**Error messages:** Read the error carefully - it tells you what's wrong
**Permission issues:** Contact your system administrator
**Technical problems:** Check SYSTEM_STATUS.md or contact support

---

**Last Updated:** November 2, 2025
**Feature Version:** 1.0
**Status:** Production Ready ✅

---

*Promoting students has never been easier! 🎓📈*
