<?php
/**
 * Utility helpers for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides utility functions for color contrast and content filtering.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Utilities
{

    /**
     * Register shortcode and filters hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_hooks(): void
    {
        add_shortcode( 'bandcamp', array( self::class, 'register_bandcamp_shortcode' ) );
        add_filter( 'wp_kses_allowed_html', array( self::class, 'kses_additions' ), 10, 2 );
    }

    /**
     * Register "bandcamp" shortcode as legacy backup for WordPress.com embed option.
     *
     * @since 1.0.0
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output of shortcode.
     */
    public static function register_bandcamp_shortcode( array $atts ): string
    {
        $defaults = array(
            'width'     => '100%',
            'height'    => '120',
            'album'     => '',
            'track'     => '',
            'size'      => 'large',
            'bgcol'     => 'ffffff',
            'linkcol'   => '0687f5',
            'tracklist' => '',
            'artwork'   => '',
            'minimal'   => '',
        );

        $atts  = shortcode_atts( $defaults, $atts, 'bandcamp' );
        $styles = '';

        if ( empty( $atts['album'] ) && empty( $atts['track'] ) ) {
            return '<p><strong>Error:</strong> Missing <code>album</code> or <code>track</code> ID in Bandcamp shortcode.</p>';
        }

        // Start building the embedded URL.
        $src    = 'https://bandcamp.com/EmbeddedPlayer';
        $params = array(
            'size=' . esc_attr( $atts['size'] ),
            'bgcol=' . esc_attr( $atts['bgcol'] ),
            'linkcol=' . esc_attr( $atts['linkcol'] ),
        );

        // Conditionally add optional parameters.
        if ( ! empty( $atts['album'] ) ) {
            $params[] = 'album=' . esc_attr( $atts['album'] );
        }
        if ( ! empty( $atts['tracklist'] ) ) {
            $params[] = 'tracklist=' . esc_attr( $atts['tracklist'] );
        }
        if ( ! empty( $atts['track'] ) ) {
            $params[] = 'track=' . esc_attr( $atts['track'] );
        }
        if ( ! empty( $atts['artwork'] ) ) {
            $params[] = 'artwork=' . esc_attr( $atts['artwork'] );
        }
        if ( ! empty( $atts['minimal'] ) ) {
            $params[] = 'minimal=' . esc_attr( $atts['minimal'] );
            $styles .= 'aspect-ratio:1/1;height:auto;';
        }

        // Always include transparent=true.
        $params[] = 'transparent=true';

        $src .= '/' . implode( '/', $params ) . '/';

        // Add an optional fallback link.
        $fallback = '<a href="https://bandcamp.com">Listen on Bandcamp</a>';

        if ( false === strpos( $atts['width'], '%' ) ) {
            $atts['width'] .= 'px';
        }

        // Return the full iframe.
        $output = '<iframe style="border: 0; width:100%; max-width:' . esc_attr( $atts['width'] ) . '; height: ' . esc_attr( $atts['height'] ) . 'px; ' . $styles . '" src="' . esc_url( $src ) . '" seamless>' . $fallback . '</iframe>';

        return $output;
    }

    /**
     * Return either "white" or "black" based on contrast against background color.
     *
     * @since 1.0.0
     *
     * @param string $bg_color 6-digit hex color, with or without leading "#".
     * @return string "white" or "black".
     */
    public static function get_contrast_color( string $bg_color ): string
    {
        // Remove a leading "#" if present.
        $hex = ltrim( $bg_color, '#' );

        // If not exactly six characters, default to black.
        if ( 6 !== strlen( $hex ) ) {
            return 'black';
        }

        // Split into RGB components.
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        // Compute brightness using the weighted RGB components.
        $brightness = ( ( 299 * $r ) + ( 587 * $g ) + ( 114 * $b ) ) / 1000;

        // Use black text for bright colors and white text otherwise.
        return ( $brightness > 128 ) ? esc_attr( '#000000' ) : esc_attr( '#ffffff' );
    }

    /**
     * Add SVG and iframe tags to wp_kses() allowed HTML.
     *
     * @since 1.0.0
     *
     * @param array  $tags    Allowed HTML tags.
     * @param string $context The context (post, strip, etc.).
     * @return array Modified allowed HTML tags.
     */
    public static function kses_additions( array $tags, string $context ): array
    {
        if ( 'post' !== $context ) {
            return $tags;
        }

        $tags['svg'] = array(
            'class'           => true,
            'aria-hidden'     => true,
            'aria-labelledby' => true,
            'role'            => true,
            'xmlns'           => true,
            'width'           => true,
            'height'          => true,
            'viewbox'         => true,
            'fill'            => true,
        );

        $tags['iframe'] = array(
            'src'             => true,
            'width'           => true,
            'height'          => true,
            'frameborder'     => true,
            'allow'           => true,
            'allowfullscreen' => true,
            'loading'         => true,
            'class'           => true,
            'id'              => true,
            'style'           => true,
            'seamless'        => true,
        );

        $tags['path'] = array(
            'd'    => true,
            'fill' => true,
        );

        return $tags;
    }
}


