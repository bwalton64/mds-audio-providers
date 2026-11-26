<?php
/**
 * Bandcamp JIT audio preview provider.
 *
 * Resolution strategy:
 * 1. Primary: Bandcamp mobile JSON endpoint (`tralbum_details`) for direct `mp3-128` stream URLs.
 * 2. Fallback: Bandcamp `EmbeddedPlayer` HTML `data-player-data` JSON extraction.
 * 3. Final fallback: Official Bandcamp `EmbeddedPlayer` iframe (`embedHtml`).
 *
 * Uses a short TTL (30 minutes) because Bandcamp signed `t4.bcbits.com` stream URLs expire.
 * Marks tracks `isTimedPreview: false` so the core Release Player applies its client-side
 * 20-second sampler cap + crossfade automatically.
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
 * Resolves Bandcamp album tracks for the Unified Audio API.
 *
 * @since 2.0.0
 */
final class BandcampProvider implements AudioProviderInterface {

	/**
	 * Short cache TTL (30 minutes) so signed Bandcamp stream tokens never go stale.
	 *
	 * @var int
	 */
	private const STREAM_TTL = 1800;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'bandcamp';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Bandcamp', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 40;
	}

	/**
	 * Checks whether the release has a positive Bandcamp Album ID.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when a Bandcamp album ID is configured.
	 */
	public function supports_release( int $post_id ): bool {
		return self::get_album_id( $post_id ) > 0;
	}

	/**
	 * Resolves Bandcamp album tracks or EmbeddedPlayer fallback.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$album_id = self::get_album_id( $post_id );
		if ( $album_id <= 0 ) {
			return null;
		}

		$cache_target = (string) $album_id;
		$fresh        = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $cache_target );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$album_url = self::get_album_url( $post_id );
		$manifest  = $this->fetch_via_mobile_api( $post_id, $album_id, $album_url );

		if ( null === $manifest ) {
			$manifest = $this->fetch_via_embedded_player_data( $post_id, $album_id, $album_url );
		}

		if ( null === $manifest ) {
			if ( ! ProviderOptions::get_bool( 'bandcamp', 'embed_fallback', true ) ) {
				return null;
			}
			// Safe final fallback: official Bandcamp EmbeddedPlayer iframe.
			$iframe_src = sprintf(
				'https://bandcamp.com/EmbeddedPlayer/album=%d/size=small/bgcol=ffffff/linkcol=2271b1/transparent=true/',
				$album_id
			);
			$manifest   = array(
				'provider'       => $this->get_slug(),
				'providerLabel'  => $this->get_label(),
				'releaseId'      => $post_id,
				'sourceUrl'      => $album_url,
				'isTimedPreview' => false,
				'ttl'            => self::STREAM_TTL,
				'embedHtml'      => '<iframe style="border:0;width:100%;height:42px;" src="' . esc_url( $iframe_src ) . '" seamless></iframe>',
				'tracks'         => array(),
			);
		}

		Cache::set( $this->get_slug(), $cache_target, $manifest, self::STREAM_TTL );
		return $manifest;
	}

	/**
	 * Fetches album tracks from Bandcamp's lightweight mobile JSON endpoint.
	 *
	 * @param int    $post_id   Release post ID.
	 * @param int    $album_id  Bandcamp album ID.
	 * @param string $album_url Canonical Bandcamp album URL.
	 * @return array<string, mixed>|null Raw manifest or null.
	 */
	private function fetch_via_mobile_api( int $post_id, int $album_id, string $album_url ): ?array {
		$url      = add_query_arg(
			array(
				'band_id'      => 1,
				'tralbum_type' => 'a',
				'tralbum_id'   => $album_id,
			),
			'https://bandcamp.com/api/mobile/25/tralbum_details'
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['tracks'] ) || ! is_array( $decoded['tracks'] ) ) {
			return null;
		}

		$artist = (string) ( $decoded['tralbum_artist'] ?? $decoded['band']['name'] ?? '' );
		$source = (string) ( $decoded['bandcamp_url'] ?? $album_url );
		$tracks = array();

		foreach ( $decoded['tracks'] as $idx => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$stream = isset( $item['streaming_url']['mp3-128'] ) && is_string( $item['streaming_url']['mp3-128'] )
				? $item['streaming_url']['mp3-128']
				: '';
			if ( '' === $stream ) {
				continue;
			}
			$tracks[] = array(
				'index'          => isset( $item['track_num'] ) ? (int) $item['track_num'] : ( $idx + 1 ),
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => $artist,
				'duration'       => isset( $item['duration'] ) ? (float) $item['duration'] : 0.0,
				'streamUrl'      => $stream,
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => false,
				'sourceUrl'      => $source,
			);
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		$art_id      = isset( $decoded['art_id'] ) ? absint( $decoded['art_id'] ) : 0;
		$artwork_url = $art_id > 0 ? sprintf( 'https://f4.bcbits.com/img/a%d_10.jpg', $art_id ) : '';

		return array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => (string) ( $decoded['title'] ?? '' ),
			'artist'         => $artist,
			'artworkUrl'     => $artwork_url,
			'sourceUrl'      => $source,
			'isTimedPreview' => false,
			'ttl'            => self::STREAM_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Fallback: parses `data-player-data` JSON from Bandcamp's EmbeddedPlayer HTML.
	 *
	 * @param int    $post_id   Release post ID.
	 * @param int    $album_id  Bandcamp album ID.
	 * @param string $album_url Canonical Bandcamp album URL.
	 * @return array<string, mixed>|null Raw manifest or null.
	 */
	private function fetch_via_embedded_player_data( int $post_id, int $album_id, string $album_url ): ?array {
		$url      = sprintf( 'https://bandcamp.com/EmbeddedPlayer/album=%d/size=large/transparent=true/', $album_id );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Logging::log( 'warning', 'Bandcamp EmbeddedPlayer fallback failed', array( 'album_id' => $album_id ) );
			return null;
		}

		$html = (string) wp_remote_retrieve_body( $response );
		if ( ! preg_match( '/data-player-data="([^"]+)"/', $html, $matches ) ) {
			return null;
		}

		$decoded = json_decode( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['tracks'] ) || ! is_array( $decoded['tracks'] ) ) {
			return null;
		}

		$artist = (string) ( $decoded['artist'] ?? '' );
		$source = (string) ( $decoded['linkback'] ?? $album_url );
		$tracks = array();

		foreach ( $decoded['tracks'] as $idx => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$stream = isset( $item['file']['mp3-128'] ) && is_string( $item['file']['mp3-128'] )
				? $item['file']['mp3-128']
				: '';
			if ( '' === $stream ) {
				continue;
			}
			$tracks[] = array(
				'index'          => isset( $item['tracknum'] ) ? ( (int) $item['tracknum'] + 1 ) : ( $idx + 1 ),
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => (string) ( $item['artist'] ?? $artist ),
				'duration'       => isset( $item['duration'] ) ? (float) $item['duration'] : 0.0,
				'streamUrl'      => $stream,
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => false,
				'sourceUrl'      => $source,
			);
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		return array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => (string) ( $decoded['album_title'] ?? '' ),
			'artist'         => $artist,
			'artworkUrl'     => (string) ( $decoded['album_art'] ?? '' ),
			'sourceUrl'      => $source,
			'isTimedPreview' => false,
			'ttl'            => self::STREAM_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Reads the Bandcamp Album ID from post meta (compatible with legacy Bandcamp Integration meta).
	 *
	 * @param int $post_id Release post ID.
	 * @return int Positive album ID, or 0.
	 */
	public static function get_album_id( int $post_id ): int {
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
	 * @return string Album URL or empty string.
	 */
	public static function get_album_url( int $post_id ): string {
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
	 * Fetches a Bandcamp page safely (SSRF-hardened to `https://*.bandcamp.com`).
	 *
	 * @param string $url Bandcamp URL.
	 * @return string|array{error: bool, message: string} Page HTML or error array.
	 */
	public static function fetch_page_html( string $url ): string|array {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || 'https' !== strtolower( (string) $parsed['scheme'] ) ) {
			return array(
				'error'   => true,
				'message' => __( 'Only HTTPS URLs are allowed.', 'mds-audio-providers' ),
			);
		}
		if ( empty( $parsed['host'] ) || ! preg_match( '/(^|\.)bandcamp\.com$/i', (string) $parsed['host'] ) ) {
			return array(
				'error'   => true,
				'message' => __( 'Only bandcamp.com URLs are allowed.', 'mds-audio-providers' ),
			);
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
					'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language' => 'en-US,en;q=0.9',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error'   => true,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 403 === $code || 429 === $code || 503 === $code ) {
			return array(
				'error'   => true,
				'message' => sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Bandcamp blocked this server’s request (HTTP %d). You can paste the Bandcamp Embed code or enter the Bandcamp Album ID manually below.', 'mds-audio-providers' ),
					$code
				),
			);
		}
		if ( 200 !== $code ) {
			return array(
				'error'   => true,
				'message' => sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Bandcamp returned HTTP %d.', 'mds-audio-providers' ),
					$code
				),
			);
		}
		if (
			str_contains( $body, 'cf-browser-verification' )
			|| str_contains( $body, 'challenge-platform' )
			|| preg_match( '/<title>\s*Just a moment/i', $body )
		) {
			return array(
				'error'   => true,
				'message' => __( 'Bandcamp served a bot-protection challenge to this server’s IP. Paste the Bandcamp Embed code or enter the Bandcamp Album ID manually below.', 'mds-audio-providers' ),
			);
		}

		return $body;
	}
}
