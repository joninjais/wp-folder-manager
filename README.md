# WP Folder Manager - Import from Folders Plugin

## คุณสมบัติที่เพิ่มเข้ามา

เพิ่มฟีเจอร์ import ข้อมูล folder และไฟล์จาก **Folders plugin (by Premio)** เข้าสู่ **WP Folder Manager**

### ไฟล์ที่สร้างใหม่

1. **`includes/class-folders-import.php`** - Class สำหรับจัดการการ import
   - อ่านข้อมูล folder จาก taxonomy ของ Folders plugin (`media_folder`, `folder`, `post_folder`)
   - แปลงโครงสร้าง folder จาก WordPress taxonomy เป็น custom table
   - Import ความสัมพันธ์ระหว่างไฟล์และ folder
   - แสดงสถิติและตัวเลือกการ import

### ไฟล์ที่แก้ไข

1. **`wp-folder-manager.php`**
   - เพิ่ม require class-folders-import.php
   - เพิ่ม submenu "Import Folders" ใน Folder Manager
   - เพิ่มฟังก์ชัน `wpfm_add_import_menu()` และ `wpfm_render_import_page()`

## วิธีใช้งาน

### ขั้นตอนการ Import

1. ไปที่เมนู **Folder Manager > Import Folders** ใน WordPress Admin
2. ดูสถิติปัจจุบัน:
   - จำนวน folder ใน Folders plugin
   - จำนวน folder ใน WP Folder Manager
   - จำนวนไฟล์/ความสัมพันธ์
3. เลือก Taxonomy ที่ต้องการ import:
   - **Media Folders (media_folder)** - สำหรับ Media Library
   - **Page Folders (folder)** - สำหรับหน้า Page
   - **Post Folders (post_folder)** - สำหรับโพสต์
4. เลือกตัวเลือก:
   - ✅ **Skip duplicate folder names** - ข้าม folder ที่มีชื่อซ้ำ (แนะนำ)
   - ✅ **Import attachment relationships** - import ความสัมพันธ์ของไฟล์ (แนะนำ)
   - ⚠️ **Delete all existing folders** - ลบ folder เดิมทั้งหมดก่อน import (ระวัง!)
5. กด **Start Import**
6. รอให้กระบวนการเสร็จสิ้น จะแสดงผลลัพธ์:
   - จำนวน folder ที่ import สำเร็จ
   - จำนวน folder ที่ข้าม
   - จำนวน error (ถ้ามี)

### หลังจาก Import เสร็จ

- ตรวจสอบว่า folder ทั้งหมด import เข้ามาถูกต้อง
- ทดสอบเปิด Media Library ดูโครงสร้าง folder
- หากทุกอย่างเรียบร้อย สามารถ deactivate Folders plugin ได้

## โครงสร้างฐานข้อมูล

### Folders Plugin (Premio)
ใช้ระบบ **WordPress Taxonomy**:
- **wp_terms** - เก็บชื่อ folder
- **wp_term_taxonomy** - เก็บประเภทและความสัมพันธ์ parent-child
- **wp_term_relationships** - เก็บความสัมพันธ์ระหว่างไฟล์กับ folder

### WP Folder Manager
ใช้ระบบ **Custom Tables**:
- **wp_media_folders** - เก็บข้อมูล folder
  - `id` - รหัส folder
  - `name` - ชื่อ folder
  - `parent_id` - รหัส folder แม่
  - `icon` - ไอคอน (ถ้ามี)
  - `color` - สี (ถ้ามี)
  - `created_at`, `updated_at` - วันที่สร้างและแก้ไข
- **wp_media_folder_relations** - เก็บความสัมพันธ์ไฟล์กับ folder
  - `attachment_id` - รหัสไฟล์
  - `folder_id` - รหัส folder

## คุณสมบัติของ Class WPFM_Folders_Import

### Methods สำคัญ

1. **`is_folders_plugin_active()`** - เช็คว่า Folders plugin ติดตั้งหรือไม่
2. **`get_folders_data($taxonomy)`** - ดึงข้อมูล folder ทั้งหมดจาก taxonomy
3. **`get_folder_attachments($term_id, $taxonomy)`** - ดึงไฟล์ในแต่ละ folder
4. **`import_folders($taxonomy, $options)`** - ทำการ import
5. **`get_import_stats($taxonomy)`** - แสดงสถิติ
6. **`render_import_page()`** - แสดงหน้า UI

### Options ของการ Import

```php
$options = array(
    'delete_existing' => false,     // ลบ folder เดิมหรือไม่ (อันตราย!)
    'skip_duplicates' => true,      // ข้าม folder ชื่อซ้ำ (แนะนำ)
    'import_attachments' => true    // import ไฟล์ด้วยหรือไม่ (แนะนำ)
);
```

## ตัวอย่างการใช้งาน Programmatically

```php
// สร้าง instance
$import = new WPFM_Folders_Import();

// เช็คว่า Folders plugin active หรือไม่
if ($import->is_folders_plugin_active()) {
    
    // ดูสถิติก่อน import
    $stats = $import->get_import_stats('media_folder');
    echo "จำนวน folder: " . $stats['folders_plugin']['total_folders'];
    
    // ทำการ import
    $result = $import->import_folders('media_folder', array(
        'skip_duplicates' => true,
        'import_attachments' => true,
        'delete_existing' => false
    ));
    
    // ตรวจสอบผลลัพธ์
    if (!is_wp_error($result)) {
        echo "Import สำเร็จ: " . $result['imported'] . " folders";
        echo "ข้าม: " . $result['skipped'] . " folders";
    }
}
```

## การจัดการ Error

Class จะคืนค่า `WP_Error` เมื่อเกิดปัญหา:
- `plugin_not_active` - Folders plugin ไม่ได้เปิดใช้งาน
- `taxonomy_not_found` - ไม่พบ taxonomy ที่ระบุ
- `no_folders` - ไม่มี folder ให้ import

ตรวจสอบด้วย:
```php
$result = $import->import_folders('media_folder');
if (is_wp_error($result)) {
    echo $result->get_error_message();
}
```

## ข้อควรระวัง

1. **สำรองข้อมูลก่อน Import** - แม้จะมี option skip_duplicates แต่ควรสำรองฐานข้อมูลก่อน
2. **ตรวจสอบ Taxonomy** - ใช้ `media_folder` สำหรับ Media Library เป็นหลัก
3. **Delete Existing Option** - ใช้ด้วยความระมัดระวัง จะลบ folder เดิมทั้งหมด!
4. **Folder Names** - ถ้ามี folder ชื่อซ้ำใน Folders plugin จะถูกข้ามไป

## Compatibility

- **WordPress:** 5.0+
- **PHP:** 7.2+
- **Folders Plugin:** ต้องติดตั้งและเปิดใช้งาน
- **WP Folder Manager:** 1.0.0+

## ทดสอบแล้ว

- ✅ Import folder hierarchy (parent-child)
- ✅ Import attachment relationships
- ✅ Skip duplicate names
- ✅ Handle large number of folders
- ✅ Error handling

## Support

หากพบปัญหาหรือต้องการความช่วยเหลือ:
1. เช็คว่า Folders plugin active
2. เช็คว่ามี taxonomy `media_folder` ใน database
3. เช็คว่ามีสิทธิ์ `manage_options`
4. ดู PHP error log

---

**Updated:** 2024
**Version:** 1.0.0
