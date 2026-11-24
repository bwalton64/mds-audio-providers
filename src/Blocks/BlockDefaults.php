<?php
/**
 * Site-wide default styling and configuration for Bandcamp blocks, shortcodes, and Elementor widgets.
 *
 * @package MDS\Bandcamp
 * @since   1.0.2
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages centralized site-wide defaults for the three Bandcamp blocks.
 *
 * @since 1.0.2
 */
final class BlockDefaults {
	/**
	 * WordPress option key storing centralized Bandcamp block defaults.
	 *
	 * @since 1.0.2
	 * @var string
	 */
	public const OPTION_KEY = 'mds_bc_block_defaults';

	/**
	 * Supported block slugs.
	 *
	 * @since 1.0.2
	 * @var string[]
	 */
	public const SLUGS = array(
		'bandcamp-album',
		'bandcamp-discography',
		'bandcamp-miniplayer',
	);

	/**
	 * Register block-default resolution hooks.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'render_block_data', array( self::class, 'apply_to_block' ), 20 );
	}

	/**
	 * Return baseline schema defaults for a block slug.
	 *
	 * @since 1.0.2
	 *
	 * @param string $slug Block slug.
	 * @return array<string, mixed> Baseline defaults.
	 */
	public static function get_schema_defaults( string $slug ): array {
		if ( 'bandcamp-album' === $slug ) {
			return array(
				'embedType'            => 'custom',
				'backgroundColor'      => '#171717',
				'textColor'            => '#ffffff',
				'buttonColor'          => '#000000',
				'trackColor'           => '#ffffff',
				'playerControlsColor'  => '#ffffff',
				'progressBarColor'     => '#ffffff',
				'display_album_art'    => true,
				'display_album_artist' => true,
				'display_album_link'   => true,
				'display_album_title'  => true,
				'display_audio'        => true,
				'display_merch'        => true,
				'display_playlist'     => true,
			);
		}

		if ( 'bandcamp-discography' === $slug ) {
			return array(
				'text_color'             => '#000000',
				'button_color'           => '#000000',
				'button_text_color'      => '#ffffff',
				'release_border'         => array(
					'color'  => '',
					'style'  => '',
					'width'  => '',
					'radius' => '',
				),
				'release_image_padding'  => array(),
				'release_titles_padding' => array(),
				'release_titles_align'   => 'left',
				'display_album_art'      => true,
				'display_album_artist'   => true,
				'display_album_link'     => true,
				'display_album_title'    => true,
				'layout_grid_gap'        => '2rem',
				'layout_columns'         => 3,
				'layout_columns_mobile'  => 2,
			);
		}

		if ( 'bandcamp-miniplayer' === $slug ) {
			return array(
				'artOpacity'           => 0.5,
				'artOpacityHover'      => 0.2,
				'aspectRatioHeight'    => '1',
				'aspectRatioWidth'     => '1',
				'backgroundColor'      => '#171717',
				'buttonColor'          => '#000000',
				'progressBarColor'     => '#ffffff',
				'textColor'            => '#ffffff',
				'trackNumber'          => '1',
				'display_album_artist' => true,
				'display_album_link'   => true,
				'display_album_title'  => true,
				'display_progress_bar' => true,
				'display_track_title'  => true,
			);
		}

		return array();
	}

	/**
	 * Get all saved site-wide block defaults.
	 *
	 * @since 1.0.2
	 *
	 * @return array<string, array<string, mixed>> Saved defaults keyed by block slug.
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			return array();
		}

		$result = array();
		foreach ( self::SLUGS as $slug ) {
			if ( isset( $saved[ $slug ] ) && is_array( $saved[ $slug ] ) ) {
				$result[ $slug ] = $saved[ $slug ];
			}
		}

		return $result;
	}

	/**
	 * Get effective site-wide defaults (schema baseline merged with saved option) for all blocks.
	 *
	 * @since 1.0.2
	 *
	 * @return array<string, array<string, mixed>> Effective defaults keyed by block slug.
	 */
	public static function get_all_effective(): array {
		$effective = array();
		foreach ( self::SLUGS as $slug ) {
			$effective[ $slug ] = self::get_effective( $slug );
		}
		return $effective;
	}

	/**
	 * Get saved site-wide defaults for one block slug.
	 *
	 * @since 1.0.2
	 *
	 * @param string $slug Block slug.
	 * @return array<string, mixed> Saved defaults for the slug.
	 */
	public static function get( string $slug ): array {
		$all = self::get_all();
		return $all[ $slug ] ?? array();
	}

	/**
	 * Get effective site-wide defaults (schema baseline + saved overrides) for one block slug.
	 *
	 * @since 1.0.2
	 *
	 * @param string $slug Block slug.
	 * @return array<string, mixed> Effective defaults.
	 */
	public static function get_effective( string $slug ): array {
		$base  = self::get_schema_defaults( $slug );
		$saved = self::get( $slug );
		return array_replace( $base, $saved );
	}

	/**
	 * Merge schema defaults, site-wide defaults, and explicit instance attributes.
	 *
	 * @since 1.0.2
	 *
	 * @param string               $slug       Block slug.
	 * @param array<string, mixed> $attributes Instance attributes.
	 * @return array<string, mixed> Resolved attributes.
	 */
	public static function resolve( string $slug, array $attributes ): array {
		$effective = self::get_effective( $slug );

		foreach ( $attributes as $key => $value ) {
			if ( null === $value ) {
				continue;
			}
			if ( is_string( $value ) && '' === $value && array_key_exists( $key, $effective ) && '' !== $effective[ $key ] ) {
				continue;
			}
			if ( is_array( $value ) && empty( $value ) && array_key_exists( $key, $effective ) && ! empty( $effective[ $key ] ) ) {
				continue;
			}
			$effective[ $key ] = $value;
		}

		return $effective;
	}

	/**
	 * Apply site-wide defaults to parsed Bandcamp blocks before WordPress injects block.json defaults.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $block Parsed block data.
	 * @return array<string, mixed> Block with resolved attributes.
	 */
	public static function apply_to_block( array $block ): array {
		$name = $block['blockName'] ?? '';
		if ( ! is_string( $name ) || 0 !== strpos( $name, 'mds-bandcamp/' ) ) {
			return $block;
		}

		$slug = substr( $name, strlen( 'mds-bandcamp/' ) );
		if ( ! in_array( $slug, self::SLUGS, true ) ) {
			return $block;
		}

		$attrs          = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$block['attrs'] = self::resolve( $slug, $attrs );

		return $block;
	}

	/**
	 * Sanitize a submitted defaults map across all supported block slugs.
	 *
	 * @since 1.0.2
	 *
	 * @param mixed $input Raw submitted defaults.
	 * @return array<string, array<string, mixed>> Sanitized defaults.
	 */
	public static function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( self::SLUGS as $slug ) {
			if ( ! isset( $input[ $slug ] ) || ! is_array( $input[ $slug ] ) ) {
				continue;
			}
			$clean = self::sanitize_block( $slug, $input[ $slug ] );
			if ( ! empty( $clean ) ) {
				$sanitized[ $slug ] = $clean;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize defaults for a single block slug.
	 *
	 * @since 1.0.2
	 *
	 * @param string               $slug   Block slug.
	 * @param array<string, mixed> $values Raw block settings.
	 * @return array<string, mixed> Sanitized block settings.
	 */
	private static function sanitize_block( string $slug, array $values ): array {
		$schema = self::get_schema_defaults( $slug );
		$clean  = array();

		$color_keys = array(
			'backgroundColor',
			'textColor',
			'buttonColor',
			'trackColor',
			'playerControlsColor',
			'progressBarColor',
			'text_color',
			'button_color',
			'button_text_color',
		);

		$bool_keys = array(
			'display_album_art',
			'display_album_artist',
			'display_album_link',
			'display_album_title',
			'display_audio',
			'display_merch',
			'display_playlist',
			'display_progress_bar',
			'display_track_title',
		);

		foreach ( $values as $key => $value ) {
			if ( ! array_key_exists( $key, $schema ) ) {
				continue;
			}

			if ( in_array( $key, $color_keys, true ) ) {
				$hex = sanitize_hex_color( is_scalar( $value ) ? (string) $value : '' );
				if ( is_string( $hex ) && '' !== $hex ) {
					$clean[ $key ] = $hex;
				}
				continue;
			}

			if ( in_array( $key, $bool_keys, true ) ) {
				$clean[ $key ] = rest_sanitize_boolean( $value );
				continue;
			}

			if ( 'embedType' === $key ) {
				$val = is_scalar( $value ) ? (string) $value : '';
				if ( in_array( $val, array( 'custom', 'bandcamp' ), true ) ) {
					$clean[ $key ] = $val;
				}
				continue;
			}

			if ( 'release_titles_align' === $key ) {
				$val = is_scalar( $value ) ? (string) $value : '';
				if ( in_array( $val, array( 'left', 'center', 'right', 'justify' ), true ) ) {
					$clean[ $key ] = $val;
				}
				continue;
			}

			if ( in_array( $key, array( 'layout_columns', 'layout_columns_mobile' ), true ) ) {
				$clean[ $key ] = max( 1, min( 6, absint( $value ) ) );
				continue;
			}

			if ( 'layout_grid_gap' === $key ) {
				$clean[ $key ] = self::sanitize_css_unit( $value, '2rem' );
				continue;
			}

			if ( 'release_border' === $key && is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_border( $value );
				continue;
			}

			if ( in_array( $key, array( 'release_image_padding', 'release_titles_padding' ), true ) && is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_box( $value );
				continue;
			}

			if ( in_array( $key, array( 'artOpacity', 'artOpacityHover' ), true ) ) {
				$num           = is_numeric( $value ) ? (float) $value : (float) $schema[ $key ];
				$clean[ $key ] = round( max( 0.0, min( 1.0, $num ) ), 2 );
				continue;
			}

			if ( in_array( $key, array( 'aspectRatioWidth', 'aspectRatioHeight' ), true ) ) {
				$num           = max( 1, min( 99, absint( $value ) ) );
				$clean[ $key ] = (string) $num;
				continue;
			}

			if ( 'trackNumber' === $key ) {
				$num           = max( 0, min( 999, absint( $value ) ) );
				$clean[ $key ] = (string) $num;
				continue;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a CSS length unit string.
	 *
	 * @since 1.0.2
	 *
	 * @param mixed  $raw      Raw length value.
	 * @param string $fallback Fallback value.
	 * @return string Sanitized CSS length.
	 */
	public static function sanitize_css_unit( $raw, string $fallback = '0' ): string {
		if ( ! is_scalar( $raw ) ) {
			return $fallback;
		}
		$raw = trim( (string) $raw );
		if ( '0' === $raw || preg_match( '/^\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)$/', $raw ) ) {
			return $raw;
		}
		if ( preg_match( '/^\d+(?:\.\d+)?$/', $raw ) ) {
			return $raw . 'px';
		}
		return $fallback;
	}

	/**
	 * Sanitize a border configuration object.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $border Raw border array.
	 * @return array{color: string, style: string, width: string, radius: string} Sanitized border array.
	 */
	private static function sanitize_border( array $border ): array {
		$allowed_styles = array( '', 'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset' );
		$color          = ! empty( $border['color'] ) && is_scalar( $border['color'] ) ? (string) sanitize_hex_color( (string) $border['color'] ) : '';
		$style          = isset( $border['style'] ) && is_string( $border['style'] ) && in_array( $border['style'], $allowed_styles, true ) ? $border['style'] : '';
		$width          = ! empty( $border['width'] ) ? self::sanitize_css_unit( $border['width'], '' ) : '';
		$radius         = ! empty( $border['radius'] ) ? self::sanitize_css_unit( $border['radius'], '' ) : '';

		return array(
			'color'  => $color,
			'style'  => $style,
			'width'  => $width,
			'radius' => $radius,
		);
	}

	/**
	 * Sanitize a four-sided box padding configuration object.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $box Raw box array.
	 * @return array<string, string> Sanitized sides.
	 */
	private static function sanitize_box( array $box ): array {
		$clean = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( isset( $box[ $side ] ) && '' !== (string) $box[ $side ] ) {
				$clean[ $side ] = self::sanitize_css_unit( $box[ $side ], '0' );
			}
		}
		return $clean;
	}
}
