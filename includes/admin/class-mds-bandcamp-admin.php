<?php
/**
 * Admin settings and release meta box for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides admin functionality for Bandcamp release settings.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Admin
{
    /**
     * Register admin hooks for the Bandcamp meta box.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_hooks(): void
    {
        add_action( 'mds_rm_register_custom_meta_boxes', array( self::class, 'register_meta_box' ) );
        add_action( 'mds_rm_save_custom_meta', array( self::class, 'save_meta' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_metabox_assets' ) );
        add_action( 'wp_ajax_mds_bandcamp_verify_url', array( self::class, 'ajax_verify_url' ) );
    }

    /**
     * Register the Bandcamp meta box for release posts.
     *
     * @since 1.0.0
     * @param WP_Post $post Post being processed.
     * @return void
     */
    public static function register_meta_box( $post ): void
    {
        if ( ! MDS_Bandcamp_Dependencies::is_mds_active() ) {
            return;
        }

        if ( ! $post || 'release' !== $post->post_type ) {
            return;
        }

        add_meta_box(
            'mds_bandcamp_settings',
            __( 'Bandcamp Integration', 'mds-bandcamp-integration' ),
            array( self::class, 'render_meta_box' ),
            'release',
            'normal',
            'default'
        );
    }

    /**
     * Render the Bandcamp meta box.
     *
     * @since 1.0.0
     * @param WP_Post $post Post being processed.
     * @return void
     */
    public static function render_meta_box( $post ): void
    {
        $is_bandcamp_active = MDS_Bandcamp_Dependencies::is_bandcamp_active();
        $bandcamp_data      = self::get_bandcamp_data( $post->ID );
        $url                = $bandcamp_data['url'] ?? '';
        $album_id           = $bandcamp_data['album_id'] ?? '';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values and emits the nonce field.
        echo MDS_Bandcamp_Templates::render(
            'admin-settings.php',
            array(
                'nonce_field'        => wp_nonce_field( 'mds_bandcamp_save', 'mds_bandcamp_nonce', false, false ),
                'url'                => $url,
                'album_id'           => $album_id,
                'is_bandcamp_active' => $is_bandcamp_active,
            )
        );
    }

    /**
     * Save the Bandcamp data when the request is valid.
     *
     * @since 1.0.0
     * @param int   $post_id   Post ID.
     * @param array $post_data Submitted post data.
     * @return void
     */
    public static function save_meta( $post_id, $post_data ): void
    {
        if ( ! MDS_Bandcamp_Dependencies::is_mds_active() || ! self::has_valid_nonce( $post_data ) ) {
            return;
        }

        // Store the album ID if provided
        if ( isset( $post_data['mds_bandcamp_album_id'] ) ) {
            $album_id = sanitize_text_field( wp_unslash( $post_data['mds_bandcamp_album_id'] ) );
            $album_id = absint( $album_id );

            if ( $album_id > 0 ) {
                $url = isset( $post_data['mds_bandcamp_url'] ) ? esc_url_raw( wp_unslash( $post_data['mds_bandcamp_url'] ) ) : '';

                $bandcamp_data = array(
                    'url'      => $url,
                    'album_id' => $album_id,
                    'saved_at' => current_time( 'timestamp' ),
                );

                update_post_meta( $post_id, 'mds_bandcamp_integration', $bandcamp_data );
            } else {
                delete_post_meta( $post_id, 'mds_bandcamp_integration' );
            }
        } elseif ( isset( $post_data['mds_bandcamp_url'] ) && empty( $post_data['mds_bandcamp_album_id'] ) ) {
            // Clear album ID if URL is set but album_id is empty (user cleared it)
            delete_post_meta( $post_id, 'mds_bandcamp_integration' );
        }
    }

    /**
     * Retrieve Bandcamp integration data from post meta.
     *
     * @since 1.0.0
     * @param int $post_id Post ID.
     * @return array Bandcamp data array with 'url' and 'album_id' keys.
     */
    private static function get_bandcamp_data( int $post_id ): array
    {
        $data = get_post_meta( $post_id, 'mds_bandcamp_integration', true );

        if ( ! is_array( $data ) ) {
            return array();
        }

        return array(
            'url'      => isset( $data['url'] ) ? (string) $data['url'] : '',
            'album_id' => isset( $data['album_id'] ) ? (int) $data['album_id'] : 0,
        );
    }

    /**
     * Check the save nonce in submitted post data.
     *
     * @since 1.0.0
     * @param array $post_data Submitted post data.
     * @return bool Whether the request nonce is valid.
     */
    private static function has_valid_nonce( $post_data ): bool
    {
        return isset( $post_data['mds_bandcamp_nonce'] )
            && wp_verify_nonce( $post_data['mds_bandcamp_nonce'], 'mds_bandcamp_save' );
    }

    /**
     * Enqueue JavaScript and styles for the metabox.
     *
     * @since 1.0.0
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public static function enqueue_metabox_assets( $hook_suffix ): void
    {
        global $post;

        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! $post || 'release' !== $post->post_type ) {
            return;
        }

        wp_enqueue_script(
            'mds-bandcamp-metabox',
            MDS_BANDCAMP_URL . 'js/metabox-verify.js',
            array( 'jquery' ),
            MDS_BANDCAMP_VERSION,
            true
        );

        wp_localize_script(
            'mds-bandcamp-metabox',
            'mdsBandcampMetabox',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mds_bandcamp_verify_nonce' ),
            )
        );
    }

    /**
     * AJAX handler for verifying Bandcamp URL and extracting album ID.
     *
     * @since 1.0.0
     * @return void
     */
    public static function ajax_verify_url(): void
    {
        check_ajax_referer( 'mds_bandcamp_verify_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mds-bandcamp-integration' ) ) );
        }

        if ( ! isset( $_POST['url'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No URL provided.', 'mds-bandcamp-integration' ) ) );
        }

        $url = esc_url_raw( wp_unslash( $_POST['url'] ) );

        // Validate URL format
        if ( empty( $url ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'mds-bandcamp-integration' ) ) );
        }

        // Verify HTTPS and bandcamp.com domain
        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['scheme'] ) || 'https' !== strtolower( $parsed['scheme'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Only HTTPS URLs are allowed.', 'mds-bandcamp-integration' ) ) );
        }

        if ( empty( $parsed['host'] ) || ! preg_match( '/(^|\.)bandcamp\.com$/i', $parsed['host'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Only bandcamp.com URLs are allowed.', 'mds-bandcamp-integration' ) ) );
        }

        // Try to fetch and parse the page
        $response = MDS_Bandcamp_API::fetch_bandcamp_page( $url );

        if ( isset( $response['error'] ) ) {
            wp_send_json_error( array( 'message' => $response['message'] ?? __( 'Failed to fetch Bandcamp page.', 'mds-bandcamp-integration' ) ) );
        }

        // Extract album ID from HTML comment
        $album_id = self::extract_album_id( $response );

        if ( ! $album_id ) {
            wp_send_json_error( array( 'message' => __( 'Could not find Album ID on this page. Ensure it\'s a valid Bandcamp album/release page.', 'mds-bandcamp-integration' ) ) );
        }

        // Validate album ID is numeric
        $album_id = absint( $album_id );
        if ( $album_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Album ID must be a positive number.', 'mds-bandcamp-integration' ) ) );
        }

        // Cache the result
        $cache_key = 'mds_bandcamp_album_id_' . md5( $url );
        set_transient( $cache_key, $album_id, HOUR_IN_SECONDS );

        wp_send_json_success( array( 'album_id' => $album_id ) );
    }

    /**
     * Extract album ID from Bandcamp page HTML using DOM parsing.
     *
     * @since 1.0.0
     * @param string $html HTML content of Bandcamp page.
     * @return int|false Album ID if found, false otherwise.
     */
    private static function extract_album_id( string $html ): int|false
    {
        if ( empty( $html ) || ! is_string( $html ) ) {
            return false;
        }

        libxml_use_internal_errors( true );

        $dom = new DOMDocument();
        $dom->loadHTML( $html );

        libxml_use_internal_errors( false );

        // Look for comment containing "album id"
        foreach ( $dom->childNodes as $node ) {
            $comment = self::find_album_id_comment( $node );
            if ( $comment ) {
                return $comment;
            }
        }

        // Check meta tags as fallback
        $metas = $dom->getElementsByTagName( 'meta' );
        foreach ( $metas as $meta ) {
            if ( 'album_id' === $meta->getAttribute( 'name' ) || 'albumId' === $meta->getAttribute( 'property' ) ) {
                $content = $meta->getAttribute( 'content' );
                $id      = absint( $content );
                if ( $id > 0 ) {
                    return $id;
                }
            }
        }

        return false;
    }

    /**
     * Recursively search DOM nodes for album ID comment.
     *
     * @since 1.0.0
     * @param DOMNode $node DOM node to search.
     * @return int|false Album ID if found, false otherwise.
     */
    private static function find_album_id_comment( DOMNode $node ): int|false
    {
        if ( XML_COMMENT_NODE === $node->nodeType ) {
            // Look for "album id" comment pattern
            if ( preg_match( '/album\s+id\s+(\d+)/i', $node->nodeValue, $matches ) ) {
                $id = absint( $matches[1] );
                if ( $id > 0 ) {
                    return $id;
                }
            }
        }

        // Recursively search child nodes
        if ( $node->hasChildNodes() ) {
            foreach ( $node->childNodes as $child ) {
                $result = self::find_album_id_comment( $child );
                if ( $result ) {
                    return $result;
                }
            }
        }

        return false;
    }
}
