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
     * Inline SVG icons used in the folder sidebar
     *
     * @param string $name Icon name.
     * @return string Static SVG markup.
     */
    private function icon( $name ) {
        $icons = array(
            // The Apex family mark and the slash from the apex/<tool> wordmark
            'mark'   => '<svg class="apexfo-mark" viewBox="0 0 274 248" aria-hidden="true" focusable="false"><path d="M23 163 L137 24 L251 163" fill="none" stroke="currentColor" stroke-width="46" stroke-linecap="round" stroke-linejoin="round"/><circle cx="137" cy="218" r="29.5" fill="#148a5a"/></svg>',
            'slash'  => '<svg class="apexfo-slash" viewBox="0 0 71 143" aria-hidden="true" focusable="false"><path d="M52 0H71L19 143H0Z" fill="#148a5a"/></svg>',
            'files'  => '<svg class="apexfo-icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><rect x="2.75" y="4.75" width="14.5" height="11.5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M3 13.5l4-4 3 3 2-2 5 5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="13" cy="8.5" r="1.25" fill="currentColor"/></svg>',
            'inbox'  => '<svg class="apexfo-icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M3 11.5l2.1-6A1.5 1.5 0 0 1 6.5 4.5h7a1.5 1.5 0 0 1 1.4 1l2.1 6v3a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 14.5v-3Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M3 11.5h4l1 2h4l1-2h4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
            'folder' => '<svg class="apexfo-icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M2.75 5.5A1.5 1.5 0 0 1 4.25 4h3.6l1.9 2h6a1.5 1.5 0 0 1 1.5 1.5v7a1.5 1.5 0 0 1-1.5 1.5h-11.5a1.5 1.5 0 0 1-1.5-1.5v-9Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
        );

        return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
    }

    /**
     * Render one folder in the sidebar list
     *
     * @param WP_Term $folder         The folder.
     * @param string  $current_folder Slug of the folder being viewed.
     * @param string  $classes        Extra classes for the list item.
     * @param int     $parent_id      Parent folder ID for subfolders.
     * @param int     $total          Count including subfolders, for parent folders.
     */
    private function render_folder_item( $folder, $current_folder, $classes, $parent_id = 0, $total = 0 ) {
        $is_current = $current_folder === $folder->slug;
        $url        = wp_nonce_url( admin_url( 'upload.php?apex_folder=' . $folder->slug ), 'apex_folder_view' );
        $is_system  = (int) $folder->term_id === (int) apex_folders_get_unassigned_id();

        echo '<li class="' . esc_attr( trim( ( $is_current ? 'current ' : '' ) . $classes ) ) . '" data-folder-id="' . intval( $folder->term_id ) . '"' . ( $parent_id ? ' data-parent-id="' . intval( $parent_id ) . '"' : '' ) . '>';
        echo '<a href="' . esc_url( $url ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>';
        echo $this->icon( $is_system ? 'inbox' : 'folder' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup.
        echo '<span class="apexfo-name">' . esc_html( $folder->name ) . '</span>';

        $count_title = '';
        if ( $total > $folder->count ) {
            /* translators: %1$d: files in this folder, %2$d: files including subfolders */
            $count_title = sprintf( __( '%1$d in this folder, %2$d including subfolders', 'apex-folders' ), $folder->count, $total );
        }
        echo '<span class="apexfo-count"' . ( $count_title ? ' title="' . esc_attr( $count_title ) . '"' : '' ) . '>' . intval( $folder->count ) . '</span>';
        echo '</a>';

        if ( ! $is_system ) {
            echo '<span class="apexfo-actions">';
            if ( ! $parent_id ) {
                echo '<button type="button" class="apexfo-action add-subfolder" data-parent-id="' . intval( $folder->term_id ) . '" data-parent-name="' . esc_attr( $folder->name ) . '" title="' . esc_attr__( 'Add subfolder', 'apex-folders' ) . '" aria-label="' . esc_attr__( 'Add subfolder', 'apex-folders' ) . '"><span class="dashicons dashicons-plus-alt2"></span></button>';
            }
            echo '<button type="button" class="apexfo-action edit-folder" data-folder-id="' . intval( $folder->term_id ) . '" data-folder-name="' . esc_attr( $folder->name ) . '" title="' . esc_attr__( 'Rename folder', 'apex-folders' ) . '" aria-label="' . esc_attr__( 'Rename folder', 'apex-folders' ) . '"><span class="dashicons dashicons-edit"></span></button>';
            echo '<button type="button" class="apexfo-action delete-folder" data-folder-id="' . intval( $folder->term_id ) . '" data-folder-name="' . esc_attr( $folder->name ) . '" title="' . esc_attr__( 'Delete folder', 'apex-folders' ) . '" aria-label="' . esc_attr__( 'Delete folder', 'apex-folders' ) . '"><span class="dashicons dashicons-trash"></span></button>';
            echo '</span>';
        }

        echo '</li>';
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

        // Every file is in exactly one folder, so the folder counts add up to the library
        $total_files = 0;
        foreach ( $organized['all'] as $folder ) {
            $total_files += (int) $folder->count;
        }

        $apex_url = 'https://apex.mountainairweb.com/?utm_source=wordpress&utm_medium=plugin&utm_campaign=apex-folders';

        // Add a CSS class based on the detected mode
        echo '<div class="apex-folder-filter apex-mode-' . esc_attr( $media_library_mode ) . '">';

        // Brand header: the Apex mark and the apex/folders wordmark
        echo '<div class="apexfo-brand">';
        echo $this->icon( 'mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup.
        echo '<span class="apexfo-wordmark" aria-hidden="true">apex' . $this->icon( 'slash' ) . 'folders</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup.
        echo '<span class="screen-reader-text">' . esc_html__( 'Apex Folders', 'apex-folders' ) . '</span>';
        echo '</div>';

        echo '<ul class="apex-folder-list">';

        // Add "All Files" option
        echo '<li class="' . ( ! $current_folder ? 'current ' : '' ) . 'all-files">';
        echo '<a href="' . esc_url( admin_url( 'upload.php' ) ) . '"' . ( ! $current_folder ? ' aria-current="page"' : '' ) . '>';
        echo $this->icon( 'files' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup.
        echo '<span class="apexfo-name">' . esc_html__( 'All files', 'apex-folders' ) . '</span>';
        echo '<span class="apexfo-count">' . intval( $total_files ) . '</span>';
        echo '</a></li>';

        // Add Unassigned folder immediately after All Files
        if ( $unassigned_folder ) {
            $this->render_folder_item( $unassigned_folder, $current_folder, 'unassigned-folder' );
        }

        // Section heading for the user's folders
        echo '<li class="apexfo-section">' . esc_html__( 'Folders', 'apex-folders' ) . '</li>';

        if ( empty( $parent_folders ) ) {
            echo '<li class="apexfo-empty">' . esc_html__( 'No folders yet. Add one below, then drag files onto it.', 'apex-folders' ) . '</li>';
        }

        // Add parent folders and their children
        foreach ( $parent_folders as $folder ) {
            $has_children = ! empty( $child_folders[ $folder->term_id ] );

            $total = (int) $folder->count;
            if ( $has_children ) {
                foreach ( $child_folders[ $folder->term_id ] as $child ) {
                    $total += (int) $child->count;
                }
            }

            $this->render_folder_item( $folder, $current_folder, 'custom-folder parent-folder' . ( $has_children ? ' has-children' : '' ), 0, $total );

            // Display children if any
            if ( $has_children ) {
                foreach ( $child_folders[ $folder->term_id ] as $child ) {
                    $this->render_folder_item( $child, $current_folder, 'custom-folder child-folder', $folder->term_id );
                }
            }
        }

        echo '</ul>';

        echo '<button type="button" class="apexfo-add add-new-folder"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add folder', 'apex-folders' ) . '</button>';

        echo '<p class="apexfo-hint">' . esc_html__( 'Drag a file onto a folder to move it. Use Bulk select to move several at once.', 'apex-folders' ) . '</p>';

        echo '<p class="apexfo-credit">';
        echo '<span class="apexfo-credit-name">apex/folders</span> ';
        echo esc_html__( 'is a free tool from Apex by Mountain Air Web.', 'apex-folders' ) . ' ';
        echo '<a href="' . esc_url( $apex_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Meet the other Apex tools', 'apex-folders' ) . ' <span aria-hidden="true">&rarr;</span></a>';
        echo '</p>';

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
            'viewNonce' => wp_create_nonce( 'apex_folder_view' ),
            'parentFolders' => array_map( function( $folder ) {
                return array(
                    'term_id' => $folder->term_id,
                    'name' => $folder->name
                );
            }, $parent_folders )
        );
        
        wp_enqueue_script( 'apex-folder-management', APEX_FOLDERS_PLUGIN_URL . 'assets/js/folder-management.js', array( 'jquery', 'jquery-ui-dialog' ), APEX_FOLDERS_VERSION, true );
        wp_enqueue_script( 'apex-folder-counts', APEX_FOLDERS_PLUGIN_URL . 'assets/js/folder-counts.js', array( 'jquery' ), APEX_FOLDERS_VERSION, true );
        
        wp_localize_script( 'apex-folder-management', 'apexFolderData', $folders_data );
        wp_localize_script( 'apex-folder-counts', 'apexFolderData', $folders_data );
        
        // Add translatable strings for JavaScript
        wp_localize_script( 'apex-folder-management', 'apexFoldersL10n', array(
            'createFolderTitle'      => __( 'Create New Folder', 'apex-folders' ),
            'createFolder'           => __( 'Create Folder', 'apex-folders' ),
            'folderName'             => __( 'Folder Name:', 'apex-folders' ),
            'parentFolderOptional'   => __( 'Parent Folder (optional):', 'apex-folders' ),
            'noParent'               => __( 'None (top level)', 'apex-folders' ),
            'createSubfolderTitle'   => __( 'Create New Subfolder', 'apex-folders' ),
            'createSubfolder'        => __( 'Create Subfolder', 'apex-folders' ),
            'subfolderName'          => __( 'Subfolder Name:', 'apex-folders' ),
            'parentFolder'           => __( 'Parent folder:', 'apex-folders' ),
            'renameFolderTitle'      => __( 'Rename Folder', 'apex-folders' ),
            'newName'                => __( 'New Name:', 'apex-folders' ),
            'cancel'                 => __( 'Cancel', 'apex-folders' ),
            'save'                   => __( 'Save', 'apex-folders' ),
            /* translators: %s: folder name */
            'confirmDelete'          => __( 'Are you sure you want to delete the folder "%s"?', 'apex-folders' ),
            'confirmDeleteNote'      => __( 'Files in this folder will be moved to the Unassigned folder.', 'apex-folders' ),
            'errorCreatingFolder'    => __( 'Error creating folder:', 'apex-folders' ),
            'errorCreatingSubfolder' => __( 'Error creating subfolder:', 'apex-folders' ),
            'errorRenamingFolder'    => __( 'Error renaming folder:', 'apex-folders' ),
            'errorDeletingFolder'    => __( 'Error deleting folder:', 'apex-folders' ),
            'unknownError'           => __( 'Unknown error', 'apex-folders' ),
            'networkError'           => __( 'Network error. Please try again.', 'apex-folders' ),
            /* translators: %1$d: files in this folder, %2$d: files including subfolders */
            'countWithSubfolders'    => __( '%1$d in this folder, %2$d including subfolders', 'apex-folders' ),
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
        
        // The field must not be named after the taxonomy: WordPress would then also
        // save the submitted folder ID as if it were a folder name.
        $dropdown = '<select name="attachments[' . $post->ID . '][apex_folder_id]" id="attachments-' . $post->ID . '-apex_folder">';
        
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
        // If no folder specified, return
        if ( ! isset( $attachment['apex_folder_id'] ) ) {
            return $post;
        }

        // Verify nonce
        $nonce_name = 'apex_folder_nonce_' . $post['ID'];
        if ( !isset($_POST[$nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), 'save_apex_folder_' . $post['ID']) ) {
            return $post;
        }

        $folder_id = absint( $attachment['apex_folder_id'] );

        // If no folder, "0" or a folder that no longer exists is selected, assign to Unassigned folder
        if ( ! $folder_id || ! term_exists( $folder_id, 'apex_folder' ) ) {
            $folder_id = (int) apex_folders_get_unassigned_id();
        }

        if ( $folder_id ) {
            // Set the term to the selected folder
            wp_set_object_terms( $post['ID'], array( $folder_id ), 'apex_folder', false );
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
