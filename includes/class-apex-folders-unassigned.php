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
        static $ids = array();

        $blog_id = get_current_blog_id();
        if ( ! empty( $ids[ $blog_id ] ) ) {
            return $ids[ $blog_id ];
        }

        // Look it up by slug: the name is translated, so it differs between languages
        $term = get_term_by( 'slug', 'unassigned', 'apex_folder' );
        if ( $term ) {
            $ids[ $blog_id ] = (int) $term->term_id;
            return $ids[ $blog_id ];
        }

        // Create it if it doesn't exist
        $unassigned = wp_insert_term(
            esc_html__( 'Unassigned', 'apex-folders' ),
            'apex_folder',
            array(
                'description' => esc_html__( 'Default folder for media items not assigned to any other folder', 'apex-folders' ),
                'slug' => 'unassigned'
            )
        );

        if ( is_wp_error( $unassigned ) ) {
            return 0;
        }

        $ids[ $blog_id ] = (int) $unassigned['term_id'];
        return $ids[ $blog_id ];
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

        if ( ! $unassigned_id ) {
            return 0;
        }

        // Find all attachments that don't have ANY folder assignment
        $unassigned_query = new WP_Query( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'apex_folder',
                    'operator' => 'NOT EXISTS',
                ),
            ),
        ) );

        $count = 0;

        // Assign them all, then recount the folder once at the end
        wp_defer_term_counting( true );

        foreach ( $unassigned_query->posts as $attachment_id ) {
            $result = wp_set_object_terms( $attachment_id, array( $unassigned_id ), 'apex_folder', true );

            if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
                $count++;
            }
        }

        wp_defer_term_counting( false );

        return $count;
    }
}
