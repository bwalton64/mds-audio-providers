<?php
/**
 * Public utility helpers for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Core;

use MDS_RM_Release_Meta;
use WP_Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides public utility methods used by Bandcamp integration code.
 *
 * @since 1.0.0
 */
final class Helpers {
	/**
	 * Check whether MDS Release Manager is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether MDS Release Manager is active.
	 */
	public static function is_mds_active(): bool {
		return Dependencies::is_mds_active();
	}

	/**
	 * Check whether integrated Bandcamp block features are available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether Bandcamp block features are available.
	 */
	public static function is_bandcamp_active(): bool {
		return Dependencies::is_bandcamp_active();
	}

	/**
	 * Get the contrast color for a given background color.
	 *
	 * @since 1.0.0
	 *
	 * @param string $bg_color Background color in hex format.
	 * @return string Contrast color.
	 */
	public static function get_contrast_color( string $bg_color ): string {
		return Utilities::get_contrast_color( $bg_color );
	}

	/**
	 * Get Bandcamp album ID for a release.
	 *
	 * Checks `mds_bandcamp_integration` first, then falls back to flat
	 * `bandcamp_album_id` meta or unmigrated legacy `bandcamp_shortcode` meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID of the release.
	 * @return int Album ID, or 0 if not set.
	 */
	public static function get_album_id( int $post_id ): int {
		$data = get_post_meta( $post_id, 'mds_bandcamp_integration', true );

		if ( is_array( $data ) && ! empty( $data['album_id'] ) ) {
			return absint( $data['album_id'] );
		}

		$flat_id = get_post_meta( $post_id, 'bandcamp_album_id', true );
		if ( is_scalar( $flat_id ) && absint( $flat_id ) > 0 ) {
			return absint( $flat_id );
		}

		$legacy_shortcode = get_post_meta( $post_id, 'bandcamp_shortcode', true );
		if ( is_string( $legacy_shortcode ) && '' !== trim( $legacy_shortcode ) ) {
			if ( preg_match( '/album(?:_id)?[=\s]+(\d+)/i', $legacy_shortcode, $matches ) ) {
				return absint( $matches[1] );
			}
			if ( preg_match( '/^\d+$/', trim( $legacy_shortcode ) ) ) {
				return absint( trim( $legacy_shortcode ) );
			}
		}

		return 0;
	}

	/**
	 * Get Bandcamp release URL for a release.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID of the release.
	 * @return string Release URL, or empty string if not set.
	 */
	public static function get_release_url( int $post_id ): string {
		$data = get_post_meta( $post_id, 'mds_bandcamp_integration', true );

		if ( is_array( $data ) && ! empty( $data['url'] ) ) {
			return (string) $data['url'];
		}

		$flat_url = get_post_meta( $post_id, 'bandcamp_album_url', true );
		if ( is_string( $flat_url ) && '' !== $flat_url ) {
			return $flat_url;
		}

		return '';
	}

	/**
	 * Resolves a contextual Release post ID for dynamic Bandcamp blocks.
	 *
	 * Uses the MDS Release Manager `MDS_RM_Release_Meta::resolve_release()` toolkit
	 * when available (supporting Release, Artist, WooCommerce product SKU, and
	 * Gutenberg ServerSideRender `post_id` contexts), falling back to `get_the_ID()`.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param mixed                $block      Optional WP_Block instance.
	 * @return int Resolved Release post ID, or 0 when unavailable.
	 */
	public static function resolve_context_release_id( array $attributes = array(), $block = null ): int {
		$context_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( ! $context_id && defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authenticated REST ServerSideRender context preview.
			$candidate = absint( function_exists( 'wp_unslash' ) ? wp_unslash( $_REQUEST['post_id'] ) : $_REQUEST['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $candidate && ( ! function_exists( 'get_post_status' ) || 'publish' === get_post_status( $candidate ) || ( function_exists( 'current_user_can' ) && current_user_can( 'read_post', $candidate ) ) ) ) {
				$context_id = $candidate;
			}
		}

		if ( class_exists( 'MDS_RM_Release_Meta' ) && method_exists( 'MDS_RM_Release_Meta', 'resolve_release' ) ) {
			$wp_block = ( class_exists( 'WP_Block' ) && $block instanceof WP_Block ) ? $block : null;
			$post     = MDS_RM_Release_Meta::resolve_release( $attributes, $wp_block, $context_id );
			if ( $post && isset( $post->ID ) ) {
				return (int) $post->ID;
			}
		}

		return $context_id;
	}

	/**
	 * Extract the Bandcamp artist subdomain from a Bandcamp URL.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url Bandcamp URL, for example https://artist.bandcamp.com/album/example.
	 * @return string Artist subdomain, or empty string for non-artist Bandcamp URLs.
	 */
	public static function parse_bandcamp_subdomain( string $url ): string {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( ! preg_match( '/^([a-z0-9][a-z0-9-]*)\.bandcamp\.com$/i', $host, $matches ) ) {
			return '';
		}

		$subdomain = strtolower( $matches[1] );

		return 'www' === $subdomain ? '' : $subdomain;
	}

	/**
	 * Get the Bandcamp artist subdomain for a release.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id Post ID of the release.
	 * @return string Artist subdomain, or empty string when unavailable.
	 */
	public static function get_release_subdomain( int $post_id ): string {
		return self::parse_bandcamp_subdomain( self::get_release_url( $post_id ) );
	}

	/**
	 * Normalizes a Bandcamp artist URL or bare subdomain into a clean artist subdomain.
	 *
	 * Accepts either a full URL (`https://artist.bandcamp.com` or `https://artist.bandcamp.com/music`)
	 * or a bare subdomain (`artist`).
	 *
	 * @since 1.1.0
	 *
	 * @param string $url_or_subdomain Bandcamp artist URL or subdomain.
	 * @return string Lowercase artist subdomain, or empty string when invalid.
	 */
	public static function normalize_artist_subdomain( string $url_or_subdomain ): string {
		$raw = trim( $url_or_subdomain );
		if ( '' === $raw ) {
			return '';
		}

		if ( false !== strpos( $raw, '://' ) || false !== strpos( $raw, '.' ) ) {
			$candidate = false === strpos( $raw, '://' ) ? 'https://' . ltrim( $raw, '/' ) : $raw;
			return self::parse_bandcamp_subdomain( $candidate );
		}

		if ( preg_match( '/\A([a-z0-9][a-z0-9-]*)\z/i', $raw, $matches ) ) {
			$sub = strtolower( $matches[1] );
			return 'www' === $sub ? '' : $sub;
		}

		return '';
	}

	/**
	 * Get the canonical Bandcamp Artist URL (`https://<subdomain>.bandcamp.com`) for an Artist post.
	 *
	 * @since 1.1.0
	 *
	 * @param int $artist_post_id Artist CPT post ID.
	 * @return string Canonical Bandcamp Artist URL, or empty string if not configured.
	 */
	public static function get_artist_url( int $artist_post_id ): string {
		$data = get_post_meta( $artist_post_id, 'mds_bandcamp_artist_integration', true );
		if ( is_array( $data ) && ! empty( $data['url'] ) ) {
			$sub = self::normalize_artist_subdomain( (string) $data['url'] );
			if ( '' !== $sub ) {
				return 'https://' . $sub . '.bandcamp.com';
			}
		}

		$flat_url = get_post_meta( $artist_post_id, 'bandcamp_artist_url', true );
		if ( is_string( $flat_url ) && '' !== trim( $flat_url ) ) {
			$sub = self::normalize_artist_subdomain( $flat_url );
			if ( '' !== $sub ) {
				return 'https://' . $sub . '.bandcamp.com';
			}
		}

		$flat_sub = get_post_meta( $artist_post_id, 'bandcamp_artist_subdomain', true );
		if ( is_string( $flat_sub ) && '' !== trim( $flat_sub ) ) {
			$sub = self::normalize_artist_subdomain( $flat_sub );
			if ( '' !== $sub ) {
				return 'https://' . $sub . '.bandcamp.com';
			}
		}

		return '';
	}

	/**
	 * Get the Bandcamp artist subdomain for an Artist post.
	 *
	 * @since 1.1.0
	 *
	 * @param int $artist_post_id Artist CPT post ID.
	 * @return string Artist subdomain, or empty string when not configured.
	 */
	public static function get_artist_subdomain( int $artist_post_id ): string {
		$data = get_post_meta( $artist_post_id, 'mds_bandcamp_artist_integration', true );
		if ( is_array( $data ) && ! empty( $data['subdomain'] ) ) {
			$sub = self::normalize_artist_subdomain( (string) $data['subdomain'] );
			if ( '' !== $sub ) {
				return $sub;
			}
		}

		$url = self::get_artist_url( $artist_post_id );
		return '' !== $url ? self::parse_bandcamp_subdomain( $url ) : '';
	}

	/**
	 * Resolves the Bandcamp artist subdomain for the Bandcamp Discography block.
	 *
	 * Precedence:
	 * 1. Explicit `artistId` block attribute (reads the live Artist CPT post meta).
	 * 2. Explicit `artist_url` block attribute (accepts full Bandcamp URL or subdomain).
	 * 3. Current post context when left blank:
	 *    - On an `artist` post: uses that Artist's Bandcamp Artist URL.
	 *    - On a `release` post (or WooCommerce product context): resolves the Release's
	 *      linked Artist (`artist_id` UUID -> Artist post) and uses that Artist's
	 *      Bandcamp Artist URL, falling back to the Release's own Bandcamp subdomain.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param mixed                $block      Optional WP_Block instance.
	 * @return string Resolved Bandcamp artist subdomain, or empty string.
	 */
	public static function resolve_discography_subdomain( array $attributes = array(), $block = null ): string {
		$artist_id = isset( $attributes['artistId'] ) ? absint( $attributes['artistId'] ) : 0;
		if ( $artist_id > 0 ) {
			$sub = self::get_artist_subdomain( $artist_id );
			if ( '' !== $sub ) {
				return $sub;
			}
		}

		$raw_url = isset( $attributes['artist_url'] ) && is_string( $attributes['artist_url'] ) ? trim( $attributes['artist_url'] ) : '';
		if ( '' !== $raw_url ) {
			$sub = self::normalize_artist_subdomain( $raw_url );
			if ( '' !== $sub ) {
				return $sub;
			}
		}

		$context_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( ! $context_id && is_object( $block ) && isset( $block->context['postId'] ) ) {
			$context_id = absint( $block->context['postId'] );
		}
		if ( ! $context_id && defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidate = absint( function_exists( 'wp_unslash' ) ? wp_unslash( $_REQUEST['post_id'] ) : $_REQUEST['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $candidate && ( ! function_exists( 'get_post_status' ) || 'publish' === get_post_status( $candidate ) || ( function_exists( 'current_user_can' ) && current_user_can( 'read_post', $candidate ) ) ) ) {
				$context_id = $candidate;
			}
		}

		if ( $context_id > 0 && function_exists( 'get_post_type' ) && 'artist' === get_post_type( $context_id ) ) {
			$sub = self::get_artist_subdomain( $context_id );
			if ( '' !== $sub ) {
				return $sub;
			}
		}

		$release_id = self::resolve_context_release_id( $attributes, $block );
		if ( $release_id > 0 ) {
			$artist_uuid = get_post_meta( $release_id, 'artist_id', true );
			if ( is_string( $artist_uuid ) && '' !== $artist_uuid && function_exists( 'get_posts' ) ) {
				$artists = get_posts(
					array(
						'post_type'      => 'artist',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'meta_key'       => '_mds_artist_uuid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'     => $artist_uuid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				);
				if ( ! empty( $artists[0]->ID ) ) {
					$sub = self::get_artist_subdomain( (int) $artists[0]->ID );
					if ( '' !== $sub ) {
						return $sub;
					}
				}
			}

			return self::get_release_subdomain( $release_id );
		}

		return '';
	}
}
