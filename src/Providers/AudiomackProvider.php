<?php
/**
 * Audiomack official embed audio provider.
 *
 * Resolves public Audiomack song, album, and playlist URLs into official Audiomack
 * iframe players (`https://audiomack.com/embed/...`) with zero API keys required.
 *
 * @package MDS\AudioProviders
 * @since   2.1.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Providers;

use MDS\AudioProviders\Core\Cache;
use MDS\ReleaseManager\Core\Audio\AudioProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves Audiomack songs, albums, and playlists via official iframe embeds.
 *
 * @since 2.1.0
 */
final class AudiomackProvider implements AudioProviderInterface {

	/**
	 * Cache TTL for Audiomack embed manifests (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'audiomack';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Audiomack', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 75;
	}

	/**
	 * Checks whether the release has a valid Audiomack song/album/playlist URL.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when a valid Audiomack URL exists.
	 */
	public function supports_release( int $post_id ): bool {
		return '' !== self::get_audiomack_url( $post_id );
	}

	/**
	 * Resolves an Audiomack URL into an official Audiomack embed manifest.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$audiomack_url = self::get_audiomack_url( $post_id );
		if ( '' === $audiomack_url ) {
			return null;
		}

		$fresh = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $audiomack_url );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$embed_info = self::parse_embed_target( $audiomack_url );
		if ( null === $embed_info ) {
			return null;
		}

		$height     = 'song' === $embed_info['type'] ? 252 : 352;
		$embed_html = '<iframe title="' . esc_attr__( 'Audiomack Audio Player', 'mds-audio-providers' ) . '" src="' . esc_url( $embed_info['embed_url'] ) . '" scrolling="no" width="100%" height="' . (int) $height . '" style="border:0;" allow="autoplay; encrypted-media; fullscreen" loading="lazy"></iframe>';

		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'sourceUrl'      => $audiomack_url,
			'isTimedPreview' => false,
			'ttl'            => self::CACHE_TTL,
			'embedHtml'      => $embed_html,
			'tracks'         => array(),
		);

		Cache::set( $this->get_slug(), $audiomack_url, $manifest, self::CACHE_TTL );
		return $manifest;
	}

	/**
	 * Parses an Audiomack song, album, or playlist URL into an official embed URL.
	 *
	 * @param string $url Validated Audiomack URL.
	 * @return array{type: string, embed_url: string}|null Parsed target, or null.
	 */
	public static function parse_embed_target( string $url ): ?array {
		$parsed = wp_parse_url( $url );
		$path   = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( '' === $path ) {
			return null;
		}

		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		if ( count( $segments ) < 3 ) {
			return null;
		}

		// Handle both `/artist/song/slug` and `/embed/artist/song/slug`.
		if ( 'embed' === strtolower( $segments[0] ) && count( $segments ) >= 4 ) {
			array_shift( $segments );
		}

		$artist = sanitize_title( $segments[0] );
		$type   = strtolower( $segments[1] );
		$slug   = sanitize_title( $segments[2] );

		if ( '' === $artist || '' === $slug || ! in_array( $type, array( 'song', 'album', 'playlist' ), true ) ) {
			return null;
		}

		return array(
			'type'      => $type,
			'embed_url' => sprintf( 'https://audiomack.com/embed/%s/%s/%s?background=1', $artist, $type, $slug ),
		);
	}

	/**
	 * Reads the Audiomack URL from release post meta (with fallback to `audiomack_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return string Valid https://audiomack.com URL, or empty string.
	 */
	public static function get_audiomack_url( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		foreach ( array( 'mds_ap_audiomack_url', 'audiomack_url' ) as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$clean  = esc_url_raw( trim( $raw ) );
				$parsed = wp_parse_url( $clean );
				if (
					! empty( $parsed['scheme'] )
					&& 'https' === strtolower( (string) $parsed['scheme'] )
					&& ! empty( $parsed['host'] )
					&& preg_match( '/(^|\.)audiomack\.com$/i', (string) $parsed['host'] )
					&& null !== self::parse_embed_target( $clean )
				) {
					return $clean;
				}
			}
		}
		return '';
	}
}
