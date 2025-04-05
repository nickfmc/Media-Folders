<?php
/**
 * AJAX Handler Class
 *
 * @package apex-folders
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APEX_FOLDERS_AJAX_Handler class.
 * Handles all AJAX interactions for the Media Folders plugin.
 */
class APEX_FOLDERS_AJAX_Handler {

    /**
     * Constructor.
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_theme_rename_media_folder', array($this, 'rename_media_folder'));
        add_action('wp_ajax_theme_delete_media_folder', array($this, 'delete_media_folder'));
        add_action('wp_ajax_get_folder_slug', array($this, 'get_folder_slug'));
        add_action('wp_ajax_theme_add_media_folder', array($this, 'add_media_folder'));
        add_action('wp_ajax_theme_get_folder_counts', array($this, 'get_folder_counts'));
        add_action('wp_ajax_upload-attachment', array($this, 'async_upload'), 1);
    }

    /**
     * Delete a media folder.
     */
    public function delete_media_folder() {
        check_ajax_referer('APEX_FOLDERS_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error();
        }
        
        global $wpdb;
        
        $folder_id = intval($_POST['folder_id']);
        $unassigned_id = APEX_FOLDERS_get_unassigned_id();
        
        // Prevent deleting the Unassigned folder
        if ($folder_id === $unassigned_id) {
            wp_send_json_error(array('message' => 'The Unassigned folder cannot be deleted.'));
            return;
        }
        
        // Get the term taxonomy IDs we need
        $folder_tt_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
             WHERE term_id = %d AND taxonomy = 'media_folder'",
            $folder_id
        ));
        
        $unassigned_tt_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
             WHERE term_id = %d AND taxonomy = 'media_folder'",
            $unassigned_id
        ));
        
        if (!$folder_tt_id || !$unassigned_tt_id) {
            wp_send_json_error(array('message' => 'Could not find taxonomy terms'));
            return;
        }
        
        // Step 1: Directly find all attachments in this folder using SQL
        $attachment_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT tr.object_id 
             FROM $wpdb->term_relationships tr
             JOIN $wpdb->posts p ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id = %d 
             AND p.post_type = 'attachment'",
            $folder_tt_id
        ));
        
        $moved_count = 0;
        
        // Step 2: For each attachment, remove the old relationship and add the new one
        if (!empty($attachment_ids)) {
            foreach ($attachment_ids as $attachment_id) {
                // First, remove the current folder assignment
                $wpdb->delete(
                    $wpdb->term_relationships,
                    array(
                        'object_id' => $attachment_id,
                        'term_taxonomy_id' => $folder_tt_id
                    )
                );
                
                // Then check if it's already in Unassigned
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $wpdb->term_relationships 
                     WHERE object_id = %d AND term_taxonomy_id = %d",
                    $attachment_id, $unassigned_tt_id
                ));
                
                // Only add to Unassigned if it's not already there
                if (!$exists) {
                    $wpdb->insert(
                        $wpdb->term_relationships,
                        array(
                            'object_id' => $attachment_id,
                            'term_taxonomy_id' => $unassigned_tt_id,
                            'term_order' => 0
                        )
                    );
                    $moved_count++;
                }
            }
            
            // Update both term counts
            $wpdb->query($wpdb->prepare(
                "UPDATE $wpdb->term_taxonomy 
                 SET count = count + %d 
                 WHERE term_taxonomy_id = %d",
                $moved_count, $unassigned_tt_id
            ));
            
            $wpdb->query($wpdb->prepare(
                "UPDATE $wpdb->term_taxonomy 
                 SET count = 0 
                 WHERE term_taxonomy_id = %d",
                $folder_tt_id
            ));
        }
        
        // Force term count updates and clear caches
        clean_term_cache(array($folder_id, $unassigned_id), 'media_folder');
        
        // Step 3: Delete the term
        $result = wp_delete_term($folder_id, 'media_folder');
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => 'Folder deleted. ' . count($attachment_ids) . ' files moved to Unassigned folder.'
            ));
        }
    }

    /**
     * Get a folder's slug by ID.
     */
    public function get_folder_slug() {
        check_ajax_referer('APEX_FOLDERS_get_slug', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error();
        }
        
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        
        if ($folder_id) {
            $term = get_term($folder_id, 'media_folder');
            if ($term && !is_wp_error($term)) {
                wp_send_json_success(array(
                    'slug' => $term->slug,
                    'name' => $term->name
                ));
            }
        }
        
        wp_send_json_error();
    }

    /**
     * Add a new media folder.
     */
    public function add_media_folder() {
        check_ajax_referer('APEX_FOLDERS_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error();
        }
        
        $folder_name = sanitize_text_field($_POST['folder_name']);
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        
        // Ensure parent folder exists if specified
        if ($parent_id > 0) {
            $parent_term = get_term($parent_id, 'media_folder');
            if (!$parent_term || is_wp_error($parent_term)) {
                wp_send_json_error(array('message' => 'Parent folder does not exist'));
                return;
            }
            
            // Ensure parent isn't the Unassigned folder
            $unassigned_id = APEX_FOLDERS_get_unassigned_id();
            if ($parent_id == $unassigned_id) {
                wp_send_json_error(array('message' => 'Cannot create subfolders under Unassigned'));
                return;
            }
            
            // Check if parent itself has a parent (limit to one level)
            $parent_parent = get_term_field('parent', $parent_id, 'media_folder');
            if (!is_wp_error($parent_parent) && $parent_parent > 0) {
                wp_send_json_error(array('message' => 'Only one level of subfolders is supported'));
                return;
            }
        }
        
        $result = wp_insert_term(
            $folder_name, 
            'media_folder',
            array(
                'parent' => $parent_id
            )
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success();
        }
    }

    /**
     * Get updated folder counts.
     */
    public function get_folder_counts() {
        // Verify permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }
        
        // Force recount all terms
        theme_update_media_folder_counts();
        
        // Get updated folder data
        $folders = get_terms(array(
            'taxonomy' => 'media_folder',
            'hide_empty' => false,
        ));
        
        $folder_data = array();
        foreach ($folders as $folder) {
            $folder_data[$folder->term_id] = array(
                'count' => $folder->count,
                'name' => $folder->name,
                'slug' => $folder->slug
            );
        }
        
        wp_send_json_success($folder_data);
    }

    /**
     * Handle async uploads with folder assignment.
     */
    public function async_upload() {
        if (isset($_POST['media_folder_id']) && isset($_POST['attachment_id'])) {
            $attachment_id = intval($_POST['attachment_id']);
            $folder_id = intval($_POST['media_folder_id']);
            
            if ($folder_id > 0) {
                wp_set_object_terms($attachment_id, array($folder_id), 'media_folder', false);
                error_log("Async assigned attachment ID $attachment_id to folder ID $folder_id");
            }
        }
    }


    /**
     * Rename a media folder.
     */
    public function rename_media_folder() {
        check_ajax_referer('APEX_FOLDERS_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error();
        }
        
        $folder_id = intval($_POST['folder_id']);
        $new_name = sanitize_text_field($_POST['new_name']);
        $unassigned_id = APEX_FOLDERS_get_unassigned_id();
        
        // Validate input
        if (empty($new_name)) {
            wp_send_json_error(array('message' => 'Folder name cannot be empty.'));
            return;
        }
        
        // Prevent renaming the Unassigned folder
        if ($folder_id === $unassigned_id) {
            wp_send_json_error(array('message' => 'The Unassigned folder cannot be renamed.'));
            return;
        }
        
        // Check if the folder exists
        $term = get_term($folder_id, 'media_folder');
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => 'Folder not found.'));
            return;
        }
        
        // Update the term
        $result = wp_update_term($folder_id, 'media_folder', array(
            'name' => $new_name
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => 'Folder renamed successfully.'));
        }
    }
}

// Initialize the class
new APEX_FOLDERS_AJAX_Handler();