<?php
/**
 * Spotify Web API audio preview & UPC catalog provider.
 *
 * Uses OAuth 2.0 Client Credentials (`https://accounts.spotify.com/api/token`)
 * to look up albums by `upc_ean`. Leverages Spotify's native 30-second `preview_url`
 * whenever non-null (e.g., for Extended Quota Mode apps), and automatically falls
 * through cleanly to other providers when Spotify returns `preview_url: null`.
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
 * Resolves Spotify UPC album tracks and native timed previews when available.
 *
 * @since 2.0.0
 */
final class SpotifyProvider implements AudioProviderInterface {

	/**
	 * OAuth token transient key.
	 *
	 * @var string
	 */
	private const TOKEN_TRANSIENT = 'mds_ap_spotify_token';

	/**
	 * Cache TTL for successful Spotify UPC lookups (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'spotify';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Spotify', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 60;
	}

	/**
	 * Checks whether Spotify can resolve this release via OAuth+UPC or via Spotify URL embed fallback.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when credentials+UPC exist or a Spotify URL exists with embed fallback enabled.
	 */
	public function supports_release( int $post_id ): bool {
		$creds = Credentials::get( 'spotify' );
		if ( '' !== $creds['client_id'] && '' !== $creds['client_secret'] && '' !== self::get_upc( $post_id ) ) {
			return true;
		}
		if ( ProviderOptions::get_bool( 'spotify', 'embed_fallback', true ) && null !== self::get_spotify_embed_target( $post_id ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Resolves native 30-second Spotify `preview_url` tracks by UPC/EAN when available,
	 * with optional official Spotify iframe embed fallback when `embed_fallback` is enabled.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null when unavailable.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$upc            = self::get_upc( $post_id );
		$embed_fallback = ProviderOptions::get_bool( 'spotify', 'embed_fallback', true );
		$direct_target  = $embed_fallback ? self::get_spotify_embed_target( $post_id ) : null;
		$cache_target   = '' !== $upc ? $upc : ( null !== $direct_target ? $direct_target['type'] . ':' . $direct_target['id'] : '' );
		if ( '' === $cache_target ) {
			return null;
		}
		$cache_key_target = $cache_target . ( $embed_fallback ? ':embed' : ':native' );

		$fresh = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $cache_key_target );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$token = '' !== $upc ? self::get_access_token() : '';
		if ( '' === $token ) {
			if ( null !== $direct_target ) {
				$manifest = self::build_embed_manifest( $post_id, $direct_target['type'], $direct_target['id'], $direct_target['url'] );
				Cache::set( $this->get_slug(), $cache_key_target, $manifest, self::CACHE_TTL );
				return $manifest;
			}
			return null;
		}

		$search = self::api_get(
			'https://api.spotify.com/v1/search',
			array(
				'q'     => 'upc:' . $upc,
				'type'  => 'album',
				'limit' => 1,
			),
			$token
		);

		$album = is_array( $search ) && isset( $search['albums']['items'][0] ) && is_array( $search['albums']['items'][0] )
			? $search['albums']['items'][0]
			: null;

		if ( null === $album || empty( $album['id'] ) ) {
			if ( null !== $direct_target ) {
				$manifest = self::build_embed_manifest( $post_id, $direct_target['type'], $direct_target['id'], $direct_target['url'] );
				Cache::set( $this->get_slug(), $cache_key_target, $manifest, self::CACHE_TTL );
				return $manifest;
			}
			Cache::set( $this->get_slug(), $cache_key_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$album_id    = sanitize_text_field( (string) $album['id'] );
		$tracks_resp = self::api_get(
			'https://api.spotify.com/v1/albums/' . rawurlencode( $album_id ) . '/tracks',
			array( 'limit' => 50 ),
			$token
		);

		$raw_tracks = is_array( $tracks_resp ) && isset( $tracks_resp['items'] ) && is_array( $tracks_resp['items'] )
			? $tracks_resp['items']
			: array();

		$artist_name = isset( $album['artists'][0]['name'] ) ? (string) $album['artists'][0]['name'] : '';
		$source_url  = isset( $album['external_urls']['spotify'] ) ? (string) $album['external_urls']['spotify'] : '';
		$artwork     = isset( $album['images'][0]['url'] ) ? (string) $album['images'][0]['url'] : '';
		$album_title = (string) ( $album['name'] ?? '' );
		$tracks      = array();

		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['preview_url'] ) || ! is_string( $item['preview_url'] ) ) {
				continue;
			}
			$tracks[] = array(
				'index'          => isset( $item['track_number'] ) ? (int) $item['track_number'] : ( $idx + 1 ),
				'title'          => (string) ( $item['name'] ?? '' ),
				'artist'         => isset( $item['artists'][0]['name'] ) ? (string) $item['artists'][0]['name'] : $artist_name,
				'duration'       => 30.0,
				'streamUrl'      => $item['preview_url'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => true,
				'sourceUrl'      => isset( $item['external_urls']['spotify'] ) ? (string) $item['external_urls']['spotify'] : $source_url,
			);
		}

		if ( empty( $tracks ) ) {
			if ( $embed_fallback ) {
				$manifest = self::build_embed_manifest( $post_id, 'album', $album_id, $source_url, $album_title, $artist_name, $artwork );
				Cache::set( $this->get_slug(), $cache_key_target, $manifest, self::CACHE_TTL );
				return $manifest;
			}
			// Standard/dev-mode Spotify apps return preview_url: null; back off so fallback providers take over cleanly.
			Cache::set( $this->get_slug(), $cache_key_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => $album_title,
			'artist'         => $artist_name,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $source_url,
			'isTimedPreview' => true,
			'ttl'            => self::CACHE_TTL,
			'tracks'         => $tracks,
		);

		Cache::set( $this->get_slug(), $cache_key_target, $manifest, self::CACHE_TTL );
		return $manifest;
	}

	/**
	 * Builds an official Spotify iframe embed manifest.
	 *
	 * @param int    $post_id    Release post ID.
	 * @param string $type       Spotify entity type (`album`, `track`, or `playlist`).
	 * @param string $entity_id  Spotify base62 ID.
	 * @param string $source_url Canonical Spotify URL.
	 * @param string $title      Optional album/track title.
	 * @param string $artist     Optional artist label.
	 * @param string $artwork    Optional artwork URL.
	 * @return array<string, mixed> Raw manifest array.
	 */
	public static function build_embed_manifest(
		int $post_id,
		string $type,
		string $entity_id,
		string $source_url = '',
		string $title = '',
		string $artist = '',
		string $artwork = ''
	): array {
		$clean_type = in_array( $type, array( 'album', 'track', 'playlist' ), true ) ? $type : 'album';
		$clean_id   = preg_replace( '/[^A-Za-z0-9]/', '', $entity_id );
		$height     = 'track' === $clean_type ? 152 : 352;
		$iframe_src = sprintf( 'https://open.spotify.com/embed/%s/%s?utm_source=generator', $clean_type, $clean_id );

		return array(
			'provider'       => 'spotify',
			'providerLabel'  => __( 'Spotify', 'mds-audio-providers' ),
			'releaseId'      => $post_id,
			'title'          => $title,
			'artist'         => $artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => '' !== $source_url ? $source_url : sprintf( 'https://open.spotify.com/%s/%s', $clean_type, $clean_id ),
			'isTimedPreview' => true,
			'ttl'            => self::CACHE_TTL,
			'embedHtml'      => '<iframe title="' . esc_attr__( 'Spotify Audio Player', 'mds-audio-providers' ) . '" style="border:0;border-radius:12px;width:100%;height:' . (int) $height . 'px;" src="' . esc_url( $iframe_src ) . '" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>',
			'tracks'         => array(),
		);
	}

	/**
	 * Extracts a Spotify album/track/playlist ID from release post meta (`spotify_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return array{type: string, id: string, url: string}|null Target descriptor, or null.
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
	 * Verifies Spotify OAuth 2.0 Client Credentials by requesting a fresh access token.
	 *
	 * @return array{ok: bool, message: string} Connection test result.
	 */
	public static function test_connection(): array {
		delete_transient( self::TOKEN_TRANSIENT );
		$token = self::get_access_token();
		if ( '' !== $token ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected to Spotify Web API.', 'mds-audio-providers' ),
			);
		}
		return array(
			'ok'      => false,
			'message' => __( 'Spotify authentication failed. Verify Client ID and Client Secret.', 'mds-audio-providers' ),
		);
	}

	/**
	 * Obtains and caches a Spotify OAuth 2.0 Client Credentials access token.
	 *
	 * @return string Bearer token or empty string.
	 */
	private static function get_access_token(): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$creds = Credentials::get( 'spotify' );
		if ( '' === $creds['client_id'] || '' === $creds['client_secret'] ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard HTTP Basic Auth header for Spotify OAuth 2.0 Client Credentials.
		$auth     = base64_encode( $creds['client_id'] . ':' . $creds['client_secret'] );
		$response = wp_safe_remote_post(
			'https://accounts.spotify.com/api/token',
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'client_credentials',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Logging::log( 'error', 'Spotify OAuth token request failed' );
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
	 * Performs an authenticated GET request against the Spotify Web API.
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
					'Accept'        => 'application/json',
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
	 * Reads the normalized UPC/EAN from a release post.
	 *
	 * @param int $post_id Release post ID.
	 * @return string Digits-only UPC/EAN, or empty string.
	 */
	private static function get_upc( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$raw = get_post_meta( $post_id, 'upc_ean', true );
		if ( ! is_string( $raw ) ) {
			return '';
		}
		return (string) preg_replace( '/\D+/', '', $raw );
	}
}
