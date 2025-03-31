<?php
/**
 * Plugin Name: Apex Folders
 * Plugin URI: 
 * Description: Organize media library files into folders for better management
 * Version: 0.9.7
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



function media_folders_admin_scripts() {
    $screen = get_current_screen();
    
    // Only on media upload screen
    if ($screen->base === 'upload') {
        // Enqueue jQuery UI core and all required components for dialogs
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-resizable');
        
        // Enqueue jQuery UI CSS
        wp_enqueue_style('wp-jquery-ui-dialog');
    }
}
add_action('admin_enqueue_scripts', 'media_folders_admin_scripts');

/**
 * Plugin activation
 */

function media_folders_activate() {
    // Register taxonomy on activation
    media_folders_register_taxonomy();
    
    // Create default "Unassigned" folder if it doesn't exist
    $unassigned = term_exists('Unassigned', 'media_folder');
    if (!$unassigned) {
        wp_insert_term(
            'Unassigned', 
            'media_folder',
            array(
                'description' => 'Default folder for media items not assigned to any other folder',
                'slug' => 'unassigned'
            )
        );
    }
    
    // Migrate existing unassigned media immediately
    media_folders_ensure_all_assigned();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
/**
 * Get the Unassigned folder ID
 */
function media_folders_get_unassigned_id() {
    $unassigned = term_exists('Unassigned', 'media_folder');
    if (!$unassigned) {
        // Create it if it doesn't exist
        $unassigned = wp_insert_term(
            'Unassigned', 
            'media_folder',
            array(
                'description' => 'Default folder for media items not assigned to any other folder',
                'slug' => 'unassigned'
            )
        );
    }
    
    return is_array($unassigned) ? $unassigned['term_id'] : $unassigned;
}

// Add before the existing theme_ajax_delete_media_folder function
function theme_ajax_delete_media_folder() {
    check_ajax_referer('media_folders_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error();
    }
    
    $folder_id = intval($_POST['folder_id']);
    $unassigned_id = media_folders_get_unassigned_id();
    
    // Prevent deleting the Unassigned folder
    if ($folder_id === $unassigned_id) {
        wp_send_json_error(array('message' => 'The Unassigned folder cannot be deleted.'));
        return;
    }
    
    // Delete the term
    $result = wp_delete_term($folder_id, 'media_folder');
    
    if (is_wp_error($result)) {
        wp_send_json_error();
    } else {
        wp_send_json_success();
    }
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

function media_folders_filter() {
    $screen = get_current_screen();
    if ($screen->base !== 'upload') return;
    
    $folders = get_terms(array(
        'taxonomy' => 'media_folder',
        'hide_empty' => false,
    ));

    // Get the unassigned ID
    $unassigned_id = media_folders_get_unassigned_id();

    // Find and categorize folders
    $unassigned_folder = null;
    $parent_folders = array();
    $child_folders = array();

    foreach ($folders as $folder) {
        if ($folder->term_id == $unassigned_id) {
            $unassigned_folder = $folder;
        } else if ($folder->parent == 0) {
            $parent_folders[] = $folder;
        } else {
            $child_folders[$folder->parent][] = $folder;
        }
    }
    
    echo '<div class="media-folder-filter">';
    echo '<h3>Media Folders</h3>';
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
                echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . $child->term_id . '" data-folder-name="' . esc_attr($child->name) . '"></span>';
                echo '</li>';
            }
        }
    }
    
    echo '</ul>';
    echo '<a href="#" class="button button-primary add-new-folder">Add New Folder</a>';
    echo '</div>';
    
    // Add CSS for the unassigned folder
        // Replace all existing CSS in the media_folders_filter function with this:
    
    echo '<style>
        /* --- Core Container --- */
        .media-folder-filter {
            float: left;
            width: 20%;
            margin: 65px 20px 0 0;
            padding: 0;
            background: transparent;
            position: relative;
            z-index: 10;
        }
        
        /* --- Admin Notices Fix --- */
        .wrap > .notice,
        .wrap > div.updated,
        .wrap > div.error,
        .wrap > div.info {
            margin-left: calc(20% + 65px);
            width: auto;
        }
        
        .media-folders-admin-notices-spacer {
            clear: both;
            height: 1px;
        }
        
        .media-folders-tools {
            margin-left: calc(20% + 40px);
            width: calc(80% - 40px);
        }
        
        /* --- Folder List Container --- */
        .media-folder-list {
            margin: 0 0 20px 0;
            padding: 0;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        /* --- Folder Section Title --- */
        .media-folder-filter h3 {
            margin: 0 0 8px 0;
            padding: 12px 15px;
            background: #f0f0f1;
            border-bottom: 1px solid #e2e4e7;
            font-size: 14px;
            color: #1d2327;
            font-weight: 600;
        }
        
        /* --- Folder Items (Common Styles) --- */
        .media-folder-list li {
            margin: 0;
            padding: 0;
            position: relative;
            border-bottom: 1px solid #f0f0f1;
            transition: all 0.15s ease;
        }
        
        .media-folder-list li:last-child {
            border-bottom: none;
        }
        
        /* Folder links */
        .media-folder-list li a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #2c3338;
            transition: all 0.15s ease;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding-right: 75px;
            font-size: 13px;
        }
        
        /* --- System Folders --- */
        /* All Files styling */
        .media-folder-list li.all-files {
            background: #f6f7f7;
        }
        
        .media-folder-list li.all-files a {
            font-weight: 600;
            color: #2271b1;
        }
        
        /* Unassigned folder styling */
        .media-folder-list li.unassigned-folder {
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        
        .media-folder-list li.unassigned-folder a {
            color: #50575e;
            font-style: italic;
        }
        
        /* Folder separator */
        .media-folder-list .folder-separator {
            height: 8px;
            background: #f0f0f1;
            padding: 0;
            border: none;
        }
        
        /* --- Custom Folders --- */
        /* Parent folders */
        .media-folder-list li.custom-folder {
            background: #fff;
        }
        
        .media-folder-list li.custom-folder:hover {
            background: #f6f7f7;
        }
        
        /* Parent folders with children */
        .media-folder-list li.parent-folder.has-children {
            border-left: 3px solid #2271b1;
        }
        
        /* Child folders - refined and compact */
        .media-folder-list li.child-folder {
            background: #fcfcfc;
            padding-left: 0;
            margin-left: 0;
            border-left: 3px solid #dcdcde;
        }
        
        .media-folder-list li.child-folder a {
            padding-left: 48px;
            font-size: 12.5px;
        }
        
        .media-folder-list li.child-folder .child-indicator {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a7aaad;
            font-size: 14px;
        }
        
        /* --- Active/Current Folder --- */
        .media-folder-list li.current {
            background: #f0f6fc;
        }
        
        .media-folder-list li.current a {
            color: #0a4b78;
            font-weight: 600;
        }
        
        /* --- Hover Effects --- */
        .media-folder-list li:not(.folder-separator):hover {
            background: #f0f6fc;
        }
        
        /* --- Folder Actions --- */
        /* Delete folder button */
        .delete-folder {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #cc1818;
            opacity: 0;
            transition: all 0.2s ease;
            cursor: pointer;
            padding: 4px;
            border-radius: 3px;
            z-index: 5;
        }
        
        .media-folder-list li:hover .delete-folder {
            opacity: 0.7;
        }
        
        .media-folder-list li .delete-folder:hover {
            opacity: 1;
            background: rgba(204, 24, 24, 0.1);
        }
        
        /* Add subfolder button */
        .add-subfolder {
            position: absolute;
            right: 36px;
            top: 50%;
            transform: translateY(-50%);
            color: #2271b1;
            opacity: 0;
            transition: all 0.2s ease;
            cursor: pointer;
            padding: 4px;
            border-radius: 3px;
            z-index: 5;
        }
        
        .media-folder-list li:hover .add-subfolder {
            opacity: 0.7;
        }
        
        .media-folder-list li .add-subfolder:hover {
            opacity: 1;
            background: rgba(34, 113, 177, 0.1);
        }
        
        /* --- Add New Folder Button --- */
        .add-new-folder {
            display: inline-block;
            width: 100%;
            padding: 10px 0;
            background: #2271b1;
            color: white;
            border: none;
            border-radius: 4px;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .add-new-folder:before {
            content: "+";
            margin-right: 8px;
            font-weight: bold;
        }
        
        .add-new-folder:hover {
            background: #135e96;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }
        
        .add-new-folder:active {
            transform: translateY(0);
        }
        
        /* --- Count Updates --- */
        .media-folder-list li.count-updated {
            background-color: #fcf9e8;
            transition: background-color 2s;
        }
        
        /* --- Dialog Styles --- */
        .folder-creation-dialog label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .folder-creation-dialog input,
        .folder-creation-dialog select {
            width: 100%;
            margin-bottom: 15px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #8c8f94;
        }
        
        /* --- Media Library Table --- */
        .wp-list-table, 
        .tablenav, 
        .search-form, 
        .subsubsub {
            width: 78%;
            float: right;
        }
        
        /* --- Responsive Design --- */
        @media (max-width: 1280px) {
            .media-folder-filter {
                width: 25%;
            }
            
            .wp-list-table, 
            .tablenav, 
            .search-form, 
            .subsubsub {
                width: 73%;
            }
            
            .wrap > .notice,
            .wrap > div.updated,
            .wrap > div.error,
            .wrap > div.info {
                margin-left: calc(25% + 65px);
            }
        }
        
        @media (max-width: 960px) {
            .media-folder-filter {
                width: 30%;
            }
            
            .wp-list-table, 
            .tablenav, 
            .search-form, 
            .subsubsub {
                width: 68%;
            }
            
            .wrap > .notice,
            .wrap > div.updated,
            .wrap > div.error,
            .wrap > div.info {
                margin-left: calc(30% + 65px);
            }
        }
        
        @media (max-width: 782px) {
            .media-folder-filter {
                width: 100%;
                float: none;
                margin: 20px 0;
            }
            
            .wp-list-table, 
            .tablenav, 
            .search-form, 
            .subsubsub {
                width: 100%;
                float: none;
            }
            
            .wrap > .notice,
            .wrap > div.updated,
            .wrap > div.error,
            .wrap > div.info {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>';
    
    // Add JavaScript for folder management
    ?>


    
    <script>
    jQuery(document).ready(function($) {
        // Add subfolder handler
        $('.add-subfolder').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var parentId = $(this).data('parent-id');
            var parentName = $(this).data('parent-name');
            
            // Display dialog to create subfolder
            var dialogContent = '<div class="folder-creation-dialog">' +
                '<p><label for="new-subfolder-name">Subfolder Name:</label>' +
                '<input type="text" id="new-subfolder-name" class="widefat" /></p>' +
                '<p>Parent folder: <strong>' + parentName + '</strong></p>' +
                '</div>';
            
            $('<div id="create-subfolder-dialog"></div>').html(dialogContent).dialog({
                title: 'Create New Subfolder',
                dialogClass: 'wp-dialog',
                modal: true,
                resizable: false,
                width: 400,
                buttons: {
                    'Create Subfolder': function() {
                        var folderName = $('#new-subfolder-name').val();
                        
                        if (folderName) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'theme_add_media_folder',
                                    folder_name: folderName,
                                    parent_id: parentId,
                                    nonce: '<?php echo wp_create_nonce('media_folders_nonce'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert('Error creating subfolder: ' + (response.data?.message || 'Unknown error'));
                                    }
                                }
                            });
                        }
                        
                        $(this).dialog('close');
                    },
                    'Cancel': function() {
                        $(this).dialog('close');
                    }
                },
                open: function() {
                    $('#new-subfolder-name').focus();
                },
                close: function() {
                    $(this).dialog('destroy').remove();
                }
            });
        });
    
        // Add new folder
        $('.add-new-folder').on('click', function(e) {
            e.preventDefault();
            
            // Create modal for folder creation
            var dialogContent = '<div class="folder-creation-dialog">' +
                '<p><label for="new-folder-name">Folder Name:</label>' +
                '<input type="text" id="new-folder-name" class="widefat" /></p>' +
                '<p><label for="new-folder-parent">Parent Folder (optional):</label>' +
                '<select id="new-folder-parent" class="widefat">' +
                '<option value="0">None (top level)</option>';
                
            // Add regular folders as potential parents
            <?php foreach ($parent_folders as $folder): ?>
            dialogContent += '<option value="<?php echo esc_attr($folder->term_id); ?>"><?php echo esc_html($folder->name); ?></option>';
            <?php endforeach; ?>
            
            dialogContent += '</select></p>' +
                '</div>';
            
            // Create dialog
            $('<div id="create-folder-dialog"></div>').html(dialogContent).dialog({
                title: 'Create New Folder',
                dialogClass: 'wp-dialog',
                modal: true,
                resizable: false,
                width: 400,
                buttons: {
                    'Create Folder': function() {
                        var folderName = $('#new-folder-name').val();
                        var parentId = $('#new-folder-parent').val();
                        
                        if (folderName) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'theme_add_media_folder',
                                    folder_name: folderName,
                                    parent_id: parentId,
                                    nonce: '<?php echo wp_create_nonce('media_folders_nonce'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert('Error creating folder: ' + (response.data?.message || 'Unknown error'));
                                    }
                                }
                            });
                        }
                        
                        $(this).dialog('close');
                    },
                    'Cancel': function() {
                        $(this).dialog('close');
                    }
                },
                open: function() {
                    // Focus the folder name input
                    $('#new-folder-name').focus();
                },
                close: function() {
                    $(this).dialog('destroy').remove();
                }
            });
        });
        
        // Delete folder (keep your existing delete folder code)
        $('.delete-folder').on('click', function(e) {
            // Your existing delete folder code...
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





function theme_ajax_add_media_folder() {
    check_ajax_referer('media_folders_nonce', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error();
    }
    
    $folder_name = sanitize_text_field($_POST['folder_name']);
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    
    // Ensure parent folder exists if specified
    if ($parent_id > 0) {
        $parent_term = get_term($parent_id, 'media_folder');
        if (!$parent_term || is_wp_error($parent_term)) {
            wp_send_json_error(array('message' => 'Parent folder does not exist'));
            return;
        }
        
        // Ensure parent isn't the Unassigned folder
        $unassigned_id = media_folders_get_unassigned_id();
        if ($parent_id == $unassigned_id) {
            wp_send_json_error(array('message' => 'Cannot create subfolders under Unassigned'));
            return;
        }
        
        // Check if parent itself has a parent (limit to one level)
        $parent_parent = get_term_field('parent', $parent_id, 'media_folder');
        if (!is_wp_error($parent_parent) && $parent_parent > 0) {
            wp_send_json_error(array('message' => 'Only one level of subfolders is supported'));
            return;
        }
    }
    
    $result = wp_insert_term(
        $folder_name, 
        'media_folder',
        array(
            'parent' => $parent_id
        )
    );
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
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
        $dropdown .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr($folder->term_id),
            $selected,
            esc_html($folder->name)
        );
        
        // Add children
        if (isset($child_folders[$folder->term_id])) {
            foreach ($child_folders[$folder->term_id] as $child) {
                $selected = selected($current_folder_id, $child->term_id, false);
                $dropdown .= sprintf(
                    '<option value="%s"%s>&nbsp;&nbsp;└─ %s</option>',
                    esc_attr($child->term_id),
                    $selected,
                    esc_html($child->name)
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
    global $wpdb;
    
    // Get the unassigned folder ID
    $unassigned_id = media_folders_get_unassigned_id();
    
    // Get the term taxonomy ID
    $tt_id = $wpdb->get_var($wpdb->prepare(
        "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
         WHERE term_id = %d AND taxonomy = 'media_folder'",
        $unassigned_id
    ));
    
    if (!$tt_id) {
        error_log("Error: Could not find term_taxonomy_id for Unassigned folder (ID: $unassigned_id)");
        return 0;
    }
    
    // Find all attachments that don't have ANY folder assignment
    $unassigned_attachments = $wpdb->get_col(
        "SELECT p.ID FROM $wpdb->posts p
         WHERE p.post_type = 'attachment'
         AND NOT EXISTS (
             SELECT 1 FROM $wpdb->term_relationships tr
             JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.taxonomy = 'media_folder' AND tr.object_id = p.ID
         )"
    );
    
    $count = 0;
    
    // Log what we found
    error_log("Found " . count($unassigned_attachments) . " attachments with no folder assignment");
    
    // Process in batches to avoid timeouts
    foreach ($unassigned_attachments as $attachment_id) {
        // Direct SQL approach to ensure reliability
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->term_relationships 
             WHERE object_id = %d AND term_taxonomy_id = %d",
            $attachment_id, $tt_id
        ));
        
        if (!$exists) {
            // Insert the relationship directly
            $wpdb->insert(
                $wpdb->term_relationships,
                array(
                    'object_id' => $attachment_id,
                    'term_taxonomy_id' => $tt_id,
                    'term_order' => 0
                )
            );
            
            if ($wpdb->insert_id || $wpdb->rows_affected) {
                $count++;
            }
        }
    }
    
    // Update term count
    if ($count > 0) {
        // Update the count in the database directly
        $wpdb->query($wpdb->prepare(
            "UPDATE $wpdb->term_taxonomy 
             SET count = count + %d
             WHERE term_taxonomy_id = %d",
            $count, $tt_id
        ));
        
        // Clear cache
        clean_term_cache($unassigned_id, 'media_folder');
    }
    
    // Force update all term counts to be sure
    theme_update_media_folder_counts();
    
    return $count;
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
    } else {
        // No folder specified, assign to Unassigned folder
        $unassigned_id = media_folders_get_unassigned_id();
        wp_set_object_terms($attachment_id, array($unassigned_id), 'media_folder', false);
        error_log("Assigned attachment ID $attachment_id to Unassigned folder ID $unassigned_id");
    }
    
    // Force update term counts
    theme_update_media_folder_counts();
    
    // Clean up the transient
    delete_transient('media_folder_for_' . sanitize_file_name($filename));
   
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
    // Only proceed for attachments
    if (get_post_type($post_id) !== 'attachment') {
        return;
    }
    
    // Check if the attachment already has a folder
    $terms = wp_get_object_terms($post_id, 'media_folder');
    
    // If it doesn't have any folder, assign to Unassigned
    if (empty($terms) || is_wp_error($terms)) {
        $unassigned_id = media_folders_get_unassigned_id();
        wp_set_object_terms($post_id, array($unassigned_id), 'media_folder', false);
    }
}
add_action('save_post', 'media_folders_ensure_folder_assignment');
add_action('edit_attachment', 'media_folders_ensure_folder_assignment');



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