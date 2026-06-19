# PDF Receipt Generation System

## Overview
The PDF Receipt Generation system allows users to download professional fee receipts in PDF format.

## Features
- **Professional Receipt Layout**: School header with branding, student details, fee breakdown
- **Amount in Words**: Automatic conversion of amount to Indian number format words
- **Print-Friendly Design**: Optimized for A4 printing
- **Auto-Print Option**: Opens print dialog automatically when PDF is generated
- **Multiple Access Points**: PDF download available from:
  - Fee Receipts List
  - Student Profile (Recent Receipts section)
  - Receipt View Page
  - Due Fees Page

## Files Involved

### Core PDF Generation
- **`pdf_receipt.php`**: Generates the PDF receipt
  - Uses HTML/CSS for professional layout
  - Includes school details, student info, fee breakdown
  - Converts amount to words (Indian format: Rupees, Lakhs, Crores)
  - Auto-print functionality

### Integration Points
- **`receipts.php`**: Fee receipts list - PDF download button in actions column
- **`students/view.php`**: Student profile - PDF button in recent receipts table
- **`receipt.php`**: Receipt view page - PDF download button at bottom
- **`pdf_helper.php`**: HTML receipt generator with PDF link

## Usage

### Download PDF Receipt
1. Navigate to any page showing fee receipts
2. Click the red **PDF** button (📄 icon)
3. Receipt will open in new tab with professional layout
4. Browser will prompt to print/save as PDF

### Direct URL Access
```
/modules/fees/pdf_receipt.php?id={receipt_id}
```

## Technical Details

### Amount to Words Conversion
Supports Indian numbering system:
- Ones, Tens, Hundreds
- Thousands
- Lakhs
- Crores

Example: 125000 → "One Lakh Twenty Five Thousand Rupees Only"

### Receipt Layout Sections
1. **School Header**
   - School name, address, contact details

2. **Receipt Information**
   - Receipt number, date

3. **Student Details**
   - Name, admission number, father's name, mother's name
   - Class, section, roll number
   - Contact number, payment mode

4. **Fee Breakdown Table**
   - S.No, Fee Head, Fee Type, Amount
   - Total row with bold formatting

5. **Amount in Words**
   - Formatted text showing amount in words

6. **Signatures**
   - Parent signature and authorized signatory sections

7. **Footer Note**
   - Computer-generated receipt notice
   - Non-refundable policy

### Print Features
- Optimized CSS for A4 printing
- Clean margins and borders
- Professional typography
- Page break controls

## Browser Compatibility
- Chrome: Full support, best PDF generation
- Firefox: Full support
- Edge: Full support
- Safari: Full support

## Future Enhancements
- QR code with receipt verification
- Digital signature support
- Email receipt directly to parents
- Batch PDF generation for multiple receipts
- Custom receipt templates based on fee type

## Security
- Login required
- Permission check: `fees` module, `view` permission
- Receipt ID validation
- SQL injection protection via prepared statements
- XSS protection via htmlspecialchars()

## Troubleshooting

### PDF doesn't download
- Check browser popup blocker settings
- Ensure JavaScript is enabled
- Try different browser

### Missing school details
- Verify school_settings table has data
- Check setting_id = 1 exists

### Amount in words incorrect
- Verify amount is numeric in database
- Check for decimal precision issues

---

**Created**: 2025-11-02
**Version**: 1.0
**Module**: Fee Management System
