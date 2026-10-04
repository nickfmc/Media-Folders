<?php
/**
 * Media Folders Query
 *
 * Handles filtering media queries by folder.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for query functions
 */
class APEX_FOLDERS_Query {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add filter for WP_Query
        add_action( 'pre_get_posts', array( $this, 'filter_media_by_folder' ) );
        
        // Add support for Block Editor media filtering
        add_filter( 'ajax_query_attachments_args', array( $this, 'filter_attachments_query' ) );
    }
    
    /**
     * Filter media library by folder
     *
     * @param WP_Query $query The query object
     */
    public function filter_media_by_folder( $query ) {
        if ( is_admin() && $query->is_main_query() && isset( $query->query['post_type'] ) && $query->query['post_type'] === 'attachment' ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a GET parameter used for filtering only, not a form submission
            if ( isset( $_GET['apex_folder'] ) && ! empty( $_GET['apex_folder'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a GET parameter used for filtering only, not a form submission
                $folder_slug = sanitize_text_field( wp_unslash( $_GET['apex_folder'] ) );
                
                // Get the term ID to make sure we're using the right one
                $term = get_term_by( 'slug', $folder_slug, 'apex_folder' );
                
                if ( $term ) {
                    // Add explicit tax query
                    $query->set( 'tax_query', array(
                        array(
                            'taxonomy' => 'apex_folder',
                            'field' => 'term_id',
                            'terms' => $term->term_id,
                            'include_children' => false,
                            'operator' => 'IN' // Explicitly set operator
                        ),
                    ) );
                }
            }
        }
    }

    /**
     * Filter AJAX attachments query
     *
     * @param array $query Query arguments
     * @return array Modified query arguments
     */
    public function filter_attachments_query( $query ) {
        if ( ! isset( $query['apex_folder'] ) ) {
            return $query;
        }
        
        $folder = $query['apex_folder'];
        
        // Handle various data formats
        if ( is_array( $folder ) ) {
            $folder = isset( $folder[0] ) ? $folder[0] : '';
        }
        
        // User selected "All Folders" - remove the apex_folder filter
        if ( '' === $folder || '0' === $folder || 0 === $folder ) {
            unset( $query['apex_folder'] );
            return $query;
        }
        
        // The media library sends the folder slug; older links may still send the ID
        $term = get_term_by( 'slug', sanitize_title( $folder ), 'apex_folder' );
        if ( ! $term && is_numeric( $folder ) ) {
            $term = get_term( (int) $folder, 'apex_folder' );
        }
        
        // Unknown folder: leave the query alone so it matches nothing
        if ( ! $term || is_wp_error( $term ) ) {
            return $query;
        }
        
        // Show only the folder's own files, the same as the list view does
        unset( $query['apex_folder'] );
        
        if ( ! isset( $query['tax_query'] ) || ! is_array( $query['tax_query'] ) ) {
            $query['tax_query'] = array();
        }
        
        $query['tax_query'][] = array(
            'taxonomy'         => 'apex_folder',
            'field'            => 'term_id',
            'terms'            => array( (int) $term->term_id ),
            'include_children' => false,
        );
        
        return $query;
    }
}

// Initialize the class
new APEX_FOLDERS_Query();