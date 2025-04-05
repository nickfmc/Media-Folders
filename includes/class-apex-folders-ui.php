<?php
/**
 * Media Folders UI
 *
 * Handles UI rendering for the Media Folders plugin.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for UI functions
 */
class APEX_FOLDERS_UI {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add folder listing to media library
        add_action('admin_notices', array($this, 'render_folders_interface'));
        
        // Add folder field to attachment edit screen
        add_filter('attachment_fields_to_edit', array($this, 'add_folder_fields'), 10, 2);
        
        // Save attachment folder assignments
        add_filter('attachment_fields_to_save', array($this, 'save_folder_assignment'), 999, 2);
    }
    
    /**
     * Render the folders interface in the media library
     */
    public function render_folders_interface() {
        $screen = get_current_screen();
        if ($screen->base !== 'upload') return;
        
        // Check if we're in list view - if so, don't show the folders
        $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : '';
        if ($mode === 'list') {
            return; // Exit early if in list view
        }

        // Get organized folders using the utility class
        $organized = APEX_FOLDERS_Utilities::get_organized_folders();
        $unassigned_folder = $organized['unassigned'];
        $parent_folders = $organized['parents'];
        $child_folders = $organized['children'];
        
        echo '<div class="apex-folder-filter">';
        echo '<h3>' . esc_html__('Apex Folders', 'apex-folders') . '</h3>';
        echo '<ul class="apex-folder-list">';
        
        // Add "All Files" option
        $class = !isset($_GET['apex_folder']) ? 'current' : '';
        echo '<li class="' . esc_attr($class) . ' all-files"><a href="' . esc_url(admin_url('upload.php')) . '">' . esc_html__('All Files', 'apex-folders') . '</a></li>';
        
        // Add Unassigned folder immediately after All Files
        if ($unassigned_folder) {
            $class = isset($_GET['apex_folder']) && sanitize_text_field($_GET['apex_folder']) === $unassigned_folder->slug ? 'current' : '';
            echo '<li class="' . esc_attr($class) . ' unassigned-folder" data-folder-id="' . intval($unassigned_folder->term_id) . '">';
            echo '<a href="' . esc_url(admin_url('upload.php?apex_folder=' . $unassigned_folder->slug)) . '">' . esc_html($unassigned_folder->name) . ' (' . intval($unassigned_folder->count) . ')</a>';
            echo '</li>';
        }
        
        // Add separator
        echo '<li class="folder-separator"></li>';
        
        // Add parent folders and their children
        foreach ($parent_folders as $folder) {
            $class = isset($_GET['apex_folder']) && $_GET['apex_folder'] === $folder->slug ? 'current' : '';
            $has_children = isset($child_folders[$folder->term_id]) && !empty($child_folders[$folder->term_id]);
            
            echo '<li class="' . $class . ' custom-folder parent-folder' . ($has_children ? ' has-children' : '') . '" data-folder-id="' . $folder->term_id . '">';
            echo '<a href="' . admin_url('upload.php?apex_folder=' . $folder->slug) . '">' . $folder->name . ' (' . $folder->count . ')</a>';
            echo '<span class="edit-folder dashicons dashicons-edit" data-folder-id="' . $folder->term_id . '" data-folder-name="' . esc_attr($folder->name) . '" title="' . esc_attr__('Edit folder', 'apex-folders') . '"></span>';
            echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . $folder->term_id . '" data-folder-name="' . esc_attr($folder->name) . '" title="' . esc_attr__('Delete folder', 'apex-folders') . '"></span>';
            
            // Add "Create Subfolder" button for parent folders
            echo '<span class="add-subfolder dashicons dashicons-plus-alt2" data-parent-id="' . $folder->term_id . '" data-parent-name="' . esc_attr($folder->name) . '" title="' . esc_attr__('Add subfolder', 'apex-folders') . '"></span>';
            
            echo '</li>';
            
            // Display children if any
            if ($has_children) {
                foreach ($child_folders[$folder->term_id] as $child) {
                    $child_class = isset($_GET['apex_folder']) && $_GET['apex_folder'] === $child->slug ? 'current' : '';
                    
                    echo '<li class="' . $child_class . ' custom-folder child-folder" data-folder-id="' . $child->term_id . '" data-parent-id="' . $folder->term_id . '">';
                    echo '<span class="child-indicator">└─</span>';
                    echo '<a href="' . admin_url('upload.php?apex_folder=' . $child->slug) . '">' . $child->name . ' (' . $child->count . ')</a>';
                    echo '<span class="edit-folder dashicons dashicons-edit" data-folder-id="' . $child->term_id . '" data-folder-name="' . esc_attr($child->name) . '" title="' . esc_attr__('Edit folder', 'apex-folders') . '"></span>';
                    echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . $child->term_id . '" data-folder-name="' . esc_attr($child->name) . '" title="' . esc_attr__('Delete folder', 'apex-folders') . '"></span>';
                    echo '</li>';
                }
            }
        }
        
        echo '</ul>';
        echo '<a href="#" class="button button-primary add-new-folder">' . esc_html__('Add New Folder', 'apex-folders') . '</a>';
        echo '</div>';
        
        $this->enqueue_scripts_and_styles($parent_folders);
    }
    
    /**
     * Enqueue scripts and styles for the folder interface
     *
     * @param array $parent_folders Parent folders
     */
    private function enqueue_scripts_and_styles($parent_folders) {
        // Enqueue scripts and styles
        wp_enqueue_style('apex-folders-css', APEX_FOLDERS_PLUGIN_URL . 'assets/css/apex-main.css', array(), APEX_FOLDERS_VERSION);
        
        // Add jQuery UI for dialogs
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-dialog');
        
        // Pass data to our scripts
        $folders_data = array(
            'currentFolder' => isset($_GET['apex_folder']) ? sanitize_text_field($_GET['apex_folder']) : null,
            'nonce' => wp_create_nonce('APEX_FOLDERS_nonce'),
            'slugNonce' => wp_create_nonce('APEX_FOLDERS_get_slug'),
            'parentFolders' => array_map(function($folder) {
                return array(
                    'term_id' => $folder->term_id,
                    'name' => $folder->name
                );
            }, $parent_folders)
        );
        
        wp_enqueue_script('apex-folder-management', APEX_FOLDERS_PLUGIN_URL . 'assets/js/folder-management.js', array('jquery', 'jquery-ui-dialog'), APEX_FOLDERS_VERSION, true);
        wp_enqueue_script('apex-attachment-tracking', APEX_FOLDERS_PLUGIN_URL . 'assets/js/attachment-tracking.js', array('jquery'), APEX_FOLDERS_VERSION, true);
        wp_enqueue_script('apex-folder-counts', APEX_FOLDERS_PLUGIN_URL . 'assets/js/folder-counts.js', array('jquery'), APEX_FOLDERS_VERSION, true);
        
        wp_localize_script('apex-folder-management', 'apexFolderData', $folders_data);
        wp_localize_script('apex-attachment-tracking', 'apexFolderData', $folders_data);
        wp_localize_script('apex-folder-counts', 'apexFolderData', $folders_data);
        
        // Add translatable strings for JavaScript
        wp_localize_script('apex-folder-management', 'apexFoldersL10n', array(
            'addFolder'        => esc_html__('Add Folder', 'apex-folders'),
            'addSubfolder'     => esc_html__('Add Subfolder', 'apex-folders'),
            'renameFolder'     => esc_html__('Rename Folder', 'apex-folders'),
            'deleteFolder'     => esc_html__('Delete Folder', 'apex-folders'),
            'folderName'       => esc_html__('Folder Name', 'apex-folders'),
            'cancel'           => esc_html__('Cancel', 'apex-folders'),
            'save'             => esc_html__('Save', 'apex-folders'),
            'delete'           => esc_html__('Delete', 'apex-folders'),
            'confirmDelete'    => esc_html__('Are you sure you want to delete this folder? Files will be moved to the Unassigned folder.', 'apex-folders'),
            'creatingFolder'   => esc_html__('Creating folder...', 'apex-folders'),
            'renamingFolder'   => esc_html__('Renaming folder...', 'apex-folders'),
            'deletingFolder'   => esc_html__('Deleting folder...', 'apex-folders'),
            'updatingCounts'   => esc_html__('Updating folder counts...', 'apex-folders'),
            'error'            => esc_html__('An error occurred', 'apex-folders'),
            'success'          => esc_html__('Success', 'apex-folders')
        ));
    }
    
    /**
     * Add folder selector to attachment edit screen
     *
     * @param array $form_fields Form fields
     * @param WP_Post $post Attachment post
     * @return array Modified form fields
     */
    public function add_folder_fields($form_fields, $post) {
        $folders = get_terms(array(
            'taxonomy' => 'apex_folder',
            'hide_empty' => false,
        ));
        
        // Get the current folder term
        $current_folders = wp_get_object_terms($post->ID, 'apex_folder');
        $current_folder_id = (!empty($current_folders) && !is_wp_error($current_folders)) ? $current_folders[0]->term_id : APEX_FOLDERS_get_unassigned_id();
        
        // Organize folders by hierarchy
        $unassigned_folder = null;
        $parent_folders = array();
        $child_folders = array();
        
        foreach ($folders as $folder) {
            if ($folder->term_id == APEX_FOLDERS_get_unassigned_id()) {
                $unassigned_folder = $folder;
            } else if ($folder->parent == 0) {
                $parent_folders[] = $folder;
            } else {
                $child_folders[$folder->parent][] = $folder;
            }
        }
        
        $dropdown = '<select name="attachments[' . $post->ID . '][apex_folder]" id="attachments-' . $post->ID . '-apex_folder">';
        
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
                    '<option value="%s"%s>%s (%s)</option>',
                    esc_attr($folder->term_id),
                    $selected,
                    esc_html($folder->name),
                    sprintf(
                        /* translators: %1$d: folder's own file count, %2$d: total file count including subfolders */
                        esc_html__('%1$d / %2$d total', 'apex-folders'),
                        $folder->count,
                        $total_count
                    )
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
        
        $form_fields['apex_folder'] = array(
            'label' => esc_html__('Folder', 'apex-folders'),
            'input' => 'html',
            'html' => $dropdown,
            'helps' => esc_html__('Select a folder for this media item', 'apex-folders')
        );
        
        return $form_fields;
    }
    
    /**
     * Save folder assignment from attachment edit screen
     *
     * @param array $post Post data
     * @param array $attachment Attachment data
     * @return array Modified post data
     */
    public function save_folder_assignment($post, $attachment) {
        global $is_processing_apex_folder;
        
        // If already processing or no folder specified, return
        if ($is_processing_apex_folder || !isset($attachment['apex_folder'])) {
            return $post;
        }
        
        $is_processing_apex_folder = true;
        
        try {
            $folder_id = intval($attachment['apex_folder']);
            $post_id = $post['ID'];
            
            // Remove existing terms
            wp_delete_object_term_relationships($post_id, 'apex_folder');
            
            if ($folder_id > 0) {
                // Set the term to the selected folder
                wp_set_object_terms($post_id, array($folder_id), 'apex_folder', false);
            } else {
                // If no folder or "0" selected, assign to Unassigned folder
                $unassigned_id = APEX_FOLDERS_get_unassigned_id();
                wp_set_object_terms($post_id, array($unassigned_id), 'apex_folder', false);
            }

            // Add JavaScript to force refresh
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
            $is_processing_apex_folder = false;
        }
        
        return $post;
    }
}

// Initialize the class
new APEX_FOLDERS_UI();