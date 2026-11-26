<?php
/**
 * TIDAL Open API v2 & Official Embed audio provider.
 *
 * Uses OAuth 2.0 Client Credentials (`https://auth.tidal.com/v1/oauth2/token`)
 * to resolve albums by UPC/barcode via `https://openapi.tidal.com/v2/albums`,
 * or parses a release's TIDAL URL directly, and renders the official TIDAL
 * iframe embed (`https://embed.tidal.com/...`).
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
 * Resolves TIDAL albums, tracks, and playlists via UPC lookup or TIDAL URL.
 *
 * @since 2.1.0
 */
final class TidalProvider implements AudioProviderInterface {

	/**
	 * OAuth token transient key.
	 *
	 * @var string
	 */
	private const TOKEN_TRANSIENT = 'mds_ap_tidal_token';

	/**
	 * Cache TTL for resolved TIDAL embed manifests (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'tidal';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'TIDAL', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 65;
	}

	/**
	 * Checks whether TIDAL can resolve via OAuth+UPC or via a release TIDAL URL.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when credentials+UPC or a TIDAL URL exists.
	 */
	public function supports_release( int $post_id ): bool {
		if ( null !== self::get_tidal_target( $post_id ) ) {
			return true;
		}
		$creds = Credentials::get( 'tidal' );
		if ( '' !== $creds['client_id'] && '' !== $creds['client_secret'] && '' !== self::get_upc( $post_id ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Resolves a TIDAL release into an official TIDAL embed manifest.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$direct_target = self::get_tidal_target( $post_id );
		$upc           = self::get_upc( $post_id );
		$cache_target  = null !== $direct_target
			? $direct_target['type'] . ':' . $direct_target['id']
			: ( '' !== $upc ? 'upc:' . $upc : '' );

		if ( '' === $cache_target ) {
			return null;
		}

		$fresh = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $cache_target );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		if ( null !== $direct_target ) {
			$manifest = self::build_embed_manifest(
				$post_id,
				$direct_target['type'],
				$direct_target['id'],
				$direct_target['url']
			);
			Cache::set( $this->get_slug(), $cache_target, $manifest, self::CACHE_TTL );
			return $manifest;
		}

		$token = self::get_access_token();
		if ( '' === $token ) {
			return null;
		}

		$lookup_url = add_query_arg(
			array(
				'filter[barcodeId]' => $upc,
				'countryCode'       => 'US',
			),
			'https://openapi.tidal.com/v2/albums'
		);

		$response = wp_safe_remote_get(
			$lookup_url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/vnd.api+json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Cache::set( $this->get_slug(), $cache_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$album   = is_array( $decoded ) && isset( $decoded['data'][0] ) && is_array( $decoded['data'][0] )
			? $decoded['data'][0]
			: null;

		if ( null === $album || empty( $album['id'] ) ) {
			Cache::set( $this->get_slug(), $cache_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$album_id = preg_replace( '/\D+/', '', (string) $album['id'] );
		if ( '' === $album_id ) {
			Cache::set( $this->get_slug(), $cache_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$attrs    = isset( $album['attributes'] ) && is_array( $album['attributes'] ) ? $album['attributes'] : array();
		$title    = (string) ( $attrs['title'] ?? '' );
		$manifest = self::build_embed_manifest(
			$post_id,
			'album',
			$album_id,
			'https://tidal.com/browse/album/' . $album_id,
			$title
		);

		Cache::set( $this->get_slug(), $cache_target, $manifest, self::CACHE_TTL );
		return $manifest;
	}

	/**
	 * Verifies TIDAL OAuth 2.0 Client Credentials by requesting a fresh access token.
	 *
	 * @return array{ok: bool, message: string} Connection test result.
	 */
	public static function test_connection(): array {
		delete_transient( self::TOKEN_TRANSIENT );
		$token = self::get_access_token();
		if ( '' !== $token ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected to TIDAL Open API v2.', 'mds-audio-providers' ),
			);
		}
		return array(
			'ok'      => false,
			'message' => __( 'TIDAL authentication failed. Verify Client ID and Client Secret.', 'mds-audio-providers' ),
		);
	}

	/**
	 * Builds an official TIDAL iframe embed manifest.
	 *
	 * @param int    $post_id    Release post ID.
	 * @param string $type       Entity type (`album`, `track`, or `playlist`).
	 * @param string $entity_id  TIDAL entity ID.
	 * @param string $source_url Canonical TIDAL URL.
	 * @param string $title      Optional title.
	 * @return array<string, mixed> Raw manifest array.
	 */
	public static function build_embed_manifest(
		int $post_id,
		string $type,
		string $entity_id,
		string $source_url = '',
		string $title = ''
	): array {
		$path_map   = array(
			'album'    => array(
				'segment' => 'albums',
				'height'  => 275,
			),
			'track'    => array(
				'segment' => 'tracks',
				'height'  => 120,
			),
			'playlist' => array(
				'segment' => 'playlists',
				'height'  => 380,
			),
		);
		$config     = $path_map[ $type ] ?? $path_map['album'];
		$clean_id   = preg_replace( '/[^A-Za-z0-9\-]/', '', $entity_id );
		$iframe_src = sprintf( 'https://embed.tidal.com/%s/%s', $config['segment'], $clean_id );

		return array(
			'provider'       => 'tidal',
			'providerLabel'  => __( 'TIDAL', 'mds-audio-providers' ),
			'releaseId'      => $post_id,
			'title'          => $title,
			'sourceUrl'      => '' !== $source_url ? $source_url : sprintf( 'https://tidal.com/browse/%s/%s', $type, $clean_id ),
			'isTimedPreview' => true,
			'ttl'            => self::CACHE_TTL,
			'embedHtml'      => '<iframe title="' . esc_attr__( 'TIDAL Audio Player', 'mds-audio-providers' ) . '" src="' . esc_url( $iframe_src ) . '" width="100%" height="' . (int) $config['height'] . '" style="border:0;" allow="encrypted-media; fullscreen; clipboard-write; web-share" loading="lazy"></iframe>',
			'tracks'         => array(),
		);
	}

	/**
	 * Parses a TIDAL album, track, or playlist URL from release post meta (`mds_ap_tidal_url` / `tidal_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return array{type: string, id: string, url: string}|null Target descriptor, or null.
	 */
	public static function get_tidal_target( int $post_id ): ?array {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return null;
		}
		foreach ( array( 'mds_ap_tidal_url', 'tidal_url' ) as $key ) {
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
				|| ! preg_match( '/(^|\.)tidal\.com$/i', (string) $parsed['host'] )
			) {
				continue;
			}
			$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
			if ( preg_match( '#^(?:browse/)?(album|track|playlist)s?/([A-Za-z0-9\-]{4,64})(?:/|$)#i', $path, $matches ) ) {
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
	 * Reads the raw TIDAL URL from release post meta for metabox display.
	 *
	 * @param int $post_id Release post ID.
	 * @return string TIDAL URL, or empty string.
	 */
	public static function get_tidal_url( int $post_id ): string {
		$target = self::get_tidal_target( $post_id );
		return null !== $target ? $target['url'] : '';
	}

	/**
	 * Obtains and caches a TIDAL OAuth 2.0 Client Credentials access token.
	 *
	 * @return string Bearer token, or empty string.
	 */
	private static function get_access_token(): string {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$creds = Credentials::get( 'tidal' );
		if ( '' === $creds['client_id'] || '' === $creds['client_secret'] ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard HTTP Basic Auth header for TIDAL OAuth 2.0 Client Credentials.
		$auth     = base64_encode( $creds['client_id'] . ':' . $creds['client_secret'] );
		$response = wp_safe_remote_post(
			'https://auth.tidal.com/v1/oauth2/token',
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
			Logging::log( 'error', 'TIDAL OAuth token request failed' );
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
