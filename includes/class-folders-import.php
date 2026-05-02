<?php
/**
 * Class WPFM_Folders_Import
 * 
 * Import folders from Folders plugin (Premio) to WP Folder Manager
 * Folders plugin uses taxonomy: media_folder, folder, post_folder
 * WP Folder Manager uses custom tables: wp_media_folders, wp_media_folder_relations
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPFM_Folders_Import {
    
    private $taxonomy_map = array(
        'media_folder' => 'attachment', // Media Library folders
        'folder' => 'page',             // Page folders
        'post_folder' => 'post',        // Post folders
    );
    
    /**
     * Check if Folders plugin is installed
     */
    public function is_folders_plugin_active() {
        return class_exists('WCP_Folders');
    }
    
    /**
     * Get all folders from Folders plugin
     */
    public function get_folders_data($taxonomy = 'media_folder') {
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('taxonomy_not_found', 'Taxonomy not found: ' . $taxonomy);
        }
        
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'parent',
            'order' => 'ASC'
        ));
        
        if (is_wp_error($terms)) {
            return $terms;
        }
        
        return $terms;
    }
    
    /**
     * Get attachments in a folder
     */
    public function get_folder_attachments($term_id, $taxonomy = 'media_folder') {
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_id
                )
            )
        );
        
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    /**
     * Import folders from Folders plugin to WP Folder Manager
     */
    public function import_folders($taxonomy = 'media_folder', $options = array()) {
        global $wpdb;
        
        $defaults = array(
            'delete_existing' => false, // ลบ folder เดิมใน WP Folder Manager หรือไม่
            'skip_duplicates' => true,  // ข้าม folder ที่มีชื่อซ้ำ
            'import_attachments' => true // import ความสัมพันธ์ของไฟล์ด้วยหรือไม่
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // เช็คว่า Folders plugin ติดตั้งและ taxonomy มีอยู่หรือไม่
        if (!$this->is_folders_plugin_active()) {
            return new WP_Error('plugin_not_active', 'Folders plugin is not active');
        }
        
        $folders = $this->get_folders_data($taxonomy);
        
        if (is_wp_error($folders)) {
            return $folders;
        }
        
        if (empty($folders)) {
            return new WP_Error('no_folders', 'No folders found in Folders plugin');
        }
        
        // ลบ folder เดิมถ้าต้องการ
        if ($options['delete_existing']) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}media_folders");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}media_folder_relations");
        }
        
        // สร้าง mapping ระหว่าง term_id และ folder_id ใหม่
        $term_to_folder_map = array();
        $imported_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        
        // Import folders (เรียงตาม parent ก่อน เพื่อให้ parent folder ถูกสร้างก่อน child)
        foreach ($folders as $term) {
            // เช็ค duplicate name
            if ($options['skip_duplicates']) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}media_folders WHERE name = %s",
                    $term->name
                ));
                
                if ($existing) {
                    $skipped_count++;
                    // ถ้ามี folder ชื่อเดียวกันอยู่แล้ว ให้ใช้ id นั้นใน mapping
                    $term_to_folder_map[$term->term_id] = $existing;
                    continue;
                }
            }
            
            // หา parent_id ใหม่จาก mapping
            $parent_id = 0;
            if ($term->parent > 0 && isset($term_to_folder_map[$term->parent])) {
                $parent_id = $term_to_folder_map[$term->parent];
            }
            
            // Insert folder
            $result = $wpdb->insert(
                $wpdb->prefix . 'media_folders',
                array(
                    'name' => $term->name,
                    'parent_id' => $parent_id,
                    'icon' => '', // Folders plugin ไม่มี icon
                    'color' => '', // Folders plugin ไม่มี color
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $error_count++;
                continue;
            }
            
            $new_folder_id = $wpdb->insert_id;
            $term_to_folder_map[$term->term_id] = $new_folder_id;
            $imported_count++;
            
            // Import attachments ถ้าต้องการ
            if ($options['import_attachments']) {
                $attachments = $this->get_folder_attachments($term->term_id, $taxonomy);
                
                foreach ($attachments as $attachment) {
                    // เช็คว่ามีความสัมพันธ์อยู่แล้วหรือไม่
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}media_folder_relations 
                        WHERE attachment_id = %d AND folder_id = %d",
                        $attachment->ID,
                        $new_folder_id
                    ));
                    
                    if (!$exists) {
                        $wpdb->insert(
                            $wpdb->prefix . 'media_folder_relations',
                            array(
                                'attachment_id' => $attachment->ID,
                                'folder_id' => $new_folder_id
                            ),
                            array('%d', '%d')
                        );
                    }
                }
            }
        }
        
        return array(
            'success' => true,
            'imported' => $imported_count,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'total' => count($folders),
            'mapping' => $term_to_folder_map
        );
    }
    
    /**
     * Get import statistics
     */
    public function get_import_stats($taxonomy = 'media_folder') {
        global $wpdb;
        
        $folders = $this->get_folders_data($taxonomy);
        
        if (is_wp_error($folders)) {
            return $folders;
        }
        
        $total_folders = count($folders);
        $total_attachments = 0;
        
        foreach ($folders as $term) {
            $total_attachments += $term->count;
        }
        
        $existing_folders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}media_folders");
        $existing_relations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}media_folder_relations");
        
        return array(
            'folders_plugin' => array(
                'total_folders' => $total_folders,
                'total_attachments' => $total_attachments
            ),
            'wp_folder_manager' => array(
                'total_folders' => $existing_folders,
                'total_relations' => $existing_relations
            ),
            'taxonomy' => $taxonomy,
            'plugin_active' => $this->is_folders_plugin_active()
        );
    }
    
    /**
     * Render import page
     */
    public function render_import_page() {
        // เช็ค permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        // Handle form submission
        if (isset($_POST['wpfm_import_folders']) && check_admin_referer('wpfm_import_folders', 'wpfm_import_nonce')) {
            $taxonomy = sanitize_text_field($_POST['taxonomy']);
            $delete_existing = isset($_POST['delete_existing']) ? true : false;
            $skip_duplicates = isset($_POST['skip_duplicates']) ? true : false;
            $import_attachments = isset($_POST['import_attachments']) ? true : false;
            
            $result = $this->import_folders($taxonomy, array(
                'delete_existing' => $delete_existing,
                'skip_duplicates' => $skip_duplicates,
                'import_attachments' => $import_attachments
            ));
            
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>';
                echo 'Import completed! ';
                echo 'Imported: ' . $result['imported'] . ' folders, ';
                echo 'Skipped: ' . $result['skipped'] . ', ';
                echo 'Errors: ' . $result['errors'];
                echo '</p></div>';
            }
        }
        
        // Get stats
        $stats = $this->get_import_stats('media_folder');
        
        ?>
        <div class="wrap">
            <h1>📦 Import from Folders Plugin</h1>
            
            <?php if (!$this->is_folders_plugin_active()): ?>
                <div class="notice notice-warning">
                    <p><strong>Warning:</strong> Folders plugin by Premio is not active. Please activate it first.</p>
                </div>
            <?php else: ?>
                
                <div class="card" style="max-width: 800px;">
                    <h2>Import Statistics</h2>
                    
                    <?php if (is_wp_error($stats)): ?>
                        <p class="description" style="color: red;">Error: <?php echo esc_html($stats->get_error_message()); ?></p>
                    <?php else: ?>
                        <table class="widefat" style="margin-bottom: 20px;">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Folders</th>
                                    <th>Attachments/Relations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Folders Plugin</strong></td>
                                    <td><?php echo number_format($stats['folders_plugin']['total_folders']); ?></td>
                                    <td><?php echo number_format($stats['folders_plugin']['total_attachments']); ?> attachments</td>
                                </tr>
                                <tr>
                                    <td><strong>WP Folder Manager</strong></td>
                                    <td><?php echo number_format($stats['wp_folder_manager']['total_folders']); ?></td>
                                    <td><?php echo number_format($stats['wp_folder_manager']['total_relations']); ?> relations</td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <h2>Import Options</h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('wpfm_import_folders', 'wpfm_import_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="taxonomy">Taxonomy</label>
                                </th>
                                <td>
                                    <select name="taxonomy" id="taxonomy" class="regular-text">
                                        <option value="media_folder">Media Folders (media_folder)</option>
                                        <option value="folder">Page Folders (folder)</option>
                                        <option value="post_folder">Post Folders (post_folder)</option>
                                    </select>
                                    <p class="description">เลือก taxonomy ที่ต้องการ import จาก Folders plugin</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Options</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="skip_duplicates" value="1" checked>
                                            Skip duplicate folder names
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="import_attachments" value="1" checked>
                                            Import attachment relationships
                                        </label>
                                        <br>
                                        <label style="color: red;">
                                            <input type="checkbox" name="delete_existing" value="1">
                                            <strong>Delete all existing folders in WP Folder Manager before import</strong>
                                        </label>
                                        <p class="description" style="color: red;">⚠️ Warning: This will delete all existing folders and relationships!</p>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" name="wpfm_import_folders" class="button button-primary button-large">
                                🔄 Start Import
                            </button>
                        </p>
                    </form>
                </div>
                
                <div class="card" style="max-width: 800px; margin-top: 20px;">
                    <h2>How it works</h2>
                    <ol>
                        <li>Select the taxonomy you want to import (usually <code>media_folder</code> for Media Library)</li>
                        <li>Choose your import options:
                            <ul>
                                <li><strong>Skip duplicates:</strong> If a folder with the same name exists, skip it</li>
                                <li><strong>Import attachments:</strong> Also import which files belong to which folders</li>
                                <li><strong>Delete existing:</strong> ⚠️ Remove all current folders before importing</li>
                            </ul>
                        </li>
                        <li>Click "Start Import" to begin the process</li>
                        <li>After import, you can safely deactivate the Folders plugin</li>
                    </ol>
                    
                    <h3>Database Structure</h3>
                    <p><strong>Folders Plugin:</strong> Uses WordPress taxonomy system (<code>wp_terms</code>, <code>wp_term_taxonomy</code>, <code>wp_term_relationships</code>)</p>
                    <p><strong>WP Folder Manager:</strong> Uses custom tables (<code>wp_media_folders</code>, <code>wp_media_folder_relations</code>)</p>
                </div>
                
            <?php endif; ?>
        </div>
        
        <style>
            .card h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }
            .card ol, .card ul {
                padding-left: 20px;
            }
            .card code {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 3px;
            }
        </style>
        <?php
    }
}
