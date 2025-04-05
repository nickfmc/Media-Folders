<?php
/**
 * Media Folders Admin Tools
 *
 * Provides debugging and administration tools for the Media Folders plugin.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for admin and debugging tools
 */
class APEX_FOLDERS_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin toolbar links
        add_action('admin_notices', array($this, 'admin_toolbar_links'));
        
        // Add debug functions (for admin only)
        add_action('admin_notices', array($this, 'debug_folder_content'));
        add_action('admin_notices', array($this, 'debug_unassigned_folder'));
        
        // Handle term cache flushing
        add_action('admin_init', array($this, 'handle_term_cache_flush'));
        
        // Handle rebuilding unassigned folder
        add_action('admin_init', array($this, 'handle_rebuild_unassigned'));

        // Register settings for plugin cleanup
        add_action('admin_init', array($this, 'register_settings'));

    }
    
    /**
     * Display admin toolbar links
     */
    public function admin_toolbar_links() {
        $screen = get_current_screen();
        if ($screen->base !== 'upload') return;
        
        if (current_user_can('manage_options')) {
            $unassigned_id = APEX_FOLDERS_get_unassigned_id();
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Media Folders Tools:</strong>
                    <a href="<?php echo wp_nonce_url(admin_url('upload.php?rebuild_unassigned=1'), 'rebuild_unassigned'); ?>" class="button">Rebuild Unassigned Folder</a>
                    <a href="<?php echo admin_url('upload.php?debug_folders=1'); ?>" class="button">Show Folder Debug Info</a>
                    <a href="<?php echo wp_nonce_url(admin_url('upload.php?flush_term_cache=1'), 'flush_term_cache'); ?>" class="button">Flush Term Cache</a>
                    <a href="<?php echo admin_url('upload.php?debug_unassigned=1'); ?>" class="button">Debug Unassigned (ID: <?php echo $unassigned_id; ?>)</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Debug folder content
     */

public function debug_folder_content() {
    $screen = get_current_screen();
    if ($screen->base !== 'upload') return;
    
    if (isset($_GET['debug_folders']) && current_user_can('manage_options')) {
        global $wpdb;
        
        // IMPROVED: Use wp_kses for HTML in admin notices
        $output = '<div class="notice notice-info"><p><strong>' . esc_html__('Media Folders Debug:', 'apex-folders') . '</strong></p><ul>';
        
        // Check all folder terms
        $folders = get_terms(array(
            'taxonomy' => 'apex_folder',
            'hide_empty' => false,
        ));
        
        foreach ($folders as $folder) {
            // Count using SQL to avoid caching issues
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                WHERE tt.term_id = %d AND p.post_type = 'attachment'",
                $folder->term_id
            ));
            
            // IMPROVED: Escape all data before output
            $output .= sprintf(
                '<li><strong>%s</strong> (ID: %d, Slug: %s): %d items</li>',
                esc_html($folder->name),
                intval($folder->term_id),
                esc_html($folder->slug),
                intval($count)
            );
        }
        
        $output .= '</ul></div>';
        
        // IMPROVED: Use wp_kses to allow only specific HTML
        echo wp_kses($output, array(
            'div' => array('class' => array()),
            'p' => array(),
            'strong' => array(),
            'ul' => array(),
            'li' => array(),
        ));
    }
}
    
    /**
     * Debug unassigned folder
     */
    public function debug_unassigned_folder() {
        if (!isset($_GET['debug_unassigned']) || !current_user_can('manage_options')) {
            return;
        }
        
        global $wpdb;
        $unassigned_id = APEX_FOLDERS_get_unassigned_id();
        
        echo '<div class="notice notice-info">';
        echo '<h3>Unassigned Folder Debug</h3>';
        
        // 1. Check term exists
        $term = get_term($unassigned_id, 'apex_folder');
        echo '<p>Term check: ' . ($term ? 'Found' : 'Not found') . '</p>';
        if ($term) {
            echo '<p>Term details: ID=' . $term->term_id . ', Name=' . $term->name . ', Slug=' . $term->slug . ', Count=' . $term->count . '</p>';
        }
        
        // 2. Check term_taxonomy record
        $tt_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
             WHERE term_id = %d AND taxonomy = 'apex_folder'",
            $unassigned_id
        ));
        echo '<p>Term taxonomy ID: ' . ($tt_id ?: 'Not found') . '</p>';
        
        // 3. Count direct from database
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->term_relationships tr
             JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.term_id = %d AND tt.taxonomy = 'apex_folder'",
            $unassigned_id
        ));
        echo '<p>Actual count in database: ' . $count . '</p>';
        
        // 4. List some items in the unassigned folder
        $items = $wpdb->get_col($wpdb->prepare(
            "SELECT tr.object_id FROM $wpdb->term_relationships tr
             JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.term_id = %d AND tt.taxonomy = 'apex_folder'
             LIMIT 10",
            $unassigned_id
        ));
        if ($items) {
            echo '<p>Sample items: ' . implode(', ', $items) . '</p>';
        } else {
            echo '<p>No items found in Unassigned folder</p>';
        }
        
        // 5. Check for media with no folder
        $no_folder = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts p 
             WHERE p.post_type = 'attachment' 
             AND NOT EXISTS (
                 SELECT 1 FROM $wpdb->term_relationships tr
                 JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE tt.taxonomy = 'apex_folder' AND tr.object_id = p.ID
             )"
        );
        echo '<p>Media items with no folder at all: ' . $no_folder . '</p>';
        
        echo '</div>';
    }
    
    /**
     * Handle flush term cache request
     */
    public function handle_term_cache_flush() {
        if (isset($_GET['flush_term_cache']) && current_user_can('manage_options')) {
            check_admin_referer('flush_term_cache');
            
            global $wpdb;
            
            // Get all media folder terms
            $terms = $wpdb->get_col("
                SELECT term_id FROM $wpdb->term_taxonomy
                WHERE taxonomy = 'apex_folder'
            ");
            
            // Clear caches
            clean_term_cache($terms, 'apex_folder');
            
            // Redirect back
            wp_redirect(add_query_arg('cache_flushed', '1', admin_url('upload.php')));
            exit;
        }
        
        if (isset($_GET['cache_flushed'])) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>Term caches have been flushed.</p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Handle rebuild unassigned folder request
     */
    public function handle_rebuild_unassigned() {
        if (isset($_GET['rebuild_unassigned']) && current_user_can('manage_options')) {
            check_admin_referer('rebuild_unassigned');
            
            // Use the direct assignment function
            $count = APEX_FOLDERS_ensure_all_assigned();
            
            // Redirect with message
            wp_redirect(add_query_arg('rebuild_complete', $count, admin_url('upload.php')));
            exit;
        }
        
        // Show success notice after rebuild
        if (isset($_GET['rebuild_complete'])) {
            add_action('admin_notices', function() {
                $count = intval($_GET['rebuild_complete']);
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>Unassigned folder rebuilt. <?php echo $count; ?> items were assigned to the Unassigned folder.</p>
                    <p><a href="<?php echo admin_url('upload.php?apex_folder=unassigned'); ?>">View Unassigned folder</a> | <a href="<?php echo admin_url('upload.php?debug_folders=1'); ?>">View folder debug info</a></p>
                </div>
                <?php
            });
        }
    }


/**
 * Register plugin settings
 */
public function register_settings() {
    // Register a new settings section
    add_settings_section(
        'apex_folders_settings',
        __('Apex Folders Settings', 'apex-folders'),
        array($this, 'render_settings_section'),
        'media'
    );
    
    // Register the uninstall setting
    register_setting(
        'media',
        'apex_folders_remove_all_data',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        )
    );
    
    // Add the field
    add_settings_field(
        'apex_folders_remove_all_data',
        __('Plugin Cleanup', 'apex-folders'),
        array($this, 'render_remove_data_field'),
        'media',
        'apex_folders_settings'
    );
}

/**
 * Render the settings section description
 */
public function render_settings_section() {
    echo '<p>' . esc_html__('Configure settings for the Apex Folders plugin.', 'apex-folders') . '</p>';
}

/**
 * Render the field for remove data option
 */
public function render_remove_data_field() {
    $value = get_option('apex_folders_remove_all_data', false);
    ?>
    <label for="apex_folders_remove_all_data">
        <input type="checkbox" id="apex_folders_remove_all_data" name="apex_folders_remove_all_data" value="1" <?php checked($value, true); ?>>
        <?php esc_html_e('Remove all folder data when plugin is uninstalled', 'apex-folders'); ?>
    </label>
    <p class="description" style="color: #d63638;">
        <?php esc_html_e('Warning: When enabled, all your media folders and organization structure will be permanently deleted if you uninstall this plugin.', 'apex-folders'); ?>
    </p>
    <?php
}

}

// Initialize the class
new APEX_FOLDERS_Admin();