<?php
/**
 * Plugin Name: Apex Folders
 * Plugin URI: https://mountainairweb.com
 * Description: Reach the apex of media library organization.
 * Version: 0.9.9
 * Author: 
 * Text Domain: apex-folders
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Define plugin constants
define('MEDIA_FOLDERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEDIA_FOLDERS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MEDIA_FOLDERS_VERSION', '0.9.0');

// Global variable for tracking folder processing
global $is_processing_media_folder;
$is_processing_media_folder = false;

// Core includes
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-folder-drag-drop.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-unassigned.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-utilities.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Additional component includes
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-ui.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-uploads.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-admin.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-query.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-block-editor.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'media_folders_activate');
register_deactivation_hook(__FILE__, 'media_folders_deactivate');

/**
 * Plugin activation
 */
function media_folders_activate() {
    // Register taxonomy on activation
    Media_Folders_Utilities::register_taxonomy();
    
    // Create default "Unassigned" folder if it doesn't exist
    $unassigned_id = Media_Folders_Unassigned::get_id();
    
    // Migrate existing unassigned media immediately
    Media_Folders_Unassigned::ensure_all_assigned();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation
 */
function media_folders_deactivate() {
    // Flush rewrite rules to remove our custom rules
    flush_rewrite_rules();
}

/**
 * Get the Unassigned folder ID
 */
function media_folders_get_unassigned_id() {
    return Media_Folders_Unassigned::get_id();
}

/**
 * Register 'media_folder' taxonomy
 */
function media_folders_register_taxonomy() {
    Media_Folders_Utilities::register_taxonomy();
}
add_action('init', 'media_folders_register_taxonomy');

/**
 * Prevent creation of terms with numeric names that should be IDs
 */
function prevent_numeric_term_creation($term, $taxonomy) {
    return Media_Folders_Utilities::prevent_numeric_term_creation($term, $taxonomy);
}
add_filter('pre_insert_term', 'prevent_numeric_term_creation', 10, 2);

/**
 * Debug term assignments
 */
function debug_media_folder_assignment($post_id, $terms, $tt_ids, $taxonomy) {
    Media_Folders_Utilities::debug_folder_assignment($post_id, $terms, $tt_ids, $taxonomy);
}
add_action('set_object_terms', 'debug_media_folder_assignment', 999, 4);

/**
 * Ensure all attachments have a folder assignment
 */
function media_folders_ensure_folder_assignment($post_id) {
    Media_Folders_Unassigned::ensure_attachment_has_folder($post_id);
}
add_action('save_post', 'media_folders_ensure_folder_assignment');
add_action('edit_attachment', 'media_folders_ensure_folder_assignment');

/**
 * Directly assign all unassigned media to the Unassigned folder
 */
function media_folders_ensure_all_assigned() {
    return Media_Folders_Unassigned::ensure_all_assigned();
}

/**
 * Force update term counts for media folders
 */
function theme_update_media_folder_counts() {
    Media_Folders_Utilities::update_folder_counts();
}

/**
 * Initialize plugin
 */
function media_folders_init() {
    // Migration is already handled during activation
    update_option('media_folders_needs_migration', false);
}
add_action('init', 'media_folders_init');