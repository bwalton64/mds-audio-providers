<?php
/**
 * Apple Music (iTunes Lookup API) audio preview provider.
 *
 * Uses the free public iTunes Lookup API by release UPC/EAN to resolve
 * official 30-second `.m4a` timed previews with zero API keys required.
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
 * Resolves native 30-second Apple Music / iTunes audio previews by UPC/EAN.
 *
 * @since 2.0.0
 */
final class AppleMusicProvider implements AudioProviderInterface {

	/**
	 * Cache TTL for successful iTunes UPC lookups (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'apple_music';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Apple Music', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 20;
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
	 * Resolves native 30-second `.m4a` previews from the iTunes Lookup API.
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

		$url      = add_query_arg(
			array(
				'upc'    => $upc,
				'entity' => 'song',
			),
			'https://itunes.apple.com/lookup'
		);
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
				'Apple Music lookup failed',
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
		$results = is_array( $decoded ) && isset( $decoded['results'] ) && is_array( $decoded['results'] ) ? $decoded['results'] : array();
		if ( empty( $results ) ) {
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$album_title = '';
		$artist_name = '';
		$artwork_url = '';
		$source_url  = '';
		$tracks      = array();

		foreach ( $results as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$wrapper = (string) ( $item['wrapperType'] ?? '' );
			if ( 'collection' === $wrapper ) {
				$album_title = (string) ( $item['collectionName'] ?? '' );
				$artist_name = (string) ( $item['artistName'] ?? '' );
				$artwork_url = (string) ( $item['artworkUrl100'] ?? '' );
				$source_url  = (string) ( $item['collectionViewUrl'] ?? '' );
				continue;
			}
			if ( 'track' === $wrapper && ! empty( $item['previewUrl'] ) && is_string( $item['previewUrl'] ) ) {
				if ( '' === $artist_name && ! empty( $item['artistName'] ) ) {
					$artist_name = (string) $item['artistName'];
				}
				if ( '' === $artwork_url && ! empty( $item['artworkUrl100'] ) ) {
					$artwork_url = (string) $item['artworkUrl100'];
				}
				$tracks[] = array(
					'index'          => isset( $item['trackNumber'] ) ? (int) $item['trackNumber'] : ( count( $tracks ) + 1 ),
					'title'          => (string) ( $item['trackName'] ?? '' ),
					'artist'         => (string) ( $item['artistName'] ?? $artist_name ),
					'duration'       => 30.0,
					'streamUrl'      => $item['previewUrl'],
					'mimeType'       => 'audio/mp4',
					'isTimedPreview' => true,
					'sourceUrl'      => (string) ( $item['trackViewUrl'] ?? $source_url ),
				);
			}
		}

		if ( empty( $tracks ) ) {
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => $album_title,
			'artist'         => $artist_name,
			'artworkUrl'     => str_replace( '100x100bb', '300x300bb', $artwork_url ),
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
