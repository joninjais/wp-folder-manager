<?php
if (!defined('ABSPATH')) {
    exit;
}

// สร้าง Folder
add_action('wp_ajax_wpfm_create_folder', 'wpfm_ajax_create_folder');
function wpfm_ajax_create_folder() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error('No permission');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    
    $name = sanitize_text_field($_POST['name']);
    $parent_id = intval($_POST['parent_id']);
    $icon = sanitize_text_field($_POST['icon']);
    $color = sanitize_hex_color($_POST['color']);
    
    $result = $wpdb->insert($table_name, array(
        'name' => $name,
        'parent_id' => $parent_id,
        'icon' => $icon,
        'color' => $color
    ));
    
    if ($result) {
        $folder_id = $wpdb->insert_id;
        $folder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $folder_id
        ));
        
        wp_send_json_success(array(
            'id' => $folder_id,
            'folder' => $folder,
            'message' => 'สร้าง folder สำเร็จ'
        ));
    } else {
        wp_send_json_error('ไม่สามารถสร้าง folder ได้');
    }
}

// อัพเดท Folder
add_action('wp_ajax_wpfm_update_folder', 'wpfm_ajax_update_folder');
function wpfm_ajax_update_folder() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error('No permission');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    
    $id = intval($_POST['id']);
    $name = sanitize_text_field($_POST['name']);
    $icon = sanitize_text_field($_POST['icon']);
    $color = sanitize_hex_color($_POST['color']);
    
    $result = $wpdb->update(
        $table_name,
        array(
            'name' => $name,
            'icon' => $icon,
            'color' => $color
        ),
        array('id' => $id)
    );
    
    if ($result !== false) {
        $folder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
        
        wp_send_json_success(array(
            'folder' => $folder,
            'message' => 'อัพเดท folder สำเร็จ'
        ));
    } else {
        wp_send_json_error('ไม่สามารถอัพเดท folder ได้');
    }
}

// ลบ Folder
add_action('wp_ajax_wpfm_delete_folder', 'wpfm_ajax_delete_folder');
function wpfm_ajax_delete_folder() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error('No permission');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    $id = intval($_POST['id']);
    
    // ตรวจสอบว่ามี subfolder หรือไม่
    $has_children = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE parent_id = %d",
        $id
    ));
    
    if ($has_children > 0) {
        wp_send_json_error('ไม่สามารถลบได้ เพราะมี subfolder อยู่');
    }
    
    // ลบความสัมพันธ์กับไฟล์
    $wpdb->delete($relation_table, array('folder_id' => $id));
    
    // ลบ folder
    $result = $wpdb->delete($table_name, array('id' => $id));
    
    if ($result) {
        wp_send_json_success('ลบ folder สำเร็จ');
    } else {
        wp_send_json_error('ไม่สามารถลบ folder ได้');
    }
}

// ดึงข้อมูล Folder
add_action('wp_ajax_wpfm_get_folder', 'wpfm_ajax_get_folder');
function wpfm_ajax_get_folder() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    $id = intval($_POST['id']);
    
    $folder = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $id
    ));
    
    if ($folder) {
        wp_send_json_success($folder);
    } else {
        wp_send_json_error('ไม่พบ folder');
    }
}

// ดึงไฟล์ใน Folder
add_action('wp_ajax_wpfm_get_folder_files', 'wpfm_ajax_get_folder_files');
function wpfm_ajax_get_folder_files() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    global $wpdb;
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    $folder_id = intval($_POST['folder_id']);
    
    $attachments = $wpdb->get_col($wpdb->prepare(
        "SELECT attachment_id FROM $relation_table WHERE folder_id = %d",
        $folder_id
    ));
    
    $files = array();
    foreach ($attachments as $attachment_id) {
        $file_url = wp_get_attachment_url($attachment_id);
        $file_type = get_post_mime_type($attachment_id);
        $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        
        // ใช้ไอคอนสำหรับไฟล์ที่ไม่ใช่รูปภาพ
        if (!$thumb_url) {
            $thumb_url = wp_mime_type_icon($file_type);
        }
        
        $file = array(
            'id' => $attachment_id,
            'title' => get_the_title($attachment_id),
            'url' => $file_url,
            'thumb' => $thumb_url,
            'type' => $file_type,
            'size' => size_format(filesize(get_attached_file($attachment_id))),
            'date' => get_the_date('d/m/Y H:i', $attachment_id)
        );
        $files[] = $file;
    }
    
    wp_send_json_success($files);
}

// อัพโหลดไฟล์เข้า Folder
add_action('wp_ajax_wpfm_upload_files', 'wpfm_ajax_upload_files');
function wpfm_ajax_upload_files() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error('No permission');
    }
    
    $folder_id = intval($_POST['folder_id']);
    
    if (empty($_FILES['files'])) {
        wp_send_json_error('ไม่มีไฟล์ที่จะอัพโหลด');
    }
    
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $uploaded_files = array();
    $errors = array();
    
    $files = $_FILES['files'];
    $file_count = count($files['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $files['name'][$i] . ': Upload error';
            continue;
        }
        
        $file = array(
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        );
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            $errors[] = $files['name'][$i] . ': ' . $upload['error'];
            continue;
        }
        
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name(pathinfo($files['name'][$i], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (is_wp_error($attachment_id)) {
            $errors[] = $files['name'][$i] . ': ' . $attachment_id->get_error_message();
            continue;
        }
        
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        // เพิ่มความสัมพันธ์กับ folder
        global $wpdb;
        $relation_table = $wpdb->prefix . 'media_folder_relations';
        $wpdb->insert($relation_table, array(
            'attachment_id' => $attachment_id,
            'folder_id' => $folder_id
        ));
        
        $uploaded_files[] = array(
            'id' => $attachment_id,
            'name' => $files['name'][$i],
            'url' => $upload['url']
        );
    }
    
    wp_send_json_success(array(
        'uploaded' => $uploaded_files,
        'errors' => $errors,
        'total' => $file_count,
        'success_count' => count($uploaded_files)
    ));
}

// ลบไฟล์
add_action('wp_ajax_wpfm_delete_files', 'wpfm_ajax_delete_files');
function wpfm_ajax_delete_files() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('delete_posts')) {
        wp_send_json_error('No permission');
    }
    
    $file_ids = isset($_POST['file_ids']) ? array_map('intval', $_POST['file_ids']) : array();
    
    if (empty($file_ids)) {
        wp_send_json_error('ไม่มีไฟล์ที่จะลบ');
    }
    
    global $wpdb;
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    
    $deleted = 0;
    $errors = array();
    
    foreach ($file_ids as $file_id) {
        // ลบความสัมพันธ์กับ folder
        $wpdb->delete($relation_table, array('attachment_id' => $file_id));
        
        // ลบไฟล์จริง
        $result = wp_delete_attachment($file_id, true);
        
        if ($result) {
            $deleted++;
        } else {
            $errors[] = 'Cannot delete file ID: ' . $file_id;
        }
    }
    
    wp_send_json_success(array(
        'deleted' => $deleted,
        'errors' => $errors,
        'message' => "ลบไฟล์สำเร็จ {$deleted} ไฟล์"
    ));
}

// ย้ายไฟล์ไปยัง Folder อื่น (หลายไฟล์)
add_action('wp_ajax_wpfm_move_files', 'wpfm_ajax_move_files');
function wpfm_ajax_move_files() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error('No permission');
    }
    
    $file_ids = isset($_POST['file_ids']) ? array_map('intval', $_POST['file_ids']) : array();
    $target_folder_id = intval($_POST['target_folder_id']);
    
    if (empty($file_ids)) {
        wp_send_json_error('ไม่มีไฟล์ที่จะย้าย');
    }
    
    global $wpdb;
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    
    $moved = 0;
    
    foreach ($file_ids as $file_id) {
        // ลบความสัมพันธ์เก่า
        $wpdb->delete($relation_table, array('attachment_id' => $file_id));
        
        // เพิ่มความสัมพันธ์ใหม่
        $result = $wpdb->insert($relation_table, array(
            'attachment_id' => $file_id,
            'folder_id' => $target_folder_id
        ));
        
        if ($result) {
            $moved++;
        }
    }
    
    wp_send_json_success(array(
        'moved' => $moved,
        'message' => "ย้ายไฟล์สำเร็จ {$moved} ไฟล์"
    ));
}

// ย้ายไฟล์ไปยัง Folder อื่น (ไฟล์เดี่ยว - สำหรับ drag and drop)
add_action('wp_ajax_wpfm_move_file', 'wpfm_ajax_move_file');
function wpfm_ajax_move_file() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error('No permission');
    }
    
    $file_id = intval($_POST['file_id']);
    $target_folder_id = intval($_POST['target_folder_id']);
    
    if (!$file_id) {
        wp_send_json_error('ไม่มีไฟล์ที่จะย้าย');
    }
    
    global $wpdb;
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    
    // ลบความสัมพันธ์เก่า
    $wpdb->delete($relation_table, array('attachment_id' => $file_id));
    
    // เพิ่มความสัมพันธ์ใหม่
    $result = $wpdb->insert($relation_table, array(
        'attachment_id' => $file_id,
        'folder_id' => $target_folder_id
    ));
    
    if ($result) {
        $file_title = get_the_title($file_id);
        $folder_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}media_folders WHERE id = %d",
            $target_folder_id
        ));
        
        wp_send_json_success(array(
            'message' => "ย้าย \"{$file_title}\" ไปยัง \"{$folder_name}\" สำเร็จ"
        ));
    } else {
        wp_send_json_error('ไม่สามารถย้ายไฟล์ได้');
    }
}

// เพิ่มไฟล์จาก Media Library เข้า Folder
add_action('wp_ajax_wpfm_add_to_folder', 'wpfm_ajax_add_to_folder');
function wpfm_ajax_add_to_folder() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error('No permission');
    }
    
    $file_ids = isset($_POST['file_ids']) ? array_map('intval', $_POST['file_ids']) : array();
    $folder_id = intval($_POST['folder_id']);
    
    if (empty($file_ids)) {
        wp_send_json_error('ไม่มีไฟล์ที่จะเพิ่ม');
    }
    
    global $wpdb;
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    
    $added = 0;
    
    foreach ($file_ids as $file_id) {
        // ตรวจสอบว่ามีความสัมพันธ์อยู่แล้วหรือไม่
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $relation_table WHERE attachment_id = %d AND folder_id = %d",
            $file_id,
            $folder_id
        ));
        
        if (!$exists) {
            $result = $wpdb->insert($relation_table, array(
                'attachment_id' => $file_id,
                'folder_id' => $folder_id
            ));
            
            if ($result) {
                $added++;
            }
        }
    }
    
    wp_send_json_success(array(
        'added' => $added,
        'message' => "เพิ่มไฟล์สำเร็จ {$added} ไฟล์"
    ));
}

// ดึงจำนวนไฟล์ใน folder
add_action('wp_ajax_wpfm_get_folder_file_count', 'wpfm_ajax_get_folder_file_count');
function wpfm_ajax_get_folder_file_count() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    global $wpdb;
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    $folder_id = intval($_POST['folder_id']);
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $relation_table WHERE folder_id = %d",
        $folder_id
    ));
    
    wp_send_json_success(array('count' => intval($count)));
}

// ดึง HTML ของ folder tree
add_action('wp_ajax_wpfm_get_folder_tree', 'wpfm_ajax_get_folder_tree');
function wpfm_ajax_get_folder_tree() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    ob_start();
    wpfm_render_folder_tree_recursive();
    $html = ob_get_clean();
    
    wp_send_json_success(array('html' => $html));
}

function wpfm_render_folder_tree_recursive($parent_id = 0, $level = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    
    $folders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE parent_id = %d ORDER BY name ASC",
        $parent_id
    ));
    
    if (empty($folders)) {
        if ($level === 0) {
            echo '<p class="wpfm-empty-tree">ยังไม่มี folder <br>คลิก "สร้าง Folder" เพื่อเริ่มต้น</p>';
        }
        return;
    }
    
    echo '<ul class="wpfm-tree-level-' . $level . '">';
    foreach ($folders as $folder) {
        $has_children = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE parent_id = %d",
            $folder->id
        ));
        
        $relation_table = $wpdb->prefix . 'media_folder_relations';
        $file_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $relation_table WHERE folder_id = %d",
            $folder->id
        ));
        
        echo '<li class="wpfm-folder-item" data-folder-id="' . $folder->id . '">';
        echo '<div class="wpfm-folder-row">';
        
        if ($has_children > 0) {
            echo '<span class="wpfm-toggle dashicons dashicons-arrow-right"></span>';
        } else {
            echo '<span class="wpfm-toggle-placeholder"></span>';
        }
        
        echo '<span class="wpfm-folder-icon" style="color:' . esc_attr($folder->color) . '">' . $folder->icon . '</span>';
        echo '<span class="wpfm-folder-name">' . esc_html($folder->name) . '</span>';
        echo '<span class="wpfm-file-badge">' . $file_count . '</span>';
        echo '<div class="wpfm-folder-actions">';
        echo '<span class="dashicons dashicons-edit wpfm-edit-folder" data-folder-id="' . $folder->id . '" title="แก้ไข"></span>';
        echo '<span class="dashicons dashicons-plus wpfm-add-subfolder" data-folder-id="' . $folder->id . '" title="เพิ่ม Subfolder"></span>';
        echo '<span class="dashicons dashicons-trash wpfm-delete-folder" data-folder-id="' . $folder->id . '" title="ลบ"></span>';
        echo '</div>';
        echo '</div>';
        
        if ($has_children > 0) {
            echo '<div class="wpfm-subfolder" style="display:none;">';
            wpfm_render_folder_tree_recursive($folder->id, $level + 1);
            echo '</div>';
        }
        
        echo '</li>';
    }
    echo '</ul>';
}

// ดึง Media Sidebar HTML สำหรับรีเฟรช
add_action("wp_ajax_wpfm_get_media_sidebar", "wpfm_ajax_get_media_sidebar");
function wpfm_ajax_get_media_sidebar() {
    check_ajax_referer("wpfm_nonce", "nonce");
    
    if (!current_user_can("upload_files")) {
        wp_send_json_error("No permission");
    }
    
    // สร้าง instance ของ class เพื่อใช้ method
    $folder_manager = new WPFM_Folder_Manager();
    
    // ใช้ reflection เพื่อเรียก private method
    $reflection = new ReflectionClass($folder_manager);
    $method = $reflection->getMethod("render_media_folder_tree");
    $method->setAccessible(true);
    
    $count = wp_count_posts("attachment");
    $total_count = $count->inherit;
    
    $html = "<div class=\"wpfm-folder-item-media wpfm-all-files active\" data-folder-id=\"0\">
        <span class=\"wpfm-folder-icon\">📂</span>
        <span class=\"wpfm-folder-name\">All Files</span>
        <span class=\"wpfm-file-count\">" . $total_count . "</span>
    </div>";
    
    $html .= $method->invoke($folder_manager);
    
    wp_send_json_success(array(
        "html" => $html
    ));
}

// ดึง folder tree สำหรับ Media Modal
add_action('wp_ajax_wpfm_get_modal_folders', 'wpfm_ajax_get_modal_folders');
function wpfm_ajax_get_modal_folders() {
    check_ajax_referer('wpfm_nonce', 'nonce');
    
    if (!current_user_can("upload_files")) {
        wp_send_json_error("No permission");
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    
    $html = wpfm_render_modal_folders(0);
    
    wp_send_json_success(array(
        "html" => $html
    ));
}

function wpfm_render_modal_folders($parent_id = 0, $level = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    
    $folders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE parent_id = %d ORDER BY name ASC",
        $parent_id
    ));
    
    if (empty($folders)) {
        return '';
    }
    
    $output = '';
    
    foreach ($folders as $folder) {
        $file_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $relation_table WHERE folder_id = %d",
            $folder->id
        ));
        
        $has_children = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE parent_id = %d",
            $folder->id
        )) > 0;
        
        $indent_style = $level > 0 ? 'style="padding-left: ' . ($level * 20) . 'px;"' : '';
        
        $output .= '<div class="wpfm-modal-folder-item" data-folder-id="' . $folder->id . '" ' . $indent_style . '>';
        
        if ($has_children) {
            $output .= '<span class="wpfm-modal-toggle-arrow">▶</span>';
        } else {
            $output .= '<span class="wpfm-modal-toggle-arrow wpfm-no-children"></span>';
        }
        
        $output .= '<span class="wpfm-folder-icon" style="color: ' . esc_attr($folder->color) . ';">' . esc_html($folder->icon) . '</span>';
        $output .= '<span class="wpfm-folder-name">' . esc_html($folder->name) . '</span>';
        $output .= '</div>';
        
        // Render children
        if ($has_children) {
            $output .= '<div class="wpfm-modal-subfolder-container" style="display: none;">';
            $output .= wpfm_render_modal_folders($folder->id, $level + 1);
            $output .= '</div>';
        }
    }
    
    return $output;
}
