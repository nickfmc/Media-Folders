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
 
// Includes
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-folder-drag-drop.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-unassigned.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-media-folders-utilities.php';
require_once MEDIA_FOLDERS_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'media_folders_activate');
register_deactivation_hook(__FILE__, 'media_folders_deactivate');



function media_folders_admin_scripts() {
    Media_Folders_Utilities::enqueue_admin_scripts();
}
add_action('admin_enqueue_scripts', 'media_folders_admin_scripts');




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
 * Get the Unassigned folder ID
 */
function media_folders_get_unassigned_id() {
    return Media_Folders_Unassigned::get_id();
}

function media_folders_enqueue_styles() {
    Media_Folders_Utilities::enqueue_styles();
}
add_action('admin_enqueue_scripts', 'media_folders_enqueue_styles');


function media_folders_enqueue_refresh_script() {
    Media_Folders_Utilities::enqueue_refresh_script();
}
add_action('admin_enqueue_scripts', 'media_folders_enqueue_refresh_script');

// Function to ensure all media items are assigned to a folder


/**
 * Plugin deactivation
 */
function media_folders_deactivate() {
    // Flush rewrite rules to remove our custom rules
    flush_rewrite_rules();
}


// Register 'media_folder' taxonomy
function media_folders_register_taxonomy() {
    Media_Folders_Utilities::register_taxonomy();
}

add_action('init', 'media_folders_register_taxonomy');



function media_folders_filter() {
    $screen = get_current_screen();
    if ($screen->base !== 'upload') return;
    
     // Check if we're in list view - if so, don't show the folders
     $mode = isset($_GET['mode']) ? $_GET['mode'] : '';
     if ($mode === 'list') {
         return; // Exit early if in list view
     }

    // Get organized folders using the utility class
    $organized = Media_Folders_Utilities::get_organized_folders();
    $unassigned_folder = $organized['unassigned'];
    $parent_folders = $organized['parents'];
    $child_folders = $organized['children'];
    
    echo '<div class="media-folder-filter">';
    echo '<h3>Apex Folders</h3>';
    echo '<ul class="media-folder-list">';
    
    // Add "All Files" option
    $class = !isset($_GET['media_folder']) ? 'current' : '';
    echo '<li class="' . $class . ' all-files"><a href="' . admin_url('upload.php') . '">All Files</a></li>';
    
    // Add Unassigned folder immediately after All Files
    if ($unassigned_folder) {
        $class = isset($_GET['media_folder']) && $_GET['media_folder'] === $unassigned_folder->slug ? 'current' : '';
        echo '<li class="' . $class . ' unassigned-folder" data-folder-id="' . $unassigned_folder->term_id . '">';
        echo '<a href="' . admin_url('upload.php?media_folder=' . $unassigned_folder->slug) . '">' . $unassigned_folder->name . ' (' . $unassigned_folder->count . ')</a>';
        echo '</li>';
    }
    
    // Add separator
    echo '<li class="folder-separator"></li>';
    
    // Add parent folders and their children
    foreach ($parent_folders as $folder) {
        $class = isset($_GET['media_folder']) && $_GET['media_folder'] === $folder->slug ? 'current' : '';
        $has_children = isset($child_folders[$folder->term_id]) && !empty($child_folders[$folder->term_id]);
        
        echo '<li class="' . $class . ' custom-folder parent-folder' . ($has_children ? ' has-children' : '') . '" data-folder-id="' . $folder->term_id . '">';
        echo '<a href="' . admin_url('upload.php?media_folder=' . $folder->slug) . '">' . $folder->name . ' (' . $folder->count . ')</a>';
        echo '<span class="edit-folder dashicons dashicons-edit" data-folder-id="' . $folder->term_id . '" data-folder-name="' . esc_attr($folder->name) . '" title="Edit folder"></span>';
        echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . $folder->term_id . '" data-folder-name="' . esc_attr($folder->name) . '"></span>';
        
        // Add "Create Subfolder" button for parent folders
        echo '<span class="add-subfolder dashicons dashicons-plus-alt2" data-parent-id="' . $folder->term_id . '" data-parent-name="' . esc_attr($folder->name) . '" title="Add subfolder"></span>';
        
        echo '</li>';
        
        // Display children if any
        if ($has_children) {
            foreach ($child_folders[$folder->term_id] as $child) {
                $child_class = isset($_GET['media_folder']) && $_GET['media_folder'] === $child->slug ? 'current' : '';
                
                echo '<li class="' . $child_class . ' custom-folder child-folder" data-folder-id="' . $child->term_id . '" data-parent-id="' . $folder->term_id . '">';
                echo '<span class="child-indicator">└─</span>';
                echo '<a href="' . admin_url('upload.php?media_folder=' . $child->slug) . '">' . $child->name . ' (' . $child->count . ')</a>';
                echo '<span class="edit-folder dashicons dashicons-edit" data-folder-id="' . $child->term_id . '" data-folder-name="' . esc_attr($child->name) . '" title="Edit folder"></span>';
                echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . $child->term_id . '" data-folder-name="' . esc_attr($child->name) . '"></span>';
                echo '</li>';
            }
        }
    }
    
    echo '</ul>';
    echo '<a href="#" class="button button-primary add-new-folder">Add New Folder</a>';
    echo '</div>';
    
    // Enqueue scripts and styles
    wp_enqueue_style('apex-folders-css', MEDIA_FOLDERS_PLUGIN_URL . 'assets/css/apex-folders.css', array(), MEDIA_FOLDERS_VERSION);
    
    // Add jQuery UI for dialogs
    wp_enqueue_style('wp-jquery-ui-dialog');
    wp_enqueue_script('jquery-ui-dialog');
    
    // Pass data to our scripts
    $folders_data = array(
        'currentFolder' => isset($_GET['media_folder']) ? sanitize_text_field($_GET['media_folder']) : null,
        'nonce' => wp_create_nonce('media_folders_nonce'),
        'slugNonce' => wp_create_nonce('media_folders_get_slug'),
        'parentFolders' => array_map(function($folder) {
            return array(
                'term_id' => $folder->term_id,
                'name' => $folder->name
            );
        }, $parent_folders)
    );
    
    wp_enqueue_script('apex-folder-management', MEDIA_FOLDERS_PLUGIN_URL . 'assets/js/folder-management.js', array('jquery', 'jquery-ui-dialog'), MEDIA_FOLDERS_VERSION, true);
    wp_enqueue_script('apex-attachment-tracking', MEDIA_FOLDERS_PLUGIN_URL . 'assets/js/attachment-tracking.js', array('jquery'), MEDIA_FOLDERS_VERSION, true);
    wp_enqueue_script('apex-folder-counts', MEDIA_FOLDERS_PLUGIN_URL . 'assets/js/folder-counts.js', array('jquery'), MEDIA_FOLDERS_VERSION, true);
    
    wp_localize_script('apex-folder-management', 'apexFolderData', $folders_data);
    wp_localize_script('apex-attachment-tracking', 'apexFolderData', $folders_data);
    wp_localize_script('apex-folder-counts', 'apexFolderData', $folders_data);
}

add_action('admin_notices', 'media_folders_filter');






function media_folders_set_object_terms($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
    if ($taxonomy === 'media_folder') {
        // Schedule a deferred count update to ensure it happens after WordPress completes its operations
        wp_schedule_single_event(time() + 2, 'theme_update_media_folder_counts_event');
    }
}
add_action('set_object_terms', 'media_folders_set_object_terms', 10, 6);

// Register the event
function theme_register_folder_count_event() {
    add_action('theme_update_media_folder_counts_event', 'theme_update_media_folder_counts');
}
add_action('init', 'theme_register_folder_count_event');










function media_folders_attachment_fields($form_fields, $post) {
    $folders = get_terms(array(
        'taxonomy' => 'media_folder',
        'hide_empty' => false,
    ));
    
    // Get the current folder term
    $current_folders = wp_get_object_terms($post->ID, 'media_folder');
    $current_folder_id = (!empty($current_folders) && !is_wp_error($current_folders)) ? $current_folders[0]->term_id : media_folders_get_unassigned_id();
    
    // Organize folders by hierarchy
    $unassigned_folder = null;
    $parent_folders = array();
    $child_folders = array();
    
    foreach ($folders as $folder) {
        if ($folder->term_id == media_folders_get_unassigned_id()) {
            $unassigned_folder = $folder;
        } else if ($folder->parent == 0) {
            $parent_folders[] = $folder;
        } else {
            $child_folders[$folder->parent][] = $folder;
        }
    }
    
    $dropdown = '<select name="attachments[' . $post->ID . '][media_folder]" id="attachments-' . $post->ID . '-media_folder">';
    
    // Add unassigned folder first
    if ($unassigned_folder) {
        $selected = selected($current_folder_id, $unassigned_folder->term_id, false);
        $dropdown .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr($unassigned_folder->term_id),
            $selected,
            esc_html($unassigned_folder->name)
        );
    }
    
    // Add parent folders and their children
    foreach ($parent_folders as $folder) {
        $selected = selected($current_folder_id, $folder->term_id, false);
        
        // Calculate total count for display purposes
        $total_count = $folder->count;
        $has_children = isset($child_folders[$folder->term_id]) && !empty($child_folders[$folder->term_id]);
        
        if ($has_children) {
            foreach ($child_folders[$folder->term_id] as $child) {
                $total_count += $child->count;
            }
            $dropdown .= sprintf(
                '<option value="%s"%s>%s (%d / %d total)</option>',
                esc_attr($folder->term_id),
                $selected,
                esc_html($folder->name),
                $folder->count,
                $total_count
            );
        } else {
            $dropdown .= sprintf(
                '<option value="%s"%s>%s (%d)</option>',
                esc_attr($folder->term_id),
                $selected,
                esc_html($folder->name),
                $folder->count
            );
        }
        
        // Add children
        if ($has_children) {
            foreach ($child_folders[$folder->term_id] as $child) {
                $selected = selected($current_folder_id, $child->term_id, false);
                $dropdown .= sprintf(
                    '<option value="%s"%s>&nbsp;&nbsp;└─ %s (%d)</option>',
                    esc_attr($child->term_id),
                    $selected,
                    esc_html($child->name),
                    $child->count
                );
            }
        }
    }
    
    $dropdown .= '</select>';
    
    $form_fields['media_folder'] = array(
        'label' => 'Folder',
        'input' => 'html',
        'html' => $dropdown,
        'helps' => 'Select a folder for this media item'
    );
    
    return $form_fields;
}


add_filter('attachment_fields_to_edit', 'media_folders_attachment_fields', 10, 2);


// Add a global variable to track processing
global $is_processing_media_folder;
$is_processing_media_folder = false;


function media_folders_attachment_save($post, $attachment) {
    global $is_processing_media_folder;
    
    // If already processing or no folder specified, return
    if ($is_processing_media_folder || !isset($attachment['media_folder'])) {
        return $post;
    }
    
    $is_processing_media_folder = true;
    
    try {
        $folder_id = intval($attachment['media_folder']);
        $post_id = $post['ID'];
        
        // Remove existing terms
        wp_delete_object_term_relationships($post_id, 'media_folder');
        
        if ($folder_id > 0) {
            // Set the term to the selected folder
            wp_set_object_terms($post_id, array($folder_id), 'media_folder', false);
        } else {
            // If no folder or "0" selected, assign to Unassigned folder
            $unassigned_id = media_folders_get_unassigned_id();
            wp_set_object_terms($post_id, array($unassigned_id), 'media_folder', false);
        }

        // Add this JavaScript to force refresh
        add_action('admin_footer', function() use ($post_id, $folder_id) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Force refresh of the media library view
                if (wp.media.frame) {
                    wp.media.frame.library.props.set({ignore: (+ new Date())});
                    wp.media.frame.library.props.trigger('change');
                }
                
                // If we're in grid mode, refresh that too
                if (wp.media.view.Attachment.Library) {
                    $('.attachments-browser .attachments').trigger('scroll');
                }
                
                // Update folder counts
                if (typeof window.updateFolderCounts === 'function') {
                    window.updateFolderCounts();
                }
            });
            </script>
            <?php
        });

    } finally {
        $is_processing_media_folder = false;
    }
    
    return $post;
}



/**
 * Prevent creation of terms with numeric names that should be IDs
 */
// Update the prevent_numeric_term_creation function
function prevent_numeric_term_creation($term, $taxonomy) {
    return Media_Folders_Utilities::prevent_numeric_term_creation($term, $taxonomy);
}

add_filter('pre_insert_term', 'prevent_numeric_term_creation', 10, 2);


function debug_media_folder_assignment($post_id, $terms, $tt_ids, $taxonomy) {
    Media_Folders_Utilities::debug_folder_assignment($post_id, $terms, $tt_ids, $taxonomy);
}


remove_all_filters('attachment_fields_to_save');
add_filter('attachment_fields_to_save', 'media_folders_attachment_save', 999, 2);
add_action('set_object_terms', 'debug_media_folder_assignment', 999, 4);



// Filter media library by folder

function theme_filter_media_by_folder($query) {
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
add_action('pre_get_posts', 'theme_filter_media_by_folder');


// ************************************************************ Add this function to help debug issues
function debug_media_folder_content() {
    $screen = get_current_screen();
    if ($screen->base !== 'upload') return;
    
    if (isset($_GET['debug_folders']) && current_user_can('manage_options')) {
        global $wpdb;
        
        $output = '<div class="notice notice-info"><p><strong>Media Folders Debug:</strong></p><ul>';
        
        // Check all folder terms
        $folders = get_terms(array(
            'taxonomy' => 'media_folder',
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
            
            // Get a sample of attachments
            $attachments = $wpdb->get_col($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.term_id = %d AND p.post_type = 'attachment'
                LIMIT 5",
                $folder->term_id
            ));
            
            $output .= sprintf(
                '<li><strong>%s</strong> (ID: %d, Slug: %s): %d items. Sample IDs: %s</li>',
                esc_html($folder->name),
                $folder->term_id,
                $folder->slug,
                $count,
                implode(', ', $attachments)
            );
        }
        
        $output .= '</ul></div>';
        
        echo $output;
    }
}
add_action('admin_notices', 'debug_media_folder_content');




// Add at the end of the file
function debug_unassigned_folder() {
    if (!isset($_GET['debug_unassigned']) || !current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $unassigned_id = media_folders_get_unassigned_id();
    
    echo '<div class="notice notice-info">';
    echo '<h3>Unassigned Folder Debug</h3>';
    
    // 1. Check term exists
    $term = get_term($unassigned_id, 'media_folder');
    echo '<p>Term check: ' . ($term ? 'Found' : 'Not found') . '</p>';
    if ($term) {
        echo '<p>Term details: ID=' . $term->term_id . ', Name=' . $term->name . ', Slug=' . $term->slug . ', Count=' . $term->count . '</p>';
    }
    
    // 2. Check term_taxonomy record
    $tt_id = $wpdb->get_var($wpdb->prepare(
        "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
         WHERE term_id = %d AND taxonomy = 'media_folder'",
        $unassigned_id
    ));
    echo '<p>Term taxonomy ID: ' . ($tt_id ?: 'Not found') . '</p>';
    
    // 3. Count direct from database
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->term_relationships tr
         JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
         WHERE tt.term_id = %d AND tt.taxonomy = 'media_folder'",
        $unassigned_id
    ));
    echo '<p>Actual count in database: ' . $count . '</p>';
    
    // 4. List some items in the unassigned folder
    $items = $wpdb->get_col($wpdb->prepare(
        "SELECT tr.object_id FROM $wpdb->term_relationships tr
         JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
         WHERE tt.term_id = %d AND tt.taxonomy = 'media_folder'
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
             WHERE tt.taxonomy = 'media_folder' AND tr.object_id = p.ID
         )"
    );
    echo '<p>Media items with no folder at all: ' . $no_folder . '</p>';
    
    echo '</div>';
}
add_action('admin_notices', 'debug_unassigned_folder');


// ************************************************************ Add this function to help debug issues


// Add this function to force-rebuild the Unassigned folder
function media_folders_force_rebuild_unassigned() {
    if (isset($_GET['rebuild_unassigned']) && current_user_can('manage_options')) {
        check_admin_referer('rebuild_unassigned');
        
        // Use the direct assignment function
        $count = media_folders_ensure_all_assigned();
        
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
                <p><a href="<?php echo admin_url('upload.php?media_folder=unassigned'); ?>">View Unassigned folder</a> | <a href="<?php echo admin_url('upload.php?debug_folders=1'); ?>">View folder debug info</a></p>
            </div>
            <?php
        });
    }
}




/**
 * Directly assign all unassigned media to the Unassigned folder
 */

function media_folders_ensure_all_assigned() {
    return Media_Folders_Unassigned::ensure_all_assigned();
}


// Enable sorting in add media modal

// Add media folder filtering capabilities to block editor
function media_folders_block_editor_assets() {
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
      'count' => $folder->count
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
    false // <-- This is important, load in header, not footer
);
  // Add server-side support for the filter
  add_filter('ajax_query_attachments_args', function($query) {
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
      
      // Confirm folder exists and has attachments
      $term = get_term($folder_id, 'media_folder');
      if (!is_wp_error($term) && $term) {
        error_log('Found folder: ' . $term->name . ' (ID: ' . $term->term_id . ')');
        
        // Check if there are any attachments in this folder
        $attachments = get_posts(array(
          'post_type' => 'attachment',
          'posts_per_page' => 1,
          'tax_query' => array(
            array(
              'taxonomy' => 'media_folder',
              'field' => 'term_id',
              'terms' => $folder_id
            )
          )
        ));
        
        error_log('Sample check - attachments in folder: ' . count($attachments));
        
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
  });
}

// Hook into both admin_init (early) and admin_enqueue_scripts


add_action('admin_init', 'media_folders_block_editor_assets', 5);
add_action('admin_enqueue_scripts', 'media_folders_block_editor_assets');





// Add folder selection to media uploader
function media_folders_uploader() {
    // Only run in admin
    if (!is_admin()) {
        return;
    }
    
    // Get folders for the dropdown
    $folders = get_terms(array(
        'taxonomy' => 'media_folder',
        'hide_empty' => false,
    ));
    
    // Only proceed if we have folders
    if (empty($folders)) {
        return;
    }

    $unassigned_id = media_folders_get_unassigned_id();
    
    // Build the dropdown HTML
    $dropdown_html = '<div class="media-folder-select-container upload-filter-section">';
    $dropdown_html .= '<label for="media-folder-select">Folder:</label>';
    $dropdown_html .= '<select id="media-folder-select" name="media-folder-select">';
    $dropdown_html .= '<option value="' . esc_attr($unassigned_id) . '">Unassigned</option>';
    
    foreach ($folders as $folder) {
        // Skip the Unassigned folder since we already added it as the first option
        if ($folder->term_id != $unassigned_id) {
            $dropdown_html .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($folder->term_id),
                esc_html($folder->name)
            );
        }
    }
    
    $dropdown_html .= '</select>';
    $dropdown_html .= '</div>';
    

    // Enqueue the script
    wp_enqueue_script(
        'media-folders-uploader',
        MEDIA_FOLDERS_PLUGIN_URL . 'assets/js/media-uploader.js',
        array('jquery'),
        MEDIA_FOLDERS_VERSION,
        true
    );
    
    // Pass data to the script
    wp_localize_script(
        'media-folders-uploader',
        'MediaFolderUploaderData',
        array(
            'currentFolder' => isset($_GET['media_folder']) ? sanitize_text_field($_GET['media_folder']) : null,
            'dropdownHtml' => $dropdown_html,
            'folderNonce' => wp_create_nonce('media_folders_nonce'),
            'unassignedId' => $unassigned_id  // Add this line to pass the unassigned folder ID
        )
    );
}

// Add to admin_enqueue_scripts hook
add_action('admin_enqueue_scripts', 'media_folders_uploader');



function media_folders_handle_upload($file) {
    // For debugging
    error_log('Upload handler called for file: ' . $file['name']);
    
    // Check all possible sources for the folder ID
    $folder_id = 0;
    $source = 'unknown';
    
    // Check various parameter names
    if (isset($_POST['media_folder_id'])) {
        $folder_id = intval($_POST['media_folder_id']);
        $source = 'POST media_folder_id';
    } 
    elseif (isset($_POST['media-folder-select'])) {
        $folder_id = intval($_POST['media-folder-select']);
        $source = 'POST media-folder-select';
    }
    
    // If not in POST, try REQUEST (which combines GET, POST, COOKIE)
    if (!$folder_id) {
        if (isset($_REQUEST['media_folder_id'])) {
            $folder_id = intval($_REQUEST['media_folder_id']);
            $source = 'REQUEST media_folder_id';
        }
        elseif (isset($_REQUEST['media-folder-select'])) {
            $folder_id = intval($_REQUEST['media-folder-select']);
            $source = 'REQUEST media-folder-select';
        }
    }
    
    // Cookie fallback
    if (!$folder_id && isset($_COOKIE['media_folder_upload_id'])) {
        $folder_id = intval($_COOKIE['media_folder_upload_id']);
        $source = 'COOKIE';
    }
    
    // If we still don't have a folder ID, use unassigned
    if (!$folder_id) {
        $folder_id = media_folders_get_unassigned_id();
        $source = 'DEFAULT (unassigned)';
    }
    
    error_log("Found folder ID {$folder_id} from {$source} for file: {$file['name']}");
    
    // Store this in a transient for later retrieval
    set_transient('media_folder_for_' . sanitize_file_name($file['name']), $folder_id, 5 * MINUTE_IN_SECONDS);
    
    // Also store in a global request variable to ensure it's available elsewhere
    $_REQUEST['media_folder_id'] = $folder_id;
    
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'media_folders_handle_upload');



// Assign folder to newly uploaded attachment
function media_folders_attachment_uploaded($attachment_id) {
    // Get the attachment
    $attachment = get_post($attachment_id);
    if (!$attachment) return;
    
    // Get the original filename
    $filename = basename(get_attached_file($attachment_id));
    
    error_log("Processing new attachment ID $attachment_id (file: $filename)");
    
    // Try multiple approaches to get the folder ID, in order of preference:
    $folder_id = 0;
    $source = 'unknown';
    
    // 1. Check POST data directly with additional fallbacks
    if (isset($_POST['media_folder_id'])) {
        $folder_id = intval($_POST['media_folder_id']);
        $source = 'POST media_folder_id';
    } 
    elseif (isset($_POST['media-folder-select'])) {
        $folder_id = intval($_POST['media-folder-select']);
        $source = 'POST select dropdown';
    }
    
    // 2. If that failed, try the transient
    if (!$folder_id) {
        $folder_id = get_transient('media_folder_for_' . sanitize_file_name($filename));
        if ($folder_id) $source = 'transient';
    }
    
    // 3. Try the cookie as a backup
    if (!$folder_id && isset($_COOKIE['media_folder_upload_id'])) {
        $folder_id = intval($_COOKIE['media_folder_upload_id']);
        $source = 'cookie';
    }
    
    // 4. If we still have no folder, use the URL query parameter if present
    if (!$folder_id && isset($_GET['media_folder'])) {
        // Convert slug to ID
        $term = get_term_by('slug', sanitize_text_field($_GET['media_folder']), 'media_folder');
        if ($term && !is_wp_error($term)) {
            $folder_id = $term->term_id;
            $source = 'URL param';
        }
    }
    
    // If still no folder, use unassigned
    if (!$folder_id) {
        $folder_id = media_folders_get_unassigned_id();
        $source = 'default (unassigned)';
    }
    
    error_log("Folder ID $folder_id found from $source for attachment ID $attachment_id");
    
    // If we have a valid folder ID and it exists, assign the attachment to it
    if ($folder_id > 0) {
        // Verify the folder exists
        $term = get_term($folder_id, 'media_folder');
        if (!is_wp_error($term) && $term) {
            // Assign to folder - use both methods for redundancy
            wp_set_object_terms($attachment_id, array($folder_id), 'media_folder', false);
            error_log("Assigned attachment ID $attachment_id to folder ID $folder_id ({$term->name})");
            
            // Also use direct database access as a backup approach
            global $wpdb;
            $tt_id = $wpdb->get_var($wpdb->prepare(
                "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
                 WHERE term_id = %d AND taxonomy = 'media_folder'",
                $folder_id
            ));
            
            if ($tt_id) {
                // Ensure there's no existing relationship
                $wpdb->delete($wpdb->term_relationships, array(
                    'object_id' => $attachment_id,
                    'term_taxonomy_id' => $tt_id
                ));
                
                // Insert direct relationship
                $wpdb->insert($wpdb->term_relationships, array(
                    'object_id' => $attachment_id,
                    'term_taxonomy_id' => $tt_id,
                    'term_order' => 0
                ));
                
                error_log("Added direct database relationship between attachment $attachment_id and folder $folder_id");
            }
            
            // Update term counts
            theme_update_media_folder_counts();
        } else {
            // Folder doesn't exist, use Unassigned
            $unassigned_id = media_folders_get_unassigned_id();
            wp_set_object_terms($attachment_id, array($unassigned_id), 'media_folder', false);
            error_log("Folder ID $folder_id doesn't exist, using Unassigned ($unassigned_id)");
        }
    }
    
    // Clean up the transient
    delete_transient('media_folder_for_' . sanitize_file_name($filename));
}
add_action('add_attachment', 'media_folders_attachment_uploaded');



// Also add back-end selection directly to media uploader params
function media_folders_plupload_init($plupload_init) {
    // Get the folder ID from multiple possible sources
    $folder_id = 0;
    
    // First try the direct POST/GET variables
    if (isset($_REQUEST['media_folder_id'])) {
        $folder_id = intval($_REQUEST['media_folder_id']);
    }
    // Second, try from the dropdown in the uploader
    elseif (isset($_REQUEST['media-folder-select'])) {
        $folder_id = intval($_REQUEST['media-folder-select']);
    }
    // Finally check cookie
    elseif (isset($_COOKIE['media_folder_upload_id'])) {
        $folder_id = intval($_COOKIE['media_folder_upload_id']);
    }
    
    // If still no folder ID, use the unassigned folder
    if (!$folder_id) {
        $folder_id = media_folders_get_unassigned_id();
    }
    
    // Add our custom folder param
    $plupload_init['multipart_params']['media_folder_id'] = $folder_id;
    
    // Log what we're doing for debugging
    error_log('Setting plupload media_folder_id to: ' . $folder_id . ' (Source: ' . 
              (isset($_REQUEST['media_folder_id']) ? 'REQUEST' : 
               (isset($_REQUEST['media-folder-select']) ? 'SELECT' : 
                (isset($_COOKIE['media_folder_upload_id']) ? 'COOKIE' : 'DEFAULT'))) . ')');
    
    return $plupload_init;
}
add_filter('plupload_init', 'media_folders_plupload_init', 10);



/**
 * Migrate existing media items with no folder to the Unassigned folder
 */
function media_folders_migrate_unassigned() {
    // Use the direct method to ensure all unassigned media is properly assigned
    return media_folders_ensure_all_assigned();
}


/**
 * Ensure all attachments have a folder assignment
 */

function media_folders_ensure_folder_assignment($post_id) {
    Media_Folders_Unassigned::ensure_attachment_has_folder($post_id);
}

add_action('save_post', 'media_folders_ensure_folder_assignment');
add_action('edit_attachment', 'media_folders_ensure_folder_assignment');



/**
 * Force update term counts for media folders
 */
function theme_update_media_folder_counts() {
    Media_Folders_Utilities::update_folder_counts();
}


// Add to the init hook
function media_folders_init() {
    // Migration is already handled during activation and by the
    // media_folders_ensure_folder_assignment hook, so we don't need 
    // to show the migration notice anymore
    
    // Set the option to false to prevent the notice from showing
    update_option('media_folders_needs_migration', false);
}
add_action('init', 'media_folders_init');





// Add admin links to help with debugging
function media_folders_admin_links() {
    $screen = get_current_screen();
    if ($screen->base !== 'upload') return;
    
    if (current_user_can('manage_options')) {
        $unassigned_id = media_folders_get_unassigned_id();
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

// Add a function to flush term caches
function media_folders_flush_term_cache() {
    if (isset($_GET['flush_term_cache']) && current_user_can('manage_options')) {
        check_admin_referer('flush_term_cache');
        
        global $wpdb;
        
        // Get all media folder terms
        $terms = $wpdb->get_col("
            SELECT term_id FROM $wpdb->term_taxonomy
            WHERE taxonomy = 'media_folder'
        ");
        
        // Clear caches
        clean_term_cache($terms, 'media_folder');
        
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
add_action('admin_init', 'media_folders_flush_term_cache');




