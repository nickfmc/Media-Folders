<?php
/**
 * Apex Folders Unassigned Handler
 *
 * Handles the Unassigned folder functionality.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for handling the Unassigned folder
 */
class APEX_FOLDERS_Unassigned {
    
    /**
     * Get the Unassigned folder ID
     * 
     * @return int Unassigned folder ID
     */
    public static function get_id() {
        $unassigned = term_exists( esc_html__( 'Unassigned', 'apex-folders' ), 'apex_folder' );
        if ( ! $unassigned ) {
            // Create it if it doesn't exist
            $unassigned = wp_insert_term(
                esc_html__( 'Unassigned', 'apex-folders' ), 
                'apex_folder',
                array(
                    'description' => esc_html__( 'Default folder for media items not assigned to any other folder', 'apex-folders' ),
                    'slug' => 'unassigned'
                )
            );
        }
        
        return is_array( $unassigned ) ? $unassigned['term_id'] : $unassigned;
    }
    
    /**
     * Ensure all attachments have a folder assignment
     * 
     * @param int $post_id The attachment ID
     * @return void
     */
    public static function ensure_attachment_has_folder( $post_id ) {
        // Only proceed for attachments
        if ( get_post_type( $post_id ) !== 'attachment' ) {
            return;
        }
        
        // Check if the attachment already has a folder
        $terms = wp_get_object_terms( $post_id, 'apex_folder' );
        
        // If it doesn't have any folder, assign to Unassigned
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            $unassigned_id = self::get_id();
            wp_set_object_terms( $post_id, array( $unassigned_id ), 'apex_folder', false );
        }
    }
    
    /**
     * Assign all unassigned media to the Unassigned folder
     * 
     * @return int Number of items assigned
     */
    public static function ensure_all_assigned() {
        // Get the unassigned folder ID
        $unassigned_id = self::get_id();
        
        // Get term object to access term_taxonomy_id
        $term = get_term($unassigned_id, 'apex_folder');
        
        if (!$term || is_wp_error($term)) {
            return 0;
        }

        // Find all attachments that don't have ANY folder assignment
        $unassigned_query = new WP_Query( array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'apex_folder',
                    'operator' => 'NOT EXISTS'
                )
            )
        ) );
        $unassigned_attachments = $unassigned_query->posts;
        
        $count = 0;
        
        
        // Process in batches to avoid timeouts
        foreach ( $unassigned_attachments as $attachment_id ) {
            // Check if attachment already has this term
            $exists = has_term( $unassigned_id, 'apex_folder', $attachment_id );
            
            if ( ! $exists ) {
                // Add term to attachment using WordPress API
                $result = wp_set_object_terms($attachment_id, $unassigned_id, 'apex_folder', true);
                
                if ( !is_wp_error($result) && !empty($result) ) {
                    $count++;
                }
            }
        }
        
        // Update term count
        if ( $count > 0 ) {
            // Update the count in the database using WordPress API
            wp_update_term_count_now( array( $unassigned_id ), 'apex_folder' );
            
            // Clear cache
            clean_term_cache( $unassigned_id, 'apex_folder' );
        }
        
        // Force update all term counts to be sure
        if ( function_exists( 'apex_folders_update_counts' ) ) {
            apex_folders_update_counts();
        }
        
        return $count;
    }
}