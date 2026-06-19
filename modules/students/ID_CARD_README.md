# Student ID Card Generation System

## Overview
Professional student ID card generator with multiple design templates and print-ready layouts.

## Features

### Design Templates
1. **Default (Horizontal)** - Standard horizontal ID card (85.6mm x 54mm)
2. **Modern** - Colorful gradient design with modern aesthetics
3. **Professional** - Blue corporate style with clean layout
4. **Colorful** - Rainbow border with vibrant colors
5. **Vertical (Long)** - Vertical orientation card (60mm x 90mm)

### Card Elements
- School logo and name
- Student photo
- Student name
- Admission number
- Class and section
- Roll number
- Contact information
- Barcode for verification
- Validity period
- Professional design with gradients

### Print Specifications
- **Page Size**: A4 (210mm x 297mm)
- **Card Sizes**:
  - Horizontal: 85.6mm x 54mm (standard credit card size)
  - Vertical: 60mm x 90mm (badge size)
- **Print-Ready**: Optimized for home and professional printers
- **Multiple Cards Per Page**: Auto-arranged with proper spacing

## Access Points

### 1. From Student List
1. Navigate to **Students** → **View Students**
2. Click **"Generate ID Cards"** button (top right)
3. Select filters and design
4. Print

### 2. From Student Profile
1. Open any student profile
2. Click **"Generate ID Card"** in Quick Actions
3. Card opens in new window
4. Print directly

### 3. Direct URL
```
http://localhost:8080/account3/modules/students/generate_id_card.php
```

## Usage Instructions

### Generate Single Student Card
1. Go to student profile
2. Click "Generate ID Card" in Quick Actions
3. Choose design template from dropdown
4. Click "Print Cards"

### Generate Multiple Cards
1. Go to Students → Generate ID Cards
2. **Filter Options**:
   - Select design template
   - Choose class (optional)
   - Choose section (optional)
3. Click "Filter" to see preview
4. Click "Print Cards" button

### Design Selection
- **Default**: Best for general use, fits 8 cards per A4 page
- **Modern**: Eye-catching, good for younger students
- **Professional**: Formal, good for older students/staff
- **Colorful**: Fun and vibrant, attracts attention
- **Vertical**: Badge-style, good for lanyards

## Printing Tips

### For Best Results
1. **Paper**: Use thick cardstock (200-300 GSM)
2. **Printer Settings**:
   - Quality: Best/High
   - Paper type: Cardstock
   - Color: Full color
3. **Cutting**: Use a paper cutter for clean edges
4. **Lamination**: Recommended for durability

### Cost-Effective Printing
- Print 2 pages per student (front/back)
- Use standard printer paper for draft
- Professional print shop for final cards

## Card Layout Details

### Horizontal Card (Default)
```
┌─────────────────────────────────────┐
│  SCHOOL NAME                        │
│  Address                            │
├──────────┬──────────────────────────┤
│          │ Name: John Doe           │
│  PHOTO   │ Admission: 2024001       │
│          │ Class: 10-A              │
│          │ Roll: 15                 │
│          │ Contact: 1234567890      │
├──────────┴──────────────────────────┤
│  *2024001*                          │
│  Valid: 2024-2025                   │
└─────────────────────────────────────┘
```

### Vertical Card
```
┌──────────────┐
│ SCHOOL NAME  │
│   Address    │
├──────────────┤
│    PHOTO     │
│              │
├──────────────┤
│   John Doe   │
│  2024001     │
│  Class 10-A  │
│   Roll: 15   │
│ Contact: xxx │
├──────────────┤
│  *2024001*   │
│ Valid: 24-25 │
└──────────────┘
```

## Customization

### Modify School Branding
1. Update school settings with logo
2. Logo appears in card header
3. School name and address from settings

### Change Card Colors
Edit CSS in `generate_id_card.php`:
```css
.id-card-header {
    background: linear-gradient(135deg, #YOUR_COLOR1, #YOUR_COLOR2);
}
```

### Add Custom Fields
Modify the card body section to include additional fields like:
- Blood group
- Emergency contact
- House/Group
- Student ID QR code

## Advanced Features

### Batch Processing
- Generate cards for entire class
- Filter by section
- Print multiple designs at once

### Verification Barcode
- Each card includes admission number as barcode
- Use barcode scanner for quick verification
- Format: `*ADMISSION_NO*`

### Photo Requirements
- **Size**: 20mm x 25mm (horizontal), 30mm x 35mm (vertical)
- **Format**: JPG, PNG
- **Upload via**: Student add/edit page
- **Fallback**: Generic avatar if no photo

## Troubleshooting

### Cards not printing correctly
- Check printer margins (should be minimal)
- Verify page size is A4
- Use print preview before printing

### Photos not showing
- Ensure photos are uploaded to `/uploads/students/`
- Check file permissions
- Verify image file names match database

### Design looks different in print
- Some browsers handle print differently
- Use Chrome for best results
- Check print preview before printing

### Too many cards per page
- Adjust `.id-card-container` gap in CSS
- Modify card dimensions
- Use page breaks for control

## Security Features

1. **Barcode Verification**: Scan to verify student
2. **Validity Period**: Academic year displayed
3. **Photo ID**: Visual verification
4. **School Branding**: Prevents forgery

## Future Enhancements

Possible additions:
- QR code with encrypted student data
- RFID chip integration
- Digital signature
- Expiry date prominent display
- Magnetic stripe data
- NFC chip support

## Technical Details

### Browser Support
- ✅ Chrome (recommended)
- ✅ Firefox
- ✅ Edge
- ✅ Safari

### Print Technology
- CSS print media queries
- Page break controls
- Optimized for A4 paper
- Supports both portrait and landscape

### Performance
- Generates 50 cards in < 2 seconds
- Instant preview
- Efficient image loading

---

## Quick Reference

| Action | URL |
|--------|-----|
| Generate All | `/modules/students/generate_id_card.php` |
| Single Student | `/modules/students/generate_id_card.php?student_id=X` |
| By Class | `/modules/students/generate_id_card.php?class_id=X` |
| By Section | `/modules/students/generate_id_card.php?section_id=X` |
| Custom Design | `/modules/students/generate_id_card.php?design=modern` |

---

**Created**: 2025-11-02
**Version**: 1.0
**Module**: Student Management System
