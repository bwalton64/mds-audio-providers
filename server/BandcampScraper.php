<?php
/**
 * Server-side Bandcamp URL verification and JIT track stream scraper.
 *
 * Keeps Bandcamp HTML parsing heuristics, mobile API extraction, and bot-challenge
 * handling exclusively on MDS Cloud Gateway.
 *
 * @package   MDS\AudioProviders\Server
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   Proprietary — Musty Dungeon Studios Commercial Server Module
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Server;

use DOMDocument;
use DOMNode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SSRF-hardened Bandcamp scraper and JIT audio stream resolver.
 */
final class BandcampScraper {

	/**
	 * Short cache TTL (30 minutes) so signed Bandcamp stream URLs never go stale.
	 *
	 * @var int
	 */
	public const STREAM_TTL = 1800;

	/**
	 * Verifies a Bandcamp Release URL and extracts its numeric Album ID.
	 *
	 * @param string $url Raw Bandcamp album URL.
	 * @return array<string, mixed> Result payload (`ok`, `album_id`, or `error`).
	 */
	public static function verify_url( string $url ): array {
		$clean = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : ( filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '' );
		if ( '' === $clean ) {
			return array(
				'ok'    => false,
				'error' => __( 'Invalid URL format.', 'mds-audio-providers' ),
			);
		}

		$html = self::fetch_page_html( $clean );
		if ( is_array( $html ) && ! empty( $html['error'] ) ) {
			return array(
				'ok'    => false,
				'error' => (string) ( $html['message'] ?? __( 'Failed to fetch Bandcamp page.', 'mds-audio-providers' ) ),
			);
		}

		$album_id = self::extract_album_id( (string) $html );
		if ( ! $album_id || $album_id <= 0 ) {
			return array(
				'ok'    => false,
				'error' => __( 'Could not find a Bandcamp Album ID on this page.', 'mds-audio-providers' ),
			);
		}

		return array(
			'ok'       => true,
			'album_id' => $album_id,
		);
	}

	/**
	 * Resolves a Bandcamp album into a normalized audio manifest.
	 *
	 * Strategy:
	 * 1. Mobile JSON API (`tralbum_details`) for direct `mp3-128` stream URLs.
	 * 2. `EmbeddedPlayer` HTML `data-player-data` JSON extraction.
	 * 3. Optional official `EmbeddedPlayer` iframe fallback when `$embed_fallback` is true.
	 *
	 * @param int    $release_id     Release post ID (echoed into manifest).
	 * @param int    $album_id       Positive Bandcamp Album ID.
	 * @param string $album_url      Canonical Bandcamp URL.
	 * @param bool   $embed_fallback Whether iframe fallback is permitted.
	 * @return array<string, mixed>|null Manifest array, or null.
	 */
	public static function resolve_album( int $release_id, int $album_id, string $album_url, bool $embed_fallback = true ): ?array {
		if ( $album_id <= 0 ) {
			return null;
		}

		$manifest = self::fetch_via_mobile_api( $release_id, $album_id, $album_url );
		if ( null === $manifest ) {
			$manifest = self::fetch_via_embedded_player_data( $release_id, $album_id, $album_url );
		}

		if ( null === $manifest ) {
			if ( ! $embed_fallback ) {
				return null;
			}
			$iframe_src = sprintf(
				'https://bandcamp.com/EmbeddedPlayer/album=%d/size=small/bgcol=ffffff/linkcol=2271b1/transparent=true/',
				$album_id
			);
			$title_attr = function_exists( 'esc_attr__' )
				? esc_attr__( 'Bandcamp Audio Player', 'mds-audio-providers' )
				: 'Bandcamp Audio Player';
			$src_attr   = function_exists( 'esc_url' ) ? esc_url( $iframe_src ) : $iframe_src;

			return array(
				'provider'       => 'bandcamp',
				'providerLabel'  => __( 'Bandcamp', 'mds-audio-providers' ),
				'releaseId'      => $release_id,
				'sourceUrl'      => $album_url,
				'isTimedPreview' => false,
				'ttl'            => self::STREAM_TTL,
				'embedHtml'      => '<iframe title="' . $title_attr . '" style="border:0;width:100%;height:42px;" src="' . $src_attr . '" seamless></iframe>',
				'tracks'         => array(),
			);
		}

		return $manifest;
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

		if ( ! function_exists( 'wp_safe_remote_get' ) ) {
			return array(
				'error'   => true,
				'message' => __( 'WordPress HTTP API is unavailable.', 'mds-audio-providers' ),
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
					__( 'Bandcamp blocked this request (HTTP %d). Paste the Bandcamp Embed code or enter the Bandcamp Album ID manually.', 'mds-audio-providers' ),
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
				'message' => __( 'Bandcamp served a bot-protection challenge. Paste the Bandcamp Embed code or enter the Bandcamp Album ID manually.', 'mds-audio-providers' ),
			);
		}

		return $body;
	}

	/**
	 * Extracts the numeric Bandcamp Album ID from page HTML.
	 *
	 * @param string $html Bandcamp page HTML.
	 * @return int|false Positive album ID, or false.
	 */
	public static function extract_album_id( string $html ): int|false {
		if ( '' === $html ) {
			return false;
		}
		if ( preg_match( '/<!--\s*album\s+id\s+(\d+)\s*-->/i', $html, $matches ) ) {
			return abs( (int) $matches[1] );
		}
		if ( preg_match( '/&quot;item_id&quot;\s*:\s*(\d+)/i', $html, $matches ) ) {
			$id = abs( (int) $matches[1] );
			if ( $id > 0 ) {
				return $id;
			}
		}
		if ( preg_match( '/"album_id"\s*:\s*(\d+)/i', $html, $matches ) ) {
			$id = abs( (int) $matches[1] );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( ! class_exists( DOMDocument::class ) ) {
			return false;
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( $html );
		libxml_use_internal_errors( false );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMDocument property.
		foreach ( $dom->childNodes as $node ) {
			$found = self::find_album_id_comment( $node );
			if ( $found ) {
				return $found;
			}
		}

		foreach ( $dom->getElementsByTagName( 'meta' ) as $meta ) {
			if ( 'album_id' === $meta->getAttribute( 'name' ) || 'albumId' === $meta->getAttribute( 'property' ) ) {
				$id = abs( (int) $meta->getAttribute( 'content' ) );
				if ( $id > 0 ) {
					return $id;
				}
			}
		}

		return false;
	}

	/**
	 * Fetches album tracks from Bandcamp's lightweight mobile JSON endpoint.
	 *
	 * @param int    $release_id Release post ID.
	 * @param int    $album_id   Bandcamp album ID.
	 * @param string $album_url  Canonical Bandcamp album URL.
	 * @return array<string, mixed>|null Raw manifest or null.
	 */
	private static function fetch_via_mobile_api( int $release_id, int $album_id, string $album_url ): ?array {
		if ( ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}

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

		$art_id      = isset( $decoded['art_id'] ) ? abs( (int) $decoded['art_id'] ) : 0;
		$artwork_url = $art_id > 0 ? sprintf( 'https://f4.bcbits.com/img/a%d_10.jpg', $art_id ) : '';

		return array(
			'provider'       => 'bandcamp',
			'providerLabel'  => __( 'Bandcamp', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
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
	 * @param int    $release_id Release post ID.
	 * @param int    $album_id   Bandcamp album ID.
	 * @param string $album_url  Canonical Bandcamp album URL.
	 * @return array<string, mixed>|null Raw manifest or null.
	 */
	private static function fetch_via_embedded_player_data( int $release_id, int $album_id, string $album_url ): ?array {
		if ( ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}

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
			'provider'       => 'bandcamp',
			'providerLabel'  => __( 'Bandcamp', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
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
	 * Recursively searches DOM comment nodes for a Bandcamp album ID.
	 *
	 * @param DOMNode $node DOM node.
	 * @return int|false Album ID or false.
	 */
	private static function find_album_id_comment( DOMNode $node ): int|false {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMNode property.
		if ( XML_COMMENT_NODE === $node->nodeType && preg_match( '/album\s+id\s+(\d+)/i', (string) $node->nodeValue, $matches ) ) {
			$id = abs( (int) $matches[1] );
			if ( $id > 0 ) {
				return $id;
			}
		}
		if ( $node->hasChildNodes() ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMNode property.
			foreach ( $node->childNodes as $child ) {
				$found = self::find_album_id_comment( $child );
				if ( $found ) {
					return $found;
				}
			}
		}
		return false;
	}
}
