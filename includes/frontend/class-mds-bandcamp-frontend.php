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
 * Renders the Bandcamp player after release metadata.
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
        add_action( 'mds_rm_after_release_meta', array( self::class, 'render' ) );
    }

    /**
     * Render the Bandcamp player on the frontend.
     *
     * @since 1.0.0
     * @param int $post_id Post ID.
     * @return void
     */
    public static function render( $post_id ): void
    {
        if ( ! MDS_Bandcamp_Dependencies::is_mds_active() || ! MDS_Bandcamp_Dependencies::is_bandcamp_active() ) {
            return;
        }

        $shortcode = get_post_meta( $post_id, 'bandcamp_shortcode', true );
        if ( empty( $shortcode ) ) {
            return;
        }

        $shortcode_html = do_shortcode( $shortcode );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template sanitizes shortcode output with an iframe allowlist.
        echo MDS_Bandcamp_Templates::render(
            'frontend-widget.php',
            array(
                'shortcode_html' => $shortcode_html,
            )
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
