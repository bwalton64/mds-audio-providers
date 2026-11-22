<?php
/**
 * Rendering and Audio Player Manager integration for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Frontend;

use MDS\Bandcamp\Core\Helpers;
use MDS\Bandcamp\Core\Templates;
use MDS\Bandcamp\Core\Utilities;
use MDS_RM_Audio_Players;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies the Bandcamp player to the Release Links block audio slot and Audio Player Manager.
 *
 * @since 1.0.0
 */
final class Frontend {
	/**
	 * Register frontend hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'mds_rm_register_audio_players', array( self::class, 'register_audio_player' ) );
		add_filter( 'mds_rm_release_links_audio_html', array( self::class, 'filter_release_links_audio' ), 10, 3 );
	}

	/**
	 * Registers the Bandcamp Album Player with the MDS Release Manager Audio Player Manager.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, array<string, mixed>> $players Registered audio player definitions.
	 * @return array<string, array<string, mixed>> Audio player definitions including Bandcamp.
	 */
	public static function register_audio_player( array $players ): array {
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
	 *
	 * @param int                  $post_id    Release post ID.
	 * @param array<string, mixed> $settings   Site-wide Bandcamp player settings from the Audio Player Manager.
	 * @param array<string, mixed> $attributes Release Links block or shortcode attributes.
	 * @return string Rendered player HTML, or empty string when not configured on the release.
	 */
	public static function render_audio_player( int $post_id, array $settings = array(), array $attributes = array() ): string {
		unset( $attributes );

		$album_id = Helpers::get_album_id( $post_id );
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

		if ( function_exists( 'wp_enqueue_style' ) && defined( 'MDS_BC_URL' ) && defined( 'MDS_BC_PATH' ) && is_readable( MDS_BC_PATH . 'css/public.css' ) ) {
			wp_enqueue_style( 'mds-bc-public', MDS_BC_URL . 'css/public.css', array(), (string) filemtime( MDS_BC_PATH . 'css/public.css' ) );
		}

		$player = Utilities::register_bandcamp_shortcode( $shortcode_atts );

		return Templates::render(
			'frontend-widget.php',
			array(
				'shortcode_html' => $player,
			)
		);
	}

	/**
	 * Converts a CSS hex color string into a 6-character hex string without `#` for Bandcamp URLs.
	 *
	 * @since 1.1.0
	 *
	 * @param string $color    Input color value.
	 * @param string $fallback 6-character hex fallback without `#`.
	 * @return string 6-character lowercase hex color without `#`.
	 */
	private static function normalize_embed_hex( string $color, string $fallback ): string {
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
	 * Earlier non-empty markup from another extension is preserved.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed                $html       Audio markup supplied by an earlier filter.
	 * @param int|string           $post_id    Release post ID.
	 * @param array<string, mixed> $attributes Release Links block attributes.
	 * @return string Player markup, or the incoming markup when unavailable.
	 */
	public static function filter_release_links_audio( $html, $post_id, $attributes = array() ): string {
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
}
