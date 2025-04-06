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
        add_action( 'wp_ajax_get_folder_slug', array( $this, 'get_folder_slug' ) );
        add_action( 'wp_ajax_apex_folders_add_apex_folder', array( $this, 'add_apex_folder' ) );
        add_action( 'wp_ajax_apex_folders_get_folder_counts', array( $this, 'get_folder_counts' ) );
        add_action( 'wp_ajax_upload-attachment', array( $this, 'async_upload' ), 1 );
    }

    /**
     * Delete a media folder.
     */
    public function delete_apex_folder() {
        check_ajax_referer( 'APEX_FOLDERS_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error();
        }
        
        global $wpdb;
        
        $folder_id = intval( $_POST['folder_id'] );
        $unassigned_id = APEX_FOLDERS_get_unassigned_id();
        
        // Prevent deleting the Unassigned folder
        if ( $folder_id === $unassigned_id ) {
            wp_send_json_error( array( 'message' => esc_html__( 'The Unassigned folder cannot be deleted.', 'apex-folders' ) ) );
            return;
        }
        
        // Get the term taxonomy IDs we need
        $folder_tt_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
             WHERE term_id = %d AND taxonomy = 'apex_folder'",
            $folder_id
        ) );
        
        $unassigned_tt_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
             WHERE term_id = %d AND taxonomy = 'apex_folder'",
            $unassigned_id
        ) );
        
        if ( ! $folder_tt_id || ! $unassigned_tt_id ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Could not find taxonomy terms', 'apex-folders' ) ) );
            return;
        }
        
        // Step 1: Directly find all attachments in this folder using SQL
        $attachment_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT tr.object_id 
             FROM $wpdb->term_relationships tr
             JOIN $wpdb->posts p ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id = %d 
             AND p.post_type = 'attachment'",
            $folder_tt_id
        ) );
        
        $moved_count = 0;
        
        // Step 2: Move each attachment to the unassigned folder
        foreach ( $attachment_ids as $attachment_id ) {
            // Remove from current folder
            $wpdb->delete(
                $wpdb->term_relationships,
                array(
                    'object_id' => $attachment_id,
                    'term_taxonomy_id' => $folder_tt_id,
                )
            );
            
            // Check if it's already in unassigned
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->term_relationships 
                 WHERE object_id = %d AND term_taxonomy_id = %d",
                $attachment_id, $unassigned_tt_id
            ) );
            
            if ( ! $exists ) {
                // Add to unassigned folder
                $wpdb->insert(
                    $wpdb->term_relationships,
                    array(
                        'object_id' => $attachment_id,
                        'term_taxonomy_id' => $unassigned_tt_id,
                        'term_order' => 0,
                    )
                );
                
                $moved_count++;
            }
        }
        
        // Step 3: Update term counts
        if ( $moved_count > 0 ) {
            $wpdb->update(
                $wpdb->term_taxonomy,
                array( 'count' => $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d",
                    $unassigned_tt_id
                ) ) ),
                array( 'term_taxonomy_id' => $unassigned_tt_id )
            );
        }
        
        // Step 4: Delete the folder
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
     * Get a folder's slug by ID.
     */
    public function get_folder_slug() {
        check_ajax_referer( 'APEX_FOLDERS_get_slug', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error();
        }
        
        // Ensure folder_id is sanitized with intval()
        $folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
        
        if ( $folder_id ) {
            $term = get_term( $folder_id, 'apex_folder' );
            if ( $term && ! is_wp_error( $term ) ) {
                wp_send_json_success( array(
                    'slug' => sanitize_text_field( $term->slug ),
                    'name' => sanitize_text_field( $term->name )
                ) );
            }
        }
        
        wp_send_json_error();
    }

    /**
     * Add a new media folder.
     */
    public function add_apex_folder() {
        check_ajax_referer( 'APEX_FOLDERS_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error();
        }
        
        $folder_name = sanitize_text_field( wp_unslash( $_POST['folder_name'] ) );
        $parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
    
        if ( empty( $folder_name ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Folder name cannot be empty', 'apex-folders' ) ) );
            return;
        }
        
        // Ensure parent folder exists if specified
        if ( $parent_id > 0 ) {
            $parent_term = get_term( $parent_id, 'apex_folder' );
            if ( ! $parent_term || is_wp_error( $parent_term ) ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Parent folder does not exist', 'apex-folders' ) ) );
                return;
            }
            
            // Ensure parent isn't the Unassigned folder
            $unassigned_id = APEX_FOLDERS_get_unassigned_id();
            if ( $parent_id == $unassigned_id ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Cannot create subfolders under Unassigned', 'apex-folders' ) ) );
                return;
            }
            
            // Check if parent itself has a parent (limit to one level)
            $parent_parent = get_term_field( 'parent', $parent_id, 'apex_folder' );
            if ( ! is_wp_error( $parent_parent ) && $parent_parent > 0 ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Only one level of subfolders is supported', 'apex-folders' ) ) );
                return;
            }
        }
        
        $result = wp_insert_term(
            $folder_name, 
            'apex_folder',
            array(
                'parent' => $parent_id
            )
        );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success();
        }
    }

    /**
     * Get updated folder counts.
     */
    public function get_folder_counts() {
        // Verify permissions
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( esc_html__( 'Permission denied', 'apex-folders' ) );
        }
        
        // Force recount all terms
        APEX_FOLDERS_update_counts();
        
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
     * Handle async uploads with folder assignment.
     */
    public function async_upload() {
        if ( isset( $_POST['apex_folder_id'] ) && isset( $_POST['attachment_id'] ) ) {
            $attachment_id = intval( $_POST['attachment_id'] );
            $folder_id = intval( $_POST['apex_folder_id'] );
            
            if ( $folder_id > 0 ) {
                wp_set_object_terms( $attachment_id, array( $folder_id ), 'apex_folder', false );
                // Use sprintf for logs with variables
                error_log(
                    sprintf(
                        /* translators: %1$d: attachment ID, %2$d: folder ID */
                        esc_html__( 'Async assigned attachment ID %1$d to folder ID %2$d', 'apex-folders' ),
                        $attachment_id,
                        $folder_id
                    )
                );
            }
        }
    }

    /**
     * Rename a media folder.
     */
    public function rename_apex_folder() {
        check_ajax_referer( 'APEX_FOLDERS_nonce', 'nonce' );
        
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error();
        }
        
        $folder_id = intval( $_POST['folder_id'] );
        $new_name = sanitize_text_field( wp_unslash( $_POST['new_name'] ) );
        $unassigned_id = APEX_FOLDERS_get_unassigned_id();
        
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