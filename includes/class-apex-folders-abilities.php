<?php
/**
 * Apex Folders Abilities
 *
 * Registers folder operations with the WordPress Abilities API (6.9+), so
 * AI agents and MCP clients connected to the site can organize the
 * Media Library themselves.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for the Abilities API integration
 */
class APEX_FOLDERS_Abilities {

    /**
     * Constructor
     */
    public function __construct() {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
    }

    /**
     * Register the category the abilities live in
     */
    public function register_category() {
        wp_register_ability_category( 'apex-folders', array(
            'label'       => __( 'Media folders', 'apex-folders' ),
            'description' => __( 'Organize the Media Library into folders with Apex Folders.', 'apex-folders' ),
        ) );
    }

    /**
     * Whether the current user may read folders
     *
     * @return bool
     */
    public static function can_read() {
        return current_user_can( 'upload_files' );
    }

    /**
     * Whether the current user may change folders
     *
     * @return bool
     */
    public static function can_write() {
        return current_user_can( 'upload_files' );
    }

    /**
     * Register the abilities
     */
    public function register_abilities() {
        $folder_schema = array(
            'type'       => 'object',
            'properties' => array(
                'id'     => array( 'type' => 'integer' ),
                'name'   => array( 'type' => 'string' ),
                'slug'   => array( 'type' => 'string' ),
                'parent' => array( 'type' => 'integer', 'description' => __( 'Parent folder ID, or 0 for a top-level folder.', 'apex-folders' ) ),
                'count'  => array( 'type' => 'integer' ),
            ),
        );

        wp_register_ability( 'apex-folders/list-folders', array(
            'label'               => __( 'List media folders', 'apex-folders' ),
            'description'         => __( 'Lists every Media Library folder with its ID, slug, parent and file count. The "unassigned" folder holds files that are not in any folder.', 'apex-folders' ),
            'category'            => 'apex-folders',
            'output_schema'       => array(
                'type'  => 'array',
                'items' => $folder_schema,
            ),
            'execute_callback'    => array( $this, 'list_folders' ),
            'permission_callback' => array( __CLASS__, 'can_read' ),
            'meta'                => array(
                'public'       => true,
                'show_in_rest' => true,
                'annotations'  => array( 'readonly' => true, 'idempotent' => true ),
            ),
        ) );

        wp_register_ability( 'apex-folders/get-unassigned-files', array(
            'label'               => __( 'Get files not in a folder', 'apex-folders' ),
            'description'         => __( 'Returns Media Library files that are still in the Unassigned folder, with the metadata useful for deciding where they belong. Paginated.', 'apex-folders' ),
            'category'            => 'apex-folders',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
                    'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'total' => array( 'type' => 'integer' ),
                    'pages' => array( 'type' => 'integer' ),
                    'files' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'id'          => array( 'type' => 'integer' ),
                                'filename'    => array( 'type' => 'string' ),
                                'title'       => array( 'type' => 'string' ),
                                'alt'         => array( 'type' => 'string' ),
                                'caption'     => array( 'type' => 'string' ),
                                'type'        => array( 'type' => 'string' ),
                                'uploaded'    => array( 'type' => 'string' ),
                                'attached_to' => array( 'type' => 'string' ),
                                'url'         => array( 'type' => 'string' ),
                            ),
                        ),
                    ),
                ),
            ),
            'execute_callback'    => array( $this, 'get_unassigned_files' ),
            'permission_callback' => array( __CLASS__, 'can_read' ),
            'meta'                => array(
                'public'       => true,
                'show_in_rest' => true,
                'annotations'  => array( 'readonly' => true, 'idempotent' => true ),
            ),
        ) );

        wp_register_ability( 'apex-folders/create-folder', array(
            'label'               => __( 'Create a media folder', 'apex-folders' ),
            'description'         => __( 'Creates a Media Library folder. Give a parent ID to create a subfolder; only one level of subfolders is supported.', 'apex-folders' ),
            'category'            => 'apex-folders',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'name'   => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 60 ),
                    'parent' => array( 'type' => 'integer', 'default' => 0 ),
                ),
                'required'   => array( 'name' ),
            ),
            'output_schema'       => $folder_schema,
            'execute_callback'    => array( $this, 'create_folder' ),
            'permission_callback' => array( __CLASS__, 'can_write' ),
            'meta'                => array(
                'public'       => true,
                'show_in_rest' => true,
                'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
            ),
        ) );

        wp_register_ability( 'apex-folders/move-files', array(
            'label'               => __( 'Move files into a folder', 'apex-folders' ),
            'description'         => __( 'Moves Media Library files into a folder by ID. A file is in exactly one folder, so this replaces its current folder. Files never move on disk and their URLs do not change.', 'apex-folders' ),
            'category'            => 'apex-folders',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'ids'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'minItems' => 1, 'maxItems' => 200 ),
                    'folder' => array( 'type' => 'integer', 'description' => __( 'Target folder ID.', 'apex-folders' ) ),
                ),
                'required'   => array( 'ids', 'folder' ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'moved'  => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
                    'failed' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
                ),
            ),
            'execute_callback'    => array( $this, 'move_files' ),
            'permission_callback' => array( __CLASS__, 'can_write' ),
            'meta'                => array(
                'public'       => true,
                'show_in_rest' => true,
                'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
            ),
        ) );
    }

    /**
     * Ability: list folders
     *
     * @return array
     */
    public function list_folders() {
        $folders = get_terms( array(
            'taxonomy'   => 'apex_folder',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $folders ) ) {
            return array();
        }

        $list = array();
        foreach ( $folders as $folder ) {
            $list[] = array(
                'id'     => (int) $folder->term_id,
                'name'   => $folder->name,
                'slug'   => $folder->slug,
                'parent' => (int) $folder->parent,
                'count'  => (int) $folder->count,
            );
        }

        return $list;
    }

    /**
     * Ability: files in the Unassigned folder
     *
     * @param array $input Input.
     * @return array
     */
    public function get_unassigned_files( $input ) {
        $page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
        $per_page = isset( $input['per_page'] ) ? min( 100, max( 1, (int) $input['per_page'] ) ) : 50;

        $query = new WP_Query( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'apex_folder',
                    'field'    => 'term_id',
                    'terms'    => (int) apex_folders_get_unassigned_id(),
                ),
            ),
        ) );

        $files = array();
        foreach ( $query->posts as $post ) {
            $file   = get_attached_file( $post->ID );
            $parent = $post->post_parent ? get_post( $post->post_parent ) : null;

            $files[] = array(
                'id'          => (int) $post->ID,
                'filename'    => $file ? wp_basename( $file ) : '',
                'title'       => $post->post_title,
                'alt'         => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
                'caption'     => $post->post_excerpt,
                'type'        => $post->post_mime_type,
                'uploaded'    => get_the_date( 'Y-m-d', $post ),
                'attached_to' => $parent ? $parent->post_title : '',
                'url'         => (string) wp_get_attachment_url( $post->ID ),
            );
        }

        return array(
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'files' => $files,
        );
    }

    /**
     * Ability: create a folder
     *
     * @param array $input Input.
     * @return array|WP_Error
     */
    public function create_folder( $input ) {
        $result = APEX_FOLDERS_Utilities::create_folder( isset( $input['name'] ) ? $input['name'] : '', isset( $input['parent'] ) ? $input['parent'] : 0 );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $term = get_term( $result, 'apex_folder' );

        return array(
            'id'     => (int) $term->term_id,
            'name'   => $term->name,
            'slug'   => $term->slug,
            'parent' => (int) $term->parent,
            'count'  => (int) $term->count,
        );
    }

    /**
     * Ability: move files
     *
     * @param array $input Input.
     * @return array|WP_Error
     */
    public function move_files( $input ) {
        return APEX_FOLDERS_Utilities::move_items( isset( $input['ids'] ) ? (array) $input['ids'] : array(), isset( $input['folder'] ) ? (int) $input['folder'] : 0 );
    }
}

// Initialize the class
new APEX_FOLDERS_Abilities();
