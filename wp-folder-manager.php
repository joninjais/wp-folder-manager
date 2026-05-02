<?php
/**
 * Plugin Name: WP Folder Manager
 * Plugin URI: https://example.com/wp-folder-manager
 * Description: ระบบจัดการ folder และ subfolder สำหรับ WordPress Media Library ที่ใช้งานง่าย พร้อมไอคอนแสดงผล
 * Version: 1.0.4
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 * Text Domain: wp-folder-manager
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// กำหนดค่าคงที่
define('WPFM_VERSION', '1.0.4');
define('WPFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFM_PLUGIN_URL', plugin_dir_url(__FILE__));

// โหลดไฟล์ที่จำเป็น
require_once WPFM_PLUGIN_DIR . 'includes/class-folder-manager.php';
require_once WPFM_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once WPFM_PLUGIN_DIR . 'includes/class-folders-import.php';

// เริ่มต้น Plugin
function wpfm_init() {
    $folder_manager = new WPFM_Folder_Manager();
    
    // เพิ่มเมนู Import
    if (is_admin()) {
        add_action('admin_menu', 'wpfm_add_import_menu');
    }
}
add_action('plugins_loaded', 'wpfm_init');

// เพิ่มเมนู Import Folders
function wpfm_add_import_menu() {
    add_submenu_page(
        'wp-folder-manager',
        'Import from Folders Plugin',
        'Import Folders',
        'manage_options',
        'wpfm-import',
        'wpfm_render_import_page'
    );
}

// Render หน้า Import
function wpfm_render_import_page() {
    $import = new WPFM_Folders_Import();
    $import->render_import_page();
}

// สร้างตารางฐานข้อมูลเมื่อติดตั้ง
function wpfm_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'media_folders';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        parent_id bigint(20) DEFAULT 0,
        icon varchar(100) DEFAULT 'folder',
        color varchar(20) DEFAULT '#FFA500',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY parent_id (parent_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // สร้างตารางความสัมพันธ์ระหว่างไฟล์และโฟลเดอร์
    $relation_table = $wpdb->prefix . 'media_folder_relations';
    $sql_relation = "CREATE TABLE IF NOT EXISTS $relation_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        attachment_id bigint(20) NOT NULL,
        folder_id bigint(20) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY attachment_folder (attachment_id, folder_id),
        KEY folder_id (folder_id)
    ) $charset_collate;";

    dbDelta($sql_relation);
}
register_activation_hook(__FILE__, 'wpfm_activate');

// ลบตารางเมื่อถอนการติดตั้ง
function wpfm_uninstall() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}media_folders");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}media_folder_relations");
}
register_uninstall_hook(__FILE__, 'wpfm_uninstall');