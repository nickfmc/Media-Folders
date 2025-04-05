<?php
/**
 * Media Folders Query
 *
 * Handles filtering media queries by folder.
 *
 * @package Media-Folders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for query functions
 */
class Media_Folders_Query {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add filter for WP_Query
        add_action('pre_get_posts', array($this, 'filter_media_by_folder'));
        
        // Add support for Block Editor media filtering
        add_filter('ajax_query_attachments_args', array($this, 'filter_attachments_query'));
        
        // Add folder term count update trigger
        add_action('set_object_terms', array($this, 'set_object_terms_callback'), 10, 6);
        
        // Register term count update event
        add_action('init', array($this, 'register_folder_count_event'));
    }
    
    /**
     * Filter media library by folder
     *
     * @param WP_Query $query The query object
     */
    public function filter_media_by_folder($query) {
        if (is_admin() && $query->is_main_query() && isset($query->query['post_type']) && $query->query['post_type'] === 'attachment') {
            if (isset($_GET['media_folder']) && !empty($_GET['media_folder'])) {
                $folder_slug = sanitize_text_field($_GET['media_folder']);
                
                // Get the term ID to make sure we're using the right one
                $term = get_term_by('slug', $folder_slug, 'media_folder');
                
                if ($term) {
                    // Add debug information
                    error_log("Filtering by folder: {$term->name} (ID: {$term->term_id})");
                    
                    // Add explicit tax query
                    $query->set('tax_query', array(
                        array(
                            'taxonomy' => 'media_folder',
                            'field' => 'term_id',
                            'terms' => $term->term_id,
                            'include_children' => false,
                            'operator' => 'IN' // Explicitly set operator
                        ),
                    ));
                    
                    // Add helpful debug
                    $sql = $query->request;
                    error_log("SQL query: $sql");
                } else {
                    error_log("Error: Media folder term not found for slug: {$folder_slug}");
                }
            }
        }
    }
    
    /**
     * Filter AJAX attachments query
     *
     * @param array $query Query arguments
     * @return array Modified query arguments
     */
    public function filter_attachments_query($query) {
        if (isset($query['media_folder'])) {
            // If empty string or 0, remove any existing tax_query for media_folder
            if ($query['media_folder'] === '' || $query['media_folder'] === '0' || $query['media_folder'] === 0) {
                // User selected "All Folders" - remove the media_folder filter
                unset($query['media_folder']);
                // Also remove any tax_query that might be for media_folder
                if (isset($query['tax_query']) && is_array($query['tax_query'])) {
                    foreach ($query['tax_query'] as $key => $tax_query) {
                        if (isset($tax_query['taxonomy']) && $tax_query['taxonomy'] === 'media_folder') {
                            unset($query['tax_query'][$key]);
                        }
                    }
                    // If tax_query is now empty, remove it
                    if (empty($query['tax_query'])) {
                        unset($query['tax_query']);
                    }
                }
                return $query;
            }
            
            // Get the folder ID, ensuring it's an integer
            $folder_id = $query['media_folder'];
            
            // Handle various data formats
            if (is_array($folder_id)) {
                $folder_id = isset($folder_id[0]) ? $folder_id[0] : 0;
            }
            
            // Convert string IDs to integers
            $folder_id = intval($folder_id);
            
            error_log('Processing folder ID: ' . $folder_id);
            
            // Skip empty folder IDs
            if (!$folder_id) {
                error_log('Empty folder ID, skipping filter');
                return $query;
            }
            
            // Confirm folder exists
            $term = get_term($folder_id, 'media_folder');
            if (!is_wp_error($term) && $term) {
                error_log('Found folder: ' . $term->name . ' (ID: ' . $term->term_id . ')');
                
                // Create tax query - use simplest possible format
                $query['tax_query'] = array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'media_folder',
                        'field' => 'term_id',
                        'terms' => array($folder_id),
                        'operator' => 'IN'
                    )
                );
                
                // Remove any conflicting query params
                unset($query['media_folder']);
                
                error_log('Modified query: ' . print_r($query, true));
            } else {
                error_log('Term not found for ID: ' . $folder_id);
            }
        }
        
        return $query;
    }
    
    /**
     * Trigger term count update when terms are set
     *
     * @param int $object_id Object ID
     * @param array $terms Terms
     * @param array $tt_ids Term taxonomy IDs
     * @param string $taxonomy Taxonomy
     * @param bool $append Whether to append to existing terms
     * @param array $old_tt_ids Old term taxonomy IDs
     */
    public function set_object_terms_callback($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if ($taxonomy === 'media_folder') {
            // Schedule a deferred count update to ensure it happens after WordPress completes its operations
            wp_schedule_single_event(time() + 2, 'theme_update_media_folder_counts_event');
        }
    }
    
    /**
     * Register folder count event
     */
    public function register_folder_count_event() {
        add_action('theme_update_media_folder_counts_event', 'theme_update_media_folder_counts');
    }
}

// Initialize the class
new Media_Folders_Query();