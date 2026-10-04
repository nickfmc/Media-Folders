<?php
/**
 * Media Folders UI
 *
 * Handles UI rendering for the Media Folders plugin.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
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
        add_action( 'admin_notices', array( $this, 'render_folders_interface' ) );
        
        // Add folder field to attachment edit screen
        // Use a higher priority (20) to ensure it runs after WordPress adds its fields
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_folder_fields' ), 20, 2 );
        
        // Save attachment folder assignments
        add_filter( 'attachment_fields_to_save', array( $this, 'save_folder_assignment' ), 999, 2 );
        
        // filter to prevent checkboxes in list view
        add_filter( 'wp_terms_checklist_args', array( $this, 'prevent_folder_checklist' ), 10, 2 );
    }
    
    /**
     * Get sanitized folder from URL parameters with nonce verification when applicable
     *
     * @return string|null Sanitized folder slug or null
     */
    private function get_current_folder() {
        // For requests that should have nonce verification
        if (isset($_GET['apex_folder']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'apex_folder_view')) {
                return sanitize_text_field(wp_unslash($_GET['apex_folder']));
            }
            return null;
        }
        
        // For regular page views (no action being taken, just viewing)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading folder filter for display purposes, not making changes
        if (isset($_GET['apex_folder'])) {
            return sanitize_text_field(wp_unslash($_GET['apex_folder']));
        }
        
        return null;
    }
    
    /**
     * Get the current view mode from URL or user settings
     *
     * @return string The current view mode ('grid' or 'list')
     */
    private function get_media_library_mode() {
        $user_id = get_current_user_id();
        $media_library_mode = get_user_option('media_library_mode', $user_id);
        
        // Default to 'grid' if not set
        if (empty($media_library_mode)) {
            $media_library_mode = 'grid';
        }
        
        // URL parameter overrides the user preference
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading view preference, not making changes
        if (isset($_GET['mode'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading view preference, not making changes
            $url_mode = sanitize_text_field(wp_unslash($_GET['mode']));
            if (!empty($url_mode) && in_array($url_mode, array('grid', 'list'), true)) {
                $media_library_mode = $url_mode;
            }
        }
        
        return $media_library_mode;
    }
    
    /**
     * Render the folders interface in the media library
     */
    public function render_folders_interface() {
        $screen = get_current_screen();
        if ( $screen->base !== 'upload' ) {
            return;
        }

        // Determine the current view mode
        $media_library_mode = $this->get_media_library_mode();
        
        // Get current folder
        $current_folder = $this->get_current_folder();

        // Get organized folders using the utility class
        $organized = APEX_FOLDERS_Utilities::get_organized_folders();
        $unassigned_folder = $organized['unassigned'];
        $parent_folders = $organized['parents'];
        $child_folders = $organized['children'];
        
        // Add a CSS class based on the detected mode
        echo '<div class="apex-folder-filter apex-mode-' . esc_attr($media_library_mode) . '">';
        echo '<h3>' . esc_html__( 'Apex Folders', 'apex-folders' ) . '</h3>';
        echo '<ul class="apex-folder-list">';
        
        // Add "All Files" option
        $class = !$current_folder ? 'current' : '';
        echo '<li class="' . esc_attr( $class ) . ' all-files"><a href="' . esc_url( admin_url( 'upload.php' ) ) . '">' . esc_html__( 'All Files', 'apex-folders' ) . '</a></li>';
        
        // Add Unassigned folder immediately after All Files
        if ( $unassigned_folder ) {
            $class = $current_folder === $unassigned_folder->slug ? 'current' : '';
            $nonce_url = wp_nonce_url(admin_url('upload.php?apex_folder=' . $unassigned_folder->slug), 'apex_folder_view');
            echo '<li class="' . esc_attr( $class ) . ' unassigned-folder" data-folder-id="' . intval( $unassigned_folder->term_id ) . '">';
            echo '<a href="' . esc_url($nonce_url) . '">' . esc_html( $unassigned_folder->name ) . ' (' . intval( $unassigned_folder->count ) . ')</a>';
            echo '</li>';
        }
        
        // Add separator
        echo '<li class="folder-separator"></li>';
        
        // Add parent folders and their children
        foreach ( $parent_folders as $folder ) {
            $class = $current_folder === $folder->slug ? 'current' : '';
            $has_children = isset( $child_folders[$folder->term_id] ) && ! empty( $child_folders[$folder->term_id] );
            $nonce_url = wp_nonce_url(admin_url('upload.php?apex_folder=' . $folder->slug), 'apex_folder_view');
            
            echo '<li class="' . esc_attr( $class ) . ' custom-folder parent-folder' . ( $has_children ? ' has-children' : '' ) . '" data-folder-id="' . intval( $folder->term_id ) . '">';
            echo '<a href="' . esc_url($nonce_url) . '">' . esc_html( $folder->name ) . ' (' . intval( $folder->count ) . ')</a>';
            echo '<span class="edit-folder dashicons dashicons-edit" data-folder-id="' . intval( $folder->term_id ) . '" data-folder-name="' . esc_attr( $folder->name ) . '" title="' . esc_attr__( 'Edit folder', 'apex-folders' ) . '"></span>';
            echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . intval( $folder->term_id ) . '" data-folder-name="' . esc_attr( $folder->name ) . '" title="' . esc_attr__( 'Delete folder', 'apex-folders' ) . '"></span>';
            
            // Add "Create Subfolder" button for parent folders
            echo '<span class="add-subfolder dashicons dashicons-plus-alt2" data-parent-id="' . intval( $folder->term_id ) . '" data-parent-name="' . esc_attr( $folder->name ) . '" title="' . esc_attr__( 'Add subfolder', 'apex-folders' ) . '"></span>';
            
            echo '</li>';
            
            // Display children if any
            if ( $has_children ) {
                foreach ( $child_folders[$folder->term_id] as $child ) {
                    $child_class = $current_folder === $child->slug ? 'current' : '';
                    $child_nonce_url = wp_nonce_url(admin_url('upload.php?apex_folder=' . $child->slug), 'apex_folder_view');
                    
                    echo '<li class="' . esc_attr( $child_class ) . ' custom-folder child-folder" data-folder-id="' . intval( $child->term_id ) . '" data-parent-id="' . intval( $folder->term_id ) . '">';
                    echo '<span class="child-indicator">└─</span>';
                    echo '<a href="' . esc_url($child_nonce_url) . '">' . esc_html( $child->name ) . ' (' . intval( $child->count ) . ')</a>';
                    echo '<span class="edit-folder dashicons dashicons-edit" data-folder-id="' . intval( $child->term_id ) . '" data-folder-name="' . esc_attr( $child->name ) . '" title="' . esc_attr__( 'Edit folder', 'apex-folders' ) . '"></span>';
                    echo '<span class="delete-folder dashicons dashicons-trash" data-folder-id="' . intval( $child->term_id ) . '" data-folder-name="' . esc_attr( $child->name ) . '" title="' . esc_attr__( 'Delete folder', 'apex-folders' ) . '"></span>';
                    echo '</li>';
                }
            }
        }
        
        echo '</ul>';
        echo '<a href="#" class="button button-primary add-new-folder">' . esc_html__( 'Add New Folder', 'apex-folders' ) . '</a>';
        echo '</div>';
        
        $this->enqueue_scripts_and_styles( $parent_folders );
    }
    
    /**
     * Enqueue scripts and styles for the folder interface
     *
     * @param array $parent_folders Parent folders
     */
    private function enqueue_scripts_and_styles( $parent_folders ) {
        // Enqueue scripts and styles
        wp_enqueue_style( 'apex-folders-css', APEX_FOLDERS_PLUGIN_URL . 'assets/css/apex-main.css', array(), APEX_FOLDERS_VERSION );
        
        // Add jQuery UI for dialogs
        wp_enqueue_style( 'wp-jquery-ui-dialog' );
        wp_enqueue_script( 'jquery-ui-dialog' );
        
        // Get current folder with safety check
        $current_folder = $this->get_current_folder();
        
        // Pass data to our scripts
        $folders_data = array(
            'currentFolder' => $current_folder,
            'nonce' => wp_create_nonce( 'APEX_FOLDERS_nonce' ),
            'slugNonce' => wp_create_nonce( 'APEX_FOLDERS_get_slug' ),
            'viewNonce' => wp_create_nonce( 'apex_folder_view' ),
            'parentFolders' => array_map( function( $folder ) {
                return array(
                    'term_id' => $folder->term_id,
                    'name' => $folder->name
                );
            }, $parent_folders )
        );
        
        wp_enqueue_script( 'apex-folder-management', APEX_FOLDERS_PLUGIN_URL . 'assets/js/folder-management.js', array( 'jquery', 'jquery-ui-dialog' ), APEX_FOLDERS_VERSION, true );
        wp_enqueue_script( 'apex-attachment-tracking', APEX_FOLDERS_PLUGIN_URL . 'assets/js/attachment-tracking.js', array( 'jquery' ), APEX_FOLDERS_VERSION, true );
        wp_enqueue_script( 'apex-folder-counts', APEX_FOLDERS_PLUGIN_URL . 'assets/js/folder-counts.js', array( 'jquery' ), APEX_FOLDERS_VERSION, true );
        
        wp_localize_script( 'apex-folder-management', 'apexFolderData', $folders_data );
        wp_localize_script( 'apex-attachment-tracking', 'apexFolderData', $folders_data );
        wp_localize_script( 'apex-folder-counts', 'apexFolderData', $folders_data );
        
        // Add translatable strings for JavaScript
        wp_localize_script( 'apex-folder-management', 'apexFoldersL10n', array(
            'addFolder'        => esc_html__( 'Add Folder', 'apex-folders' ),
            'addSubfolder'     => esc_html__( 'Add Subfolder', 'apex-folders' ),
            'renameFolder'     => esc_html__( 'Rename Folder', 'apex-folders' ),
            'deleteFolder'     => esc_html__( 'Delete Folder', 'apex-folders' ),
            'folderName'       => esc_html__( 'Folder Name', 'apex-folders' ),
            'cancel'           => esc_html__( 'Cancel', 'apex-folders' ),
            'save'             => esc_html__( 'Save', 'apex-folders' ),
            'delete'           => esc_html__( 'Delete', 'apex-folders' ),
            'confirmDelete'    => esc_html__( 'Are you sure you want to delete this folder? Files will be moved to the Unassigned folder.', 'apex-folders' ),
            'creatingFolder'   => esc_html__( 'Creating folder...', 'apex-folders' ),
            'renamingFolder'   => esc_html__( 'Renaming folder...', 'apex-folders' ),
            'deletingFolder'   => esc_html__( 'Deleting folder...', 'apex-folders' ),
            'updatingCounts'   => esc_html__( 'Updating folder counts...', 'apex-folders' ),
            'error'            => esc_html__( 'An error occurred', 'apex-folders' ),
            'success'          => esc_html__( 'Success', 'apex-folders' )
        ) );
    }
    
    /**
     * Add folder selector to attachment edit screen
     *
     * @param array $form_fields Form fields
     * @param WP_Post $post Attachment post
     * @return array Modified form fields
     */
    public function add_folder_fields( $form_fields, $post ) {
        // Always remove any existing apex_folder fields, regardless of how they're rendered
        if (isset($form_fields['apex_folder'])) {
            unset($form_fields['apex_folder']);
        }

        // Store the current folder ID before getting new terms
        // This ensures we don't lose the current selection
        $current_folder_id = apex_folders_get_unassigned_id();
        
        // Get the current folder term
        $current_folders = wp_get_object_terms( $post->ID, 'apex_folder' );
        if ( !empty($current_folders) && !is_wp_error($current_folders) ) {
            $current_folder_id = $current_folders[0]->term_id;
        }
        
        // Get folders for the dropdown
        $folders = get_terms( array(
            'taxonomy' => 'apex_folder',
            'hide_empty' => false,
        ) );
        
        // Organize folders by hierarchy
        $unassigned_folder = null;
        $parent_folders = array();
        $child_folders = array();
        
        foreach ( $folders as $folder ) {
            if ( $folder->term_id == apex_folders_get_unassigned_id() ) {
                $unassigned_folder = $folder;
            } elseif ( $folder->parent == 0 ) {
                $parent_folders[] = $folder;
            } else {
                $child_folders[$folder->parent][] = $folder;
            }
        }
        
        $dropdown = '<select name="attachments[' . $post->ID . '][apex_folder]" id="attachments-' . $post->ID . '-apex_folder">';
        
        // Add unassigned folder first
        if ( $unassigned_folder ) {
            $selected = selected( $current_folder_id, $unassigned_folder->term_id, false );
            $dropdown .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $unassigned_folder->term_id ),
                $selected,
                esc_html( $unassigned_folder->name )
            );
        }
        
        // Add parent folders and their children
        foreach ( $parent_folders as $folder ) {
            $selected = selected( $current_folder_id, $folder->term_id, false );
            
            // Calculate total count for display purposes
            $total_count = $folder->count;
            $has_children = isset( $child_folders[$folder->term_id] ) && ! empty( $child_folders[$folder->term_id] );
            
            if ( $has_children ) {
                foreach ( $child_folders[$folder->term_id] as $child ) {
                    $total_count += $child->count;
                }
                $dropdown .= sprintf(
                    '<option value="%s"%s>%s (%s)</option>',
                    esc_attr( $folder->term_id ),
                    $selected,
                    esc_html( $folder->name ),
                    sprintf(
                        /* translators: %1$d: folder's own file count, %2$d: total file count including subfolders */
                        esc_html__( '%1$d / %2$d total', 'apex-folders' ),
                        $folder->count,
                        $total_count
                    )
                );
            } else {
                $dropdown .= sprintf(
                    '<option value="%s"%s>%s (%d)</option>',
                    esc_attr( $folder->term_id ),
                    $selected,
                    esc_html( $folder->name ),
                    $folder->count
                );
            }
            
            // Add children
            if ( $has_children ) {
                foreach ( $child_folders[$folder->term_id] as $child ) {
                    $selected = selected( $current_folder_id, $child->term_id, false );
                    $dropdown .= sprintf(
                        '<option value="%s"%s>&nbsp;&nbsp;└─ %s (%d)</option>',
                        esc_attr( $child->term_id ),
                        $selected,
                        esc_html( $child->name ),
                        $child->count
                    );
                }
            }
        }
        
        $dropdown .= '</select>';
        
        // Add a nonce field
        $nonce_field = wp_nonce_field('save_apex_folder_' . $post->ID, 'apex_folder_nonce_' . $post->ID, true, false);
        $dropdown .= $nonce_field;
        
        $form_fields['apex_folder'] = array(
            'label' => esc_html__( 'Folder', 'apex-folders' ),
            'input' => 'html',
            'html' => $dropdown,
            'helps' => esc_html__( 'Select a folder for this media item', 'apex-folders' )
        );
        
        return $form_fields;
    }
    
    /**
     * Prevent WordPress from showing the terms checklist for our taxonomy
     *
     * @param array $args Arguments for the terms checklist
     * @param int $post_id Post ID
     * @return array Modified arguments
     */
    public function prevent_folder_checklist( $args, $post_id ) {
        // If this is our taxonomy, prevent the checklist completely
        if ( isset( $args['taxonomy'] ) && $args['taxonomy'] === 'apex_folder' ) {
            // Set a special walker that outputs nothing
            $args['walker'] = new APEXFO_Walker_Empty();
            $args['checked_ontop'] = false;
            $args['selected_cats'] = array();
        }
        return $args;
    }
    
    /**
     * Save folder assignment from attachment edit screen
     *
     * @param array $post Post data
     * @param array $attachment Attachment data
     * @return array Modified post data
     */
    public function save_folder_assignment( $post, $attachment ) {
        // If already processing or no folder specified, return
        if ( Apex_Folders_State::is_processing() || ! isset( $attachment['apex_folder'] ) ) {
            return $post;
        }
        
        // Verify nonce
        $nonce_name = 'apex_folder_nonce_' . $post['ID'];
        if ( !isset($_POST[$nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), 'save_apex_folder_' . $post['ID']) ) {
            return $post;
        }
        
        Apex_Folders_State::set_processing( true );
        
        try {
            $folder_id = intval( $attachment['apex_folder'] );
            $post_id = $post['ID'];
            
            // Remove existing terms
            wp_delete_object_term_relationships( $post_id, 'apex_folder' );
            
            if ( $folder_id > 0 ) {
                // Set the term to the selected folder
                wp_set_object_terms( $post_id, array( $folder_id ), 'apex_folder', false );
            } else {
                // If no folder or "0" selected, assign to Unassigned folder
                $unassigned_id = apex_folders_get_unassigned_id();
                wp_set_object_terms( $post_id, array( $unassigned_id ), 'apex_folder', false );
            }

            // Add JavaScript to force refresh using wp_add_inline_script
            $refresh_script = 'jQuery(document).ready(function($) {
                // Force refresh of the media library view
                if (wp.media.frame) {
                    wp.media.frame.library.props.set({ignore: (+ new Date())});
                    wp.media.frame.library.props.trigger("change");
                }
                
                // If we are in grid mode, refresh that too
                if (wp.media.view.Attachment.Library) {
                    $(".attachments-browser .attachments").trigger("scroll");
                }
                
                // Update folder counts
                if (typeof window.updateFolderCounts === "function") {
                    window.updateFolderCounts();
                }
            });';
            wp_add_inline_script( 'jquery', $refresh_script );

        } finally {
            Apex_Folders_State::set_processing( false );
        }
        
        return $post;
    }
}

/**
 * Empty walker class for apex folders.
 * Using proper naming convention to avoid conflicts.
 */
class APEXFO_Walker_Empty extends Walker {
    public function start_lvl( &$output, $depth = 0, $args = array() ) {
        // Do nothing but maintain the expected structure
        $output .= '';
    }
    
    public function end_lvl( &$output, $depth = 0, $args = array() ) {
        // Do nothing but maintain the expected structure
        $output .= '';
    }
    
    public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
        // Do nothing but maintain the expected structure
        $output .= '';
    }
    
    public function end_el( &$output, $object, $depth = 0, $args = array() ) {
        // Do nothing but maintain the expected structure
        $output .= '';
    }
}

// Initialize the class
new APEX_FOLDERS_UI();
