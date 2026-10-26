<?php
/**
 * Apex Folders AI
 *
 * One-click auto-organize, built on the AI Client that ships with
 * WordPress 7.0 and the provider the site owner connected under
 * Settings > Connectors. The plugin never sees an API key.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for the AI auto-organize feature
 */
class APEX_FOLDERS_AI {

    /**
     * REST namespace
     */
    const REST_NAMESPACE = 'apex-folders/v1';

    /**
     * Option holding the last apply, for Undo
     */
    const UNDO_OPTION = 'apex_folders_last_organize';

    /**
     * Files sent to the AI per request
     */
    const BATCH_SIZE = 20;

    /**
     * Whether the site's AI Client reported a provider this request
     *
     * @var bool|null
     */
    private static $configured = null;

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Whether this WordPress has the AI Client (7.0+) and allows AI
     *
     * @return bool
     */
    public static function is_available() {
        return function_exists( 'wp_ai_client_prompt' ) && ( ! function_exists( 'wp_supports_ai' ) || wp_supports_ai() );
    }

    /**
     * Whether a provider that can generate text is connected
     *
     * This asks the AI Client, which makes no API call for the check.
     *
     * @return bool
     */
    public static function is_configured() {
        if ( null !== self::$configured ) {
            return self::$configured;
        }

        self::$configured = false;

        if ( self::is_available() ) {
            try {
                self::$configured = (bool) wp_ai_client_prompt( 'Hello' )->is_supported_for_text_generation();
            } catch ( Throwable $e ) {
                self::$configured = false;
            }
        }

        /**
         * Filters whether an AI provider is considered connected.
         *
         * @param bool $configured Whether the AI Client reported a usable text model.
         */
        self::$configured = (bool) apply_filters( 'apex_folders_ai_configured', self::$configured );

        return self::$configured;
    }

    /**
     * Whether the current user may run auto-organize
     *
     * It spends the site's AI credits, so administrators only unless filtered.
     *
     * @return bool
     */
    public static function can_organize() {
        /**
         * Filters the capability needed to run AI auto-organize.
         *
         * @param string $capability Capability name. Default 'manage_options'.
         */
        return current_user_can( apply_filters( 'apex_folders_organize_capability', 'manage_options' ) );
    }

    /**
     * The Settings > Connectors screen
     *
     * @return string
     */
    public static function connectors_url() {
        return admin_url( 'options-connectors.php' );
    }

    /**
     * Enqueue the auto-organize script and its strings
     */
    public static function enqueue_script() {
        wp_enqueue_script(
            'apex-folders-organize',
            APEX_FOLDERS_PLUGIN_URL . 'assets/js/auto-organize.js',
            array( 'jquery', 'jquery-ui-dialog', 'wp-api-fetch' ),
            APEX_FOLDERS_VERSION,
            true
        );

        wp_localize_script(
            'apex-folders-organize',
            'apexfoOrganize',
            array(
                'restBase'      => self::REST_NAMESPACE . '/organize',
                'batchSize'     => self::BATCH_SIZE,
                'connectorsUrl' => self::connectors_url(),
                'unassignedId'  => (int) apex_folders_get_unassigned_id(),
                'strings'       => array(
                    'title'            => __( 'Auto-organize with AI', 'apex-folders' ),
                    'intro'            => __( 'The AI provider connected to this site reads each file\'s name, title, alt text, caption and a small thumbnail, then proposes folders. Nothing changes until you apply the plan.', 'apex-folders' ),
                    'scopeUnassigned'  => __( 'Unassigned files only', 'apex-folders' ),
                    'scopeAll'         => __( 'Every file in the library', 'apex-folders' ),
                    /* translators: %1$s: number of files, %2$s: number of images */
                    'filesSummary'     => __( '%1$s files, %2$s of them images', 'apex-folders' ),
                    /* translators: %1$s: low token estimate, %2$s: high token estimate */
                    'tokensEstimate'   => __( 'About %1$s to %2$s tokens', 'apex-folders' ),
                    /* translators: %1$s: low cost, %2$s: high cost */
                    'costEstimate'     => __( 'Roughly %1$s to %2$s, depending on the model your provider picks. Your provider bills you directly; this is an estimate.', 'apex-folders' ),
                    'nothingToDo'      => __( 'There are no files to organize.', 'apex-folders' ),
                    'notConfigured'    => __( 'No AI provider is connected yet.', 'apex-folders' ),
                    'connectProvider'  => __( 'Connect a provider in Settings → Connectors', 'apex-folders' ),
                    'start'            => __( 'Analyse files', 'apex-folders' ),
                    'cancel'           => __( 'Cancel', 'apex-folders' ),
                    'close'            => __( 'Close', 'apex-folders' ),
                    /* translators: %1$s: files analysed so far, %2$s: total files */
                    'progress'         => __( 'Analysed %1$s of %2$s files', 'apex-folders' ),
                    /* translators: %1$s: tokens used, %2$s: model name */
                    'usage'            => __( '%1$s tokens so far · %2$s', 'apex-folders' ),
                    /* translators: %1$s: cost so far, %2$s: projected cost */
                    'costSoFar'        => __( 'About %1$s so far, %2$s projected', 'apex-folders' ),
                    'reviewTitle'      => __( 'Review the plan', 'apex-folders' ),
                    /* translators: %1$s: number of files to move, %2$s: number of folders, %3$s: number of new folders */
                    'reviewSummary'    => __( '%1$s files into %2$s folders (%3$s new)', 'apex-folders' ),
                    'newFolder'        => __( 'New', 'apex-folders' ),
                    'subfolderOf'      => __( 'in', 'apex-folders' ),
                    'skipGroup'        => __( 'Skip', 'apex-folders' ),
                    'staysUnassigned'  => __( 'Left in Unassigned: nothing fit well enough', 'apex-folders' ),
                    'adjustLabel'      => __( 'Anything to change?', 'apex-folders' ),
                    'adjustPlaceholder'=> __( 'For example: put every PDF in Documents, and do not create a Screenshots folder.', 'apex-folders' ),
                    'replan'           => __( 'Re-plan', 'apex-folders' ),
                    'apply'            => __( 'Apply', 'apex-folders' ),
                    'applying'         => __( 'Applying…', 'apex-folders' ),
                    'doneTitle'        => __( 'Done', 'apex-folders' ),
                    /* translators: %1$s: number of files moved, %2$s: number of folders created */
                    'doneSummary'      => __( '%1$s files moved, %2$s folders created.', 'apex-folders' ),
                    'undo'             => __( 'Undo', 'apex-folders' ),
                    'undone'           => __( 'Folders restored.', 'apex-folders' ),
                    'error'            => __( 'Something went wrong.', 'apex-folders' ),
                    'noProposals'      => __( 'The AI could not place any of these files. Try adding an instruction and re-planning.', 'apex-folders' ),
                    'reload'           => __( 'Reload to see the folders', 'apex-folders' ),
                ),
            )
        );
    }

    /**
     * Register the REST routes behind the dialog
     */
    public function register_routes() {
        $permission = array( __CLASS__, 'rest_permission' );

        register_rest_route( self::REST_NAMESPACE, '/organize/estimate', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_estimate' ),
            'permission_callback' => $permission,
            'args'                => array(
                'scope' => array(
                    'type'    => 'string',
                    'enum'    => array( 'unassigned', 'all' ),
                    'default' => 'unassigned',
                ),
            ),
        ) );

        register_rest_route( self::REST_NAMESPACE, '/organize/plan', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_plan' ),
            'permission_callback' => $permission,
            'args'                => array(
                'ids' => array(
                    'type'     => 'array',
                    'items'    => array( 'type' => 'integer' ),
                    'required' => true,
                    'maxItems' => self::BATCH_SIZE,
                ),
                'instructions' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'known_new_folders' => array(
                    'type'    => 'array',
                    'items'   => array( 'type' => 'string' ),
                    'default' => array(),
                ),
            ),
        ) );

        register_rest_route( self::REST_NAMESPACE, '/organize/apply', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_apply' ),
            'permission_callback' => $permission,
        ) );

        register_rest_route( self::REST_NAMESPACE, '/organize/undo', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_undo' ),
            'permission_callback' => $permission,
        ) );
    }

    /**
     * REST permission: the feature must exist and the user must be allowed to spend on it
     *
     * @return bool|WP_Error
     */
    public static function rest_permission() {
        if ( ! self::is_available() ) {
            return new WP_Error( 'apex_folders_ai_unavailable', __( 'This WordPress does not have the AI Client.', 'apex-folders' ), array( 'status' => 501 ) );
        }

        if ( ! self::can_organize() ) {
            return new WP_Error( 'apex_folders_forbidden', __( 'You are not allowed to auto-organize this library.', 'apex-folders' ), array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * Attachment IDs in scope
     *
     * @param string $scope 'unassigned' or 'all'.
     * @return int[]
     */
    private function get_scope_ids( $scope ) {
        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( 'all' !== $scope ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'apex_folder',
                    'field'    => 'term_id',
                    'terms'    => (int) apex_folders_get_unassigned_id(),
                ),
            );
        }

        $query = new WP_Query( $args );

        return array_map( 'intval', $query->posts );
    }

    /**
     * Rough token and cost figures, so people know before they click
     *
     * @param int $files  Number of files.
     * @param int $images Number of images among them.
     * @return array
     */
    private function estimate( $files, $images ) {
        $batches = (int) ceil( $files / self::BATCH_SIZE );

        // Metadata per file, a 300px thumbnail per image, the folder list and instructions per batch, and the JSON answer
        $input  = ( $files * 90 ) + ( $images * 260 ) + ( $batches * 400 );
        $output = $files * 40;

        /**
         * Filters the per-million-token rates used for the cost estimate.
         *
         * @param array $rates { input_low, output_low, input_high, output_high } in US dollars per million tokens.
         */
        $rates = apply_filters( 'apex_folders_token_rates', array(
            'input_low'   => 0.15,
            'output_low'  => 0.60,
            'input_high'  => 3.00,
            'output_high' => 15.00,
        ) );

        return array(
            'tokens_low'  => (int) round( ( $input + $output ) * 0.8 ),
            'tokens_high' => (int) round( ( $input + $output ) * 1.3 ),
            'cost_low'    => round( ( $input * $rates['input_low'] + $output * $rates['output_low'] ) / 1000000, 4 ),
            'cost_high'   => round( ( $input * $rates['input_high'] + $output * $rates['output_high'] ) / 1000000, 4 ),
            'rates'       => $rates,
        );
    }

    /**
     * GET organize/estimate
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_estimate( $request ) {
        $ids    = $this->get_scope_ids( $request['scope'] );
        $images = 0;

        foreach ( $ids as $id ) {
            if ( wp_attachment_is_image( $id ) ) {
                $images++;
            }
        }

        return rest_ensure_response( array_merge(
            array(
                'configured'     => self::is_configured(),
                'connectors_url' => self::connectors_url(),
                'files'          => count( $ids ),
                'images'         => $images,
                'ids'            => $ids,
                'batch_size'     => self::BATCH_SIZE,
            ),
            $this->estimate( count( $ids ), $images )
        ) );
    }

    /**
     * Folder list sent to the AI and used to validate its answer
     *
     * @return array { slug => array( name, id, parent_slug ) }
     */
    private function get_folder_catalog() {
        $catalog       = array();
        $organized     = APEX_FOLDERS_Utilities::get_organized_folders();
        $unassigned_id = (int) apex_folders_get_unassigned_id();
        $by_id         = array();

        foreach ( $organized['all'] as $folder ) {
            $by_id[ (int) $folder->term_id ] = $folder;
        }

        foreach ( $organized['all'] as $folder ) {
            if ( (int) $folder->term_id === $unassigned_id ) {
                continue;
            }

            $parent_slug = ( $folder->parent && isset( $by_id[ (int) $folder->parent ] ) ) ? $by_id[ (int) $folder->parent ]->slug : null;

            $catalog[ $folder->slug ] = array(
                'id'     => (int) $folder->term_id,
                'name'   => $folder->name,
                'parent' => $parent_slug,
            );
        }

        return $catalog;
    }

    /**
     * One line of metadata per file for the prompt
     *
     * @param int $id Attachment ID.
     * @return array
     */
    private function describe_file( $id ) {
        $post = get_post( $id );
        if ( ! $post || 'attachment' !== $post->post_type ) {
            return null;
        }

        $file = get_attached_file( $id );

        $description = array(
            'id'       => (int) $id,
            'filename' => $file ? wp_basename( $file ) : '',
            'title'    => $post->post_title,
            'alt'      => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
            'caption'  => $post->post_excerpt,
            'type'     => $post->post_mime_type,
            'uploaded' => get_the_date( 'Y-m-d', $post ),
        );

        if ( $post->post_parent ) {
            $parent = get_post( $post->post_parent );
            if ( $parent ) {
                $description['attached_to'] = $parent->post_title;
            }
        }

        // Drop empty fields to keep the prompt short
        return array_filter( $description, function( $value ) {
            return '' !== $value && null !== $value;
        } );
    }

    /**
     * Path of a small version of an image to show the model
     *
     * @param int $id Attachment ID.
     * @return string|null
     */
    private function get_thumbnail_path( $id ) {
        if ( ! wp_attachment_is_image( $id ) ) {
            return null;
        }

        $file = get_attached_file( $id );
        if ( ! $file ) {
            return null;
        }

        $dir = dirname( $file );

        foreach ( array( 'medium', 'thumbnail' ) as $size ) {
            $intermediate = image_get_intermediate_size( $id, $size );
            if ( $intermediate && ! empty( $intermediate['file'] ) ) {
                $path = path_join( $dir, wp_basename( $intermediate['file'] ) );
                if ( file_exists( $path ) ) {
                    return $path;
                }
            }
        }

        // No small version: only send the original if it is small, so a 20 MB photo never goes out
        if ( file_exists( $file ) && filesize( $file ) < MB_IN_BYTES ) {
            return $file;
        }

        return null;
    }

    /**
     * JSON schema the model must answer with
     *
     * @return array
     */
    private function get_plan_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'files' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'         => array( 'type' => 'integer' ),
                            'folder'     => array( 'type' => array( 'string', 'null' ) ),
                            'new_folder' => array( 'type' => array( 'string', 'null' ) ),
                            'parent'     => array( 'type' => array( 'string', 'null' ) ),
                            'reason'     => array( 'type' => 'string' ),
                        ),
                        'required'   => array( 'id', 'folder', 'new_folder', 'parent', 'reason' ),
                        'additionalProperties' => false,
                    ),
                ),
            ),
            'required'   => array( 'files' ),
            'additionalProperties' => false,
        );
    }

    /**
     * POST organize/plan: ask the AI where one batch of files belongs
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function rest_plan( $request ) {
        $ids               = array_values( array_unique( array_map( 'absint', (array) $request['ids'] ) ) );
        $instructions      = sanitize_textarea_field( (string) $request['instructions'] );
        $known_new_folders = array_values( array_filter( array_map( 'sanitize_text_field', (array) $request['known_new_folders'] ) ) );

        $files = array();
        foreach ( $ids as $id ) {
            $description = $this->describe_file( $id );
            if ( $description ) {
                $files[] = $description;
            }
        }

        if ( empty( $files ) ) {
            return new WP_Error( 'apex_folders_no_files', __( 'None of these files exist.', 'apex-folders' ), array( 'status' => 400 ) );
        }

        $catalog = $this->get_folder_catalog();

        /**
         * Short-circuits the AI call with a ready-made plan.
         *
         * Used for testing without a provider. Return an array shaped like the
         * model's answer: array( 'files' => array( array( 'id', 'folder', 'new_folder', 'parent', 'reason' ) ) ).
         *
         * @param array|null $plan         Plan, or null to ask the AI.
         * @param array      $files        File descriptions in this batch.
         * @param array      $catalog      Existing folders keyed by slug.
         * @param string     $instructions The user's adjustment, if any.
         */
        $plan  = apply_filters( 'apex_folders_organize_plan_response', null, $files, $catalog, $instructions );
        $usage = array( 'input' => 0, 'output' => 0, 'total' => 0 );
        $model = '';
        $provider = '';

        if ( ! is_array( $plan ) ) {
            if ( ! self::is_configured() ) {
                return new WP_Error( 'apex_folders_not_configured', __( 'No AI provider is connected yet.', 'apex-folders' ), array( 'status' => 409 ) );
            }

            $result = $this->ask_ai( $files, $catalog, $instructions, $known_new_folders );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $plan     = $result['plan'];
            $usage    = $result['usage'];
            $model    = $result['model'];
            $provider = $result['provider'];
        }

        return rest_ensure_response( array(
            'proposals' => $this->validate_plan( $plan, $ids, $catalog ),
            'usage'     => $usage,
            'model'     => $model,
            'provider'  => $provider,
        ) );
    }

    /**
     * Build the prompt, send it, and parse the JSON answer
     *
     * @param array    $files             File descriptions.
     * @param array    $catalog           Existing folders keyed by slug.
     * @param string   $instructions      The user's adjustment.
     * @param string[] $known_new_folders Folders proposed in earlier batches.
     * @return array|WP_Error { plan, usage, model, provider }
     */
    private function ask_ai( $files, $catalog, $instructions, $known_new_folders ) {
        $folder_lines = array();
        foreach ( $catalog as $slug => $folder ) {
            $folder_lines[] = $folder['parent']
                ? sprintf( '- %s (slug: %s, subfolder of %s)', $folder['name'], $slug, $folder['parent'] )
                : sprintf( '- %s (slug: %s)', $folder['name'], $slug );
        }

        $system = implode( "\n", array(
            'You are a meticulous media librarian organizing a WordPress Media Library into folders.',
            'For each file, choose the best existing folder by its slug. Prefer existing folders.',
            'Propose a new folder only when at least three files in this batch, or a folder already proposed earlier, share one clear theme. New folder names are short Title Case nouns, at most 30 characters, and never duplicate an existing folder name.',
            'A new folder may be a subfolder of an existing top-level folder (set parent to that slug); never nest deeper than one level.',
            'If nothing fits well, leave the file where it is: folder null and new_folder null.',
            'Every file id from the input must appear exactly once in the answer. Keep each reason under 80 characters.',
            'Answer with JSON only, matching the schema.',
        ) );

        $text = "Existing folders:\n" . ( $folder_lines ? implode( "\n", $folder_lines ) : '(none yet)' ) . "\n\n";

        if ( $known_new_folders ) {
            $text .= "Folders already proposed in earlier batches (reuse these names instead of inventing variants):\n- " . implode( "\n- ", $known_new_folders ) . "\n\n";
        }

        if ( '' !== $instructions ) {
            $text .= "Instructions from the site owner (follow these first):\n" . $instructions . "\n\n";
        }

        $text .= "Files to place:\n";
        foreach ( $files as $file ) {
            $text .= wp_json_encode( $file ) . "\n";
        }

        try {
            $builder = wp_ai_client_prompt()
                ->using_system_instruction( $system )
                ->with_text( $text );

            // Show the model a small version of each image so badly named photos can still be sorted
            foreach ( $files as $file ) {
                $thumbnail = $this->get_thumbnail_path( $file['id'] );
                if ( $thumbnail ) {
                    $builder = $builder->with_text( sprintf( 'Image for file id %d:', $file['id'] ) )->with_file( $thumbnail );
                }
            }

            /**
             * Filters the model preference for auto-organize, in priority order.
             *
             * @param string[] $models Model IDs. Default empty: any model the provider offers.
             */
            $preference = apply_filters( 'apex_folders_organize_model_preference', array() );
            if ( ! empty( $preference ) ) {
                $builder = $builder->using_model_preference( ...$preference );
            }

            $builder = $builder
                ->as_json_response( $this->get_plan_schema() )
                ->using_max_tokens( 120 * count( $files ) + 200 );

            $result = $builder->generate_text_result();
        } catch ( Throwable $e ) {
            return new WP_Error( 'apex_folders_ai_failed', $e->getMessage(), array( 'status' => 502 ) );
        }

        if ( is_wp_error( $result ) ) {
            $result->add_data( array( 'status' => 502 ) );
            return $result;
        }

        try {
            $answer = $result->toText();
        } catch ( Throwable $e ) {
            return new WP_Error( 'apex_folders_ai_empty', __( 'The AI returned no answer.', 'apex-folders' ), array( 'status' => 502 ) );
        }

        $plan = json_decode( $this->strip_code_fence( $answer ), true );

        if ( ! is_array( $plan ) || ! isset( $plan['files'] ) ) {
            return new WP_Error( 'apex_folders_ai_unreadable', __( 'The AI answer could not be read.', 'apex-folders' ), array( 'status' => 502 ) );
        }

        $usage    = array( 'input' => 0, 'output' => 0, 'total' => 0 );
        $model    = '';
        $provider = '';

        try {
            $tokens = $result->getTokenUsage();
            $usage  = array(
                'input'  => (int) $tokens->getPromptTokens(),
                'output' => (int) $tokens->getCompletionTokens(),
                'total'  => (int) $tokens->getTotalTokens(),
            );
            $model    = (string) $result->getModelMetadata()->getName();
            $provider = (string) $result->getProviderMetadata()->getName();
        } catch ( Throwable $e ) {
            // Usage is informational only
        }

        return array(
            'plan'     => $plan,
            'usage'    => $usage,
            'model'    => $model,
            'provider' => $provider,
        );
    }

    /**
     * Some models wrap JSON in a Markdown code fence despite instructions
     *
     * @param string $text Model answer.
     * @return string
     */
    private function strip_code_fence( $text ) {
        $text = trim( $text );
        if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/s', $text, $m ) ) {
            return $m[1];
        }
        return $text;
    }

    /**
     * Keep only answers that refer to the files asked about and folders that exist or are sane to create
     *
     * @param array $plan    Decoded model answer.
     * @param int[] $ids     Attachment IDs that were asked about.
     * @param array $catalog Existing folders keyed by slug.
     * @return array Proposals ready for the dialog.
     */
    private function validate_plan( $plan, $ids, $catalog ) {
        $proposals = array();
        $seen      = array();
        $names     = array_map( 'strtolower', wp_list_pluck( $catalog, 'name' ) );

        foreach ( (array) $plan['files'] as $entry ) {
            $id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
            if ( ! in_array( $id, $ids, true ) || isset( $seen[ $id ] ) ) {
                continue;
            }
            $seen[ $id ] = true;

            $folder_id  = null;
            $new_folder = null;
            $parent_id  = null;
            $folder     = isset( $entry['folder'] ) && is_string( $entry['folder'] ) ? sanitize_title( $entry['folder'] ) : '';
            $new_name   = isset( $entry['new_folder'] ) && is_string( $entry['new_folder'] ) ? sanitize_text_field( $entry['new_folder'] ) : '';
            $parent     = isset( $entry['parent'] ) && is_string( $entry['parent'] ) ? sanitize_title( $entry['parent'] ) : '';

            if ( '' !== $folder && isset( $catalog[ $folder ] ) ) {
                $folder_id = $catalog[ $folder ]['id'];
            } elseif ( '' !== $new_name ) {
                $new_name = mb_substr( $new_name, 0, 30 );

                // A "new" folder that already exists is just that folder
                $existing = array_search( strtolower( $new_name ), $names, true );
                if ( false !== $existing ) {
                    $folder_id = $catalog[ $existing ]['id'];
                } else {
                    $new_folder = $new_name;
                    // Only top-level existing folders can take subfolders
                    if ( '' !== $parent && isset( $catalog[ $parent ] ) && null === $catalog[ $parent ]['parent'] ) {
                        $parent_id = $catalog[ $parent ]['id'];
                    }
                }
            }

            $proposals[] = array(
                'id'         => $id,
                'folder_id'  => $folder_id,
                'new_folder' => $new_folder,
                'parent_id'  => $parent_id,
                'reason'     => isset( $entry['reason'] ) ? mb_substr( sanitize_text_field( (string) $entry['reason'] ), 0, 120 ) : '',
            );
        }

        // Files the model skipped stay where they are
        foreach ( $ids as $id ) {
            if ( ! isset( $seen[ $id ] ) ) {
                $proposals[] = array(
                    'id'         => $id,
                    'folder_id'  => null,
                    'new_folder' => null,
                    'parent_id'  => null,
                    'reason'     => '',
                );
            }
        }

        return $proposals;
    }

    /**
     * POST organize/apply: create the folders and move the files
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function rest_apply( $request ) {
        $new_folders = (array) $request->get_param( 'new_folders' );
        $moves       = (array) $request->get_param( 'moves' );

        if ( empty( $moves ) ) {
            return new WP_Error( 'apex_folders_nothing_to_apply', __( 'There is nothing to apply.', 'apex-folders' ), array( 'status' => 400 ) );
        }

        // Create the folders first, so moves can refer to them by key
        $created  = array();
        $key_ids  = array();
        $failures = array();

        foreach ( $new_folders as $folder ) {
            $key    = isset( $folder['key'] ) ? sanitize_key( $folder['key'] ) : '';
            $name   = isset( $folder['name'] ) ? sanitize_text_field( $folder['name'] ) : '';
            $parent = isset( $folder['parent_id'] ) ? absint( $folder['parent_id'] ) : 0;

            if ( '' === $key || '' === $name ) {
                continue;
            }

            $result = APEX_FOLDERS_Utilities::create_folder( $name, $parent );

            if ( is_wp_error( $result ) ) {
                // A folder with this name may already exist: use it
                $existing = get_term_by( 'name', $name, 'apex_folder' );
                if ( $existing ) {
                    $key_ids[ $key ] = (int) $existing->term_id;
                } else {
                    $failures[] = $result->get_error_message();
                }
                continue;
            }

            $key_ids[ $key ] = $result;
            $created[]       = $result;
        }

        // Group moves by target folder and remember where every file was
        $by_folder = array();
        $previous  = array();

        foreach ( $moves as $move ) {
            $id = isset( $move['id'] ) ? absint( $move['id'] ) : 0;
            if ( ! $id || get_post_type( $id ) !== 'attachment' ) {
                continue;
            }

            $target = 0;
            if ( ! empty( $move['folder_id'] ) ) {
                $target = absint( $move['folder_id'] );
            } elseif ( ! empty( $move['key'] ) && isset( $key_ids[ sanitize_key( $move['key'] ) ] ) ) {
                $target = $key_ids[ sanitize_key( $move['key'] ) ];
            }

            if ( ! $target ) {
                continue;
            }

            $terms = get_the_terms( $id, 'apex_folder' );
            $previous[ $id ] = ( $terms && ! is_wp_error( $terms ) ) ? (int) $terms[0]->term_id : (int) apex_folders_get_unassigned_id();

            $by_folder[ $target ][] = $id;
        }

        $moved = 0;
        foreach ( $by_folder as $folder_id => $ids ) {
            $result = APEX_FOLDERS_Utilities::move_items( $ids, $folder_id );
            if ( ! is_wp_error( $result ) ) {
                $moved += count( $result['moved'] );
            }
        }

        update_option( self::UNDO_OPTION, array(
            'created'  => $created,
            'previous' => $previous,
            'time'     => time(),
            'user'     => get_current_user_id(),
        ), false );

        return rest_ensure_response( array(
            'moved'    => $moved,
            'created'  => count( $created ),
            'failures' => $failures,
            'can_undo' => ! empty( $previous ),
        ) );
    }

    /**
     * POST organize/undo: put every file back and remove folders that were created and are now empty
     *
     * @return WP_REST_Response|WP_Error
     */
    public function rest_undo() {
        $snapshot = get_option( self::UNDO_OPTION );

        if ( empty( $snapshot['previous'] ) ) {
            return new WP_Error( 'apex_folders_nothing_to_undo', __( 'There is nothing to undo.', 'apex-folders' ), array( 'status' => 400 ) );
        }

        $by_folder = array();
        foreach ( $snapshot['previous'] as $id => $folder_id ) {
            $by_folder[ (int) $folder_id ][] = (int) $id;
        }

        $restored = 0;
        foreach ( $by_folder as $folder_id => $ids ) {
            $result = APEX_FOLDERS_Utilities::move_items( $ids, $folder_id );
            if ( ! is_wp_error( $result ) ) {
                $restored += count( $result['moved'] );
            }
        }

        $removed = 0;
        foreach ( (array) $snapshot['created'] as $folder_id ) {
            $term = get_term( (int) $folder_id, 'apex_folder' );
            if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count && empty( get_term_children( $term->term_id, 'apex_folder' ) ) ) {
                if ( ! is_wp_error( wp_delete_term( $term->term_id, 'apex_folder' ) ) ) {
                    $removed++;
                }
            }
        }

        delete_option( self::UNDO_OPTION );

        return rest_ensure_response( array(
            'restored' => $restored,
            'removed'  => $removed,
        ) );
    }
}

// Initialize the class
new APEX_FOLDERS_AI();
