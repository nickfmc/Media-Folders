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
        global $wpdb;
        
        // Get the unassigned folder ID
        $unassigned_id = self::get_id();
        
        // Get the term taxonomy ID
        $tt_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
             WHERE term_id = %d AND taxonomy = 'apex_folder'",
            $unassigned_id
        ) );
        
        if ( ! $tt_id ) {
            return 0;
        }

        // Find all attachments that don't have ANY folder assignment
        $unassigned_attachments = $wpdb->get_col(
            "SELECT p.ID FROM $wpdb->posts p
             WHERE p.post_type = 'attachment'
             AND NOT EXISTS (
                 SELECT 1 FROM $wpdb->term_relationships tr
                 JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE tt.taxonomy = 'apex_folder' AND tr.object_id = p.ID
             )"
        );

        $count = 0;

        // Process in batches to avoid timeouts: one insert per 500 attachments
        foreach ( array_chunk( $unassigned_attachments, 500 ) as $batch ) {
            $values = array();
            foreach ( $batch as $attachment_id ) {
                $values[] = $wpdb->prepare( '(%d, %d, 0)', $attachment_id, $tt_id );
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each row is prepared above.
            $inserted = $wpdb->query( "INSERT IGNORE INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES " . implode( ', ', $values ) );

            if ( $inserted ) {
                $count += $inserted;
            }

            // The relationships were written directly, so drop the cached (empty) folder lists
            clean_object_term_cache( array_map( 'intval', $batch ), 'attachment' );
        }

        // Update term count
        if ( $count > 0 ) {
            wp_update_term_count_now( array( (int) $tt_id ), 'apex_folder' );
        }

        return $count;
    }
}