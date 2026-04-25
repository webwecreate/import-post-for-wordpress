# MASTER ARCHITECTURE
# Plugin: CSV Post Importer
# Last Updated: 2026-04-25
# Version: 1.1.0

---

## 1. Project Overview

**Plugin Name:** CSV Post Importer
**Plugin Slug:** csv-post-importer
**Description:** WordPress plugin สำหรับ import posts จากไฟล์ CSV พร้อม featured image (จาก Media Library หรือ URL) และ category/sub-category mapping แบบ manual พร้อม error log และ import mode (create/update)
**Tech Stack:** PHP, WordPress, Vanilla JS (Admin UI)

---

## 2. File Structure

```
csv-post-importer/
├── csv-post-importer.php           # Main plugin file (bootstrap)
├── CHANGELOG.md                    # Changelog (append only — ห้ามเขียนทับ)
├── readme.txt                      # WordPress plugin readme
│
├── includes/
│   ├── class-cpi-activator.php     # Activation hook
│   ├── class-cpi-deactivator.php   # Deactivation hook
│   ├── class-cpi-csv-parser.php    # CSV parsing logic
│   ├── class-cpi-post-creator.php  # Create/update WP posts
│   ├── class-cpi-image-handler.php # Media Library lookup + URL download
│   ├── class-cpi-category-handler.php # Category mapping + assign mode
│   └── class-cpi-logger.php        # Error/import log handler
│
├── admin/
│   ├── class-cpi-admin.php         # Admin menu + page controller
│   ├── views/
│   │   ├── page-import.php         # Step 1: Upload CSV
│   │   ├── page-mapping.php        # Step 2: Map columns (manual)
│   │   ├── page-result.php         # Step 3: Import result + summary
│   │   └── page-logs.php           # Error log viewer
│   ├── css/
│   │   └── cpi-admin.css           # Admin styles
│   └── js/
│       └── cpi-admin.js            # Admin JS (mapping UI + dynamic dropdowns)
│
└── uploads/                        # Temp CSV storage (gitignored)
```

---

## 3. Class & Responsibility Map

| Class | File | Responsibility |
|---|---|---|
| `CSV_Post_Importer` | `csv-post-importer.php` | Bootstrap, load dependencies |
| `CPI_Activator` | `class-cpi-activator.php` | Create temp upload dir on activate |
| `CPI_Deactivator` | `class-cpi-deactivator.php` | Cleanup on deactivate |
| `CPI_CSV_Parser` | `class-cpi-csv-parser.php` | Parse CSV → array of rows |
| `CPI_Post_Creator` | `class-cpi-post-creator.php` | `wp_insert_post()` + set meta + update mode |
| `CPI_Image_Handler` | `class-cpi-image-handler.php` | Find by filename in Media Library หรือ download จาก URL |
| `CPI_Category_Handler` | `class-cpi-category-handler.php` | Get/create category + assign mode |
| `CPI_Logger` | `class-cpi-logger.php` | บันทึก import log + error log ลง DB |
| `CPI_Admin` | `class-cpi-admin.php` | Register menu, handle AJAX, render views |

---

## 4. Import Flow (3 Steps)

```
[Step 1] Upload CSV
    → User uploads .csv file
    → CPI_CSV_Parser reads headers + preview 5 rows
    → Store CSV path in transient

[Step 2] Map Columns (Manual — user กำหนดทั้งหมดเอง)
    → แสดง CSV headers จริงใน dropdown ทุกช่อง
    → User map:
        - Post Title        (required)
        - Post Content      (optional)
        - Post Status       (optional, default = publish)
        - Featured Image    → เลือก mode: Filename / URL
        - Main Category     (optional)
        - Parent Sub-cat    (optional)
        - Sub-category      (optional)
        - Category Assign Mode
        - Import Mode       → Create / Update + unique key
    → Submit → trigger import via AJAX

[Step 3] Run Import + Show Result
    → CPI_Post_Creator loops rows
    → CPI_Category_Handler assigns categories ตาม assign mode
    → CPI_Image_Handler set featured image ตาม image mode
    → CPI_Logger บันทึก success/skip/error ทุก row
    → แสดง summary: success / skipped / error count
    → แสดง error log inline + link ไปหน้า Logs
```

---

## 5. Column Mapping Options (Step 2 — Manual)

User map CSV column ไปยัง mapping target เองทั้งหมด:

| Mapping Target | Required | Notes |
|---|---|---|
| `post_title` | ✅ Yes | ชื่อโพส |
| `post_content` | No | เนื้อหา |
| `post_status` | No | default = `publish` |
| `featured_image` | No | ดู Section 6 |
| `category` | No | Main category |
| `parent_sub_category` | No | Sub-category ระดับ 1 |
| `sub_category` | No | Sub-category ระดับ 2 |

**Category Assign Mode** (เลือก 1):

| Mode | พฤติกรรม |
|---|---|
| All levels | Assign ทุก level ที่มีข้อมูล (main + parent_sub + sub) |
| Deepest only | Assign เฉพาะ level ที่ลึกที่สุดที่มีข้อมูล |
| Custom | User ติ๊กเองว่าจะ assign level ไหนบ้าง |

**Import Mode** (เลือก 1):

| Mode | พฤติกรรม |
|---|---|
| Create new | สร้าง post ใหม่เสมอ |
| Update existing | หา post ที่มีอยู่ด้วย unique key แล้ว update |

**Unique Key สำหรับ Update** (เลือก 1):

| Key | คำอธิบาย |
|---|---|
| `post_title` | เทียบจากชื่อโพส |
| `post_id` | เทียบจาก ID (ต้องมี column ID ใน CSV) |
| `post_slug` | เทียบจาก slug |
| `custom_meta` | เทียบจาก custom field — user ระบุ meta key เอง |

---

## 6. Image Handling Strategy

User เลือก image mode ใน Step 2:

### Mode A — Filename (ค้นจาก Media Library)
```php
// รับ filename จาก CSV เช่น "photo.webp"
// ค้นหา attachment ใน DB ด้วย 3 วิธีตามลำดับ:
// 1. post_title ของ attachment (ชื่อไฟล์ไม่มี extension)
// 2. guid (URL เต็ม contains filename)
// 3. _wp_attached_file meta (relative path contains filename)
// พบแล้ว → set_post_thumbnail($post_id, $attach_id)
// ไม่พบ → บันทึก error log แต่ไม่หยุด import
```

### Mode B — URL (Download + Import)
```php
// รับ URL จาก CSV เช่น "https://example.com/photo.webp"
// ใช้ media_sideload_image() download เข้า Media Library
// set_post_thumbnail($post_id, $attach_id)
// ถ้า download ล้มเหลว → บันทึก error log แต่ไม่หยุด import
```

**หมายเหตุ:** ไม่ใช้ path เต็ม เพราะ WordPress เก็บรูปใน `/ปี/เดือน/` ที่ต่างกัน

---

## 7. Category Handling Strategy

```php
// รับ slug หรือชื่อ category จาก CSV
// ถ้า category มีอยู่แล้ว → ใช้ term_id ที่มี
// ถ้าไม่มี → สร้างใหม่ด้วย wp_insert_term()
// ลำดับสร้าง: main → parent_sub → sub (nested parent)

// Assign Mode:
// - All levels: wp_set_post_terms([main_id, parent_id, sub_id])
// - Deepest only: wp_set_post_terms([deepest_id ที่มีข้อมูล])
// - Custom: wp_set_post_terms([ids ที่ user ติ๊กไว้])

// ถ้า field ว่าง → ข้าม level นั้น ไม่ error
```

---

## 8. Error Log & Logging Strategy

### CPI_Logger
- บันทึกลง WordPress option หรือ custom DB table: `wp_cpi_logs`
- เก็บ: `import_id`, `row_number`, `filename`, `status`, `message`, `created_at`

### Status Types

| Status | ความหมาย |
|---|---|
| `success` | สร้าง/อัปเดต post สำเร็จ |
| `updated` | Update post เดิมสำเร็จ |
| `skipped` | ข้ามเพราะ unique key ซ้ำและ mode = create |
| `error` | เกิด error ระหว่าง insert/update |
| `image_error` | สร้าง post สำเร็จแต่ featured image ล้มเหลว |
| `category_error` | สร้าง post สำเร็จแต่ category assign ล้มเหลว |

### หน้า Logs (page-logs.php)
- แสดง log ทุก import run
- Filter ได้ด้วย: status / import date / import_id
- มีปุ่ม Clear logs
- แสดง row ที่ error พร้อม message และ CSV row data

---

## 9. Admin Menu

```
WordPress Admin
└── Tools
    └── CSV Post Importer
        ├── Import              ← Step 1-3 (page-import / mapping / result)
        └── Import Logs         ← Error log viewer (page-logs.php)
```

---

## 10. Version History

| Version | Date | Description |
|---|---|---|
| 1.0.0 | 2026-04-25 | Initial Master Architecture |
| 1.1.0 | 2026-04-25 | เพิ่ม image mode (filename/URL), update mode + unique key (title/id/slug/meta), category assign mode (all/deepest/custom), CPI_Logger, page-logs.php |

---

## 11. Chat Splitting Guide

| Chat | งาน | ไฟล์ที่สร้าง/แก้ |
|---|---|---|
| **Chat A** | Plugin bootstrap + Activator + Logger | `csv-post-importer.php`, `class-cpi-activator.php`, `class-cpi-deactivator.php`, `class-cpi-logger.php`, `CHANGELOG.md` |
| **Chat B** | CSV Parser + Admin UI (Upload + Mapping) | `class-cpi-csv-parser.php`, `class-cpi-admin.php`, `page-import.php`, `page-mapping.php`, `cpi-admin.css`, `cpi-admin.js` |
| **Chat C** | Post Creator + Category Handler | `class-cpi-post-creator.php`, `class-cpi-category-handler.php` |
| **Chat D** | Image Handler + Result Page + Log Page | `class-cpi-image-handler.php`, `page-result.php`, `page-logs.php` |
| **Chat E** | Testing + Bug fixes | ไฟล์ที่มีปัญหา (ขอจาก user ก่อนแก้) |

---

## 12. Hooks & Filters (Planned)

| Hook | Type | Description |
|---|---|---|
| `cpi_before_insert_post` | action | ก่อนสร้าง post แต่ละรายการ |
| `cpi_after_insert_post` | action | หลังสร้าง post สำเร็จ |
| `cpi_before_update_post` | action | ก่อน update post |
| `cpi_after_update_post` | action | หลัง update post สำเร็จ |
| `cpi_row_data` | filter | แก้ไข row data ก่อน insert |
| `cpi_image_search_args` | filter | ปรับ WP_Query สำหรับค้นรูป |
| `cpi_category_assign_ids` | filter | ปรับ term ids ก่อน assign |

---

## 13. Coding Standards

- PHP: WordPress Coding Standards
- Prefix ทุก function/class/hook ด้วย `cpi_` หรือ `CPI_`
- Nonce ทุก form และ AJAX request
- Sanitize input ทุกตัวก่อนใช้
- Escape output ทุกตัวก่อน echo
- ใส่ `@since`, `@param`, `@return` ใน docblock
- Error ทุกจุดต้อง log ผ่าน `CPI_Logger` เสมอ — ห้าม silent fail

---

## 14. กฎสำหรับการพัฒนา

1. ✅ **อ่าน Master นี้ก่อนเริ่มทำงานทุกครั้ง**
2. ✅ **ใช้ชื่อไฟล์ / class / function ตาม Master เท่านั้น**
3. ✅ **เพิ่ม version header ทุกครั้งที่แก้ไฟล์**
4. ✅ **สรุป changelog หลังแก้เสร็จ และอัปเดตไฟล์ CHANGELOG.md (ห้ามเขียนทับ)**
5. ✅ **ถาม user ถ้าไม่แน่ใจเรื่อง version**
6. ✅ **🔴 กฎ Version Control (สำคัญมาก):**
   - **ก่อนแก้ไขไฟล์ใดๆ** → บอก user ว่าต้องการไฟล์ไหน → รอ user ส่งเวอร์ชันล่าสุดมาก่อน
   - **ห้ามอ้างอิงไฟล์จาก context/memory** ของ Claude เพราะอาจเป็นเวอร์ชันเก่า
   - **ถ้าสร้างไฟล์ใหม่ทั้งหมด** → ไม่ต้องขอ (ไม่มี version conflict)
   - **ถ้าแก้ไขไฟล์ที่มีอยู่** → ต้องขอเวอร์ชันล่าสุดจาก user ก่อนเสมอ

### เมื่อเริ่ม Chat ใหม่
```
1. บอก Claude: "อ่าน Master Architecture ก่อน"
2. ระบุว่าจะทำงาน Chat ไหน (A/B/C/D/E)
3. ตรวจสอบ version ปัจจุบันจาก Master (Section 10)
4. 🔴 ถ้าจะแก้ไขไฟล์ที่มีอยู่ → บอก user ว่าต้องการไฟล์ไหน → รอรับก่อนเริ่ม
5. จบ Chat → สรุป changelog สำหรับอัปเดต Master
```
