<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPFM_Folder_Manager {
    
    public function __construct() {
        // เพิ่ม Admin Menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // โหลด Scripts และ Styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // โหลด Scripts สำหรับ Elementor Editor (ใช้ wp_enqueue_scripts แทน admin_enqueue_scripts)
        add_action('elementor/editor/after_enqueue_scripts', array($this, 'enqueue_scripts_elementor'));
        
        // เพิ่มคอลัมน์ใน Media Library
        add_filter('manage_media_columns', array($this, 'add_folder_column'));
        add_action('manage_media_custom_column', array($this, 'show_folder_column'), 10, 2);
        
        // เพิ่ม Bulk Actions ใน Media Library
        add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);
        
        // เพิ่ม Sidebar ในหน้า Media Library
        add_action('admin_footer', array($this, 'add_media_sidebar'));
        
        // เพิ่ม body class สำหรับหน้า Media Library
        add_filter('admin_body_class', array($this, 'add_media_body_class'));
        
        // Filter Media Library query ตาม folder
        add_filter('ajax_query_attachments_args', array($this, 'filter_media_by_folder'));
        add_action('pre_get_posts', array($this, 'filter_media_library_query'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Folder Manager',
            'Folder Manager',
            'upload_files',
            'wp-folder-manager',
            array($this, 'render_admin_page'),
            'dashicons-category',
            30
        );
    }
    
    public function enqueue_scripts($hook) {
        // กำหนด dependency ตามหน้าที่ใช้งาน
        $js_deps = array('jquery');
        
        // หน้าที่ต้องการ media scripts
        $media_pages = array('toplevel_page_wp-folder-manager', 'upload.php', 'post.php', 'post-new.php');
        if (in_array($hook, $media_pages)) {
            wp_enqueue_media();
        }
        
        $css_ver = WPFM_VERSION . '.' . filemtime(WPFM_PLUGIN_DIR . 'css/style.css');
        $js_ver = WPFM_VERSION . '.' . filemtime(WPFM_PLUGIN_DIR . 'js/script.js');
        
        wp_enqueue_style('wpfm-style', WPFM_PLUGIN_URL . 'css/style.css', array(), $css_ver);
        wp_enqueue_script('wpfm-script', WPFM_PLUGIN_URL . 'js/script.js', $js_deps, $js_ver, true);
        
        wp_localize_script('wpfm-script', 'wpfm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpfm_nonce'),
            'upload_url' => admin_url('async-upload.php'),
            'folder_manager_url' => admin_url('admin.php?page=wp-folder-manager')
        ));
    }
    
    /**
     * Enqueue scripts for Elementor Editor
     * Elementor ใช้ wp_enqueue_scripts แทน admin_enqueue_scripts
     * และลบ admin_enqueue_scripts ทิ้ง ดังนั้นต้อง hook แยก
     */
    public function enqueue_scripts_elementor() {
        // ตรวจสอบว่ายังไม่ได้โหลด
        if (wp_script_is('wpfm-script', 'enqueued')) {
            return;
        }
        
        $css_ver = WPFM_VERSION . '.' . filemtime(WPFM_PLUGIN_DIR . 'css/style.css');
        $js_ver = WPFM_VERSION . '.' . filemtime(WPFM_PLUGIN_DIR . 'js/script.js');
        
        wp_enqueue_style('wpfm-style', WPFM_PLUGIN_URL . 'css/style.css', array(), $css_ver);
        wp_enqueue_script('wpfm-script', WPFM_PLUGIN_URL . 'js/script.js', array('jquery'), $js_ver, true);
        
        wp_localize_script('wpfm-script', 'wpfm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpfm_nonce'),
            'upload_url' => admin_url('async-upload.php'),
            'folder_manager_url' => admin_url('admin.php?page=wp-folder-manager')
        ));
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap wpfm-wrap">
            <h1>📁 Folder Manager</h1>
            
            <div class="wpfm-container">
                <div class="wpfm-sidebar">
                    <div class="wpfm-header">
                        <button id="wpfm-create-folder" class="button button-primary">
                            <span class="dashicons dashicons-plus"></span> สร้าง Folder
                        </button>
                    </div>
                    
                    <div id="wpfm-folder-tree" class="wpfm-folder-tree">
                        <?php $this->render_folder_tree(); ?>
                    </div>
                </div>
                
                <div class="wpfm-content">
                    <div class="wpfm-content-header">
                        <h2 id="wpfm-current-folder">All Files</h2>
                        <div class="wpfm-toolbar">
                            <button id="wpfm-upload-files" class="button button-primary">
                                <span class="dashicons dashicons-upload"></span> อัพโหลดไฟล์
                            </button>
                            <button id="wpfm-select-from-library" class="button">
                                <span class="dashicons dashicons-admin-media"></span> เลือกจาก Media Library
                            </button>
                            <div class="wpfm-file-count">
                                <span id="wpfm-selected-count">0</span> ไฟล์ถูกเลือก
                                <button id="wpfm-move-selected" class="button" style="display:none;">
                                    <span class="dashicons dashicons-move"></span> ย้ายไฟล์
                                </button>
                                <button id="wpfm-delete-selected" class="button" style="display:none;">
                                    <span class="dashicons dashicons-trash"></span> ลบที่เลือก
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="wpfm-upload-progress" class="wpfm-upload-progress" style="display:none;">
                        <div class="wpfm-progress-header">
                            <span>กำลังอัพโหลด...</span>
                            <span id="wpfm-progress-text">0%</span>
                        </div>
                        <div class="wpfm-progress-bar">
                            <div id="wpfm-progress-fill"></div>
                        </div>
                        <div id="wpfm-upload-status"></div>
                    </div>
                    
                    <div id="wpfm-file-list" class="wpfm-file-list">
                        <p class="wpfm-empty-state">เลือก folder เพื่อดูไฟล์</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal สำหรับสร้าง/แก้ไข Folder -->
        <div id="wpfm-folder-modal" class="wpfm-modal" style="display:none;">
            <div class="wpfm-modal-content">
                <span class="wpfm-close">&times;</span>
                <h2 id="wpfm-modal-title">สร้าง Folder ใหม่</h2>
                <form id="wpfm-folder-form">
                    <input type="hidden" id="wpfm-folder-id" name="folder_id">
                    <input type="hidden" id="wpfm-parent-id" name="parent_id" value="0">
                    
                    <div class="wpfm-form-group">
                        <label for="wpfm-folder-name">ชื่อ Folder:</label>
                        <input type="text" id="wpfm-folder-name" name="folder_name" required>
                    </div>
                    
                    <div class="wpfm-form-group">
                        <label>เลือกไอคอน:</label>
                        <div class="wpfm-icon-selector">
                            <label><input type="radio" name="icon" value="📁" checked> 📁 Folder</label>
                            <!-- <label><input type="radio" name="icon" value="📂"> 📂 Open Folder</label>
                            <label><input type="radio" name="icon" value="📷"> 📷 Camera</label>
                            <label><input type="radio" name="icon" value="🎨"> 🎨 Art</label>
                            <label><input type="radio" name="icon" value="📄"> 📄 Document</label>
                            <label><input type="radio" name="icon" value="🎵"> 🎵 Music</label>
                            <label><input type="radio" name="icon" value="🎬"> 🎬 Video</label>
                            <label><input type="radio" name="icon" value="⭐"> ⭐ Star</label> -->
                        </div>
                    </div>
                    
                    <div class="wpfm-form-group">
                        <label for="wpfm-folder-color">สีของ Folder:</label>
                        <input type="color" id="wpfm-folder-color" name="color" value="#FFA500">
                    </div>
                    
                    <div class="wpfm-form-actions">
                        <button type="submit" class="button button-primary">บันทึก</button>
                        <button type="button" class="button wpfm-cancel">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal สำหรับย้ายไฟล์ -->
        <div id="wpfm-move-modal" class="wpfm-modal" style="display:none;">
            <div class="wpfm-modal-content">
                <span class="wpfm-close">&times;</span>
                <h2>ย้ายไฟล์ไปยัง Folder</h2>
                <div class="wpfm-form-group">
                    <label for="wpfm-target-folder">เลือก Folder ปลายทาง:</label>
                    <select id="wpfm-target-folder" class="wpfm-folder-select">
                        <option value="">-- เลือก Folder --</option>
                        <?php $this->render_folder_options(); ?>
                    </select>
                </div>
                <p class="wpfm-move-info">
                    จะย้าย <strong><span id="wpfm-move-count">0</span></strong> ไฟล์
                </p>
                <div class="wpfm-form-actions">
                    <button type="button" id="wpfm-confirm-move" class="button button-primary">ย้ายไฟล์</button>
                    <button type="button" class="button wpfm-cancel">ยกเลิก</button>
                </div>
            </div>
        </div>
        
        <!-- Hidden file input for upload -->
        <input type="file" id="wpfm-file-input" multiple style="display:none;">
        <?php
    }
    
    private function render_folder_tree($parent_id = 0, $level = 0) {
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
            
            // นับจำนวนไฟล์
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
                $this->render_folder_tree($folder->id, $level + 1);
                echo '</div>';
            }
            
            echo '</li>';
        }
        echo '</ul>';
    }
    
    private function render_folder_options($parent_id = 0, $level = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'media_folders';
        
        $folders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE parent_id = %d ORDER BY name ASC",
            $parent_id
        ));
        
        if (empty($folders)) {
            return;
        }
        
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
        
        foreach ($folders as $folder) {
            echo '<option value="' . esc_attr($folder->id) . '">';
            echo $indent . esc_html($folder->icon) . ' ' . esc_html($folder->name);
            echo '</option>';
            
            // Render children recursively
            $this->render_folder_options($folder->id, $level + 1);
        }
    }
    
    public function add_folder_column($columns) {
        $columns['folder'] = '📁 Folder';
        return $columns;
    }
    
    public function show_folder_column($column_name, $post_id) {
        if ($column_name === 'folder') {
            global $wpdb;
            $relation_table = $wpdb->prefix . 'media_folder_relations';
            $folder_table = $wpdb->prefix . 'media_folders';
            
            $folder = $wpdb->get_row($wpdb->prepare(
                "SELECT f.name, f.icon, f.color FROM $folder_table f
                INNER JOIN $relation_table r ON f.id = r.folder_id
                WHERE r.attachment_id = %d",
                $post_id
            ));
            
            if ($folder) {
                echo '<span style="color:' . esc_attr($folder->color) . '">' . $folder->icon . '</span> ';
                echo esc_html($folder->name);
            } else {
                echo '—';
            }
        }
    }
    
    public function add_bulk_actions($actions) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'media_folders';
        $folders = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name ASC");
        
        if (!empty($folders)) {
            $actions['move_to_folder'] = 'ย้ายไปยัง Folder';
        }
        
        return $actions;
    }
    
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action === 'move_to_folder') {
            // จะจัดการใน AJAX
        }
        
        return $redirect_to;
    }
    
    /**
     * เพิ่ม body class สำหรับหน้า Media Library (sidebar แสดงอยู่ตลอด)
     */
    public function add_media_body_class($classes) {
        global $pagenow;
        if ($pagenow === 'upload.php') {
            $classes .= ' wpfm-sidebar-visible';
        }
        return $classes;
    }
    
    /**
     * เพิ่ม Folder Sidebar ในหน้า Media Library (แสดงตลอดเวลา)
     */
    public function add_media_sidebar() {
        $screen = get_current_screen();
        
        // แสดงเฉพาะในหน้า Media Library (upload.php)
        if ($screen && $screen->id === 'upload') {
            ?>
            <!-- Folder Sidebar -->
            <div id="wpfm-media-sidebar">
                <div id="wpfm-sidebar-resizer"></div>
                <div class="wpfm-media-sidebar-content">
                    <div class="wpfm-media-header">
                        <h3>📁 Folders</h3>
                        <div class="wpfm-header-actions">
                            <button id="wpfm-media-upload-files" class="button button-small" title="อัพโหลดไฟล์">
                                <span class="dashicons dashicons-upload"></span>
                            </button>
                            <button id="wpfm-media-create-folder" class="button button-small" title="สร้าง Folder">
                                <span class="dashicons dashicons-plus-alt"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div id="wpfm-current-folder-info" style="display:none;">
                        <div class="wpfm-current-folder-notice">
                            <span class="dashicons dashicons-info"></span>
                            <span id="wpfm-current-folder-text">เลือก folder เพื่ออัพโหลดไฟล์เข้าไป</span>
                        </div>
                    </div>
                    
                    <div class="wpfm-media-folder-list">
                        <div class="wpfm-folder-item-media wpfm-all-files active" data-folder-id="0">
                            <span class="wpfm-folder-icon">📂</span>
                            <span class="wpfm-folder-name">All Files</span>
                            <span class="wpfm-file-count"><?php echo $this->get_total_attachments(); ?></span>
                        </div>
                        
                        <?php echo $this->render_media_folder_tree(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Modal สำหรับสร้าง/แก้ไข Folder ในหน้า Media -->
            <div id="wpfm-media-folder-modal" class="wpfm-modal" style="display:none;">
                <div class="wpfm-modal-content">
                    <span class="wpfm-close">&times;</span>
                    <h2 id="wpfm-media-modal-title">สร้าง Folder ใหม่</h2>
                    <form id="wpfm-media-folder-form">
                        <input type="hidden" id="wpfm-media-folder-id" name="folder_id">
                        <input type="hidden" id="wpfm-media-parent-id" name="parent_id" value="0">
                        
                        <div class="wpfm-form-group">
                            <label for="wpfm-media-folder-name">ชื่อ Folder:</label>
                            <input type="text" id="wpfm-media-folder-name" name="folder_name" required>
                        </div>
                        
                        <div class="wpfm-form-group">
                            <label>เลือกไอคอน:</label>
                            <div class="wpfm-icon-selector">
                                <label><input type="radio" name="icon" value="📁" checked> 📁 Folder</label>
                                <!-- <label><input type="radio" name="icon" value="📂"> 📂 Open Folder</label>
                                <label><input type="radio" name="icon" value="📷"> 📷 Camera</label>
                                <label><input type="radio" name="icon" value="🎨"> 🎨 Art</label>
                                <label><input type="radio" name="icon" value="📄"> 📄 Document</label>
                                <label><input type="radio" name="icon" value="🎵"> 🎵 Music</label>
                                <label><input type="radio" name="icon" value="🎬"> 🎬 Video</label>
                                <label><input type="radio" name="icon" value="⭐"> ⭐ Star</label> -->
                            </div>
                        </div>
                        
                        <div class="wpfm-form-group">
                            <label for="wpfm-media-folder-color">สีของ Folder:</label>
                            <input type="color" id="wpfm-media-folder-color" name="color" value="#FFA500">
                        </div>
                        
                        <div class="wpfm-form-actions">
                            <button type="submit" class="button button-primary">บันทึก</button>
                            <button type="button" class="button wpfm-cancel">ยกเลิก</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * นับจำนวนไฟล์ทั้งหมด
     */
    private function get_total_attachments() {
        $count = wp_count_posts('attachment');
        return $count->inherit;
    }
    
    /**
     * แสดง Folder Tree สำหรับหน้า Media Library
     */
    private function render_media_folder_tree($parent_id = 0, $level = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'media_folders';
        
        $folders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE parent_id = %d ORDER BY name ASC",
            $parent_id
        ));
        
        if (empty($folders)) {
            return '';
        }
        
        $output = '';
        
        foreach ($folders as $folder) {
            $file_count = $this->get_folder_file_count($folder->id);
            $has_children = $this->has_subfolders($folder->id);
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
            
            $output .= sprintf(
                '<div class="wpfm-folder-item-media" data-folder-id="%d" data-parent-id="%d" data-folder-name="%s" data-folder-icon="%s" data-folder-color="%s" style="padding-left: %dpx;">
                    %s
                    <span class="wpfm-folder-icon" style="color: %s;">%s</span>
                    <span class="wpfm-folder-name">%s</span>
                    <span class="wpfm-file-count">%d</span>
                    <div class="wpfm-media-folder-actions">
                        <span class="dashicons dashicons-edit wpfm-media-edit-folder" data-folder-id="%d" title="แก้ไข"></span>
                        <span class="dashicons dashicons-plus wpfm-media-add-subfolder" data-folder-id="%d" title="เพิ่ม Subfolder"></span>
                        <span class="dashicons dashicons-trash wpfm-media-delete-folder" data-folder-id="%d" title="ลบ"></span>
                    </div>
                </div>',
                $folder->id,
                $folder->parent_id,
                esc_attr($folder->name),
                esc_attr($folder->icon),
                esc_attr($folder->color),
                ($level * 20),
                $has_children ? '<span class="wpfm-toggle-arrow">▶</span>' : '<span class="wpfm-toggle-arrow wpfm-no-children"></span>',
                esc_attr($folder->color),
                esc_html($folder->icon),
                esc_html($folder->name),
                $file_count,
                $folder->id,
                $folder->id,
                $folder->id
            );
            
            // Render children
            if ($has_children) {
                $output .= '<div class="wpfm-subfolder-container" style="display: none;">';
                $output .= $this->render_media_folder_tree($folder->id, $level + 1);
                $output .= '</div>';
            }
        }
        
        return $output;
    }
    
    /**
     * เช็คว่ามี subfolder หรือไม่
     */
    private function has_subfolders($folder_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'media_folders';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE parent_id = %d",
            $folder_id
        ));
        
        return $count > 0;
    }
    
    /**
     * นับจำนวนไฟล์ใน folder
     */
    private function get_folder_file_count($folder_id) {
        global $wpdb;
        $relation_table = $wpdb->prefix . 'media_folder_relations';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $relation_table WHERE folder_id = %d",
            $folder_id
        ));
        
        return (int) $count;
    }
    
    /**
     * Filter Media Library Query (สำหรับ Grid View - AJAX)
     */
    public function filter_media_by_folder($query) {
        if (isset($_REQUEST['query']['wpfm_folder'])) {
            $folder_id = intval($_REQUEST['query']['wpfm_folder']);
            
            if ($folder_id > 0) {
                global $wpdb;
                $relation_table = $wpdb->prefix . 'media_folder_relations';
                
                // ดึง attachment IDs ที่อยู่ใน folder
                $attachment_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT attachment_id FROM $relation_table WHERE folder_id = %d",
                    $folder_id
                ));
                
                if (!empty($attachment_ids)) {
                    $query['post__in'] = $attachment_ids;
                } else {
                    // ถ้าไม่มีไฟล์ใน folder ให้แสดงผลลัพธ์ว่าง
                    $query['post__in'] = array(0);
                }
            } elseif ($folder_id === -1) {
                // แสดงเฉพาะไฟล์ที่ไม่มี folder
                global $wpdb;
                $relation_table = $wpdb->prefix . 'media_folder_relations';
                
                $assigned_ids = $wpdb->get_col("SELECT DISTINCT attachment_id FROM $relation_table");
                
                if (!empty($assigned_ids)) {
                    $query['post__not_in'] = $assigned_ids;
                }
            }
        }
        
        return $query;
    }
    
    /**
     * Filter Media Library Query (สำหรับ List View - Page Load)
     */
    public function filter_media_library_query($query) {
        global $pagenow;
        
        if ($pagenow === 'upload.php' && $query->is_main_query() && isset($_GET['wpfm_folder'])) {
            $folder_id = intval($_GET['wpfm_folder']);
            
            if ($folder_id > 0) {
                global $wpdb;
                $relation_table = $wpdb->prefix . 'media_folder_relations';
                
                $attachment_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT attachment_id FROM $relation_table WHERE folder_id = %d",
                    $folder_id
                ));
                
                if (!empty($attachment_ids)) {
                    $query->set('post__in', $attachment_ids);
                } else {
                    $query->set('post__in', array(0));
                }
            } elseif ($folder_id === -1) {
                global $wpdb;
                $relation_table = $wpdb->prefix . 'media_folder_relations';
                
                $assigned_ids = $wpdb->get_col("SELECT DISTINCT attachment_id FROM $relation_table");
                
                if (!empty($assigned_ids)) {
                    $query->set('post__not_in', $assigned_ids);
                }
            }
        }
    }
}