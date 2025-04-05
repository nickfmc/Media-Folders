<?php
/**
 * Media Folders Editor Integration
 *
 * Integrates folder filtering with the block editor.
 *
 * @package Media-Folders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for block editor integration
 */
class Media_Folders_Editor {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add assets to block editor
        add_action('admin_init', array($this, 'enqueue_block_editor_assets'), 5);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_block_editor_assets'));
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // Get media folders data
        $folders = get_terms(array(
            'taxonomy' => 'media_folder',
            'hide_empty' => false,
        ));
        
        // Convert to array for JavaScript
        $folders_data = array();
        foreach ($folders as $folder) {
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
            'media-folder-data',
            '',
            array('media-editor'),
            '1.0',
            false
        );
        
        // Add media folders data to the page
        wp_add_inline_script(
            'media-folder-data',
            'window.mediaFolders = ' . json_encode($folders_data) . ';',
            'before'
        );
        wp_enqueue_script('media-folder-data');
        
        // Then, enqueue our filtering script - IMPORTANT: set in_footer to FALSE
        wp_enqueue_script(
            'media-folder-filters',
            MEDIA_FOLDERS_PLUGIN_URL . 'assets/js/apex-folders.js', 
            array('jquery', 'wp-blocks', 'media-editor', 'media-folder-data'), 
            filemtime(MEDIA_FOLDERS_PLUGIN_DIR . 'assets/js/apex-folders.js'),
            false // Load in header, not footer
        );
    }
}

// Initialize the class
new Media_Folders_Editor();