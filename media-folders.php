<?php
/**
 * Plugin Name: Media Folders
 * Plugin URI: 
 * Description: Organize media library files into folders for better management
 * Version: 0.9.0
 * Author: 
 * Text Domain: media-folders
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


// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'media_folders_activate');
register_deactivation_hook(__FILE__, 'media_folders_deactivate');



/**
 * Plugin activation
 */
function media_folders_activate() {
    // Register taxonomy on activation
    media_folders_register_taxonomy();
    
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


// Register 'media_folder' taxonomy
function media_folders_register_taxonomy() {
    register_taxonomy(
        'media_folder',
        'attachment',
        array(
            'labels' => array(
                'name' => 'Media Folders',
                'singular_name' => 'Media Folder',
                'menu_name' => 'Folders',
                'all_items' => 'All Folders',
                'edit_item' => 'Edit Folder',
                'view_item' => 'View Folder',
                'update_item' => 'Update Folder',
                'add_new_item' => 'Add New Folder',
                'new_item_name' => 'New Folder Name',
                'parent_item' => 'Parent Folder',
                'parent_item_colon' => 'Parent Folder:',
                'search_items' => 'Search Folders',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'media-folder'),
        )
    );
}
add_action('init', 'media_folders_register_taxonomy');

// Add media folders to media library screen
function media_folders_filter() {
    $screen = get_current_screen();
    if ($screen->base !== 'upload') return;
    
    $folders = get_terms(array(
        'taxonomy' => 'media_folder',
        'hide_empty' => false,
    ));
    
    echo '<div class="media-folder-filter">';
    echo '<h3>Media Folders</h3>';
    echo '<ul class="media-folder-list">';
    
    // Add "All Files" option
    $class = !isset($_GET['media_folder']) ? 'current' : '';
    echo '<li class="' . $class . '"><a href="' . admin_url('upload.php') . '">All Files</a></li>';
    
    // Add each folder
    foreach ($folders as $folder) {
        $class = isset($_GET['media_folder']) && $_GET['media_folder'] === $folder->slug ? 'current' : '';
        echo '<li class="' . $class . '" data-folder-id="' . $folder->term_id . '">';
        echo '<a href="' . admin_url('upload.php?media_folder=' . $folder->slug) . '">' . $folder->name . ' (' . $folder->count . ')</a>';
        echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . $folder->term_id . '" data-folder-name="' . esc_attr($folder->name) . '"></span>';
        echo '</li>';
    }
    echo '</ul>';
    echo '<a href="#" class="button button-primary add-new-folder">Add New Folder</a>';
    echo '</div>';
    
    // Add some basic CSS
    echo '<style>
    .media-folder-list li.count-updated {
    background-color: #ffff99;
    transition: background-color 2s;
}
        /* Folder Filter Container */
        .media-folder-filter {
            float: left;
            width: 20%;
            margin: 65px 20px 0 0;
            padding: 20px;
            background: #ffffff;
            border-radius: 0;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }
        
        .media-folder-filter:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        /* Add New Folder Button */
        .add-new-folder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px 16px;
            margin-top: 15px !important;
            background: linear-gradient(45deg, #2271b1, #135e96);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            cursor: pointer;
        }
        
        .add-new-folder:before {
            content: "+";
            margin-right: 8px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .add-new-folder:hover {
            transform: translateY(-2px);
            background: linear-gradient(45deg, #135e96, #2271b1);
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.2);
        }
        
        .add-new-folder:active {
            transform: translateY(0);
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .media-folder-filter {
                width: 25%;
            }
        }
        
        @media (max-width: 768px) {
            .media-folder-filter {
                width: 100%;
                margin: 20px 0;
                float: none;
            }
        }
        
        /* Folder List Container */
        .media-folder-list {
            margin: 0;
            padding: 15px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        /* Folder List Items */
        .media-folder-list li {
            margin: 8px 0;
            padding: 10px 15px;
            position: relative;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            border-left: 3px solid transparent;
        }
        
        .media-folder-list li:hover {
            background: #f0f2f5;
            border-left: 3px solid #2271b1;
            transform: translateX(5px);
        }
        
        /* Current Folder Styling */
        .media-folder-list li.current a {
            font-weight: 600;
            color: #2271b1;
        }
        
        /* Folder Links */
        .media-folder-list li a {
            color: #1d2327;
            text-decoration: none;
            font-size: 14px;
            display: block;
            padding-right: 30px;
        }
        
        /* Add New Folder Button */
        .add-new-folder {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 16px;
            background: #2271b1;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .add-new-folder:hover {
            background: #135e96;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.2);
        }
        
        /* Page Title Action Button */
        .wrap .page-title-action {
            margin-left: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #2271b1;
            color: #2271b1;
            transition: all 0.3s ease;
        }
        
        .wrap .page-title-action:hover {
            background: #2271b1;
            color: white;
        }
        
        /* Table and Navigation Elements */
        .wp-list-table, 
        .tablenav, 
        .search-form, 
        .subsubsub {
            width: 78%;
            float: right;
            background: #ffffff;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        /* Delete Folder Button */
        .delete-folder {
            cursor: pointer;
            color: #dc3545;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: all 0.3s ease;
            padding: 5px;
            border-radius: 4px;
        }
        
        .media-folder-list li:hover .delete-folder {
            opacity: 1;
        }
        
        .delete-folder:hover {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .wp-list-table, 
            .tablenav, 
            .search-form, 
            .subsubsub {
                width: 100%;
                float: none;
            }
        }
        
    </style>';
    
    // Add JavaScript for folder management
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Add new folder
        $('.add-new-folder').on('click', function(e) {
            e.preventDefault();
            var folderName = prompt('Enter folder name:');
            if (folderName) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'theme_add_media_folder',
                        folder_name: folderName,
                        nonce: '<?php echo wp_create_nonce('media_folders_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error creating folder');
                        }
                    }
                });
            }
        });
        $('.delete-folder').on('click', function(e) {
            e.preventDefault();
            var folderId = $(this).data('folder-id');
            var folderName = $(this).data('folder-name');
            
            if (confirm('Are you sure you want to delete the folder "' + folderName + '"? Files in this folder will not be deleted.')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'theme_delete_media_folder',
                        folder_id: folderId,
                        nonce: '<?php echo wp_create_nonce('media_folders_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error deleting folder');
                        }
                    }
                });
            }
        });
    });
    </script>
    <?php
}
add_action('admin_notices', 'media_folders_filter');



// Add AJAX endpoint for getting updated folder counts

function theme_ajax_get_folder_counts() {
    // Verify permissions
    if (!current_user_can('upload_files')) {
        wp_send_json_error('Permission denied');
    }
    
    // Force recount all terms
    theme_update_media_folder_counts();
    
    // Get updated folder data
    $folders = get_terms(array(
        'taxonomy' => 'media_folder',
        'hide_empty' => false,
    ));
    
    $folder_data = array();
    foreach ($folders as $folder) {
        $folder_data[$folder->term_id] = array(
            'count' => $folder->count,
            'name' => $folder->name,
            'slug' => $folder->slug
        );
    }
    
    wp_send_json_success($folder_data);
}
add_action('wp_ajax_theme_get_folder_counts', 'theme_ajax_get_folder_counts');


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




// AJAX handler for adding new folders
function theme_ajax_add_media_folder() {
    check_ajax_referer('media_folders_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error();
    }
    
    $folder_name = sanitize_text_field($_POST['folder_name']);
    
    $result = wp_insert_term($folder_name, 'media_folder');
    
    if (is_wp_error($result)) {
        wp_send_json_error();
    } else {
        wp_send_json_success();
    }
}
add_action('wp_ajax_theme_add_media_folder', 'theme_ajax_add_media_folder');

function media_folders_attachment_fields($form_fields, $post) {
    $folders = get_terms(array(
        'taxonomy' => 'media_folder',
        'hide_empty' => false,
    ));
    
    // Get the current folder term
    $current_folders = wp_get_object_terms($post->ID, 'media_folder');
    $current_folder_id = (!empty($current_folders) && !is_wp_error($current_folders)) ? $current_folders[0]->term_id : 0;
    
    $dropdown = '<select name="attachments[' . $post->ID . '][media_folder]" id="attachments-' . $post->ID . '-media_folder">';
    $dropdown .= '<option value="0"' . selected($current_folder_id, 0, false) . '>No Folder</option>';
    
    foreach ($folders as $folder) {
        $selected = selected($current_folder_id, $folder->term_id, false);
        $dropdown .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr($folder->term_id),
            $selected,
            esc_html($folder->name)
        );
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
        
        // Remove existing terms regardless of new folder value
        wp_delete_object_term_relationships($post_id, 'media_folder');
        
        // Only set new terms if folder_id is not 0
        if ($folder_id !== 0) {
            // Set the term
            wp_set_object_terms($post_id, array($folder_id), 'media_folder', false);
        }
        // If folder_id is 0, we already removed all terms above,
        // so no need to do anything else
    } finally {
        $is_processing_media_folder = false;
    }
    
    return $post;
}


/**
 * Prevent creation of terms with numeric names that should be IDs
 */
function prevent_numeric_term_creation($term, $taxonomy) {
    // Only check media_folder taxonomy
    if ($taxonomy !== 'media_folder') {
        return $term;
    }
    
    // If term is numeric, it's likely an ID being misinterpreted
    // Changed condition to catch "0" as well
    if (is_numeric($term)) {
        error_log("Preventing creation of numeric term: " . $term);
        
        // Return an error to prevent term creation
        return new WP_Error('invalid_term', "Can't create term with numeric name");
    }
    
    return $term;
}
add_filter('pre_insert_term', 'prevent_numeric_term_creation', 10, 2);


function debug_media_folder_assignment($post_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy !== 'media_folder') {
        return;
    }
    
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
    $caller_info = array();
    $ignore_functions = array('debug_media_folder_assignment', 'apply_filters', 'do_action');
    
    foreach ($backtrace as $trace) {
        if (isset($trace['function']) && !in_array($trace['function'], $ignore_functions)) {
            $caller_info[] = $trace['function'];
        }
    }
    
    if (!empty($caller_info)) {
        error_log(sprintf(
            "[Media Folder Debug] Post: %d, Original Caller: %s, Terms: %s",
            $post_id,
            implode(' -> ', array_reverse($caller_info)),
            json_encode($terms)
        ));
    }
}


remove_all_filters('attachment_fields_to_save');
add_filter('attachment_fields_to_save', 'media_folders_attachment_save', 999, 2);
add_action('set_object_terms', 'debug_media_folder_assignment', 999, 4);




// Filter media library by folder
function theme_filter_media_by_folder($query) {
    if (is_admin() && $query->is_main_query() && isset($query->query['post_type']) && $query->query['post_type'] === 'attachment') {
        if (isset($_GET['media_folder']) && !empty($_GET['media_folder'])) {
            $query->set('tax_query', array(
                array(
                    'taxonomy' => 'media_folder',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['media_folder']),
                ),
            ));
        }
    }
}
add_action('pre_get_posts', 'theme_filter_media_by_folder');

// Add AJAX handler for deleting folders
function theme_ajax_delete_media_folder() {
    check_ajax_referer('media_folders_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error();
    }
    
    $folder_id = intval($_POST['folder_id']);
    
    // Delete the term
    $result = wp_delete_term($folder_id, 'media_folder');
    
    if (is_wp_error($result)) {
        wp_send_json_error();
    } else {
        wp_send_json_success();
    }
}
add_action('wp_ajax_theme_delete_media_folder', 'theme_ajax_delete_media_folder');





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
    MEDIA_FOLDERS_PLUGIN_URL . 'assets/js/media-folders.js', 
    array('jquery', 'wp-blocks', 'media-editor', 'media-folder-data'), 
    filemtime(MEDIA_FOLDERS_PLUGIN_DIR . 'assets/js/media-folders.js'),
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
    
    // Build the dropdown HTML
    $dropdown_html = '<div class="media-folder-select-container upload-filter-section">';
    $dropdown_html .= '<label for="media-folder-select">Folder:</label>';
    $dropdown_html .= '<select id="media-folder-select" name="media-folder-select">';
    $dropdown_html .= '<option value="0">No Folder</option>';
    
    foreach ($folders as $folder) {
        $dropdown_html .= sprintf(
            '<option value="%s">%s</option>',
            esc_attr($folder->term_id),
            esc_html($folder->name)
        );
    }
    
    $dropdown_html .= '</select>';
    $dropdown_html .= '</div>';
    
    // Add CSS to style the folder dropdown
    $css = '
        <style>
            .media-folder-select-container {
                margin: 10px 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .media-folder-select-container label {
                margin-right: 8px;
                font-weight: bold;
            }
            .media-folder-select-container select {
                flex-grow: 1;
                max-width: 300px;
            }
            /* Fixes for WordPress media modal */
            .media-modal .uploader-inline .media-folder-select-container {
                padding: 0 16px;
            }
        </style>
    ';
    
    // Add JavaScript to handle the folder selection
    $js = '
        <script>
        jQuery(document).ready(function($) {
            console.log("Media folder uploader script loaded");
            
            // Function to add folder dropdown to uploader
            function addFolderDropdownToUploader() {
                console.log("Attempting to add folder dropdown");
                
                // Simplify targeting - look for common upload interface elements
                var $uploadUI = $(".upload-ui");
                if ($uploadUI.length && !$uploadUI.next(".media-folder-select-container").length) {
                    console.log("Found upload UI, adding dropdown");
                    $uploadUI.after(\'' . $dropdown_html . '\');
                }
                
                // Also try for media modal uploader
                var $modalUploadUI = $(".media-modal .uploader-inline-content .upload-ui");
                if ($modalUploadUI.length && !$modalUploadUI.next(".media-folder-select-container").length) {
                    console.log("Found modal upload UI, adding dropdown");
                    $modalUploadUI.after(\'' . $dropdown_html . '\');
                }
                
                              // Hook into the uploader to capture the folder selection
                if (typeof wp !== "undefined" && wp.Uploader && wp.Uploader.prototype && !window.mediaFolderHooked) {
                    var originalInit = wp.Uploader.prototype.init;
                    
                    wp.Uploader.prototype.init = function() {
                        originalInit.apply(this, arguments);
                        
                        this.uploader.bind("BeforeUpload", function(up, file) {
                            var folder_id = $("#media-folder-select").val();
                            console.log("Setting upload folder to: " + folder_id);
                            up.settings.multipart_params.media_folder_id = folder_id;
                        });
                        
                        // ADD THIS CODE HERE - For updating folder counts after upload
                                              this.uploader.bind("FileUploaded", function(up, file, response) {
                            console.log("File uploaded, will update counts soon");
                            setTimeout(function() {
                                if (typeof window.updateFolderCounts === "function") {
                                    window.updateFolderCounts();
                                } else {
                                    console.error("updateFolderCounts function not found in global scope!");
                                    // Fallback: just reload the folder data via AJAX
                                    jQuery.post(ajaxurl, {
                                        action: "theme_get_folder_counts"
                                    });
                                }
                            }, 1000);
                        });
                    };
                    
                    window.mediaFolderHooked = true;
                    console.log("Successfully hooked into wp.Uploader");
                }
            }
            
            // Run on page load
            addFolderDropdownToUploader();
            
            // Handle dynamic uploader initialization
            $(document).on("click", ".media-modal .upload-files, .insert-media, .add_media", function() {
                console.log("Media upload button clicked");
                setTimeout(addFolderDropdownToUploader, 200);
            });
            
            // Extra check with a longer delay to catch late-initializing uploaders
            $(document).on("DOMNodeInserted", ".media-modal", function() {
                setTimeout(addFolderDropdownToUploader, 500);
            });
            
            // Final fallback - periodically check for upload UI that might appear later
            var checkCount = 0;
            var checkInterval = setInterval(function() {
                if ($(".upload-ui").length && !$(".upload-ui").next(".media-folder-select-container").length) {
                    addFolderDropdownToUploader();
                }
                
                checkCount++;
                if (checkCount > 10) clearInterval(checkInterval);
            }, 1000);
        });
        </script>
    ';
    
    // Output CSS and JavaScript
    echo $css . $js;
}

// THIS IS THE MISSING HOOK - Add this to make it work
add_action('admin_footer', 'media_folders_uploader');



// Handle file uploads with folder assignment
function media_folders_handle_upload($file) {
    // For debugging
    error_log('Upload handler called for file: ' . $file['name']);
    
    // Check if a folder was selected
    if (isset($_POST['media_folder_id'])) {
        error_log('Folder ID found in request: ' . $_POST['media_folder_id']);
        // Store folder ID in a transient with the filename as key
        // This lets us retrieve it later when the attachment is created
        set_transient('media_folder_for_' . sanitize_file_name($file['name']), intval($_POST['media_folder_id']), 5 * MINUTE_IN_SECONDS);
    } else {
        error_log('No folder ID found in upload request');
    }
    
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
    
    // Try to get the folder ID from the transient
    $folder_id = get_transient('media_folder_for_' . sanitize_file_name($filename));
    
    // Check for folder ID directly in the request as well
    if (!$folder_id && isset($_POST['media_folder_id'])) {
        $folder_id = intval($_POST['media_folder_id']);
    }
    
    error_log("Checking folder assignment for attachment ID $attachment_id (file: $filename)");
    
    // If we have a valid folder ID, assign the attachment to it
    if ($folder_id && $folder_id > 0) {
        // Assign to folder
        wp_set_object_terms($attachment_id, array($folder_id), 'media_folder', false);
        error_log("Assigned attachment ID $attachment_id to folder ID $folder_id");

         // Force update term counts
         theme_update_media_folder_counts();
        
        // Clean up the transient
        delete_transient('media_folder_for_' . sanitize_file_name($filename));
    }  else {
        error_log("No folder ID found for attachment ID $attachment_id");
    }  // If we have a valid folder ID, assign the attachment to it
   
}
add_action('add_attachment', 'media_folders_attachment_uploaded');

// Additional hook to make sure we catch all uploads
function media_folders_async_upload() {
    if (isset($_POST['media_folder_id']) && isset($_POST['attachment_id'])) {
        $attachment_id = intval($_POST['attachment_id']);
        $folder_id = intval($_POST['media_folder_id']);
        
        if ($folder_id > 0) {
            wp_set_object_terms($attachment_id, array($folder_id), 'media_folder', false);
            error_log("Async assigned attachment ID $attachment_id to folder ID $folder_id");
        }
    }
}
add_action('wp_ajax_upload-attachment', 'media_folders_async_upload', 1);

// Also add back-end selection directly to media uploader params
function media_folders_plupload_init($plupload_init) {
    // Add our custom folder param
    $plupload_init['multipart_params']['media_folder_id'] = isset($_REQUEST['media_folder_id']) ? $_REQUEST['media_folder_id'] : '';
    
    return $plupload_init;
}
add_filter('plupload_init', 'media_folders_plupload_init', 10);





/**
 * Force update term counts for media folders
 */
function theme_update_media_folder_counts() {
    global $wpdb;
    
    // Get all media folder terms
    $folders = get_terms(array(
        'taxonomy' => 'media_folder',
        'hide_empty' => false,
    ));
    
    if (empty($folders)) {
        return;
    }
    
    foreach ($folders as $folder) {
        // Count attachments in this folder
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->term_relationships
             JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
             WHERE $wpdb->term_relationships.term_taxonomy_id = %d
             AND $wpdb->posts.post_type = 'attachment'",
             $folder->term_taxonomy_id
        ));
        
        // Update the count in the database directly
        $wpdb->update(
            $wpdb->term_taxonomy,
            array('count' => $count),
            array('term_taxonomy_id' => $folder->term_taxonomy_id)
        );
    }
    
    // Clear caches
    clean_term_cache(wp_list_pluck($folders, 'term_id'), 'media_folder');
    delete_transient('media_folder_counts');
}
