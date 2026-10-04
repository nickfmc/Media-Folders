<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user opted to remove all data
$remove_all_data = get_option('apex_folders_remove_all_data', false);

if ($remove_all_data) {
    // First make sure the taxonomy is registered so we can remove terms
    if (!taxonomy_exists('apex_folder')) {
        register_taxonomy('apex_folder', 'attachment');
    }
    
    // Remove taxonomy terms and relationships
    global $wpdb;
    
    // Get all terms for our taxonomy
    $terms = get_terms([
        'taxonomy' => 'apex_folder',
        'hide_empty' => false,
    ]);
    
    if (is_wp_error($terms)) {
        // Handle error silently in production
    } else {
        // Delete all terms in the taxonomy
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, 'apex_folder');
        }
    }
    
    // Remove all plugin options
    delete_option('apex_folders_remove_all_data');
    delete_option('apex_folders_needs_migration');
    
    // Clean up any transients
    delete_transient('apex_folder_counts');
    
    // Find and delete all plugin options
    $option_names = array(
        'apex_folders_remove_all_data',
        'apex_folders_needs_migration',
        'apex_folders_version',
        'apex_folders_settings',
        'apex_folders_unassigned_id'
    );
    
    foreach ( $option_names as $option ) {
        delete_option( $option );
    }
} else {
    // User opted to keep data, do nothing
}