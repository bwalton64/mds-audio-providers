<?php
/**
 * Local Release post-meta helper for MDS Audio Providers.
 *
 * Reads and validates release metadata (UPC/EAN, Bandcamp Album ID, and provider URLs)
 * locally on the WordPress site before dispatching a lightweight request to MDS Cloud Gateway.
 * Performs zero outbound HTTP requests.
 *
 * @package   MDS\AudioProviders
 * @since     2.2.0
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts validated release metadata for all gateway-backed providers.
 *
 * @since 2.2.0
 */
final class ReleaseMeta {

	/**
	 * Reads the normalized digits-only UPC/EAN from a release post.
	 *
	 * @param int $post_id Release post ID.
	 * @return string Digits-only UPC/EAN, or empty string.
	 */
	public static function get_upc( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$raw = get_post_meta( $post_id, 'upc_ean', true );
		if ( ! is_string( $raw ) ) {
			return '';
		}
		return (string) preg_replace( '/\D+/', '', $raw );
	}

	/**
	 * Reads the Bandcamp Album ID from post meta (compatible with legacy Bandcamp Integration meta).
	 *
	 * @param int $post_id Release post ID.
	 * @return int Positive album ID, or 0.
	 */
	public static function get_bandcamp_album_id( int $post_id ): int {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return 0;
		}
		$direct = absint( (string) get_post_meta( $post_id, 'bandcamp_album_id', true ) );
		if ( $direct > 0 ) {
			return $direct;
		}
		$legacy = get_post_meta( $post_id, 'mds_bandcamp_integration', true );
		if ( is_array( $legacy ) && ! empty( $legacy['album_id'] ) ) {
			return absint( (string) $legacy['album_id'] );
		}
		return 0;
	}

	/**
	 * Reads the Bandcamp Album URL from post meta.
	 *
	 * @param int $post_id Release post ID.
	 * @return string Album URL, or empty string.
	 */
	public static function get_bandcamp_album_url( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$url = get_post_meta( $post_id, 'bandcamp_album_url', true );
		if ( is_string( $url ) && '' !== trim( $url ) ) {
			return trim( $url );
		}
		$legacy = get_post_meta( $post_id, 'mds_bandcamp_integration', true );
		if ( is_array( $legacy ) && ! empty( $legacy['url'] ) && is_string( $legacy['url'] ) ) {
			return trim( $legacy['url'] );
		}
		return '';
	}

	/**
	 * Reads a validated Audius URL (`mds_ap_audius_url` / `audius_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return string Valid `https://audius.co` URL, or empty string.
	 */
	public static function get_audius_url( int $post_id ): string {
		return self::find_https_url_for_host( $post_id, array( 'mds_ap_audius_url', 'audius_url' ), '/(^|\.)audius\.co$/i' );
	}

	/**
	 * Reads a validated SoundCloud URL (`mds_ap_soundcloud_url` / `soundcloud_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return string Valid SoundCloud URL, or empty string.
	 */
	public static function get_soundcloud_url( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		foreach ( array( 'mds_ap_soundcloud_url', 'soundcloud_url' ) as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$clean  = esc_url_raw( trim( $raw ) );
				$parsed = wp_parse_url( $clean );
				if ( ! empty( $parsed['host'] ) && preg_match( '/(^|\.)soundcloud\.com$/i', (string) $parsed['host'] ) ) {
					return $clean;
				}
			}
		}
		return '';
	}

	/**
	 * Parses a Jamendo album or track target from post meta (`mds_ap_jamendo_url` / `jamendo_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return array{type: string, id: string, url: string}|null
	 */
	public static function get_jamendo_target( int $post_id ): ?array {
		$url = self::find_https_url_for_host( $post_id, array( 'mds_ap_jamendo_url', 'jamendo_url' ), '/(^|\.)(jamendo\.com|jamen\.do)$/i' );
		if ( '' === $url ) {
			return null;
		}
		$parsed = wp_parse_url( $url );
		$path   = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( preg_match( '#^(album|track|a|t)/(\d+)(?:/|$)#i', $path, $matches ) ) {
			$raw_type = strtolower( $matches[1] );
			return array(
				'type' => ( 'a' === $raw_type || 'album' === $raw_type ) ? 'album' : 'track',
				'id'   => $matches[2],
				'url'  => $url,
			);
		}
		return null;
	}

	/**
	 * Reads the Jamendo URL for metabox display.
	 *
	 * @param int $post_id Release post ID.
	 * @return string
	 */
	public static function get_jamendo_url( int $post_id ): string {
		$target = self::get_jamendo_target( $post_id );
		return null !== $target ? $target['url'] : '';
	}

	/**
	 * Parses a TIDAL album/track/playlist target from post meta (`mds_ap_tidal_url` / `tidal_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return array{type: string, id: string, url: string}|null
	 */
	public static function get_tidal_target( int $post_id ): ?array {
		$url = self::find_https_url_for_host( $post_id, array( 'mds_ap_tidal_url', 'tidal_url' ), '/(^|\.)tidal\.com$/i' );
		if ( '' === $url ) {
			return null;
		}
		$parsed = wp_parse_url( $url );
		$path   = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( preg_match( '#^(?:browse/)?(album|track|playlist)s?/([A-Za-z0-9\-]{4,64})(?:/|$)#i', $path, $matches ) ) {
			return array(
				'type' => strtolower( $matches[1] ),
				'id'   => $matches[2],
				'url'  => $url,
			);
		}
		return null;
	}

	/**
	 * Reads the TIDAL URL for metabox display.
	 *
	 * @param int $post_id Release post ID.
	 * @return string
	 */
	public static function get_tidal_url( int $post_id ): string {
		$target = self::get_tidal_target( $post_id );
		return null !== $target ? $target['url'] : '';
	}

	/**
	 * Reads a validated Mixcloud show URL (`mds_ap_mixcloud_url` / `mixcloud_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return string
	 */
	public static function get_mixcloud_url( int $post_id ): string {
		$url = self::find_https_url_for_host( $post_id, array( 'mds_ap_mixcloud_url', 'mixcloud_url' ), '/(^|\.)mixcloud\.com$/i' );
		if ( '' === $url ) {
			return '';
		}
		$parsed   = wp_parse_url( $url );
		$path     = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		return count( $segments ) >= 2 ? $url : '';
	}

	/**
	 * Reads a validated Audiomack song/album/playlist URL (`mds_ap_audiomack_url` / `audiomack_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return string
	 */
	public static function get_audiomack_url( int $post_id ): string {
		$url = self::find_https_url_for_host( $post_id, array( 'mds_ap_audiomack_url', 'audiomack_url' ), '/(^|\.)audiomack\.com$/i' );
		if ( '' === $url ) {
			return '';
		}
		$parsed   = wp_parse_url( $url );
		$path     = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		if ( count( $segments ) < 3 ) {
			return '';
		}
		if ( 'embed' === strtolower( $segments[0] ) && count( $segments ) >= 4 ) {
			array_shift( $segments );
		}
		$type = strtolower( $segments[1] );
		return in_array( $type, array( 'song', 'album', 'playlist' ), true ) ? $url : '';
	}

	/**
	 * Extracts a Spotify album/track/playlist target from post meta (`mds_ap_spotify_url` / `spotify_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return array{type: string, id: string, url: string}|null
	 */
	public static function get_spotify_embed_target( int $post_id ): ?array {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return null;
		}
		foreach ( array( 'mds_ap_spotify_url', 'spotify_url' ) as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
				continue;
			}
			$clean  = esc_url_raw( trim( $raw ) );
			$parsed = wp_parse_url( $clean );
			if ( empty( $parsed['host'] ) || ! preg_match( '/(^|\.)open\.spotify\.com$/i', (string) $parsed['host'] ) ) {
				continue;
			}
			$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
			if ( preg_match( '#^(?:intl-[a-z-]+/)?(album|track|playlist)/([A-Za-z0-9]{10,40})(?:/|$)#i', $path, $matches ) ) {
				return array(
					'type' => strtolower( $matches[1] ),
					'id'   => $matches[2],
					'url'  => $clean,
				);
			}
		}
		return null;
	}

	/**
	 * Reads the first valid HTTPS URL matching `$host_regex` across candidate meta keys.
	 *
	 * @param int      $post_id    Release post ID.
	 * @param string[] $meta_keys  Ordered post meta keys.
	 * @param string   $host_regex Host validation regex.
	 * @return string
	 */
	private static function find_https_url_for_host( int $post_id, array $meta_keys, string $host_regex ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		foreach ( $meta_keys as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
				continue;
			}
			$clean  = esc_url_raw( trim( $raw ) );
			$parsed = wp_parse_url( $clean );
			if (
				! empty( $parsed['scheme'] )
				&& 'https' === strtolower( (string) $parsed['scheme'] )
				&& ! empty( $parsed['host'] )
				&& preg_match( $host_regex, (string) $parsed['host'] )
			) {
				return $clean;
			}
		}
		return '';
	}
}
