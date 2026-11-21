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
        add_filter( 'mds_rm_release_links_audio_html', array( self::class, 'filter_release_links_audio' ), 10, 3 );
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
        if ( '' !== trim( $html ) || ! self::is_release_player_enabled() ) {
            return $html;
        }

        $album_id = MDS_Bandcamp_Helpers::get_album_id( (int) $post_id );
        if ( $album_id <= 0 ) {
            return $html;
        }

        $player = MDS_Bandcamp_Utilities::register_bandcamp_shortcode(
            array(
                'album'   => (string) $album_id,
                'height'  => '42',
                'size'    => 'small',
                'artwork' => 'none',
            )
        );

        return MDS_Bandcamp_Templates::render(
            'frontend-widget.php',
            array(
                'shortcode_html' => $player,
            )
        );
    }

    /**
     * Check whether the release-page player setting is enabled.
     *
     * Defaults to enabled until the setting has been saved.
     *
     * @since 1.1.0
     * @return bool Whether the Release Links player is enabled.
     */
    private static function is_release_player_enabled(): bool
    {
        $settings = get_option( 'mds_bandcamp__settings', array() );
        if ( ! is_array( $settings ) || ! array_key_exists( 'release_player', $settings ) ) {
            return true;
        }

        return ! empty( $settings['release_player'] );
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
