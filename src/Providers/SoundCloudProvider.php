<?php
/**
 * SoundCloud OAuth 2.1 audio preview provider.
 *
 * Uses OAuth 2.1 Client Credentials (`https://secure.soundcloud.com/oauth/token`)
 * to resolve SoundCloud track/playlist URLs and retrieve official 30-second previews
 * (`/tracks/{urn}/preview`) with fallback to stream endpoints.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
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
 * Resolves SoundCloud track/playlist previews via the official SoundCloud API.
 *
 * @since 2.0.0
 */
final class SoundCloudProvider implements AudioProviderInterface {

	/**
	 * OAuth token transient key.
	 *
	 * @var string
	 */
	private const TOKEN_TRANSIENT = 'mds_ap_soundcloud_token';

	/**
	 * Stream manifest TTL (15 minutes, since SoundCloud signed stream URLs expire).
	 *
	 * @var int
	 */
	private const STREAM_TTL = 900;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'soundcloud';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'SoundCloud', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 50;
	}

	/**
	 * Checks whether SoundCloud credentials are configured and the release has a SoundCloud URL.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when credentials and release SoundCloud URL exist.
	 */
	public function supports_release( int $post_id ): bool {
		$creds = Credentials::get( 'soundcloud' );
		if ( '' === $creds['client_id'] || '' === $creds['client_secret'] ) {
			return false;
		}
		return '' !== self::get_soundcloud_url( $post_id );
	}

	/**
	 * Resolves a SoundCloud track or playlist into a normalized audio manifest.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$sc_url = self::get_soundcloud_url( $post_id );
		if ( '' === $sc_url ) {
			return null;
		}

		$fresh = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $sc_url );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$token = self::get_access_token();
		if ( '' === $token ) {
			return null;
		}

		$resolved = self::api_get(
			'https://api.soundcloud.com/resolve',
			array( 'url' => $sc_url ),
			$token
		);
		if ( ! is_array( $resolved ) ) {
			Cache::set( $this->get_slug(), $sc_url, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$kind       = (string) ( $resolved['kind'] ?? '' );
		$raw_tracks = array();
		if ( 'playlist' === $kind && isset( $resolved['tracks'] ) && is_array( $resolved['tracks'] ) ) {
			$raw_tracks = array_slice( $resolved['tracks'], 0, 20 );
		} elseif ( 'track' === $kind ) {
			$raw_tracks = array( $resolved );
		}

		$tracks = array();
		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$urn = isset( $item['urn'] ) && is_string( $item['urn'] )
				? $item['urn']
				: ( ! empty( $item['id'] ) ? 'soundcloud:tracks:' . absint( (string) $item['id'] ) : '' );
			if ( '' === $urn ) {
				continue;
			}

			$stream_info = $this->resolve_track_stream( $urn, $token );
			if ( null === $stream_info ) {
				continue;
			}

			$tracks[] = array(
				'index'          => $idx + 1,
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => isset( $item['user']['username'] ) ? (string) $item['user']['username'] : '',
				'duration'       => $stream_info['isTimedPreview']
					? 30.0
					: ( isset( $item['duration'] ) ? ( (float) $item['duration'] / 1000.0 ) : 0.0 ),
				'streamUrl'      => $stream_info['url'],
				'mimeType'       => $stream_info['mime'],
				'isTimedPreview' => $stream_info['isTimedPreview'],
				'sourceUrl'      => (string) ( $item['permalink_url'] ?? $sc_url ),
			);
		}

		if ( empty( $tracks ) ) {
			Cache::set( $this->get_slug(), $sc_url, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$artwork  = (string) ( $resolved['artwork_url'] ?? '' );
		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => (string) ( $resolved['title'] ?? '' ),
			'artist'         => isset( $resolved['user']['username'] ) ? (string) $resolved['user']['username'] : '',
			'artworkUrl'     => str_replace( '-large.', '-t300x300.', $artwork ),
			'sourceUrl'      => (string) ( $resolved['permalink_url'] ?? $sc_url ),
			'isTimedPreview' => (bool) $tracks[0]['isTimedPreview'],
			'ttl'            => self::STREAM_TTL,
			'tracks'         => $tracks,
		);

		Cache::set( $this->get_slug(), $sc_url, $manifest, self::STREAM_TTL );
		return $manifest;
	}

	/**
	 * Verifies SoundCloud OAuth 2.1 Client Credentials by requesting a fresh access token.
	 *
	 * @return array{ok: bool, message: string} Connection test result.
	 */
	public static function test_connection(): array {
		delete_transient( self::TOKEN_TRANSIENT );
		$token = self::get_access_token();
		if ( '' !== $token ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected to SoundCloud OAuth 2.1 API.', 'mds-audio-providers' ),
			);
		}
		return array(
			'ok'      => false,
			'message' => __( 'SoundCloud authentication failed. Verify Client ID and Client Secret.', 'mds-audio-providers' ),
		);
	}

	/**
	 * Resolves the native 30s preview URL (preferred) or progressive stream URL for a track URN.
	 *
	 * @param string $urn   SoundCloud track URN.
	 * @param string $token OAuth Bearer token.
	 * @return array{url: string, mime: string, isTimedPreview: bool}|null Stream details.
	 */
	private function resolve_track_stream( string $urn, string $token ): ?array {
		$encoded = rawurlencode( $urn );

		// 1. Prefer native 30-second timed preview endpoint.
		$preview = self::api_get( "https://api.soundcloud.com/tracks/{$encoded}/preview", array(), $token );
		if ( is_array( $preview ) && ! empty( $preview['preview_mp3_128_url'] ) && is_string( $preview['preview_mp3_128_url'] ) ) {
			return array(
				'url'            => $preview['preview_mp3_128_url'],
				'mime'           => 'audio/mpeg',
				'isTimedPreview' => true,
			);
		}

		// 2. Fallback to progressive MP3 or HLS stream.
		$streams = self::api_get( "https://api.soundcloud.com/tracks/{$encoded}/streams", array(), $token );
		if ( is_array( $streams ) ) {
			if ( ! empty( $streams['http_mp3_128_url'] ) && is_string( $streams['http_mp3_128_url'] ) ) {
				return array(
					'url'            => $streams['http_mp3_128_url'],
					'mime'           => 'audio/mpeg',
					'isTimedPreview' => false,
				);
			}
			if ( ! empty( $streams['hls_aac_160_url'] ) && is_string( $streams['hls_aac_160_url'] ) ) {
				return array(
					'url'            => $streams['hls_aac_160_url'],
					'mime'           => 'application/x-mpegURL',
					'isTimedPreview' => false,
				);
			}
		}

		return null;
	}

	/**
	 * Obtains and caches a SoundCloud OAuth 2.1 Client Credentials access token.
	 *
	 * @return string Bearer token or empty string.
	 */
	private static function get_access_token(): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$creds = Credentials::get( 'soundcloud' );
		if ( '' === $creds['client_id'] || '' === $creds['client_secret'] ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard HTTP Basic Auth header for OAuth 2.1 Client Credentials.
		$auth     = base64_encode( $creds['client_id'] . ':' . $creds['client_secret'] );
		$response = wp_safe_remote_post(
			'https://secure.soundcloud.com/oauth/token',
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
					'Accept'        => 'application/json; charset=utf-8',
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'client_credentials',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Logging::log( 'error', 'SoundCloud OAuth token request failed' );
			return '';
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token   = is_array( $decoded ) && ! empty( $decoded['access_token'] ) && is_string( $decoded['access_token'] )
			? $decoded['access_token']
			: '';
		if ( '' === $token ) {
			return '';
		}

		$expires_in = isset( $decoded['expires_in'] ) ? max( 60, (int) $decoded['expires_in'] - 60 ) : 3000;
		set_transient( self::TOKEN_TRANSIENT, $token, $expires_in );
		return $token;
	}

	/**
	 * Performs an authenticated GET request against the SoundCloud API.
	 *
	 * @param string               $endpoint API endpoint URL.
	 * @param array<string, mixed> $query    Query parameters.
	 * @param string               $token    OAuth Bearer token.
	 * @return array<string, mixed>|null Decoded JSON array, or null.
	 */
	private static function api_get( string $endpoint, array $query, string $token ): ?array {
		$url      = ! empty( $query ) ? add_query_arg( $query, $endpoint ) : $endpoint;
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json; charset=utf-8',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Reads the SoundCloud track/playlist URL from release post meta (with fallback to `soundcloud_url` link field).
	 *
	 * @param int $post_id Release post ID.
	 * @return string Valid https://soundcloud.com URL, or empty string.
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
}
