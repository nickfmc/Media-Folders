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
        // Load the uploader integration wherever the media grid/modal is used
        add_action( 'wp_enqueue_media', array( $this, 'enqueue_uploader_script' ) );

        // The standalone "Add Media File" screen does not call wp_enqueue_media()
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_on_media_new' ) );

        // Add folder selector to uploader UI
        add_action( 'pre-upload-ui', array( $this, 'render_folder_selector' ) );

        // Assign folder to newly uploaded attachment
        add_action( 'add_attachment', array( $this, 'assign_folder_to_attachment' ) );

        // Tell the media library which folder each attachment is in
        add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_folder_to_js' ), 10, 2 );
    }

    /**
     * Enqueue the uploader script on the standalone upload screen
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_on_media_new( $hook ) {
        if ( 'media-new.php' === $hook ) {
            $this->enqueue_uploader_script();
        }
    }

    /**
     * Enqueue the script that sends the selected folder with each upload
     */
    public function enqueue_uploader_script() {
        // It must run after the core uploader is defined and before any uploader is created
        $dependencies = array( 'jquery' );
        if ( wp_script_is( 'wp-plupload', 'enqueued' ) || did_action( 'wp_enqueue_media' ) ) {
            $dependencies[] = 'media-views';
        }

        wp_enqueue_script(
            'apex-folders-uploader',
            APEX_FOLDERS_PLUGIN_URL . 'assets/js/media-uploader.js',
            $dependencies,
            APEX_FOLDERS_VERSION,
            true
        );

        // Uploads default to the folder being viewed
        $current_folder_id = 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading the GET parameter for UI purposes
        if ( is_admin() && isset( $_GET['apex_folder'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading the GET parameter for UI purposes
            $term = get_term_by( 'slug', sanitize_text_field( wp_unslash( $_GET['apex_folder'] ) ), 'apex_folder' );
            if ( $term && ! is_wp_error( $term ) ) {
                $current_folder_id = (int) $term->term_id;
            }
        }

        // Pass data to the script
        wp_localize_script(
            'apex-folders-uploader',
            'apexfoMediaFolderUploaderData',
            array(
                'currentFolderId' => $current_folder_id,
                'unassignedId'    => (int) apex_folders_get_unassigned_id(),
            )
        );
    }

    /**
     * Print the folder selector inside the upload UI
     *
     * Runs in the media modal's uploader template and on the standalone upload screen.
     */
    public function render_folder_selector() {
        // Get folders for the dropdown
        $folders = get_terms( array(
            'taxonomy'   => 'apex_folder',
            'hide_empty' => false,
        ) );

        // Only proceed if we have folders
        if ( empty( $folders ) || is_wp_error( $folders ) ) {
            return;
        }

        $unassigned_id = apex_folders_get_unassigned_id();

        echo '<div class="apex-folder-select-container">';
        echo '<label for="apex-folder-select">' . esc_html__( 'Upload to folder', 'apex-folders' ) . '</label>';
        echo '<select id="apex-folder-select" class="apex-folder-select" name="apex-folder-select">';
        echo '<option value="' . esc_attr( $unassigned_id ) . '">' . esc_html__( 'Unassigned', 'apex-folders' ) . '</option>';

        foreach ( $folders as $folder ) {
            // Skip the Unassigned folder since we already added it as the first option
            if ( (int) $folder->term_id !== (int) $unassigned_id ) {
                printf(
                    '<option value="%s">%s</option>',
                    esc_attr( $folder->term_id ),
                    esc_html( $folder->name )
                );
            }
        }

        echo '</select>';
        echo '</div>';
    }

    /**
     * Get the folder the current upload request asked for
     *
     * @return int Folder term ID, or 0 if none was requested or it does not exist.
     */
    private function get_requested_folder_id() {
        // apex_folder_id is sent by the uploader script, apex-folder-select by the browser uploader form.
        foreach ( array( 'apex_folder_id', 'apex-folder-select' ) as $key ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core verifies the upload nonce before the attachment is created.
            if ( ! isset( $_POST[ $key ] ) ) {
                continue;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $folder_id = absint( wp_unslash( $_POST[ $key ] ) );
            if ( $folder_id && term_exists( $folder_id, 'apex_folder' ) ) {
                return $folder_id;
            }
        }

        return 0;
    }

    /**
     * Assign folder to newly uploaded attachment
     *
     * The folder travels with the upload request itself, so every file in a
     * multi-file upload is assigned in the same request that creates it.
     *
     * @param int $attachment_id The attachment ID.
     */
    public function assign_folder_to_attachment( $attachment_id ) {
        $folder_id = $this->get_requested_folder_id();

        // If no folder was requested, use unassigned
        if ( ! $folder_id ) {
            $folder_id = (int) apex_folders_get_unassigned_id();
        }

        if ( $folder_id ) {
            wp_set_object_terms( $attachment_id, array( $folder_id ), 'apex_folder', false );
        }
    }

    /**
     * Add the folder ID to the attachment data used by the media library
     *
     * @param array   $response   Attachment data for JavaScript.
     * @param WP_Post $attachment Attachment post.
     * @return array Modified attachment data.
     */
    public function add_folder_to_js( $response, $attachment ) {
        $folders = get_the_terms( $attachment->ID, 'apex_folder' );

        $response['apexFolder'] = ( $folders && ! is_wp_error( $folders ) ) ? (int) $folders[0]->term_id : 0;

        return $response;
    }
}

// Initialize the class
new APEX_FOLDERS_Upload();
