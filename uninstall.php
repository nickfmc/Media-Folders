<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user opted to remove all data
$remove_all_data = get_option('apex_folders_remove_all_data', false);

if ($remove_all_data) {
    // Remove taxonomy terms and relationships
    global $wpdb;
    
    // Get all terms for our taxonomy
    $terms = get_terms([
        'taxonomy' => 'apex_folder',
        'hide_empty' => false,
    ]);
    
    // Delete all terms in the taxonomy
    foreach ($terms as $term) {
        wp_delete_term($term->term_id, 'apex_folder');
    }
    
    // Remove all plugin options
    delete_option('apex_folders_remove_all_data');
    delete_option('apex_folders_needs_migration');
    
    // Clean up any transients
    delete_transient('apex_folder_counts');
    
    // Additional cleanup if needed
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'apex_folders_%'");
}