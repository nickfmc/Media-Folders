<?php
/**
 * Media Folders - Drag and Drop Handler
 *
 * Handles the server-side operations for drag and drop functionality.
 *
 * @package Media-Folders
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling drag and drop operations in Media Folders
 */
class Media_Folder_Drag_Drop {

    /**
     * Constructor
     */
    public function __construct() {
        // Register scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Add AJAX handlers for drag and drop operations
        add_action('wp_ajax_media_folder_move_items', array($this, 'handle_move_items'));
    }

    /**
     * Register and enqueue assets for drag and drop functionality
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_assets($hook) {
        // Only load on media library page
        if ('upload.php' !== $hook) {
            return;
        }

        // Register and enqueue CSS
        wp_register_style(
            'media-folder-drag-drop',
            MEDIA_FOLDERS_PLUGIN_URL . 'assets/css/folder-drag-drop.css',
            array(),
            MEDIA_FOLDERS_VERSION
        );
        wp_enqueue_style('media-folder-drag-drop');

        // Register and enqueue JS - with dependencies on jQuery UI
        wp_register_script(
            'media-folder-drag-drop',
            MEDIA_FOLDERS_PLUGIN_URL . 'assets/js/folder-drag-drop.js',
            array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            MEDIA_FOLDERS_VERSION,
            true
        );
        
        // Localize script with nonce and settings
        wp_localize_script(
            'media-folder-drag-drop',
            'mediaFolderSettings',
            array(
                'nonce' => wp_create_nonce('media_folders_drag_drop_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'moveItemsAction' => 'media_folder_move_items',
                'unassignedFolderId' => media_folders_get_unassigned_id(),
                'i18n' => array(
                    'movingItem' => __('Moving item', 'media-folders'),
                    'movingItems' => __('Moving items', 'media-folders'),
                    'successMove' => __('Successfully moved', 'media-folders'),
                    'errorMove' => __('Error moving items', 'media-folders'),
                )
            )
        );
        
        wp_enqueue_script('media-folder-drag-drop');
    }

    /**
     * Handle the AJAX request to move items between folders
     */
    public function handle_move_items() {
        // Verify nonce
        check_ajax_referer('media_folders_drag_drop_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to move files.', 'media-folders')
            ));
            return;
        }
        
        // Get data from request
        $attachment_ids = isset($_POST['attachment_ids']) ? $_POST['attachment_ids'] : array();
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        
        // Validate data
        if (empty($attachment_ids) || !is_array($attachment_ids)) {
            wp_send_json_error(array(
                'message' => __('No files selected to move.', 'media-folders')
            ));
            return;
        }
        
        if (!$folder_id) {
            wp_send_json_error(array(
                'message' => __('Invalid folder selected.', 'media-folders')
            ));
            return;
        }
        
        // Verify the target folder exists
        $folder_term = get_term($folder_id, 'media_folder');
        if (!$folder_term || is_wp_error($folder_term)) {
            wp_send_json_error(array(
                'message' => __('The selected folder does not exist.', 'media-folders')
            ));
            return;
        }
        
        // Process each attachment
        $success_count = 0;
        $error_count = 0;
        $processed_ids = array();
        
        foreach ($attachment_ids as $id) {
            $id = intval($id);
            
            // Check if it's a valid attachment
            if (!get_post($id) || get_post_type($id) !== 'attachment') {
                $error_count++;
                continue;
            }
            
            // Remove current folder assignments
            wp_delete_object_term_relationships($id, 'media_folder');
            
            // Assign to new folder
            $result = wp_set_object_terms($id, array($folder_id), 'media_folder', false);
            
            if (is_wp_error($result)) {
                $error_count++;
            } else {
                $success_count++;
                $processed_ids[] = $id;
            }
        }
        
        // Update folder counts
        theme_update_media_folder_counts();
        
        // Prepare the response
        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    _n(
                        '%d file moved successfully to "%s".',
                        '%d files moved successfully to "%s".',
                        $success_count,
                        'media-folders'
                    ),
                    $success_count,
                    $folder_term->name
                ),
                'success_count' => $success_count,
                'error_count' => $error_count,
                'processed_ids' => $processed_ids
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to move files. Please try again.', 'media-folders')
            ));
        }
    }
}

// Initialize the class
new Media_Folder_Drag_Drop();