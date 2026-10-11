<?php
/**
 * Media Folders Editor Integration
 *
 * Integrates folder filtering with the block editor.
 *
 * @package apex-folders
 * @since 0.9.9
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for block editor integration
 */
class APEX_FOLDERS_Editor {

    /**
     * Constructor
     */
    public function __construct() {
        // Add assets wherever the media grid/modal is loaded
        add_action( 'wp_enqueue_media', array( $this, 'enqueue_block_editor_assets' ) );
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // Get media folders data
        $folders = get_terms( array(
            'taxonomy' => 'apex_folder',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $folders ) ) {
            $folders = array();
        }

        // Convert to array for JavaScript
        $folders_data = array();
        foreach ( $folders as $folder ) {
            $folders_data[] = array(
                'id' => $folder->term_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'count' => $folder->count,
                'parent' => $folder->parent
            );
        }

        // Enqueue our filtering script. It loads after the media views it extends
        // and runs before any media frame is created.
        wp_enqueue_script(
            'apex-folder-filters',
            APEX_FOLDERS_PLUGIN_URL . 'assets/js/apex-folders.js',
            array( 'jquery', 'media-views' ),
            filemtime( APEX_FOLDERS_PLUGIN_DIR . 'assets/js/apex-folders.js' ),
            true
        );

        // Add media folders data to the page
        wp_add_inline_script(
            'apex-folder-filters',
            'window.mediaFolders = ' . wp_json_encode( $folders_data ) . ';',
            'before'
        );

        // Add translatable strings for JavaScript
        wp_localize_script(
            'apex-folder-filters',
            'apexfoFoldersMedia',
            array(
                'allFolders'     => __( 'All folders', 'apex-folders' ),
                'filterByFolder' => __( 'Filter by folder', 'apex-folders' ),
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'APEX_FOLDERS_nonce' ),
            )
        );

        // Enqueue the CSS for the folder interface
        wp_enqueue_style(
            'apex-folders-css',
            APEX_FOLDERS_PLUGIN_URL . 'assets/css/apex-main.css',
            array(),
            filemtime( APEX_FOLDERS_PLUGIN_DIR . 'assets/css/apex-main.css' )
        );
    }
}

// Initialize the class
new APEX_FOLDERS_Editor();
