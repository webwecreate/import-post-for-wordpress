# CHANGELOG
# Plugin: CSV Post Importer
# Format: [Version] Date — Description
# กฎ: ห้ามเขียนทับ — append only

---

## [1.5.7] 2026-04-26
### Fixed (Chat E — wp_cpi_logs table doesn't exist)
- `class-cpi-activator.php`: เพิ่ม maybe_setup() — เรียก create_upload_dir()
  + create_log_table() เมื่อ cpi_db_version option ไม่ตรงกับ CPI_VERSION;
  ใช้ dbDelta() ซึ่งปลอดภัย ไม่ทับ table ที่มีข้อมูลอยู่แล้ว
- `csv-post-importer.php`: cpi_init() เรียก CPI_Activator::maybe_setup()
  ทุกครั้งที่โหลด — ครอบคลุมกรณี upload ไฟล์ทับผ่าน FTP โดยไม่ deactivate
  (activation hook ไม่ยิง); อัปเดต CPI_VERSION → 1.5.7

---
## [1.5.6] 2026-04-26
### Fixed (Chat E — Cumulative fix: menu + preview + import loop)

- `admin/class-cpi-admin.php`:
  [1.5.1] register_menu(): add_management_page() → add_menu_page() standalone
          top-level; dashicons-media-spreadsheet; position 30; submenu Import +
          Import Logs ใต้ csv-post-importer
  [1.5.1] enqueue_scripts(): hook suffix tools_page_ → toplevel_page_ และ
          csv-importer_page_
  [1.5.2] run_import_loop(): create_or_update() เพิ่ม $import_id arg ที่ขาด
  [1.5.2] handle_import(): redirect tools.php → admin.php
  [1.5.2] handle_upload(): get_preview($path,5) → parse() ดึงครบทุก row;
          เพิ่ม instantiate $parser; get_headers() แยก
  [1.5.3] run_import_loop(): CPI_Logger::STATUS_* ทุกตัว → string literals
          'success','updated','skipped','error','image_error','category_error'
  [1.5.5] run_import_loop(): assign_categories() → assign(); สร้าง
          $category_data (main/parent_sub/sub) จาก $row_data; extract
          assign_mode + custom_levels จาก $mapping; is_wp_error → !['success']
  [1.5.5] run_import_loop(): set_featured_image เพิ่ม $import_id; 
          is_wp_error → !$img_result['success']

- `admin/views/page-mapping.php`:
  [1.5.4] preview table: เพิ่ม label "Total: N rows"; เพิ่มคอลัมน์ #
  [1.5.4] ปุ่ม "Back to Upload": tools.php → admin.php

---

## [1.5.4] 2026-04-26
### Fixed (Chat E — Preview ครบทุก record ใน Step 2)
- `admin/views/page-mapping.php`:
  - เพิ่ม label "Total: N rows" เหนือ preview table
  - เพิ่มคอลัมน์ # (row number) นำหน้าทุก row
  - แก้ปุ่ม "Back to Upload" URL จาก tools.php เป็น admin.php
  - (ทำงานร่วมกับ class-cpi-admin.php v1.5.3 ที่เปลี่ยน
    get_preview(5) เป็น parse() — ต้องอัปโหลด CSV ใหม่
    เพื่อล้าง transient เก่า)

---

## [1.5.3] 2026-04-26
### Fixed (Chat E — Fatal: Undefined constant CPI_Logger::STATUS_*)
- `class-cpi-admin.php`: แทนที่ CPI_Logger::STATUS_SUCCESS / STATUS_UPDATED /
  STATUS_SKIPPED / STATUS_ERROR / STATUS_IMAGE_ERROR / STATUS_CATEGORY_ERROR
  ด้วย string literals 'success' / 'updated' / 'skipped' / 'error' /
  'image_error' / 'category_error' เนื่องจาก CPI_Logger ไม่มี class constants
  เหล่านี้ — มีแค่ STATUSES array

---

## [1.5.2] 2026-04-26
### Fixed (Chat E — Import 500 + Preview ครบทุก record)
- `class-cpi-admin.php`:
  - Bug: run_import_loop() → create_or_update() ส่งแค่ 2 args แทน 3
    (ขาด $import_id) → PHP 8 TypeError → HTTP 500; เพิ่ม $import_id
  - Bug: redirect หลัง import ชี้ไป admin_url('tools.php') ซึ่งผิดหลังย้ายเมนู
    เป็น top-level; แก้เป็น admin_url('admin.php')
  - Bug: handle_upload() เรียก get_preview($path,5) hardcoded 5 rows;
    เปลี่ยนเป็น parse() ดึงทุก row + get_headers() แยก; เพิ่ม instantiate $parser
- `admin/js/cpi-admin.js`:
  - renderPreview() เพิ่มคอลัมน์ # (row number) นำหน้า; render ครบทุก row;
    แสดง "scroll to review all" เมื่อ row_count > 20

---

## [1.5.1] 2026-04-26
### Fixed (Chat E — Bug Fix: Menu not visible)
- `csv-post-importer.php`: uncomment ทุก require ใน cpi_load_dependencies()
  — class-cpi-csv-parser, class-cpi-post-creator, class-cpi-image-handler,
    class-cpi-category-handler, class-cpi-admin ล้วนถูก comment ไว้ตั้งแต่ Chat A
- `class-cpi-admin.php`: เปลี่ยน register_menu() จาก add_management_page() (Tools)
  เป็น add_menu_page() standalone top-level menu พร้อม dashicons-media-spreadsheet
  position 30; เพิ่ม submenu "Import" rename + "Import Logs"; แก้ enqueue_scripts()
  hook suffix จาก tools_page_ เป็น toplevel_page_ และ csv-importer_page_

---

## [1.5.0] 2026-04-26
### Added (Chat D — Image Handler + Result Page + Log Page)
- `class-cpi-image-handler.php` — CPI_Image_Handler:
  - set_featured_image($post_id, $value, $image_mode, $import_id) → {success, message}
  - find_by_filename($filename) — ค้น attachment 3 วิธี: post_title / guid LIKE / _wp_attached_file meta; apply_filters cpi_image_search_args ก่อน WP_Query
  - download_from_url($url, $post_id) → attachment_id|WP_Error — validate URL, require media.php on-demand, media_sideload_image()
  - Return array {success, message} เสมอ — ไม่ throw exception
- `admin/views/page-result.php` — Step 3 Import Result:
  - Resolve import_id จาก GET หรือ transient cpi_last_import_id_{user_id}
  - Summary cards: Total / Created / Updated / Skipped / Errors
  - Filter bar link-based: Errors Only (default) / All / แต่ละ status
  - Result table: Row# / Filename / Post Title / Status badge / Message (decode JSON)
  - ปุ่ม Import Again + View Full Logs
- `admin/views/page-logs.php` — Import Logs Viewer:
  - Filter bar: dropdown import run + dropdown status + Apply Filter
  - Mini summary cards ของ run ที่เลือก
  - Log table: Row# / Filename / Status badge / Message / Date; pagination 50 rows/page
  - Clear This Run + Clear All Logs via AJAX + confirm dialog + feedback + auto-reload
  - Empty state เมื่อไม่มี log

---

## [1.4.0] 2026-04-26
### Added (Chat C — Post Creator + Category Handler)
- `class-cpi-post-creator.php` — CPI_Post_Creator:
  - create_or_update($row_data, $mapping, $import_id) → {post_id, status, message}
  - find_existing_post($value, $unique_key, $meta_key) — ค้นด้วย post_title/post_id/post_slug/custom_meta
  - create_post($row_data) — wp_insert_post() + fires cpi_before/after_insert_post + save_extra_meta()
  - update_post($post_id, $row_data) — wp_update_post() + fires cpi_before/after_update_post + save_extra_meta()
  - apply_filters cpi_row_data ก่อน insert/update ทุกครั้ง
  - sanitize ทุก core field: post_title, post_content (wp_kses_post), post_status (whitelist), post_type, post_date, post_excerpt, post_name
  - extra fields (non-core) → update_post_meta() อัตโนมัติ
  - ถ้า error → throw Exception → caller (CPI_Admin) รับไป log ผ่าน CPI_Logger
- `class-cpi-category-handler.php` — CPI_Category_Handler:
  - assign($post_id, $category_data, $assign_mode, $custom_levels) → {success, message}
  - get_or_create_term($name, $parent_id) — ค้น slug → ค้น name (case-insensitive) → wp_insert_term(); handle term_exists error
  - build_term_tree($category_data) → {success, message, term_tree} — สร้าง hierarchy main→parent_sub→sub; ข้าม level ว่างได้
  - resolve_assign_ids($term_tree, $assign_mode, $custom_levels) — all/deepest/custom
  - apply_filters cpi_category_assign_ids ก่อน wp_set_post_terms
  - ถ้า error → return {success:false, message} → caller log ผ่าน CPI_Logger

---

## [1.3.0] 2026-04-26
### Added (Chat B — CSV Parser + Admin UI)
- `class-cpi-csv-parser.php` — CPI_CSV_Parser: parse(), get_headers(), get_preview($limit=5), count_rows(); UTF-8 BOM strip; path security validation ให้อยู่ใน CPI_UPLOAD_DIR เท่านั้น
- `class-cpi-admin.php` — CPI_Admin: register_menu() (Tools > CSV Post Importer + Import Logs), enqueue_scripts(), handle_upload() AJAX, handle_import() AJAX + run_import_loop() + map_row() + parse_mapping_config(), handle_clear_logs() AJAX; nonce ทุก endpoint; delegate ไปยัง CPI_Post_Creator / CPI_Category_Handler / CPI_Image_Handler / CPI_Logger
- `admin/views/page-import.php` — Step 1: drag & drop upload zone, xhr progress bar, preview table (render จาก JS), ปุ่ม Next
- `admin/views/page-mapping.php` — Step 2: mapping table 4 sections (Post Fields / Featured Image / Categories / Import Mode); radio Filename/URL; Category Assign Mode (All/Deepest/Custom); Import Mode (Create/Update) + Unique Key (post_title/post_id/post_slug/custom_meta); collapsible CSV preview
- `admin/css/cpi-admin.css` — styles: step indicator, cards, upload zone, progress, mapping table, radio groups, status badges, result stat cards, log page, responsive
- `admin/js/cpi-admin.js` — AJAX upload + drag & drop + xhr progress; preview table render; Import Mode toggle; unique key type toggle; assign mode toggle; image mode toggle; radio label highlight; Run Import AJAX; clear logs AJAX

---

## [1.2.0] 2026-04-25
### Added (Chat A — Bootstrap + Activator + Logger)
- `csv-post-importer.php` — Plugin bootstrap: constants (CPI_VERSION, CPI_PLUGIN_DIR, CPI_PLUGIN_URL, CPI_UPLOAD_DIR, CPI_LOG_TABLE), cpi_load_dependencies(), activation/deactivation hooks, cpi_init() via plugins_loaded
- `class-cpi-activator.php` — CPI_Activator::activate(): สร้าง uploads/ dir + .htaccess + index.php เพื่อป้องกัน direct access; สร้าง wp_cpi_logs table ด้วย dbDelta(); บันทึก cpi_db_version option
- `class-cpi-deactivator.php` — CPI_Deactivator::deactivate(): ลบไฟล์ .csv ใน uploads/ dir; placeholder สำหรับ clear cron events
- `class-cpi-logger.php` — CPI_Logger class:
  - `log($import_id, $row_number, $filename, $status, $message)` — เขียน log 1 row
  - `get_logs($import_id, $status, $limit, $offset)` — ดึง logs พร้อม filter
  - `get_summary($import_id)` — สรุป count แยกตาม status
  - `get_import_ids($limit)` — ดึง distinct import run IDs สำหรับ log viewer
  - `clear_logs($import_id)` — ลบ logs ของ run เดียว
  - `clear_all_logs()` — TRUNCATE ทั้งตาราง
  - `generate_import_id()` — สร้าง unique import ID รูปแบบ cpi_YYYYMMDD_HHiiss_xxxxxx
  - Status constants: success / updated / skipped / error / image_error / category_error

---

## [1.1.0] 2026-04-25
### Added
- Image mode: เลือกได้ระหว่าง Filename (ค้น Media Library) หรือ URL (download + import)
- Import mode: Create new / Update existing
- Unique key สำหรับ Update: post_title / post_id / post_slug / custom meta
- Category Assign Mode: All levels / Deepest only / Custom
- CPI_Logger class: บันทึก import log + error log ลง DB (wp_cpi_logs)
- หน้า Import Logs (page-logs.php): ดู log, filter, clear logs
- Status types: success / updated / skipped / error / image_error / category_error
- Hooks เพิ่ม: cpi_before_update_post, cpi_after_update_post, cpi_category_assign_ids

### Changed
- Admin menu เพิ่ม submenu "Import Logs"
- Section 5 Column Mapping เพิ่ม Assign Mode และ Import Mode
- Chat Splitting Guide: Chat A เพิ่ม class-cpi-logger.php, Chat D เพิ่ม page-logs.php
- Coding Standards เพิ่มกฎ: Error ทุกจุดต้อง log ผ่าน CPI_Logger

---

## [1.0.0] 2026-04-25
### Added
- Initial Master Architecture
- File structure, class map, import flow 3 steps
- Column mapping, image handling, category handling
- Hooks & Filters planned
- Coding standards และกฎ version control
