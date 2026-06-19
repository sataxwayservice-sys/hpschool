# Recycle Bin System - Documentation

## Overview
The Recycle Bin system provides a safety net for deleted data in your School Management System. Instead of permanently deleting records, items are moved to a recycle bin where they can be restored within 30 days.

## Features

### ✅ Soft Delete Protection
- Students
- Fee Receipts
- Users
- Other data types can be added

### ✅ 30-Day Recovery Window
- All deleted items are kept for 30 days
- Automatic cleanup after 30 days
- Manual restore anytime before expiration

### ✅ Complete Audit Trail
- Track who deleted the item
- When it was deleted
- Reason for deletion
- Original data preserved

## Accessing the Recycle Bin

### Via Navigation Menu
1. Log in as **Admin** or **Super Admin**
2. Click **Settings** in the top navigation
3. Click **Recycle Bin**

### Direct URL
```
http://localhost:8080/account3/modules/settings/recycle_bin.php
```

## Using the Recycle Bin

### View Deleted Items
- **All Items Tab**: Shows all deleted items
- **Students Tab**: Shows only deleted students
- **Fee Receipts Tab**: Shows only deleted fee receipts
- **Users Tab**: Shows only deleted users

### Restore an Item
1. Find the item in the list
2. Click the **Restore** button (green)
3. Confirm the restoration
4. Item is restored to its original location

### Permanently Delete an Item
1. Find the item in the list
2. Click the **Delete** button (red)
3. Confirm permanent deletion
4. **Warning**: This action cannot be undone!

### Empty Recycle Bin
1. Click **Empty Recycle Bin** button (top right)
2. Confirm the action
3. All items are permanently deleted

## For Developers

### Using Soft Delete in Your Code

#### Delete a Student
```php
// Instead of:
executeQuery("DELETE FROM students WHERE student_id = ?", 'i', [$studentId]);

// Use:
softDeleteStudent($studentId, 'Reason for deletion');
```

#### Delete a Fee Receipt
```php
softDeleteFeeReceipt($receiptId, 'Receipt cancelled');
```

#### Delete a User
```php
softDeleteUser($userId, 'User removed by admin');
```

### Adding Soft Delete for New Data Types

```php
function softDeleteYourItem($itemId, $reason = 'Deleted') {
    // Get item data
    $item = fetchOne("SELECT * FROM your_table WHERE item_id = ?", 'i', [$itemId]);

    if (!$item) {
        return false;
    }

    // Move to recycle bin
    $moved = moveToRecycleBin('your_item_type', $itemId, $item, $reason);

    if ($moved) {
        // Delete from your table
        executeQuery("DELETE FROM your_table WHERE item_id = ?", 'i', [$itemId]);
        return true;
    }

    return false;
}
```

### Update Restore Logic

Edit `modules/settings/recycle_bin.php` and add your case to the switch statement:

```php
case 'your_item_type':
    $query = "INSERT INTO your_table (...) VALUES (...)";
    executeQuery($query, 'types', [...]);
    break;
```

## Automatic Cleanup

### Setup Automatic Cleanup (Windows Task Scheduler)

1. Open **Task Scheduler**
2. Click **Create Basic Task**
3. Name: "School Management - Recycle Bin Cleanup"
4. Trigger: **Daily** at **2:00 AM**
5. Action: **Start a program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\account3\cron\cleanup_recycle_bin.php`
6. Finish

### Setup Automatic Cleanup (Linux Cron)

Add to crontab:
```bash
0 2 * * * php /var/www/html/account3/cron/cleanup_recycle_bin.php
```

### Manual Cleanup

Run the script manually:
```bash
php C:\xampp\htdocs\account3\cron\cleanup_recycle_bin.php
```

### View Cleanup Logs

Check the log file:
```
C:\xampp\htdocs\account3\logs\recycle_bin_cleanup.log
```

## Database Structure

### deleted_items Table
```sql
CREATE TABLE deleted_items (
    deleted_id INT AUTO_INCREMENT PRIMARY KEY,
    item_type VARCHAR(50) NOT NULL,
    item_id INT NOT NULL,
    item_data TEXT NOT NULL,
    deleted_by INT NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255),
    INDEX idx_type (item_type),
    INDEX idx_deleted_at (deleted_at)
);
```

## Best Practices

### ✅ DO
- Always provide a reason for deletion
- Review recycle bin periodically
- Test restore functionality regularly
- Keep automatic cleanup enabled

### ❌ DON'T
- Don't empty the recycle bin frequently
- Don't skip soft delete for important data
- Don't modify deleted_items table directly
- Don't disable automatic cleanup

## Troubleshooting

### Items Not Appearing in Recycle Bin
**Cause**: Code still uses hard delete
**Solution**: Update delete code to use soft delete functions

### Restore Fails
**Cause**: Foreign key constraints or missing related data
**Solution**: Check error logs and ensure related records exist

### Old Items Not Cleaning Up
**Cause**: Cron job not running
**Solution**: Verify Task Scheduler/cron setup and check logs

## Statistics

The recycle bin dashboard shows:
- **Total Items**: All items in recycle bin
- **Students**: Count of deleted students
- **Fee Receipts**: Count of deleted fee receipts
- **Users**: Count of deleted users

## Security

- Only **Admin** and **Super Admin** can access
- All actions are logged
- Original data is encrypted in JSON format
- Audit trail is maintained

## Support

For issues or questions:
1. Check the log file: `logs/recycle_bin_cleanup.log`
2. Review this documentation
3. Contact system administrator

---

**Version**: 1.0
**Last Updated**: 2024
**Compatibility**: School Management System v1.0+
