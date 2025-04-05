<?php
/**
 * Media Folders Utilities
 *
 * Helper functions for the Media Folders plugin.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for utility functions
 */ 
class APEX_FOLDERS_Utilities {
    
    /**
     * Register the media folder taxonomy
     * 
     * @return void
     */
    public static function register_taxonomy() {
        register_taxonomy(
            'media_folder',
            'attachment',
            array(
                'labels' => array(
                    'name' => 'Media Folders',
                    'singular_name' => 'Media Folder',
                    'menu_name' => 'Folders',
                    'all_items' => 'All Folders',
                    'edit_item' => 'Edit Folder',
                    'view_item' => 'View Folder',
                    'update_item' => 'Update Folder',
                    'add_new_item' => 'Add New Folder',
                    'new_item_name' => 'New Folder Name',
                    'parent_item' => 'Parent Folder',
                    'parent_item_colon' => 'Parent Folder:',
                    'search_items' => 'Search Folders',
                ),
                'hierarchical' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'show_in_nav_menus' => false,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'media-folder'),
            )
        );
    }
    
    /**
     * Enqueue admin scripts for the media library
     * 
     * @return void
     */
    public static function enqueue_admin_scripts() {
        $screen = get_current_screen();
        
        // Only on media upload screen
        if ($screen->base === 'upload') {
            // Enqueue jQuery UI core and all required components for dialogs
            wp_enqueue_script('jquery-ui-core'); 
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-resizable');
            
            // Enqueue jQuery UI CSS
            wp_enqueue_style('wp-jquery-ui-dialog');
        }
    }
    
    /**
     * Enqueue CSS styles for the media library
     * 
     * @return void
     */
    public static function enqueue_styles() {
        $screen = get_current_screen();
        if ($screen->base === 'upload') {
            wp_enqueue_style(
                'apex-folders-css',
                APEX_FOLDERS_PLUGIN_URL . 'assets/css/apex-main.css',
                array(),
                APEX_FOLDERS_VERSION
            );
        }
    }
    
    /**
     * Enqueue refresh script for the media library
     * 
     * @return void
     */
    public static function enqueue_refresh_script() {
        wp_add_inline_script('media-editor', '
            // Force refresh helper
            window.mediaFoldersRefreshView = function() {
                if (wp.media.frame) {
                    wp.media.frame.library.props.set({ignore: (+ new Date())});
                    wp.media.frame.library.props.trigger("change");
                }
                jQuery(".attachments-browser .attachments").trigger("scroll");
            };
        ');
    }
    
    /**
     * Update term counts for all media folders
     * 
     * @return void
     */
    public static function update_folder_counts() {
        global $wpdb;
        
        // Get all media folder terms
        $folders = get_terms(array(
            'taxonomy' => 'media_folder',
            'hide_empty' => false,
        ));
        
        if (empty($folders)) {
            return;
        }
        
        foreach ($folders as $folder) {
            // Count attachments in this folder
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->term_relationships
                 JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
                 WHERE $wpdb->term_relationships.term_taxonomy_id = %d
                 AND $wpdb->posts.post_type = 'attachment'",
                 $folder->term_taxonomy_id
            ));
            
            // Update the count in the database directly
            $wpdb->update(
                $wpdb->term_taxonomy,
                array('count' => $count),
                array('term_taxonomy_id' => $folder->term_taxonomy_id)
            );
        }
        
        // Clear caches
        clean_term_cache(wp_list_pluck($folders, 'term_id'), 'media_folder');
        delete_transient('media_folder_counts');
    }
    
    /**
     * Get organized folders
     * 
     * Returns folders organized into parent folders, child folders,
     * and the unassigned folder.
     * 
     * @return array Organized folders
     */
    public static function get_organized_folders() {
        $folders = get_terms(array(
            'taxonomy' => 'media_folder',
            'hide_empty' => false,
        ));
        
        // Get the unassigned ID
        $unassigned_id = APEX_FOLDERS_Unassigned::get_id();
        
        // Find and categorize folders
        $unassigned_folder = null;
        $parent_folders = array();
        $child_folders = array();
        
        foreach ($folders as $folder) {
            if ($folder->term_id == $unassigned_id) {
                $unassigned_folder = $folder;
            } else if ($folder->parent == 0) {
                $parent_folders[] = $folder;
            } else {
                $child_folders[$folder->parent][] = $folder;
            }
        }
        
        return array(
            'all' => $folders,
            'unassigned' => $unassigned_folder,
            'parents' => $parent_folders,
            'children' => $child_folders
        );
    }
    
    /**
     * Prevent numeric term creation
     * 
     * @param string $term     The term name
     * @param string $taxonomy The taxonomy name
     * @return string|WP_Error The term name or error
     */
    public static function prevent_numeric_term_creation($term, $taxonomy) {
        // Only check media_folder taxonomy
        if ($taxonomy !== 'media_folder') {
            return $term;
        }
        
        // If term is numeric, it's likely an ID being misinterpreted
        if (is_numeric($term)) {
            error_log("Preventing creation of numeric term: " . $term);
            
            // Return an error to prevent term creation
            return new WP_Error('invalid_term', "Can't create term with numeric name");
        }
        
        return $term;
    }
    
    /**
     * Debug attachment folder assignment
     * 
     * @param int    $post_id  The post ID
     * @param array  $terms    The terms being assigned
     * @param array  $tt_ids   The term taxonomy IDs
     * @param string $taxonomy The taxonomy name
     * @return void
     */
    public static function debug_folder_assignment($post_id, $terms, $tt_ids, $taxonomy) {
        if ($taxonomy !== 'media_folder') {
            return;
        }
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller_info = array();
        $ignore_functions = array('debug_media_folder_assignment', 'apply_filters', 'do_action');
        
        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && !in_array($trace['function'], $ignore_functions)) {
                $caller_info[] = $trace['function'];
            }
        }
        
        if (!empty($caller_info)) {
            error_log(sprintf(
                "[Media Folder Debug] Post: %d, Original Caller: %s, Terms: %s",
                $post_id,
                implode(' -> ', array_reverse($caller_info)),
                json_encode($terms)
            ));
        }
    }
}