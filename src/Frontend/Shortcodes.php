<?php
/**
 * Shortcodes for Bandcamp Album, Discography, and Miniplayer.
 *
 * @package MDS\Bandcamp
 * @since   1.0.2
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Frontend;

use MDS\Bandcamp\Blocks\BlockRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders shortcodes corresponding to the three Bandcamp blocks.
 *
 * @since 1.0.2
 */
final class Shortcodes {
	/**
	 * Register the Bandcamp block shortcodes.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_shortcode( 'bandcamp-album', array( self::class, 'render_album' ) );
		add_shortcode( 'bandcamp-discography', array( self::class, 'render_discography' ) );
		add_shortcode( 'bandcamp-miniplayer', array( self::class, 'render_miniplayer' ) );
	}

	/**
	 * Parse a nullable boolean shortcode attribute.
	 *
	 * @since 1.0.2
	 *
	 * @param mixed $value Raw shortcode attribute value.
	 * @return bool|null Boolean value, or null when omitted/empty.
	 */
	private static function parse_optional_bool( $value ): ?bool {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Render the `[bandcamp-album]` shortcode.
	 *
	 * @since 1.0.2
	 *
	 * @param mixed $atts Raw shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public static function render_album( $atts = array() ): string {
		$raw = is_array( $atts ) ? $atts : array();

		$attributes = array();

		$string_map = array(
			'album_id'              => 'albumID',
			'url'                   => 'url',
			'release_id'            => 'release_id',
			'upc'                   => 'upc',
			'embed_type'            => 'embedType',
			'background_color'      => 'backgroundColor',
			'text_color'            => 'textColor',
			'button_color'          => 'buttonColor',
			'track_color'           => 'trackColor',
			'player_controls_color' => 'playerControlsColor',
			'progress_bar_color'    => 'progressBarColor',
			'anchor'                => 'anchor',
		);

		foreach ( $string_map as $shortcode_key => $attr_key ) {
			if ( isset( $raw[ $shortcode_key ] ) && '' !== trim( (string) $raw[ $shortcode_key ] ) ) {
				$attributes[ $attr_key ] = sanitize_text_field( (string) $raw[ $shortcode_key ] );
			}
		}

		$bool_keys = array(
			'display_album_art',
			'display_album_artist',
			'display_album_link',
			'display_album_title',
			'display_audio',
			'display_merch',
			'display_playlist',
		);

		foreach ( $bool_keys as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$parsed = self::parse_optional_bool( $raw[ $key ] );
				if ( null !== $parsed ) {
					$attributes[ $key ] = $parsed;
				}
			}
		}

		return BlockRenderer::render_album( $attributes, null, false );
	}

	/**
	 * Render the `[bandcamp-discography]` shortcode.
	 *
	 * @since 1.0.2
	 *
	 * @param mixed $atts Raw shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public static function render_discography( $atts = array() ): string {
		$raw = is_array( $atts ) ? $atts : array();

		$attributes = array();

		if ( isset( $raw['artist_id'] ) && '' !== trim( (string) $raw['artist_id'] ) ) {
			$attributes['artistId'] = absint( $raw['artist_id'] );
		}

		$string_map = array(
			'artist_url'           => 'artist_url',
			'subdomain'            => 'artist_url',
			'text_color'           => 'text_color',
			'button_color'         => 'button_color',
			'button_text_color'    => 'button_text_color',
			'release_titles_align' => 'release_titles_align',
			'title_align'          => 'release_titles_align',
			'layout_grid_gap'      => 'layout_grid_gap',
			'grid_gap'             => 'layout_grid_gap',
			'anchor'               => 'anchor',
		);

		foreach ( $string_map as $shortcode_key => $attr_key ) {
			if ( isset( $raw[ $shortcode_key ] ) && '' !== trim( (string) $raw[ $shortcode_key ] ) ) {
				$attributes[ $attr_key ] = sanitize_text_field( (string) $raw[ $shortcode_key ] );
			}
		}

		if ( isset( $raw['layout_columns'] ) && '' !== trim( (string) $raw['layout_columns'] ) ) {
			$attributes['layout_columns'] = max( 1, min( 6, absint( $raw['layout_columns'] ) ) );
		} elseif ( isset( $raw['columns'] ) && '' !== trim( (string) $raw['columns'] ) ) {
			$attributes['layout_columns'] = max( 1, min( 6, absint( $raw['columns'] ) ) );
		}

		if ( isset( $raw['layout_columns_mobile'] ) && '' !== trim( (string) $raw['layout_columns_mobile'] ) ) {
			$attributes['layout_columns_mobile'] = max( 1, min( 6, absint( $raw['layout_columns_mobile'] ) ) );
		} elseif ( isset( $raw['columns_mobile'] ) && '' !== trim( (string) $raw['columns_mobile'] ) ) {
			$attributes['layout_columns_mobile'] = max( 1, min( 6, absint( $raw['columns_mobile'] ) ) );
		}

		$bool_keys = array(
			'display_album_art',
			'display_album_artist',
			'display_album_link',
			'display_album_title',
		);

		foreach ( $bool_keys as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$parsed = self::parse_optional_bool( $raw[ $key ] );
				if ( null !== $parsed ) {
					$attributes[ $key ] = $parsed;
				}
			}
		}

		return BlockRenderer::render_discography( $attributes, null, false );
	}

	/**
	 * Render the `[bandcamp-miniplayer]` shortcode.
	 *
	 * @since 1.0.2
	 *
	 * @param mixed $atts Raw shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public static function render_miniplayer( $atts = array() ): string {
		$raw = is_array( $atts ) ? $atts : array();

		$attributes = array();

		$string_map = array(
			'album_id'            => 'albumID',
			'url'                 => 'url',
			'release_id'          => 'release_id',
			'upc'                 => 'upc',
			'track_number'        => 'trackNumber',
			'aspect_ratio_width'  => 'aspectRatioWidth',
			'aspect_ratio_height' => 'aspectRatioHeight',
			'background_color'    => 'backgroundColor',
			'text_color'          => 'textColor',
			'button_color'        => 'buttonColor',
			'progress_bar_color'  => 'progressBarColor',
			'anchor'              => 'anchor',
		);

		foreach ( $string_map as $shortcode_key => $attr_key ) {
			if ( isset( $raw[ $shortcode_key ] ) && '' !== trim( (string) $raw[ $shortcode_key ] ) ) {
				$attributes[ $attr_key ] = sanitize_text_field( (string) $raw[ $shortcode_key ] );
			}
		}

		if ( isset( $raw['art_opacity'] ) && is_numeric( $raw['art_opacity'] ) ) {
			$attributes['artOpacity'] = (float) $raw['art_opacity'];
		}
		if ( isset( $raw['art_opacity_hover'] ) && is_numeric( $raw['art_opacity_hover'] ) ) {
			$attributes['artOpacityHover'] = (float) $raw['art_opacity_hover'];
		}

		$bool_keys = array(
			'display_album_artist',
			'display_album_link',
			'display_album_title',
			'display_progress_bar',
			'display_track_title',
		);

		foreach ( $bool_keys as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$parsed = self::parse_optional_bool( $raw[ $key ] );
				if ( null !== $parsed ) {
					$attributes[ $key ] = $parsed;
				}
			}
		}

		return BlockRenderer::render_miniplayer( $attributes, null, false );
	}
}
