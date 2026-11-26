<?php
/**
 * Audius Open Audio Protocol preview provider.
 *
 * Resolves public Audius track, album, and playlist URLs via `https://api.audius.co/v1/resolve`
 * and serves native 320kbps HTML5 audio streams (`/v1/tracks/{id}/stream`).
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
 * Resolves native HTML5 audio streams from Audius track/album/playlist URLs.
 *
 * @since 2.1.0
 */
final class AudiusProvider implements AudioProviderInterface {

	/**
	 * Public Audius API base URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://api.audius.co/v1';

	/**
	 * Application identifier passed to Audius discovery nodes.
	 *
	 * @var string
	 */
	private const APP_NAME = 'mds_audio_providers';

	/**
	 * Cache TTL for resolved Audius manifests (1 hour).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 3600;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'audius';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Audius', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 45;
	}

	/**
	 * Checks whether the release has a valid Audius URL configured.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when an Audius URL exists.
	 */
	public function supports_release( int $post_id ): bool {
		return '' !== self::get_audius_url( $post_id );
	}

	/**
	 * Resolves an Audius track, album, or playlist into a normalized audio manifest.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$audius_url = self::get_audius_url( $post_id );
		if ( '' === $audius_url ) {
			return null;
		}

		$fresh = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $audius_url );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$resolve_url = add_query_arg(
			array(
				'url'      => $audius_url,
				'app_name' => self::APP_NAME,
			),
			self::API_BASE . '/resolve'
		);

		$response = wp_safe_remote_get(
			$resolve_url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logging::log(
				'error',
				'Audius resolve request failed',
				array(
					'url'   => $audius_url,
					'error' => $response->get_error_message(),
				)
			);
			Cache::set( $this->get_slug(), $audius_url, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			Cache::set( $this->get_slug(), $audius_url, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data    = is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] )
			? $decoded['data']
			: null;

		if ( null === $data ) {
			Cache::set( $this->get_slug(), $audius_url, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		// Audius `/resolve` returns a list wrapper for playlists or a single object for tracks.
		$entity = isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : $data;

		$raw_tracks = array();
		if ( isset( $entity['tracks'] ) && is_array( $entity['tracks'] ) ) {
			$raw_tracks = array_slice( $entity['tracks'], 0, 12 );
		} elseif ( ! empty( $entity['id'] ) && isset( $entity['title'] ) ) {
			$raw_tracks = array( $entity );
		}

		$default_artist = isset( $entity['user']['name'] ) ? (string) $entity['user']['name'] : '';
		$tracks         = array();

		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			if ( isset( $item['is_streamable'] ) && false === $item['is_streamable'] ) {
				continue;
			}
			$track_id = sanitize_text_field( (string) $item['id'] );
			if ( '' === $track_id ) {
				continue;
			}

			$stream_url = add_query_arg(
				array( 'app_name' => self::APP_NAME ),
				self::API_BASE . '/tracks/' . rawurlencode( $track_id ) . '/stream'
			);

			$permalink = isset( $item['permalink'] ) && is_string( $item['permalink'] ) && '' !== $item['permalink']
				? 'https://audius.co' . $item['permalink']
				: $audius_url;

			$tracks[] = array(
				'index'          => $idx + 1,
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => isset( $item['user']['name'] ) ? (string) $item['user']['name'] : $default_artist,
				'duration'       => isset( $item['duration'] ) ? (float) $item['duration'] : 0.0,
				'streamUrl'      => $stream_url,
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => false,
				'sourceUrl'      => $permalink,
			);
		}

		if ( empty( $tracks ) ) {
			Cache::set( $this->get_slug(), $audius_url, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$artwork = '';
		if ( isset( $entity['artwork'] ) && is_array( $entity['artwork'] ) ) {
			$artwork = (string) ( $entity['artwork']['480x480'] ?? $entity['artwork']['150x150'] ?? '' );
		}

		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => (string) ( $entity['playlist_name'] ?? $entity['title'] ?? '' ),
			'artist'         => $default_artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $audius_url,
			'isTimedPreview' => false,
			'ttl'            => self::CACHE_TTL,
			'tracks'         => $tracks,
		);

		Cache::set( $this->get_slug(), $audius_url, $manifest, self::CACHE_TTL );
		return $manifest;
	}

	/**
	 * Reads the Audius track/album/playlist URL from release post meta (with fallback to `audius_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return string Valid https://audius.co URL, or empty string.
	 */
	public static function get_audius_url( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		foreach ( array( 'mds_ap_audius_url', 'audius_url' ) as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$clean  = esc_url_raw( trim( $raw ) );
				$parsed = wp_parse_url( $clean );
				if (
					! empty( $parsed['scheme'] )
					&& 'https' === strtolower( (string) $parsed['scheme'] )
					&& ! empty( $parsed['host'] )
					&& preg_match( '/(^|\.)audius\.co$/i', (string) $parsed['host'] )
				) {
					return $clean;
				}
			}
		}
		return '';
	}
}
