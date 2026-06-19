# Recycle Bin Workflow - Quick Guide

## ✅ Complete Fee Receipt Recovery System

### 📋 **Step 1: Cancel a Fee Receipt**

1. **Go to**: Fees → Fee Receipts
2. Find the receipt you want to cancel
3. Click **Delete** (red button)
4. **Select a reason** from dropdown:
   - Duplicate payment
   - Wrong amount entered
   - Wrong student
   - Payment refunded
   - Error in receipt
   - Requested by parent
   - Other
5. Click **"Yes, Cancel This Receipt"**
6. Success message: "Receipt cancelled and moved to recycle bin. You can restore it from Settings → Recycle Bin within 30 days."

**What happens:**
- Receipt is marked as `is_cancelled = 1`
- Complete receipt data (including fee details) saved to `deleted_items` table
- Receipt still visible in reports but marked as CANCELLED
- Can be restored within 30 days

---

### 🔄 **Step 2: View Cancelled Receipts**

#### **Option A: Recycle Bin**
1. **Go to**: Settings → Recycle Bin
2. Click **"Fee Receipts"** tab
3. See all cancelled receipts with:
   - Receipt number
   - Amount
   - Deleted by (who cancelled it)
   - Deleted on (when)
   - Days remaining (30-day countdown)
   - Cancellation reason

#### **Option B: PDF Receipt**
1. View cancelled receipt: `http://localhost:8080/account3/modules/fees/pdf_receipt.php?id=7`
2. Shows:
   - **Large "CANCELLED" watermark** (diagonal, semi-transparent)
   - **Red alert box** at top: "⚠️ THIS RECEIPT HAS BEEN CANCELLED AND IS NO LONGER VALID"
   - Notice about 30-day restoration period
   - Title shows: "FEE RECEIPT (CANCELLED)"

---

### ↩️ **Step 3: Restore a Cancelled Receipt**

1. **Go to**: Settings → Recycle Bin
2. Click **"Fee Receipts"** tab
3. Find the receipt to restore
4. Click green **"Restore"** button
5. Confirm restoration
6. Receipt is restored with:
   - ✅ `is_cancelled` set to `0` (active again)
   - ✅ All fee details restored
   - ✅ Original receipt number preserved
   - ✅ Payment details intact
   - ✅ Receipt visible in reports as active

---

### 🗑️ **Step 4: Permanent Deletion (Optional)**

**Manual Deletion:**
1. In Recycle Bin, click red **"Delete"** button
2. Confirm permanent deletion
3. ⚠️ **Warning**: Cannot be undone!

**Automatic Cleanup:**
- After 30 days, items are automatically deleted
- Cleanup runs daily at 2:00 AM (if configured)
- Check logs: `logs/recycle_bin_cleanup.log`

---

## 🎯 **Quick Access URLs**

| Page | URL |
|------|-----|
| Recycle Bin | `http://localhost:8080/account3/modules/settings/recycle_bin.php` |
| Fee Receipts Tab | `http://localhost:8080/account3/modules/settings/recycle_bin.php?type=fee_receipt` |
| Cancel Receipt | Fee Receipts → Delete button |
| View PDF Receipt | `http://localhost:8080/account3/modules/fees/pdf_receipt.php?id=X` |

---

## 📊 **Database Tables Involved**

### 1. **fee_receipts**
```sql
- receipt_id (Primary Key)
- is_cancelled (0 = Active, 1 = Cancelled)
- ... (other fields)
```

### 2. **deleted_items** (Recycle Bin)
```sql
- deleted_id (Primary Key)
- item_type = 'fee_receipt'
- item_id = receipt_id
- item_data = JSON (complete receipt + details)
- deleted_by = user_id
- deleted_at = timestamp
- reason = cancellation reason
```

### 3. **fee_receipt_details**
```sql
- receipt_id (Foreign Key)
- fee_head_id
- amount
- ... (restored with receipt)
```

---

## 🔍 **Example Scenarios**

### **Scenario 1: Duplicate Payment**
1. **Problem**: Accidentally collected fee twice from same student
2. **Action**: Cancel duplicate receipt → Select "Duplicate payment"
3. **Result**: Cancelled receipt in Recycle Bin, can restore if needed
4. **Timeline**: 30 days to decide if permanent deletion needed

### **Scenario 2: Wrong Amount**
1. **Problem**: Entered ₹5000 instead of ₹500
2. **Better Option**: Use **Edit** instead of Cancel
3. **If cancelled**: Restore from Recycle Bin → Edit → Save
4. **Alternative**: Create new correct receipt

### **Scenario 3: Payment Refunded**
1. **Problem**: Parent requested refund
2. **Action**: Cancel receipt → Select "Payment refunded"
3. **Result**: Receipt marked cancelled, visible in audit trail
4. **Note**: Physical refund process handled separately

---

## ✨ **Key Features**

### **Soft Delete Benefits:**
- ✅ No data loss
- ✅ Complete audit trail
- ✅ 30-day recovery window
- ✅ Reason tracking
- ✅ User accountability (tracks who deleted)

### **Smart Restoration:**
- ✅ Uncancels existing receipts
- ✅ Restores fee breakdown
- ✅ Maintains original receipt numbers
- ✅ Preserves relationships

### **Visual Indicators:**
- ✅ PDF watermark for cancelled receipts
- ✅ Color-coded badges
- ✅ Days remaining countdown
- ✅ Clear warning messages

---

## 🛠️ **For Developers**

### **Soft Delete a Receipt**
```php
// In your delete code
$receiptId = 7;
$reason = "Duplicate payment";
$success = softDeleteFeeReceipt($receiptId, $reason);
```

### **Check if Receipt is Cancelled**
```php
$receipt = fetchOne("SELECT is_cancelled FROM fee_receipts WHERE receipt_id = ?", 'i', [7]);
if ($receipt['is_cancelled'] == 1) {
    echo "This receipt is cancelled";
}
```

### **Get Recycle Bin Count**
```php
$count = getRecycleBinCount('fee_receipt');
echo "Cancelled receipts: $count";
```

---

## 📞 **Support**

### **Common Issues:**

**Q: Can't find cancelled receipt in Recycle Bin?**
A: Check "Fee Receipts" tab specifically. Make sure cancellation was successful.

**Q: Restore button not working?**
A: Check error logs. Ensure receipt ID doesn't conflict with existing active receipt.

**Q: Receipt shows CANCELLED watermark after restore?**
A: Refresh page. The `is_cancelled` flag should be 0 after restore.

**Q: Lost receipts after 30 days?**
A: Automatic cleanup removes old items. Check `logs/recycle_bin_cleanup.log`.

---

## 🎓 **Best Practices**

1. **Always provide a reason** when cancelling
2. **Review cancelled receipts** before permanent deletion
3. **Restore within 30 days** if needed
4. **Use Edit** for minor corrections instead of cancel
5. **Keep audit trail** for accounting purposes
6. **Check PDF before finalizing** cancellation

---

**Version**: 1.0
**Last Updated**: 2025
**Status**: ✅ Fully Functional
