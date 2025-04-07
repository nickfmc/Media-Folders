<?php
/**
 * Apex Folders
 *
 * @package     ApexFolders
 * @author      Nick Murray
 * @copyright   2025 Mountain Air Web
 * @license     GPL-2.0+
 * @wordpress-plugin
 *
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
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access is not permitted.' );
}

// Define plugin constants.
define( 'APEX_FOLDERS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APEX_FOLDERS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APEX_FOLDERS_VERSION', '0.9.9' );

/**
 * Class for tracking folder processing state.
 * 
 * @since 0.9.9
 */
class Apex_Folders_State {
    /**
     * Storage for tracking folder processing state.
     *
     * @var boolean
     */
    private static $is_processing_folder = false;
    
    /**
     * Set the processing state.
     *
     * @param boolean $value The processing state.
     * @return void
     */
    public static function set_processing( $value ) {
        self::$is_processing_folder = $value;
    }
    
    /**
     * Check if folders are currently being processed.
     *
     * @return boolean Current processing state.
     */
    public static function is_processing() {
        return self::$is_processing_folder;
    }
}

// Core includes.
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-folder-drag-drop.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-unassigned.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-utilities.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Additional component includes.
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-ui.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-uploads.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-admin.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-media-query.php';
require_once APEX_FOLDERS_PLUGIN_DIR . 'includes/class-apex-folders-block-editor.php';

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'apex_folders_activate' );
register_deactivation_hook( __FILE__, 'apex_folders_deactivate' );

/**
 * Plugin activation handler.
 *
 * Registers taxonomy and creates default unassigned folder.
 *
 * @since 0.9.9
 * @return void
 */
function apex_folders_activate() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    
    // Register taxonomy on activation.
    APEX_FOLDERS_Utilities::register_taxonomy();
    
    // Create default "Unassigned" folder if it doesn't exist.
    $unassigned_id = APEX_FOLDERS_Unassigned::get_id();
    
    // Migrate existing unassigned media immediately.
    APEX_FOLDERS_Unassigned::ensure_all_assigned();
    
    // Flush rewrite rules.
    flush_rewrite_rules();
}

/**
 * Plugin deactivation handler.
 *
 * @since 0.9.9
 * @return void
 */
function apex_folders_deactivate() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    
    // Flush rewrite rules to remove our custom rules.
    flush_rewrite_rules();
}

/**
 * Get the Unassigned folder ID.
 *
 * @since 0.9.9
 * @return int The unassigned folder term ID.
 */
function apex_folders_get_unassigned_id() {
    return APEX_FOLDERS_Unassigned::get_id();
}

/**
 * Register 'apex_folder' taxonomy.
 *
 * @since 0.9.9
 * @return void
 */
function apex_folders_register_taxonomy() {
    APEX_FOLDERS_Utilities::register_taxonomy();
}
add_action( 'init', 'apex_folders_register_taxonomy' );

/**
 * Prevent creation of terms with numeric names that should be IDs.
 *
 * @since 0.9.9
 * @param string $term     The term name to check.
 * @param string $taxonomy The taxonomy name.
 * @return mixed Term name or WP_Error on failure.
 */
function prevent_numeric_term_creation( $term, $taxonomy ) {
    return APEX_FOLDERS_Utilities::prevent_numeric_term_creation( $term, $taxonomy );
}
add_filter( 'pre_insert_term', 'prevent_numeric_term_creation', 10, 2 );

/**
 * Ensure all attachments have a folder assignment.
 *
 * @since 0.9.9
 * @param int $post_id The attachment post ID.
 * @return void
 */
function apex_folders_ensure_folder_assignment( $post_id ) {
    if ( ! $post_id || ! is_numeric( $post_id ) ) {
        return;
    }
    APEX_FOLDERS_Unassigned::ensure_attachment_has_folder( $post_id );
}
add_action( 'save_post', 'apex_folders_ensure_folder_assignment' );
add_action( 'edit_attachment', 'apex_folders_ensure_folder_assignment' );

/**
 * Directly assign all unassigned media to the Unassigned folder.
 *
 * @since 0.9.9
 * @return int Number of media items assigned.
 */
function apex_folders_ensure_all_assigned() {
    return APEX_FOLDERS_Unassigned::ensure_all_assigned();
}

/**
 * Force update term counts for media folders.
 *
 * @since 0.9.9
 * @return void
 */
function apex_folders_update_counts() {
    APEX_FOLDERS_Utilities::update_folder_counts();
}

/**
 * Initialize plugin.
 *
 * @since 0.9.9
 * @return void
 */
function apex_folders_init() {
    // Migration is already handled during activation.
    update_option( 'apex_folders_needs_migration', false );
}
add_action( 'init', 'apex_folders_init' );