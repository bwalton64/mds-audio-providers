<?php
/**
 * Rendering, templates, and assets for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Supplies the Bandcamp player to the Release Links block audio slot.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Frontend
{
    /**
     * Register frontend hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_hooks(): void
    {
        add_filter( 'mds_rm_register_audio_players', array( self::class, 'register_audio_player' ) );
        add_filter( 'mds_rm_release_links_audio_html', array( self::class, 'filter_release_links_audio' ), 10, 3 );
    }

    /**
     * Registers the Bandcamp Album Player with the MDS Release Manager Audio Player Manager.
     *
     * @since 1.1.0
     * @param array $players Registered audio player definitions.
     * @return array Audio player definitions including Bandcamp.
     */
    public static function register_audio_player( array $players ): array
    {
        $players['bandcamp'] = array(
            'label'           => __( 'Bandcamp Album Player', 'mds-bandcamp-integration' ),
            'description'     => __( 'Displays the embedded Bandcamp album player for releases with a verified Bandcamp Album ID. Automatically falls back to the built-in HTML5 Player (.mp3) on releases without a Bandcamp album.', 'mds-bandcamp-integration' ),
            'icon'            => 'dashicons dashicons-album',
            'render_callback' => array( self::class, 'render_audio_player' ),
            'fields'          => array(
                'size'      => array(
                    'type'    => 'select',
                    'label'   => __( 'Player Size', 'mds-bandcamp-integration' ),
                    'help'    => __( 'Choose the slim 42px bar or the larger player layout.', 'mds-bandcamp-integration' ),
                    'default' => 'small',
                    'options' => array(
                        'small' => __( 'Small (42px Slim)', 'mds-bandcamp-integration' ),
                        'large' => __( 'Large', 'mds-bandcamp-integration' ),
                    ),
                ),
                'height'    => array(
                    'type'    => 'range',
                    'label'   => __( 'Player Height (px)', 'mds-bandcamp-integration' ),
                    'help'    => __( 'Standard Bandcamp heights: 42px for Small, 120px for Large without tracklist, or 240–470px with tracklist.', 'mds-bandcamp-integration' ),
                    'min'     => 42,
                    'max'     => 700,
                    'step'    => 1,
                    'default' => 42,
                ),
                'bgcol'     => array(
                    'type'    => 'color',
                    'label'   => __( 'Background Color', 'mds-bandcamp-integration' ),
                    'help'    => __( 'Hex color applied to the Bandcamp player background.', 'mds-bandcamp-integration' ),
                    'default' => '#ffffff',
                ),
                'linkcol'   => array(
                    'type'    => 'color',
                    'label'   => __( 'Link & Accent Color', 'mds-bandcamp-integration' ),
                    'help'    => __( 'Hex color applied to play controls and links inside the Bandcamp player.', 'mds-bandcamp-integration' ),
                    'default' => '#0687f5',
                ),
                'artwork'   => array(
                    'type'    => 'select',
                    'label'   => __( 'Artwork Display', 'mds-bandcamp-integration' ),
                    'default' => 'none',
                    'options' => array(
                        'none'  => __( 'None', 'mds-bandcamp-integration' ),
                        'small' => __( 'Small', 'mds-bandcamp-integration' ),
                        'big'   => __( 'Large', 'mds-bandcamp-integration' ),
                    ),
                ),
                'tracklist' => array(
                    'type'    => 'toggle',
                    'label'   => __( 'Show Tracklist', 'mds-bandcamp-integration' ),
                    'help'    => __( 'Applies when Player Size is set to Large.', 'mds-bandcamp-integration' ),
                    'default' => false,
                ),
                'minimal'   => array(
                    'type'    => 'toggle',
                    'label'   => __( 'Minimal Artwork Square', 'mds-bandcamp-integration' ),
                    'default' => false,
                ),
            ),
        );

        return $players;
    }

    /**
     * Renders the Bandcamp audio preview player for the Audio Player Manager.
     *
     * @since 1.1.0
     * @param int   $post_id    Release post ID.
     * @param array $settings   Site-wide Bandcamp player settings from the Audio Player Manager.
     * @param array $attributes Release Links block or shortcode attributes.
     * @return string Rendered player HTML, or empty string when not configured on the release.
     */
    public static function render_audio_player( int $post_id, array $settings = array(), array $attributes = array() ): string
    {
        $album_id = MDS_Bandcamp_Helpers::get_album_id( $post_id );
        if ( $album_id <= 0 ) {
            return '';
        }

        if ( empty( $settings ) && class_exists( 'MDS_RM_Audio_Players' ) && method_exists( 'MDS_RM_Audio_Players', 'get_player_settings' ) ) {
            $settings = MDS_RM_Audio_Players::get_player_settings( 'bandcamp' );
        }

        $size    = isset( $settings['size'] ) && in_array( $settings['size'], array( 'small', 'large' ), true ) ? $settings['size'] : 'small';
        $artwork = isset( $settings['artwork'] ) && in_array( $settings['artwork'], array( 'none', 'small', 'big' ), true ) ? $settings['artwork'] : 'none';
        $bgcol   = self::normalize_embed_hex( (string) ( $settings['bgcol'] ?? 'ffffff' ), 'ffffff' );
        $linkcol = self::normalize_embed_hex( (string) ( $settings['linkcol'] ?? '0687f5' ), '0687f5' );
        $height  = isset( $settings['height'] ) && is_numeric( $settings['height'] )
            ? max( 42, min( 700, (int) $settings['height'] ) )
            : ( 'large' === $size ? 120 : 42 );

        if ( 'large' === $size && 42 === $height ) {
            $height = ! empty( $settings['tracklist'] ) ? 240 : 120;
        }

        $shortcode_atts = array(
            'album'   => (string) $album_id,
            'height'  => (string) $height,
            'size'    => $size,
            'bgcol'   => $bgcol,
            'linkcol' => $linkcol,
            'artwork' => $artwork,
        );

        if ( 'large' === $size ) {
            $shortcode_atts['tracklist'] = ! empty( $settings['tracklist'] ) ? 'true' : 'false';
        }
        if ( ! empty( $settings['minimal'] ) ) {
            $shortcode_atts['minimal'] = 'true';
        }

        if ( function_exists( 'wp_enqueue_style' ) && defined( 'MDS_BANDCAMP_URL' ) && defined( 'MDS_BANDCAMP_PATH' ) && is_readable( MDS_BANDCAMP_PATH . 'css/public.css' ) ) {
            wp_enqueue_style( 'mds-bandcamp-public', MDS_BANDCAMP_URL . 'css/public.css', array(), filemtime( MDS_BANDCAMP_PATH . 'css/public.css' ) );
        }

        $player = MDS_Bandcamp_Utilities::register_bandcamp_shortcode( $shortcode_atts );

        return MDS_Bandcamp_Templates::render(
            'frontend-widget.php',
            array(
                'shortcode_html' => $player,
            )
        );
    }

    /**
     * Converts a CSS hex color string into a 6-character hex string without `#` for Bandcamp URLs.
     *
     * @param string $color    Input color value.
     * @param string $fallback 6-character hex fallback without `#`.
     * @return string 6-character lowercase hex color without `#`.
     */
    private static function normalize_embed_hex( string $color, string $fallback ): string
    {
        $clean = ltrim( trim( $color ), '#' );
        if ( preg_match( '/\A([0-9a-f]{6})(?:[0-9a-f]{2})?\z/i', $clean, $matches ) ) {
            return strtolower( $matches[1] );
        }
        if ( preg_match( '/\A([0-9a-f])([0-9a-f])([0-9a-f])(?:[0-9a-f])?\z/i', $clean, $matches ) ) {
            return strtolower( $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3] );
        }
        return $fallback;
    }

    /**
     * Supply the Bandcamp album player for the Release Links block.
     *
     * Runs on the Release Manager `mds_rm_release_links_audio_html` filter.
     * Earlier non-empty markup from another extension is preserved. The
     * player renders through the theme-overridable frontend-widget template
     * when the release has a saved Bandcamp album and the release-page
     * player setting is enabled.
     *
     * @since 1.1.0
     *
     * @param string $html       Audio markup supplied by an earlier filter.
     * @param int    $post_id    Release post ID.
     * @param array  $attributes Release Links block attributes (unused).
     * @return string Player markup, or the incoming markup when unavailable.
     */
    public static function filter_release_links_audio( $html, $post_id, $attributes = array() ): string
    {
        $html = is_string( $html ) ? $html : '';
        if ( '' !== trim( $html ) ) {
            return $html;
        }

        return self::render_audio_player(
            (int) $post_id,
            array(),
            is_array( $attributes ) ? $attributes : array()
        );
    }

    /**
     * Handle the [bandcamp] shortcode.
     *
     * Renders a Bandcamp embed iframe with the provided attributes.
     * This replaces functionality from the standalone Blocks for Bandcamp plugin
     * since MDS Bandcamp Integration deactivates it.
     *
     * @since 1.0.0
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content (unused).
     * @return string HTML embed iframe or empty string.
     */
    public static function handle_bandcamp_shortcode( $atts, $content = '' ): string
    {
        // Parse attributes with defaults.
        $atts = shortcode_atts(
            array(
                'width'  => '100%',
                'height' => '42',
                'album'  => '',
                'track'  => '',
                'size'   => 'small',
                'bgcol'  => 'ffffff',
                'linkcol' => '0687f5',
                'artwork' => 'none',
            ),
            $atts,
            'bandcamp'
        );

        // Build the embed URL.
        if ( ! empty( $atts['album'] ) ) {
            $item_type = 'album';
            $item_id   = absint( $atts['album'] );
        } elseif ( ! empty( $atts['track'] ) ) {
            $item_type = 'track';
            $item_id   = absint( $atts['track'] );
        } else {
            return '';
        }

        if ( $item_id <= 0 ) {
            return '';
        }

        // Build the Bandcamp embed iframe src URL.
        $src = sprintf(
            'https://bandcamp.com/EmbeddedPlayer/%s=%d/size=%s/bgcol=%s/linkcol=%s/artwork=%s/',
            $item_type,
            $item_id,
            sanitize_text_field( $atts['size'] ),
            sanitize_hex_color_no_hash( $atts['bgcol'] ),
            sanitize_hex_color_no_hash( $atts['linkcol'] ),
            sanitize_text_field( $atts['artwork'] )
        );

        // Build the HTML embed code.
        $width  = sanitize_text_field( $atts['width'] );
        $height = sanitize_text_field( $atts['height'] );

        return sprintf(
            '<iframe style="border: 0; width: %s; height: %s;" src="%s" seamless></iframe>',
            esc_attr( $width ),
            esc_attr( $height ),
            esc_url( $src )
        );
    }
}
