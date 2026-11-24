<?php
/**
 * Deezer Public API audio preview provider.
 *
 * Uses Deezer's free public UPC endpoint (`https://api.deezer.com/album/upc:{UPC}`)
 * to resolve official 30-second `.mp3` timed previews with zero API keys required.
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
 * Resolves native 30-second Deezer audio previews by UPC/EAN.
 *
 * @since 2.0.0
 */
final class DeezerProvider implements AudioProviderInterface {

	/**
	 * Cache TTL for successful Deezer UPC lookups (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'deezer';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Deezer', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 30;
	}

	/**
	 * Checks whether the release has a normalized UPC/EAN identifier.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when a UPC/EAN is present.
	 */
	public function supports_release( int $post_id ): bool {
		return '' !== self::get_upc( $post_id );
	}

	/**
	 * Resolves native 30-second `.mp3` previews from the Deezer public API.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$upc = self::get_upc( $post_id );
		if ( '' === $upc ) {
			return null;
		}

		$fresh = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $upc );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$url      = 'https://api.deezer.com/album/upc:' . rawurlencode( $upc );
		$response = wp_safe_remote_get(
			$url,
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
				'Deezer lookup failed',
				array(
					'upc'   => $upc,
					'error' => $response->get_error_message(),
				)
			);
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || ! empty( $decoded['error'] ) ) {
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$raw_tracks = isset( $decoded['tracks']['data'] ) && is_array( $decoded['tracks']['data'] )
			? $decoded['tracks']['data']
			: array();

		$artist_name = isset( $decoded['artist']['name'] ) ? (string) $decoded['artist']['name'] : '';
		$source_url  = isset( $decoded['link'] ) ? (string) $decoded['link'] : '';
		$tracks      = array();

		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['preview'] ) || ! is_string( $item['preview'] ) ) {
				continue;
			}
			$tracks[] = array(
				'index'          => $idx + 1,
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => isset( $item['artist']['name'] ) ? (string) $item['artist']['name'] : $artist_name,
				'duration'       => 30.0,
				'streamUrl'      => $item['preview'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => true,
				'sourceUrl'      => (string) ( $item['link'] ?? $source_url ),
			);
		}

		if ( empty( $tracks ) ) {
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => (string) ( $decoded['title'] ?? '' ),
			'artist'         => $artist_name,
			'artworkUrl'     => (string) ( $decoded['cover_medium'] ?? $decoded['cover'] ?? '' ),
			'sourceUrl'      => $source_url,
			'isTimedPreview' => true,
			'ttl'            => self::CACHE_TTL,
			'tracks'         => $tracks,
		);

		Cache::set( $this->get_slug(), $upc, $manifest, self::CACHE_TTL );
		return $manifest;
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
