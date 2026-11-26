<?php
/**
 * Funkwhale self-hosted / Fediverse pod native HTML5 audio provider.
 *
 * Resolves Funkwhale album and track URLs (`https://<pod>/library/(albums|tracks)/{id}`)
 * via the pod's REST API (`/api/v1/tracks/`) with optional Bearer token authentication
 * for private pods.
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
 * Resolves native HTML5 audio streams from self-hosted or federated Funkwhale pods.
 *
 * @since 2.1.0
 */
final class FunkwhaleProvider implements AudioProviderInterface {

	/**
	 * Cache TTL for Funkwhale manifests (1 hour).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 3600;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'funkwhale';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Funkwhale', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 58;
	}

	/**
	 * Checks whether the release has a valid Funkwhale album or track URL.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when a valid Funkwhale URL exists.
	 */
	public function supports_release( int $post_id ): bool {
		return null !== self::get_funkwhale_target( $post_id );
	}

	/**
	 * Resolves a Funkwhale album or track URL into a native HTML5 audio manifest.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$target = self::get_funkwhale_target( $post_id );
		if ( null === $target ) {
			return null;
		}

		$cache_target = $target['url'];
		$fresh        = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $cache_target );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$creds   = Credentials::get( 'funkwhale' );
		$headers = array( 'Accept' => 'application/json' );
		if ( '' !== $creds['client_secret'] ) {
			$headers['Authorization'] = 'Bearer ' . $creds['client_secret'];
		}

		if ( 'album' === $target['type'] ) {
			$api_url = add_query_arg(
				array(
					'album'     => $target['id'],
					'page_size' => 12,
				),
				$target['origin'] . '/api/v1/tracks/'
			);
		} else {
			$api_url = $target['origin'] . '/api/v1/tracks/' . rawurlencode( $target['id'] ) . '/';
		}

		$response = wp_safe_remote_get(
			$api_url,
			array(
				'timeout' => 8,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Logging::log( 'error', 'Funkwhale API lookup failed', array( 'url' => $api_url ) );
			Cache::set( $this->get_slug(), $cache_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$decoded    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$raw_tracks = array();
		if ( is_array( $decoded ) && isset( $decoded['results'] ) && is_array( $decoded['results'] ) ) {
			$raw_tracks = $decoded['results'];
		} elseif ( is_array( $decoded ) && ! empty( $decoded['listen_url'] ) ) {
			$raw_tracks = array( $decoded );
		}

		$tracks      = array();
		$album_title = '';
		$artist_name = '';
		$artwork     = '';

		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['listen_url'] ) || ! is_string( $item['listen_url'] ) ) {
				continue;
			}

			$stream_url = str_starts_with( $item['listen_url'], 'http' )
				? $item['listen_url']
				: $target['origin'] . '/' . ltrim( $item['listen_url'], '/' );

			$track_artist = isset( $item['artist']['name'] ) ? (string) $item['artist']['name'] : '';
			if ( '' === $artist_name && '' !== $track_artist ) {
				$artist_name = $track_artist;
			}
			if ( '' === $album_title && isset( $item['album']['title'] ) ) {
				$album_title = (string) $item['album']['title'];
			}
			if ( '' === $artwork && isset( $item['album']['cover']['urls']['medium_square_crop'] ) ) {
				$artwork = (string) $item['album']['cover']['urls']['medium_square_crop'];
			}

			$tracks[] = array(
				'index'          => isset( $item['position'] ) ? max( 1, (int) $item['position'] ) : ( $idx + 1 ),
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => '' !== $track_artist ? $track_artist : $artist_name,
				'duration'       => isset( $item['uploads'][0]['duration'] ) ? (float) $item['uploads'][0]['duration'] : 0.0,
				'streamUrl'      => $stream_url,
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => false,
				'sourceUrl'      => $target['url'],
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
	 * Verifies connectivity to the configured Funkwhale Pod Base URL.
	 *
	 * @return array{ok: bool, message: string} Connection test result.
	 */
	public static function test_connection(): array {
		$creds   = Credentials::get( 'funkwhale' );
		$pod_url = rtrim( esc_url_raw( $creds['client_id'] ), '/' );
		$parsed  = wp_parse_url( $pod_url );
		if ( '' === $pod_url || empty( $parsed['scheme'] ) || 'https' !== strtolower( (string) $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Enter a valid HTTPS Funkwhale Pod URL (e.g. https://open.audio) to test.', 'mds-audio-providers' ),
			);
		}

		$headers = array( 'Accept' => 'application/json' );
		if ( '' !== $creds['client_secret'] ) {
			$headers['Authorization'] = 'Bearer ' . $creds['client_secret'];
		}

		$response = wp_safe_remote_get(
			$pod_url . '/api/v1/instance/nodeinfo/2.0/',
			array(
				'timeout' => 8,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Could not connect to the Funkwhale pod.', 'mds-audio-providers' ),
			);
		}

		$decoded  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$software = is_array( $decoded ) && isset( $decoded['software']['name'] ) ? strtolower( (string) $decoded['software']['name'] ) : '';
		if ( 'funkwhale' === $software ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected to Funkwhale pod.', 'mds-audio-providers' ),
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'Endpoint responded, but does not identify as a Funkwhale pod.', 'mds-audio-providers' ),
		);
	}

	/**
	 * Parses a Funkwhale album or track URL from release post meta (`mds_ap_funkwhale_url` / `funkwhale_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return array{type: string, id: string, origin: string, url: string}|null Target descriptor, or null.
	 */
	public static function get_funkwhale_target( int $post_id ): ?array {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return null;
		}
		foreach ( array( 'mds_ap_funkwhale_url', 'funkwhale_url' ) as $key ) {
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
			) {
				continue;
			}
			$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
			if ( preg_match( '#^(?:library|api/v1)/(albums|tracks)/([A-Za-z0-9\-]+)(?:/|$)#i', $path, $matches ) ) {
				$type   = 'albums' === strtolower( $matches[1] ) ? 'album' : 'track';
				$origin = 'https://' . $parsed['host'] . ( ! empty( $parsed['port'] ) ? ':' . (int) $parsed['port'] : '' );
				return array(
					'type'   => $type,
					'id'     => $matches[2],
					'origin' => $origin,
					'url'    => $clean,
				);
			}
		}
		return null;
	}

	/**
	 * Reads the raw Funkwhale URL from release post meta for metabox display.
	 *
	 * @param int $post_id Release post ID.
	 * @return string Funkwhale URL, or empty string.
	 */
	public static function get_funkwhale_url( int $post_id ): string {
		$target = self::get_funkwhale_target( $post_id );
		return null !== $target ? $target['url'] : '';
	}
}
