<?php
/**
 * Musicfetch commercial multi-DSP UPC audio preview provider (optional BYO paid key).
 *
 * Queries `https://api.musicfetch.io/upc` using an encrypted `x-token` API key
 * (or `MDS_AP_MUSICFETCH_API_KEY` constant) to resolve timed preview clips across
 * 30+ streaming services when direct free providers do not match.
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
 * Optional commercial BYO-key UPC preview resolver backed by Musicfetch.
 *
 * @since 2.1.0
 */
final class MusicfetchProvider implements AudioProviderInterface {

	/**
	 * Cache TTL for successful Musicfetch UPC lookups (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'musicfetch';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Musicfetch (BYO Key)', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 80;
	}

	/**
	 * Checks whether a Musicfetch API token is configured and the release has a UPC/EAN.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when API token and UPC/EAN exist.
	 */
	public function supports_release( int $post_id ): bool {
		$creds = Credentials::get( 'musicfetch' );
		if ( '' === $creds['client_secret'] ) {
			return false;
		}
		return '' !== self::get_upc( $post_id );
	}

	/**
	 * Resolves timed preview clips via Musicfetch's `/upc` endpoint.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$upc   = self::get_upc( $post_id );
		$creds = Credentials::get( 'musicfetch' );
		if ( '' === $upc || '' === $creds['client_secret'] ) {
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
				'upc'      => $upc,
				'services' => 'appleMusic,deezer,spotify',
			),
			'https://api.musicfetch.io/upc'
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'  => 'application/json',
					'x-token' => $creds['client_secret'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logging::log(
				'error',
				'Musicfetch lookup failed',
				array(
					'upc'   => $upc,
					'error' => $response->get_error_message(),
				)
			);
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			$backoff     = max( Cache::ERROR_BACKOFF_TTL, min( 3600, $retry_after ) );
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), $backoff );
			return null;
		}
		if ( $code < 200 || $code >= 300 ) {
			Cache::set( $this->get_slug(), $upc, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$result  = is_array( $decoded ) && isset( $decoded['result'] ) && is_array( $decoded['result'] )
			? $decoded['result']
			: ( is_array( $decoded ) ? $decoded : array() );

		$title      = (string) ( $result['name'] ?? $result['title'] ?? '' );
		$artist     = isset( $result['artists'][0]['name'] ) ? (string) $result['artists'][0]['name'] : '';
		$artwork    = isset( $result['image']['url'] ) ? (string) $result['image']['url'] : '';
		$source_url = (string) ( $result['link'] ?? '' );
		$tracks     = array();

		if ( isset( $result['tracks'] ) && is_array( $result['tracks'] ) ) {
			foreach ( $result['tracks'] as $idx => $item ) {
				if ( ! is_array( $item ) || empty( $item['previewUrl'] ) || ! is_string( $item['previewUrl'] ) ) {
					continue;
				}
				$tracks[] = array(
					'index'          => $idx + 1,
					'title'          => (string) ( $item['name'] ?? $item['title'] ?? '' ),
					'artist'         => isset( $item['artists'][0]['name'] ) ? (string) $item['artists'][0]['name'] : $artist,
					'duration'       => 30.0,
					'streamUrl'      => $item['previewUrl'],
					'mimeType'       => 'audio/mpeg',
					'isTimedPreview' => true,
					'sourceUrl'      => (string) ( $item['link'] ?? $source_url ),
				);
			}
		} elseif ( ! empty( $result['previewUrl'] ) && is_string( $result['previewUrl'] ) ) {
			$tracks[] = array(
				'index'          => 1,
				'title'          => $title,
				'artist'         => $artist,
				'duration'       => 30.0,
				'streamUrl'      => $result['previewUrl'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => true,
				'sourceUrl'      => $source_url,
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
			'title'          => $title,
			'artist'         => $artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $source_url,
			'isTimedPreview' => true,
			'ttl'            => self::CACHE_TTL,
			'tracks'         => $tracks,
		);

		Cache::set( $this->get_slug(), $upc, $manifest, self::CACHE_TTL );
		return $manifest;
	}

	/**
	 * Verifies the configured Musicfetch `x-token` API key.
	 *
	 * @return array{ok: bool, message: string} Connection test result.
	 */
	public static function test_connection(): array {
		$creds = Credentials::get( 'musicfetch' );
		if ( '' === $creds['client_secret'] ) {
			return array(
				'ok'      => false,
				'message' => __( 'Musicfetch API Key is not configured.', 'mds-audio-providers' ),
			);
		}

		$url      = add_query_arg(
			array(
				'upc'      => '000000000000',
				'services' => 'appleMusic',
			),
			'https://api.musicfetch.io/upc'
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'  => 'application/json',
					'x-token' => $creds['client_secret'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Could not reach Musicfetch API.', 'mds-audio-providers' ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code || 404 === $code ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected to Musicfetch API.', 'mds-audio-providers' ),
			);
		}
		if ( 429 === $code ) {
			return array(
				'ok'      => true,
				'message' => __( 'Musicfetch API key accepted, but rate limit is currently reached.', 'mds-audio-providers' ),
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'Musicfetch authentication failed. Verify your API Key.', 'mds-audio-providers' ),
		);
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
