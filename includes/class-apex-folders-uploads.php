<?php
/**
 * Media Folders Upload
 *
 * Handles folder assignment during upload process.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for upload integration
 */
class APEX_FOLDERS_Upload {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add folder selector to uploader UI
        add_action( 'admin_enqueue_scripts', array( $this, 'add_folder_selector' ) );
        
        // Handle file upload with folder assignment
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'handle_upload' ) );
        
        // Assign folder to newly uploaded attachment
        add_action( 'add_attachment', array( $this, 'assign_folder_to_attachment' ) );
        
        // Add folder info to plupload parameters
        add_filter( 'plupload_init', array( $this, 'modify_plupload_config' ) );
    }
    
    /**
     * Add folder selection to media uploader
     */
    public function add_folder_selector() {
        // Only run in admin
        if ( ! is_admin() ) {
            return;
        }
        
        // Get folders for the dropdown
        $folders = get_terms( array(
            'taxonomy'   => 'apex_folder',
            'hide_empty' => false,
        ) );
        
        // Only proceed if we have folders
        if ( empty( $folders ) ) {
            return;
        }

        $unassigned_id = apex_folders_get_unassigned_id();
        
        // Build the dropdown HTML
        $dropdown_html  = '<div class="apex-folder-select-container upload-filter-section">';
        $dropdown_html .= '<label for="apex-folder-select">' . esc_html__( 'Folder:', 'apex-folders' ) . '</label>';
        $dropdown_html .= '<select id="apex-folder-select" name="apex-folder-select">';
        $dropdown_html .= '<option value="' . esc_attr( $unassigned_id ) . '">' . esc_html__( 'Unassigned', 'apex-folders' ) . '</option>';
        
        foreach ( $folders as $folder ) {
            // Skip the Unassigned folder since we already added it as the first option
            if ( $folder->term_id != $unassigned_id ) {
                $dropdown_html .= sprintf(
                    '<option value="%s">%s</option>',
                    esc_attr( $folder->term_id ),
                    esc_html( $folder->name )
                );
            }
        }
        
        $dropdown_html .= '</select>';
        $dropdown_html .= '</div>';
        
        // Enqueue the script
        wp_enqueue_script(
            'apex-folders-uploader',
            APEX_FOLDERS_PLUGIN_URL . 'assets/js/media-uploader.js',
            array( 'jquery' ),
            APEX_FOLDERS_VERSION,
            true
        );
        
        // Pass data to the script
        wp_localize_script(
            'apex-folders-uploader',
            'MediaFolderUploaderData',
            array(
                'currentFolder' => isset( $_GET['apex_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['apex_folder'] ) ) : null,
                'dropdownHtml'  => $dropdown_html,
                'folderNonce'   => wp_create_nonce( 'APEX_FOLDERS_nonce' ),
                'unassignedId'  => $unassigned_id,
                'strings'       => array(
                    'selectFolder' => esc_html__( 'Select folder', 'apex-folders' ),
                    'uploading'    => esc_html__( 'Uploading to', 'apex-folders' ),
                ),
            )
        );
    }
    
    /**
     * Process upload to get folder ID
     *
     * @param array $file File being uploaded.
     * @return array File information.
     */
    public function handle_upload( $file ) {
        // For debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // translators: %s: file name being uploaded.
            error_log( sprintf( esc_html__( 'Upload handler called for file: %s', 'apex-folders' ), sanitize_text_field( $file['name'] ) ) );
        }
        
        // Check all possible sources for the folder ID
        $folder_id = 0;
        $source    = 'unknown';
        
        // Check various parameter names
        if ( isset( $_POST['apex_folder_id'] ) ) {
            $folder_id = intval( $_POST['apex_folder_id'] );
            $source    = 'POST apex_folder_id';
        } elseif ( isset( $_POST['apex-folder-select'] ) ) {
            $folder_id = intval( $_POST['apex-folder-select'] );
            $source    = 'POST apex-folder-select';
        }
        
        // If not in POST, try REQUEST (which combines GET, POST, COOKIE)
        if ( ! $folder_id ) {
            if ( isset( $_REQUEST['apex_folder_id'] ) ) {
                $folder_id = intval( $_REQUEST['apex_folder_id'] );
                $source    = 'REQUEST apex_folder_id';
            } elseif ( isset( $_REQUEST['apex-folder-select'] ) ) {
                $folder_id = intval( $_REQUEST['apex-folder-select'] );
                $source    = 'REQUEST apex-folder-select';
            }
        }
        
        // Cookie fallback with sanitization
        if ( ! $folder_id && isset( $_COOKIE['apex_folder_upload_id'] ) ) {
            $folder_id = intval( $_COOKIE['apex_folder_upload_id'] );
            $source    = 'COOKIE';
        }
        
        // If we still don't have a folder ID, use unassigned
        if ( ! $folder_id ) {
            $folder_id = apex_folders_get_unassigned_id();
            $source    = 'DEFAULT (unassigned)';
        }
        
        // translators: %1$d: folder ID, %2$s: source of folder ID, %3$s: file name.
        error_log( sprintf( esc_html__( 'Found folder ID %1$d from %2$s for file: %3$s', 'apex-folders' ), $folder_id, $source, $file['name'] ) );
        
        // Store this in a transient for later retrieval
        set_transient( 'apex_folder_for_' . sanitize_file_name( $file['name'] ), $folder_id, 5 * MINUTE_IN_SECONDS );
        
        // Also store in a global request variable to ensure it's available elsewhere
        $_REQUEST['apex_folder_id'] = $folder_id;
        
        return $file;
    }
    
    /**
     * Assign folder to newly uploaded attachment
     *
     * @param int $attachment_id The attachment ID.
     */
    public function assign_folder_to_attachment( $attachment_id ) {
        // Get the attachment
        $attachment = get_post( $attachment_id );
        if ( ! $attachment ) {
            return;
        }
        
        // Get the original filename
        $filename = basename( get_attached_file( $attachment_id ) );
        
        // translators: %1$d: attachment ID, %2$s: filename.
        error_log( sprintf( esc_html__( 'Processing new attachment ID %1$d (file: %2$s)', 'apex-folders' ), $attachment_id, $filename ) );
        
        // Try multiple approaches to get the folder ID, in order of preference:
        $folder_id = 0;
        $source    = 'unknown';
        
        // 1. Check POST data directly with additional fallbacks
        if ( isset( $_POST['apex_folder_id'] ) ) {
            $folder_id = intval( $_POST['apex_folder_id'] );
            $source    = 'POST apex_folder_id';
        } elseif ( isset( $_POST['apex-folder-select'] ) ) {
            $folder_id = intval( $_POST['apex-folder-select'] );
            $source    = 'POST select dropdown';
        }
        
        // 2. If that failed, try the transient
        if ( ! $folder_id ) {
            $folder_id = get_transient( 'apex_folder_for_' . sanitize_file_name( $filename ) );
            if ( $folder_id ) {
                $source = 'transient';
            }
        }
        
        // 3. Try the cookie as a backup
        if ( ! $folder_id && isset( $_COOKIE['apex_folder_upload_id'] ) ) {
            $folder_id = intval( $_COOKIE['apex_folder_upload_id'] );
            $source    = 'cookie';
        }
        
        // 4. If we still have no folder, use the URL query parameter if present
        if ( ! $folder_id && isset( $_GET['apex_folder'] ) ) {
            // Convert slug to ID
            $term = get_term_by( 'slug', sanitize_text_field( wp_unslash( $_GET['apex_folder'] ) ), 'apex_folder' );
            if ( $term && ! is_wp_error( $term ) ) {
                $folder_id = $term->term_id;
                $source    = 'URL param';
            }
        }
        
        // If still no folder, use unassigned
        if ( ! $folder_id ) {
            $folder_id = apex_folders_get_unassigned_id();
            $source    = 'default (unassigned)';
        }
        
        // translators: %1$d: folder ID, %2$s: source of folder ID, %3$d: attachment ID.
        error_log( sprintf( esc_html__( 'Folder ID %1$d found from %2$s for attachment ID %3$d', 'apex-folders' ), $folder_id, $source, $attachment_id ) );
        
        // If we have a valid folder ID and it exists, assign the attachment to it
        if ( $folder_id > 0 ) {
            // Verify the folder exists
            $term = get_term( $folder_id, 'apex_folder' );
            if ( ! is_wp_error( $term ) && $term ) {
                // Assign to folder - use both methods for redundancy
                wp_set_object_terms( $attachment_id, array( $folder_id ), 'apex_folder', false );
                
                // translators: %1$d: attachment ID, %2$d: folder ID, %3$s: folder name.
                error_log( sprintf( esc_html__( 'Assigned attachment ID %1$d to folder ID %2$d (%3$s)', 'apex-folders' ), $attachment_id, $folder_id, $term->name ) );
                
                // Also use direct database access as a backup approach
                global $wpdb;
                $tt_id = $wpdb->get_var( 
                    $wpdb->prepare(
                        "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
                         WHERE term_id = %d AND taxonomy = 'apex_folder'",
                        $folder_id
                    )
                );
                
                if ( $tt_id ) {
                    // Ensure there's no existing relationship
                    $wpdb->delete(
                        $wpdb->term_relationships,
                        array(
                            'object_id'        => $attachment_id,
                            'term_taxonomy_id' => $tt_id,
                        )
                    );
                    
                    // Insert direct relationship
                    $wpdb->insert(
                        $wpdb->term_relationships,
                        array(
                            'object_id'        => $attachment_id,
                            'term_taxonomy_id' => $tt_id,
                            'term_order'       => 0,
                        )
                    );
                    
                    // translators: %1$d: attachment ID, %2$d: folder ID.
                    error_log( sprintf( esc_html__( 'Added direct database relationship between attachment %1$d and folder %2$d', 'apex-folders' ), $attachment_id, $folder_id ) );
                }
                
                // Update term counts
                apex_folders_update_counts();
            } else {
                // Folder doesn't exist, use Unassigned
                $unassigned_id = apex_folders_get_unassigned_id();
                wp_set_object_terms( $attachment_id, array( $unassigned_id ), 'apex_folder', false );
                
                // translators: %1$d: folder ID that doesn't exist, %2$d: unassigned folder ID.
                error_log( sprintf( esc_html__( 'Folder ID %1$d doesn\'t exist, using Unassigned (%2$d)', 'apex-folders' ), $folder_id, $unassigned_id ) );
            }
        }
        
        // Clean up the transient
        delete_transient( 'apex_folder_for_' . sanitize_file_name( $filename ) );
    }
    
    /**
     * Add folder ID to plupload configuration
     *
     * @param array $plupload_init Plupload config.
     * @return array Modified config.
     */
    public function modify_plupload_config( $plupload_init ) {
        // Get the folder ID from multiple possible sources
        $folder_id = 0;
        $source    = 'DEFAULT';
        
        // First try the direct POST/GET variables
        if ( isset( $_REQUEST['apex_folder_id'] ) ) {
            $folder_id = intval( $_REQUEST['apex_folder_id'] );
            $source    = 'REQUEST';
        } elseif ( isset( $_REQUEST['apex-folder-select'] ) ) { // Second, try from the dropdown in the uploader
            $folder_id = intval( $_REQUEST['apex-folder-select'] );
            $source    = 'SELECT';
        } elseif ( isset( $_COOKIE['apex_folder_upload_id'] ) ) { // Finally check cookie
            $folder_id = intval( $_COOKIE['apex_folder_upload_id'] );
            $source    = 'COOKIE';
        }
        
        // If still no folder ID, use the unassigned folder
        if ( ! $folder_id ) {
            $folder_id = apex_folders_get_unassigned_id();
        }
        
        // Add our custom folder param
        $plupload_init['multipart_params']['apex_folder_id'] = $folder_id;
        
        // Log what we're doing for debugging
        // translators: %1$d: folder ID, %2$s: source of folder ID.
        error_log( sprintf( esc_html__( 'Setting plupload apex_folder_id to: %1$d (Source: %2$s)', 'apex-folders' ), $folder_id, $source ) );
        
        return $plupload_init;
    }
}

// Initialize the class
new APEX_FOLDERS_Upload();