<?php
/**
 * Mixcloud official embed / oEmbed audio provider.
 *
 * Resolves public Mixcloud show/cloudcast URLs into official Mixcloud iframe widgets
 * (`https://player-widget.mixcloud.com/widget/iframe/...`) with zero API keys required.
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
 * Resolves Mixcloud shows and DJ mixes via official oEmbed / iframe widgets.
 *
 * @since 2.1.0
 */
final class MixcloudProvider implements AudioProviderInterface {

	/**
	 * Cache TTL for Mixcloud embed manifests (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'mixcloud';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Mixcloud', 'mds-audio-providers' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return 70;
	}

	/**
	 * Checks whether the release has a valid Mixcloud show URL configured.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool True when a Mixcloud URL exists.
	 */
	public function supports_release( int $post_id ): bool {
		return '' !== self::get_mixcloud_url( $post_id );
	}

	/**
	 * Resolves a Mixcloud show URL into an official Mixcloud embed manifest.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null Raw manifest array, or null.
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		$mixcloud_url = self::get_mixcloud_url( $post_id );
		if ( '' === $mixcloud_url ) {
			return null;
		}

		$fresh = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->get_slug(), $mixcloud_url );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$feed_path = self::extract_feed_path( $mixcloud_url );
		if ( '' === $feed_path ) {
			return null;
		}

		$title      = '';
		$artist     = '';
		$artwork    = '';
		$iframe_src = self::build_widget_url( $feed_path );

		// Attempt zero-auth oEmbed metadata enrichment when WordPress HTTP API is available.
		if ( function_exists( 'wp_safe_remote_get' ) ) {
			$oembed_url = add_query_arg(
				array(
					'url'    => $mixcloud_url,
					'format' => 'json',
				),
				'https://app.mixcloud.com/oembed/'
			);
			$response   = wp_safe_remote_get(
				$oembed_url,
				array(
					'timeout' => 6,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( is_array( $decoded ) ) {
					$title   = (string) ( $decoded['title'] ?? '' );
					$artist  = (string) ( $decoded['author_name'] ?? '' );
					$artwork = (string) ( $decoded['image'] ?? '' );
				}
			}
		}

		$embed_html = '<iframe title="' . esc_attr__( 'Mixcloud Audio Player', 'mds-audio-providers' ) . '" width="100%" height="120" style="border:0;" src="' . esc_url( $iframe_src ) . '" allow="encrypted-media; fullscreen; autoplay; idle-detection; speaker-selection; web-share;" loading="lazy"></iframe>';

		$manifest = array(
			'provider'       => $this->get_slug(),
			'providerLabel'  => $this->get_label(),
			'releaseId'      => $post_id,
			'title'          => $title,
			'artist'         => $artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $mixcloud_url,
			'isTimedPreview' => false,
			'ttl'            => self::CACHE_TTL,
			'embedHtml'      => $embed_html,
			'tracks'         => array(),
		);

		Cache::set( $this->get_slug(), $mixcloud_url, $manifest, self::CACHE_TTL );
		return $manifest;
	}

	/**
	 * Builds the official Mixcloud iframe widget URL for a validated feed path.
	 *
	 * @param string $feed_path Normalized `/username/show-slug/` path.
	 * @return string Widget iframe URL.
	 */
	public static function build_widget_url( string $feed_path ): string {
		return 'https://player-widget.mixcloud.com/widget/iframe/?hide_cover=1&light=1&feed=' . rawurlencode( $feed_path );
	}

	/**
	 * Extracts a normalized `/username/show/` path from a Mixcloud URL.
	 *
	 * @param string $url Validated Mixcloud URL.
	 * @return string Feed path, or empty string.
	 */
	public static function extract_feed_path( string $url ): string {
		$parsed = wp_parse_url( $url );
		$path   = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( '' === $path ) {
			return '';
		}
		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		if ( count( $segments ) < 2 ) {
			return '';
		}
		return '/' . rawurlencode( $segments[0] ) . '/' . rawurlencode( $segments[1] ) . '/';
	}

	/**
	 * Reads the Mixcloud URL from release post meta (with fallback to `mixcloud_url`).
	 *
	 * @param int $post_id Release post ID.
	 * @return string Valid https://mixcloud.com URL, or empty string.
	 */
	public static function get_mixcloud_url( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		foreach ( array( 'mds_ap_mixcloud_url', 'mixcloud_url' ) as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$clean  = esc_url_raw( trim( $raw ) );
				$parsed = wp_parse_url( $clean );
				if (
					! empty( $parsed['scheme'] )
					&& 'https' === strtolower( (string) $parsed['scheme'] )
					&& ! empty( $parsed['host'] )
					&& preg_match( '/(^|\.)mixcloud\.com$/i', (string) $parsed['host'] )
					&& '' !== self::extract_feed_path( $clean )
				) {
					return $clean;
				}
			}
		}
		return '';
	}
}
