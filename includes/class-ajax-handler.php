<?php
/**
 * AJAX Handler Class
 *
 * @package apex-folders
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
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
        add_action( 'wp_ajax_apex_folders_rename_apex_folder', array( $this, 'rename_apex_folder' ) );
        add_action( 'wp_ajax_apex_folders_delete_apex_folder', array( $this, 'delete_apex_folder' ) );
        add_action( 'wp_ajax_apex_folders_add_apex_folder', array( $this, 'add_apex_folder' ) );
        add_action( 'wp_ajax_apex_folders_get_folder_counts', array( $this, 'get_folder_counts' ) );
    }

    /**
     * Delete a media folder.
     */
    public function delete_apex_folder() {
        check_ajax_referer( 'APEX_FOLDERS_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error();
        }

        $folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
        $unassigned_id = (int) apex_folders_get_unassigned_id();

        // Prevent deleting the Unassigned folder
        if ( $folder_id === $unassigned_id ) {
            wp_send_json_error( array( 'message' => esc_html__( 'The Unassigned folder cannot be deleted.', 'apex-folders' ) ) );
        }

        // Check if the folder exists
        $term = $folder_id ? get_term( $folder_id, 'apex_folder' ) : null;
        if ( ! $term || is_wp_error( $term ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Folder not found.', 'apex-folders' ) ) );
        }

        // Move the folder's files to the Unassigned folder
        $attachment_ids = get_objects_in_term( $folder_id, 'apex_folder' );
        if ( is_wp_error( $attachment_ids ) ) {
            $attachment_ids = array();
        }

        wp_defer_term_counting( true );
        foreach ( $attachment_ids as $attachment_id ) {
            wp_set_object_terms( (int) $attachment_id, array( $unassigned_id ), 'apex_folder', false );
        }
        wp_defer_term_counting( false );

        // Delete the folder
        $result = wp_delete_term( $folder_id, 'apex_folder' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %d: number of files moved to unassigned folder */
                    esc_html__( 'Folder deleted. %d files moved to Unassigned folder.', 'apex-folders' ),
                    count( $attachment_ids )
                )
            ) );
        }
    }

    /**
     * Add a new media folder.
     */
    public function add_apex_folder() {
        check_ajax_referer( 'APEX_FOLDERS_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error();
        }
        
        $folder_name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
        $parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
    
        $result = APEX_FOLDERS_Utilities::create_folder( $folder_name, $parent_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => esc_html( $result->get_error_message() ) ) );
        } else {
            wp_send_json_success( array( 'folder_id' => $result ) );
        }
    }

    /**
     * Get updated folder counts.
     */
    public function get_folder_counts() {
        check_ajax_referer( 'APEX_FOLDERS_nonce', 'nonce' );

        // Verify permissions
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( esc_html__( 'Permission denied', 'apex-folders' ) );
        }
        
        // Get updated folder data
        $folders = get_terms( array(
            'taxonomy'   => 'apex_folder',
            'hide_empty' => false,
        ) );
        
        $folder_data = array();
        foreach ( $folders as $folder ) {
            $folder_data[$folder->term_id] = array(
                'count' => $folder->count,
                'name'  => $folder->name,
                'slug'  => $folder->slug
            );
        }
        
        wp_send_json_success( $folder_data );
    }

    /**
     * Rename a media folder.
     */
    public function rename_apex_folder() {
        check_ajax_referer( 'APEX_FOLDERS_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error();
        }
        
        $folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
        $new_name = isset( $_POST['new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_name'] ) ) : '';
        $unassigned_id = (int) apex_folders_get_unassigned_id();
        
        // Validate input
        if ( empty( $new_name ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Folder name cannot be empty.', 'apex-folders' ) ) );
            return;
        }
        
        // Prevent renaming the Unassigned folder
        if ( $folder_id === $unassigned_id ) {
            wp_send_json_error( array( 'message' => esc_html__( 'The Unassigned folder cannot be renamed.', 'apex-folders' ) ) );
            return;
        }
        
        // Check if the folder exists
        $term = get_term( $folder_id, 'apex_folder' );
        if ( ! $term || is_wp_error( $term ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Folder not found.', 'apex-folders' ) ) );
            return;
        }
        
        // Update the term
        $result = wp_update_term( $folder_id, 'apex_folder', array(
            'name' => $new_name
        ) );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array( 'message' => esc_html__( 'Folder renamed successfully.', 'apex-folders' ) ) );
        }
    }
}

// Initialize the class
new APEX_FOLDERS_AJAX_Handler();