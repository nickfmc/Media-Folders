<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// DEBUGGING: Log the uninstall process (remove in production)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Apex Folders: Uninstall process started');
}

// Check if user opted to remove all data
$remove_all_data = get_option('apex_folders_remove_all_data', false);

// DEBUGGING: Log the option value
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Apex Folders: remove_all_data option value: ' . ($remove_all_data ? 'true' : 'false'));
}

if ($remove_all_data) {
    // First make sure the taxonomy is registered so we can remove terms
    if (!taxonomy_exists('apex_folder')) {
        register_taxonomy('apex_folder', 'attachment');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Apex Folders: Registered taxonomy for uninstall');
        }
    }
    
    // Remove taxonomy terms and relationships
    global $wpdb;
    
    // Get all terms for our taxonomy
    $terms = get_terms([
        'taxonomy' => 'apex_folder',
        'hide_empty' => false,
    ]);
    
    if (is_wp_error($terms)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Apex Folders: Error getting terms: ' . $terms->get_error_message());
        }
    } else {
        // Delete all terms in the taxonomy
        foreach ($terms as $term) {
            $result = wp_delete_term($term->term_id, 'apex_folder');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (is_wp_error($result)) {
                    error_log('Apex Folders: Error deleting term ' . $term->term_id . ': ' . $result->get_error_message());
                } else {
                    error_log('Apex Folders: Deleted term ' . $term->term_id);
                }
            }
        }
    }
    
    // Remove all plugin options
    delete_option('apex_folders_remove_all_data');
    delete_option('apex_folders_needs_migration');
    
    // Clean up any transients
    delete_transient('apex_folder_counts');
    
    // Additional cleanup if needed
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'apex_folders_%'");
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Apex Folders: Cleanup completed');
    }
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Apex Folders: No cleanup performed (option not enabled)');
    }
}