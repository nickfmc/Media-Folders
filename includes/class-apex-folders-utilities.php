<?php
/**
 * Media Folders Utilities
 *
 * Helper functions for the Media Folders plugin.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
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
            'apex_folder',
            'attachment',
            array(
                'labels' => array(
                    'name'              => esc_html__( 'Media Folders', 'apex-folders' ),
                    'singular_name'     => esc_html__( 'Media Folder', 'apex-folders' ),
                    'menu_name'         => esc_html__( 'Folders', 'apex-folders' ),
                    'all_items'         => esc_html__( 'All Folders', 'apex-folders' ),
                    'edit_item'         => esc_html__( 'Edit Folder', 'apex-folders' ),
                    'view_item'         => esc_html__( 'View Folder', 'apex-folders' ),
                    'update_item'       => esc_html__( 'Update Folder', 'apex-folders' ),
                    'add_new_item'      => esc_html__( 'Add New Folder', 'apex-folders' ),
                    'new_item_name'     => esc_html__( 'New Folder Name', 'apex-folders' ),
                    'parent_item'       => esc_html__( 'Parent Folder', 'apex-folders' ),
                    'parent_item_colon' => esc_html__( 'Parent Folder:', 'apex-folders' ),
                    'search_items'      => esc_html__( 'Search Folders', 'apex-folders' ),
                ),
                'hierarchical'      => true,
                'show_ui'           => false,
                'show_in_menu'      => false,
                'show_in_nav_menus' => false,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array( 'slug' => 'apex-folder' ),
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
        if ( $screen->base === 'upload' ) {
            // Enqueue jQuery UI core and all required components for dialogs
            wp_enqueue_script( 'jquery-ui-core' ); 
            wp_enqueue_script( 'jquery-ui-dialog' );
            wp_enqueue_script( 'jquery-ui-draggable' );
            wp_enqueue_script( 'jquery-ui-resizable' );
            
            // Enqueue jQuery UI CSS
            wp_enqueue_style( 'wp-jquery-ui-dialog' );
        }
    }
    
    /**
     * Enqueue CSS styles for the media library
     * 
     * @return void
     */
    public static function enqueue_styles() {
        $screen = get_current_screen();
        if ( $screen->base === 'upload' ) {
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
        wp_add_inline_script( 'media-editor', '
            // Force refresh helper
            window.mediaFoldersRefreshView = function() {
                if (wp.media.frame) {
                    wp.media.frame.library.props.set({ignore: (+ new Date())});
                    wp.media.frame.library.props.trigger("change");
                }
                jQuery(".attachments-browser .attachments").trigger("scroll");
            };
        ' );
    }
    
    /**
     * Update term counts for all media folders
     * 
     * @return void
     */
    public static function update_folder_counts() {
        global $wpdb;
        
        // Get all media folder terms
        $folders = get_terms( array(
            'taxonomy'   => 'apex_folder',
            'hide_empty' => false,
        ) );
        
        if ( empty( $folders ) ) {
            return;
        }
        
        foreach ( $folders as $folder ) {
            // Count attachments in this folder
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->term_relationships
                 JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
                 WHERE $wpdb->term_relationships.term_taxonomy_id = %d
                 AND $wpdb->posts.post_type = 'attachment'",
                 $folder->term_taxonomy_id
            ) );
            
            // Update the count in the database directly
            $wpdb->update(
                $wpdb->term_taxonomy,
                array( 'count' => $count ),
                array( 'term_taxonomy_id' => $folder->term_taxonomy_id )
            );
        }
        
        // Clear caches
        clean_term_cache( wp_list_pluck( $folders, 'term_id' ), 'apex_folder' );
        delete_transient( 'apex_folder_counts' );
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
        $folders = get_terms( array(
            'taxonomy'   => 'apex_folder',
            'hide_empty' => false,
        ) );
        
        // Get the unassigned ID
        $unassigned_id = APEX_FOLDERS_Unassigned::get_id();
        
        // Find and categorize folders
        $unassigned_folder = null;
        $parent_folders    = array();
        $child_folders     = array();
        
        foreach ( $folders as $folder ) {
            if ( $folder->term_id == $unassigned_id ) {
                $unassigned_folder = $folder;
            } elseif ( $folder->parent == 0 ) {
                $parent_folders[] = $folder;
            } else {
                $child_folders[$folder->parent][] = $folder;
            }
        }
        
        return array(
            'all'        => $folders,
            'unassigned' => $unassigned_folder,
            'parents'    => $parent_folders,
            'children'   => $child_folders
        );
    }
    
    /**
     * Prevent numeric term creation
     * 
     * @param string $term     The term name
     * @param string $taxonomy The taxonomy name
     * @return string|WP_Error The term name or error
     */
    public static function prevent_numeric_term_creation( $term, $taxonomy ) {
        // Only check apex_folder taxonomy
        if ( $taxonomy !== 'apex_folder' ) {
            return $term;
        }
        
        // If term is numeric, it's likely an ID being misinterpreted
        if ( is_numeric( $term ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // translators: %s: numeric term name that was prevented
                error_log( sprintf( esc_html__( 'Preventing creation of numeric term: %s', 'apex-folders' ), $term ) );
            }
            
            // Return an error to prevent term creation
            return new WP_Error( 'invalid_term', esc_html__( "Can't create folder with numeric name", 'apex-folders' ) );
        }
        
        return $term;
    }
}