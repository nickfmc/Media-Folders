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
        // Add assets to block editor
        add_action( 'admin_init', array( $this, 'enqueue_block_editor_assets' ), 5 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_block_editor_assets' ) );
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

        // First, register and enqueue the folder data
        wp_register_script(
            'apex-folder-data',
            '',
            array( 'media-editor' ),
            '1.0',
            false
        );
        
        // Add media folders data to the page
        wp_add_inline_script(
            'apex-folder-data',
            'window.mediaFolders = ' . wp_json_encode( $folders_data ) . ';',
            'before'
        );
        wp_enqueue_script( 'apex-folder-data' );
        
        // Then, enqueue our filtering script - IMPORTANT: set in_footer to FALSE
        wp_enqueue_script(
            'apex-folder-filters',
            APEX_FOLDERS_PLUGIN_URL . 'assets/js/apex-folders.js', 
            array( 'jquery', 'wp-blocks', 'media-editor', 'apex-folder-data' ), 
            filemtime( APEX_FOLDERS_PLUGIN_DIR . 'assets/js/apex-folders.js' ),
            false // Load in header, not footer
        );
        
        // Add translatable strings for JavaScript
        wp_localize_script(
            'apex-folder-filters',
            'apexFoldersL10n',
            array(
                'allMedia'       => esc_html__( 'All Media', 'apex-folders' ),
                'unassigned'     => esc_html__( 'Unassigned', 'apex-folders' ),
                'folders'        => esc_html__( 'Folders', 'apex-folders' ),
                'filterByFolder' => esc_html__( 'Filter by folder', 'apex-folders' ),
                'selectFolder'   => esc_html__( 'Select folder', 'apex-folders' ),
                'createFolder'   => esc_html__( 'Create folder', 'apex-folders' ),
                'deleteFolder'   => esc_html__( 'Delete folder', 'apex-folders' ),
                'renameFolder'   => esc_html__( 'Rename folder', 'apex-folders' ),
                'moveTo'         => esc_html__( 'Move to', 'apex-folders' ),
                'loading'        => esc_html__( 'Loading folders...', 'apex-folders' ),
                'error'          => esc_html__( 'Error loading folders', 'apex-folders' )
            )
        );
    }
}

// Initialize the class
new APEX_FOLDERS_Editor();