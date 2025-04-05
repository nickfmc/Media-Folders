<?php
/**
 * Plugin Name: Apex Folders
 * Plugin URI: https://mountainairweb.com
 * Description: Reach the apex of media library organization.
 * Version: 0.9.9
 * Author: Nick Murray
 * Author URI: https://mountainairweb.com
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
define('APEX_FOLDERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APEX_FOLDERS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('APEX_FOLDERS_VERSION', '0.9.9');

// Global variable for tracking folder processing
global $is_processing_apex_folder;
$is_processing_apex_folder = false;

// Core includes
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-folder-drag-drop.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-unassigned.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-utilities.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Additional component includes
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-ui.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-uploads.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-admin.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-media-query.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-block-editor.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'APEX_FOLDERS_activate');
register_deactivation_hook(__FILE__, 'APEX_FOLDERS_deactivate');

/**
 * Plugin activation
 */
function APEX_FOLDERS_activate() {
    // Register taxonomy on activation
    APEX_FOLDERS_Utilities::register_taxonomy();
    
    // Create default "Unassigned" folder if it doesn't exist
    $unassigned_id = APEX_FOLDERS_Unassigned::get_id();
    
    // Migrate existing unassigned media immediately
    APEX_FOLDERS_Unassigned::ensure_all_assigned();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation
 */
function APEX_FOLDERS_deactivate() {
    // Flush rewrite rules to remove our custom rules
    flush_rewrite_rules();
}

/**
 * Get the Unassigned folder ID
 */
function APEX_FOLDERS_get_unassigned_id() {
    return APEX_FOLDERS_Unassigned::get_id();
}

/**
 * Register 'apex_folder' taxonomy
 */
function APEX_FOLDERS_register_taxonomy() {
    APEX_FOLDERS_Utilities::register_taxonomy();
}
add_action('init', 'APEX_FOLDERS_register_taxonomy');

/**
 * Prevent creation of terms with numeric names that should be IDs
 */
function prevent_numeric_term_creation($term, $taxonomy) {
    return APEX_FOLDERS_Utilities::prevent_numeric_term_creation($term, $taxonomy);
}
add_filter('pre_insert_term', 'prevent_numeric_term_creation', 10, 2);



/**
 * Ensure all attachments have a folder assignment
 */
function APEX_FOLDERS_ensure_folder_assignment($post_id) {
    APEX_FOLDERS_Unassigned::ensure_attachment_has_folder($post_id);
}
add_action('save_post', 'APEX_FOLDERS_ensure_folder_assignment');
add_action('edit_attachment', 'APEX_FOLDERS_ensure_folder_assignment');

/**
 * Directly assign all unassigned media to the Unassigned folder
 */
function APEX_FOLDERS_ensure_all_assigned() {
    return APEX_FOLDERS_Unassigned::ensure_all_assigned();
}

/**
 * Force update term counts for media folders
 */
function theme_update_apex_folder_counts() {
    APEX_FOLDERS_Utilities::update_folder_counts();
}

/**
 * Initialize plugin
 */
function APEX_FOLDERS_init() {
    // Migration is already handled during activation
    update_option('APEX_FOLDERS_needs_migration', false);
}
add_action('init', 'APEX_FOLDERS_init');