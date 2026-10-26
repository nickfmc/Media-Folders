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
                // Count every attachment; the default callback skips unattached media
                'update_count_callback' => '_update_generic_term_count',
            )
        );
    }
    
    /**
     * Update term counts for all media folders
     * 
     * @return void
     */
    public static function update_folder_counts() {
        // Get all media folder terms
        $folders = get_terms( array(
            'taxonomy'   => 'apex_folder',
            'hide_empty' => false,
        ) );

        if ( empty( $folders ) || is_wp_error( $folders ) ) {
            return;
        }

        // Recount through WordPress; the taxonomy's count callback counts every attachment
        wp_update_term_count_now( wp_list_pluck( $folders, 'term_taxonomy_id' ), 'apex_folder' );

        // Clear caches
        clean_term_cache( wp_list_pluck( $folders, 'term_id' ), 'apex_folder' );
        delete_transient( 'apex_folder_counts' );
    }

    /**
     * Create a folder, enforcing the one level of subfolders the sidebar supports
     *
     * @param string $name      Folder name.
     * @param int    $parent_id Parent folder ID, or 0 for a top-level folder.
     * @return int|WP_Error The new folder's term ID, or an error.
     */
    public static function create_folder( $name, $parent_id = 0 ) {
        $name      = sanitize_text_field( $name );
        $parent_id = absint( $parent_id );

        if ( '' === $name ) {
            return new WP_Error( 'apex_folders_empty_name', __( 'Folder name cannot be empty', 'apex-folders' ) );
        }

        // Ensure parent folder exists if specified
        if ( $parent_id > 0 ) {
            $parent_term = get_term( $parent_id, 'apex_folder' );
            if ( ! $parent_term || is_wp_error( $parent_term ) ) {
                return new WP_Error( 'apex_folders_no_parent', __( 'Parent folder does not exist', 'apex-folders' ) );
            }

            // Ensure parent isn't the Unassigned folder
            if ( $parent_id === (int) APEX_FOLDERS_Unassigned::get_id() ) {
                return new WP_Error( 'apex_folders_unassigned_parent', __( 'Cannot create subfolders under Unassigned', 'apex-folders' ) );
            }

            // Check if parent itself has a parent (limit to one level)
            if ( (int) $parent_term->parent > 0 ) {
                return new WP_Error( 'apex_folders_too_deep', __( 'Only one level of subfolders is supported', 'apex-folders' ) );
            }
        }

        $result = wp_insert_term( $name, 'apex_folder', array( 'parent' => $parent_id ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return (int) $result['term_id'];
    }

    /**
     * Move attachments into a folder
     *
     * @param int[] $attachment_ids Attachment IDs.
     * @param int   $folder_id      Target folder term ID.
     * @return array|WP_Error { moved: int[], failed: int[] }, or an error if the folder does not exist.
     */
    public static function move_items( $attachment_ids, $folder_id ) {
        $folder_id = absint( $folder_id );

        $folder_term = $folder_id ? get_term( $folder_id, 'apex_folder' ) : null;
        if ( ! $folder_term || is_wp_error( $folder_term ) ) {
            return new WP_Error( 'apex_folders_no_folder', __( 'The selected folder does not exist.', 'apex-folders' ) );
        }

        $moved  = array();
        $failed = array();

        // Recount the folders once at the end rather than after every file
        wp_defer_term_counting( true );

        foreach ( array_unique( array_map( 'absint', (array) $attachment_ids ) ) as $id ) {
            if ( ! $id || get_post_type( $id ) !== 'attachment' ) {
                $failed[] = $id;
                continue;
            }

            $result = wp_set_object_terms( $id, array( $folder_id ), 'apex_folder', false );

            if ( is_wp_error( $result ) ) {
                $failed[] = $id;
            } else {
                $moved[] = $id;
            }
        }

        wp_defer_term_counting( false );

        return array(
            'moved'  => $moved,
            'failed' => $failed,
        );
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
}
