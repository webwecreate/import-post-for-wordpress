# CHANGELOG
# Plugin: CSV Post Importer
# Format: [Version] Date — Description
# กฎ: ห้ามเขียนทับ — append only


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
