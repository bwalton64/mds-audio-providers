<?php
/**
 * Jamendo API v3.0 native HTML5 audio provider.
 *
 * Uses a free Jamendo developer `client_id` to resolve direct MP3 audio streams
 * for Jamendo albums and tracks (`https://api.jamendo.com/v3.0/tracks/`).
 *
 * @package MDS\AudioProviders
 * @since   2.1.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Providers;

use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Core\Logging;
use MDS\ReleaseManager\Core\Audio\AudioProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves native HTML5 `.mp3` streams from Jamendo album/track URLs.
 *
 * @since 2.1.0
 */
final class JamendoProvider implements AudioProviderInterface {

	/**
	 * Cache TTL for Jamendo track manifests (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'jamendo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Jamendo', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 55;
	}

	/**
	 * Checks whether a Jamendo `client_id` and a valid release Jamendo URL exist.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when credentials and Jamendo URL exist.
	 */
	public function supports_release( int $post_id ): bool {
		$creds = Credentials::get( 'jamendo' );
		if ( '' === $creds['client_id'] ) {
			return false;
		}
		return null !== self::get_jamendo_target( $post_id );
	}

	/**
	 * Resolves a Jamendo album or track into a native HTML5 audio manifest.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$target = self::get_jamendo_target( $post_id );
		if ( null === $target ) {
			return null;
		}

		$creds = Credentials::get( 'jamendo' );
		if ( '' === $creds['client_id'] ) {
			return null;
		}

		$cache_target = $target['type'] . ':' . $target['id'];
		$fresh        = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $cache_target );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$query = array(
			'client_id'   => $creds['client_id'],
			'format'      => 'json',
			'audioformat' => 'mp32',
			'limit'       => 20,
		);
		if ( 'album' === $target['type'] ) {
			$query['album_id'] = $target['id'];
		} else {
			$query['id'] = $target['id'];
		}

		$url      = add_query_arg( $query, 'https://api.jamendo.com/v3.0/tracks/' );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Logging::log( 'error', 'Jamendo API lookup failed', array( 'target' => $cache_target ) );
			Cache::set( $this->get_slug(), $cache_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$results = is_array( $decoded ) && isset( $decoded['results'] ) && is_array( $decoded['results'] )
			? $decoded['results']
			: array();

		$tracks      = array();
		$album_title = '';
		$artist_name = '';
		$artwork     = '';

		foreach ( $results as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['audio'] ) || ! is_string( $item['audio'] ) ) {
				continue;
			}
			if ( '' === $album_title && ! empty( $item['album_name'] ) ) {
				$album_title = (string) $item['album_name'];
			}
			if ( '' === $artist_name && ! empty( $item['artist_name'] ) ) {
				$artist_name = (string) $item['artist_name'];
			}
			if ( '' === $artwork && ! empty( $item['album_image'] ) ) {
				$artwork = (string) $item['album_image'];
			}

			$tracks[] = array(
				'index'          => isset( $item['position'] ) ? max( 1, (int) $item['position'] ) : ( $idx + 1 ),
				'title'          => (string) ( $item['name'] ?? '' ),
				'artist'         => (string) ( $item['artist_name'] ?? $artist_name ),
				'duration'       => isset( $item['duration'] ) ? (float) $item['duration'] : 0.0,
				'streamUrl'      => $item['audio'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => false,
				'sourceUrl'      => (string) ( $item['shareurl'] ?? $target['url'] ),
			);
		}

		if ( empty( $tracks ) ) {
			Cache::set( $this->get_slug(), $cache_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => $album_title,
			'artist'         => $artist_name,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $target['url'],
			'isTimedPreview' => false,
			'ttl'            => self::CACHE_TTL,
			'tracks'         => $tracks,
		);

		Cache::set( $this->get_slug(), $cache_target, $manifest, self::CACHE_TTL );
		return $manifest;
	}

	/**
	 * Verifies the configured Jamendo API `client_id` against `/v3.0/tracks/`.
	 *
	 * @return array{ok: bool, message: string} Connection test result.
	 */
	public static function test_connection(): array {
		$creds = Credentials::get( 'jamendo' );
		if ( '' === $creds['client_id'] ) {
			return array(
				'ok'      => false,
				'message' => __( 'Jamendo Client ID is not configured.', 'mds-audio-providers' ),
			);
		}

		$url      = add_query_arg(
			array(
				'client_id' => $creds['client_id'],
				'format'    => 'json',
				'limit'     => 1,
			),
			'https://api.jamendo.com/v3.0/tracks/'
		);
		$response = wp_safe_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Could not reach Jamendo API v3.0.', 'mds-audio-providers' ),
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$status  = is_array( $decoded ) && isset( $decoded['headers']['status'] ) ? (string) $decoded['headers']['status'] : '';
		if ( 'success' === $status ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected to Jamendo API v3.0.', 'mds-audio-providers' ),
			);
		}

		$err = is_array( $decoded ) && ! empty( $decoded['headers']['error_message'] )
			? (string) $decoded['headers']['error_message']
			: __( 'Invalid Jamendo Client ID.', 'mds-audio-providers' );

		return array(
			'ok'      => false,
			'message' => $err,
		);
	}

	/**
	 * Parses a Jamendo album or track URL from release post meta (`mds_ap_jamendo_url` / `jamendo_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return array{type: string, id: string, url: string}|null Target descriptor, or null.
	 */
	public static function get_jamendo_target( int $post_id ): ?array {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return null;
		}
		foreach ( array( 'mds_ap_jamendo_url', 'jamendo_url' ) as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
				continue;
			}
			$clean  = esc_url_raw( trim( $raw ) );
			$parsed = wp_parse_url( $clean );
			if (
				empty( $parsed['scheme'] )
				|| 'https' !== strtolower( (string) $parsed['scheme'] )
				|| empty( $parsed['host'] )
				|| ! preg_match( '/(^|\.)(jamendo\.com|jamen\.do)$/i', (string) $parsed['host'] )
			) {
				continue;
			}
			$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
			if ( preg_match( '#^(album|track|a|t)/(\d+)(?:/|$)#i', $path, $matches ) ) {
				$raw_type = strtolower( $matches[1] );
				$type     = ( 'a' === $raw_type || 'album' === $raw_type ) ? 'album' : 'track';
				return array(
					'type' => $type,
					'id'   => $matches[2],
					'url'  => $clean,
				);
			}
		}
		return null;
	}

	/**
	 * Reads the raw Jamendo URL from release post meta for metabox display.
	 *
	 * @param int $post_id Release post ID.
	 * @return string Jamendo URL, or empty string.
	 */
	public static function get_jamendo_url( int $post_id ): string {
		$target = self::get_jamendo_target( $post_id );
		return null !== $target ? $target['url'] : '';
	}
}
