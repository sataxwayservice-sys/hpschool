# Setup Guide: Discount & Academic Year Features

## What's New?

Two new features have been added to your fee collection system:

1. **Discount Support** - Give discounts on individual fees with optional reason
2. **Academic Year Customization** - Start academic year from any month (April, May, June, etc.)

---

## Step 1: Run Database Migration Scripts

You need to run these scripts ONCE to add the necessary database columns:

### 1.1 Add Academic Year Setting

Open in browser:
```
http://localhost:8080/account3/add_academic_year_settings.php
```

This adds:
- `academic_year_start_month` column to `school_settings` table
- Default value: 4 (April)

### 1.2 Add Discount Support

Open in browser:
```
http://localhost:8080/account3/add_discount_support.php
```

This adds:
- `discount` column to `fee_receipt_details` table
- `discount_reason` column to `fee_receipt_details` table

---

## Step 2: Test the Features

### Testing Discount Feature

1. Go to Fee Collection page:
   ```
   http://localhost:8080/account3/modules/fees/collect_complete.php
   ```

2. Search and select a student

3. Add some fees to the "Payable Fee List" (click green checkmark)

4. You'll now see a **Discount** column in the payable fee table:
   - Enter discount amount (e.g., 50.00)
   - Optionally enter a reason (e.g., "Sibling discount", "Scholarship", "Early payment")
   - The **Payable** amount will automatically update (Amount - Discount)
   - Total discount and net payable amount will show in the footer

5. Save the receipt and verify discount appears on the receipt

### Testing Academic Year Feature

1. The pending fees will now generate based on the academic year start month

2. **To change the academic year start month:**
   - Go to School Settings page
   - Find "Academic Year Start Month" setting
   - Change from April (4) to your desired month:
     - 1 = January
     - 4 = April
     - 5 = May
     - 6 = June
     - etc.

3. **Example:** If you set start month to May (5):
   - Academic year will be: May 2025 → April 2026
   - Pending fees will show: May, June, July, Aug, Sep, Oct, Nov, Dec, Jan, Feb, Mar, Apr

---

## How Discount Works

### In Fee Collection:

**Before (without discount):**
```
Fee: Tuition Fee (Jan 2025)
Amount: 1000.00
Payable: 1000.00
```

**After (with discount):**
```
Fee: Tuition Fee (Jan 2025)
Amount: 1000.00
Discount: 100.00
Payable: 900.00
Reason: Early payment discount
```

### Validation:
- ✅ Discount cannot exceed the original fee amount
- ✅ Discount must be 0 or positive
- ✅ Reason is optional (max 255 characters)

---

## Features Summary

### Discount Support
- ✅ Give discount on any individual fee/month
- ✅ Enter optional reason for tracking
- ✅ Automatic calculation: Payable = Amount - Discount
- ✅ Shows total discount in receipt footer
- ✅ Validation: discount cannot exceed amount
- ✅ Saved in database for reporting

### Academic Year Support
- ✅ Configurable start month (1-12)
- ✅ Pending fees follow academic year cycle
- ✅ Example: May-April, June-May, April-March, etc.
- ✅ Automatic year adjustment for cross-year months
- ✅ Won't show future months

---

## Troubleshooting

### "Column 'discount' doesn't exist" error
**Solution:** You forgot to run `add_discount_support.php` - run it now!

### "Column 'academic_year_start_month' doesn't exist" error
**Solution:** You forgot to run `add_academic_year_settings.php` - run it now!

### Pending fees still show Jan-Dec instead of academic year
**Solution:**
1. Make sure you ran the academic year migration script
2. Set the `academic_year_start_month` value in the database
3. Refresh the fee collection page

### Discount not saving
**Solution:** Make sure the migration script ran successfully and created both columns

---

## Database Changes

### `school_settings` table:
```sql
ALTER TABLE school_settings
ADD COLUMN academic_year_start_month INT DEFAULT 4
COMMENT 'Start month of academic year (1=Jan, 4=Apr, 6=Jun)';
```

### `fee_receipt_details` table:
```sql
ALTER TABLE fee_receipt_details
ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Discount amount',
ADD COLUMN discount_reason VARCHAR(255) DEFAULT NULL COMMENT 'Reason for discount';
```

---

## Next Steps

After running the migration scripts:

1. ✅ Test discount feature on a sample student
2. ✅ Test academic year with different start months
3. ✅ Verify receipts show discount correctly
4. ✅ Check if PDF receipts need to be updated to show discounts

---

**Need Help?** Check the console for errors or verify the migration scripts ran successfully!
