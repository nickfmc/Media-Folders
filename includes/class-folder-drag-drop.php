<?php
/**
 * Media Folders - Drag and Drop Handler
 *
 * Handles the server-side operations for drag and drop functionality.
 *
 * @package apex-folders
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for handling drag and drop operations in Media Folders
 */
class APEX_FOLDERS_Drag_Drop {

    /**
     * Constructor
     */
    public function __construct() {
        // Register scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // Add AJAX handlers for drag and drop operations
        add_action( 'wp_ajax_apex_folder_move_items', array( $this, 'handle_move_items' ) );
    }

    /**
     * Register and enqueue assets for drag and drop functionality
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_assets( $hook ) {
        // Only load on media library page
        if ( 'upload.php' !== $hook ) {
            return;
        }

        // Register and enqueue CSS
        wp_register_style(
            'apex-folder-drag-drop',
            APEX_FOLDERS_PLUGIN_URL . 'assets/css/folder-drag-drop.css',
            array(),
            APEX_FOLDERS_VERSION
        );
        wp_enqueue_style( 'apex-folder-drag-drop' );

        // Register and enqueue JS - with dependencies on jQuery UI
        wp_register_script(
            'apex-folder-drag-drop',
            APEX_FOLDERS_PLUGIN_URL . 'assets/js/folder-drag-drop.js',
            array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ),
            APEX_FOLDERS_VERSION,
            true
        );
        
        // Localize script with nonce and settings
        wp_localize_script(
            'apex-folder-drag-drop',
            'mediaFolderSettings',
            array(
                'nonce' => wp_create_nonce( 'APEX_FOLDERS_drag_drop_nonce' ),
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'moveItemsAction' => 'apex_folder_move_items',
                'unassignedFolderId' => apex_folders_get_unassigned_id(),
                'i18n' => array(
                    'movingItem' => esc_html__( 'Moving item', 'apex-folders' ),
                    'movingItems' => esc_html__( 'Moving items', 'apex-folders' ),
                    'successMove' => esc_html__( 'Successfully moved', 'apex-folders' ),
                    'errorMove' => esc_html__( 'Error moving items', 'apex-folders' ),
                    'dropToMove' => esc_html__( 'Drop to move to this folder', 'apex-folders' ),
                    'noFilesSelected' => esc_html__( 'No files selected', 'apex-folders' ),
                    'selectFiles' => esc_html__( 'Select files to move', 'apex-folders' ),
                )
            )
        );
        
        wp_enqueue_script( 'apex-folder-drag-drop' );
    }

    /**
     * Handle the AJAX request to move items between folders
     */
    public function handle_move_items() {
        // Verify nonce
        check_ajax_referer( 'APEX_FOLDERS_drag_drop_nonce', 'nonce' );
        
        // Check permissions
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array(
                'message' => esc_html__( 'You do not have permission to move files.', 'apex-folders' )
            ) );
            return;
        }
        
        // Get data from request and sanitize
        $attachment_ids = isset( $_POST['attachment_ids'] ) ? wp_unslash( $_POST['attachment_ids'] ) : array();
        $folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
        
        // Sanitize attachment IDs array 
        if ( is_array( $attachment_ids ) ) {
            $attachment_ids = array_map( 'intval', $attachment_ids );
        } else {
            $attachment_ids = array();
        }
        
        // Validate data
        if ( empty( $attachment_ids ) ) {
            wp_send_json_error( array(
                'message' => esc_html__( 'No files selected to move.', 'apex-folders' )
            ) );
            return;
        }
        
        if ( ! $folder_id ) {
            wp_send_json_error( array(
                'message' => esc_html__( 'Invalid folder selected.', 'apex-folders' )
            ) );
            return;
        }
        
        // Verify the target folder exists
        $folder_term = get_term( $folder_id, 'apex_folder' );
        if ( ! $folder_term || is_wp_error( $folder_term ) ) {
            wp_send_json_error( array(
                'message' => esc_html__( 'The selected folder does not exist.', 'apex-folders' )
            ) );
            return;
        }
        
        // Process each attachment
        $success_count = 0;
        $error_count = 0;
        $processed_ids = array();
        
        foreach ( $attachment_ids as $id ) {
            $id = intval( $id );
            
            // Check if it's a valid attachment
            if ( ! get_post( $id ) || get_post_type( $id ) !== 'attachment' ) {
                $error_count++;
                continue;
            }
            
            // Remove current folder assignments
            wp_delete_object_term_relationships( $id, 'apex_folder' );
            
            // Assign to new folder
            $result = wp_set_object_terms( $id, array( $folder_id ), 'apex_folder', false );
            
            if ( is_wp_error( $result ) ) {
                $error_count++;
            } else {
                $success_count++;
                $processed_ids[] = $id;
            }
        }
        
        // Update folder counts
        apex_folders_update_counts();
        
        // Prepare the response
        if ( $success_count > 0 ) {
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %1$d: number of files moved, %2$s: folder name */
                    _n(
                        '%1$d file moved successfully to "%2$s".',
                        '%1$d files moved successfully to "%2$s".',
                        $success_count,
                        'apex-folders'
                    ),
                    $success_count,
                    esc_html( $folder_term->name )
                ),
                'success_count' => $success_count,
                'error_count' => $error_count,
                'processed_ids' => $processed_ids
            ) );
        } else {
            wp_send_json_error( array(
                'message' => esc_html__( 'Failed to move files. Please try again.', 'apex-folders' )
            ) );
        }
    }
}

// Initialize the class
new APEX_FOLDERS_Drag_Drop();