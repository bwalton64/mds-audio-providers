<?php
/**
 * Thin-client gateway-backed audio provider for MDS Release Manager's Unified Audio API.
 *
 * Instantiated once per cloud-backed streaming provider (`apple_music`, `deezer`, `bandcamp`,
 * `audius`, `mixcloud`, `audiomack`, `soundcloud`, `jamendo`, `spotify`, `tidal`, `musicfetch`).
 * Checks release metadata and local transient cache first, then delegates resolution to
 * MDS Cloud Gateway (`/wp-json/mds-gw/v1/proxy/audio-resolve`).
 *
 * @package   MDS\AudioProviders
 * @since     2.2.0
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Providers;

use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Core\Logging;
use MDS\AudioProviders\Gateway\GatewayBridge;
use MDS\ReleaseManager\Core\Audio\AudioProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin GPL client provider backed by MDS Cloud Gateway's `audio-resolve` service.
 *
 * @since 2.2.0
 */
final class GatewayProxyProvider implements AudioProviderInterface {

	/**
	 * Provider slug (e.g., `apple_music`, `bandcamp`, `spotify`).
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Human-readable provider label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Default priority order in the Unified Audio API waterfall.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Minimum gateway license tier (`free` or `pro`).
	 *
	 * @var string
	 */
	private string $min_tier;

	/**
	 * Constructor.
	 *
	 * @param string $slug     Provider slug.
	 * @param string $label    Human-readable label.
	 * @param int    $priority Default priority.
	 * @param string $min_tier Minimum license tier (`free` or `pro`).
	 */
	public function __construct( string $slug, string $label, int $priority, string $min_tier = 'free' ) {
		$this->slug     = sanitize_key( $slug );
		$this->label    = $label;
		$this->priority = $priority;
		$this->min_tier = $min_tier;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Returns the minimum license tier required for this provider.
	 *
	 * @return string
	 */
	public function get_min_tier(): string {
		return $this->min_tier;
	}

	/**
	 * Checks whether the active license tier and release metadata support this provider.
	 *
	 * @param int $post_id Release post ID.
	 * @return bool
	 */
	public function supports_release( int $post_id ): bool {
		if ( ! GatewayBridge::has_tier( $this->min_tier ) ) {
			return false;
		}
		return null !== $this->build_request_descriptor( $post_id );
	}

	/**
	 * Resolves a normalized audio manifest via local cache or MDS Cloud Gateway.
	 *
	 * @param int                  $post_id Release post ID.
	 * @param array<string, mixed> $context Optional resolution context (`fresh`).
	 * @return array<string, mixed>|null
	 */
	public function resolve( int $post_id, array $context = array() ): ?array {
		if ( ! GatewayBridge::has_tier( $this->min_tier ) ) {
			return null;
		}

		$descriptor = $this->build_request_descriptor( $post_id );
		if ( null === $descriptor ) {
			return null;
		}

		$cache_target = $descriptor['cache_target'];
		$fresh        = ! empty( $context['fresh'] );
		if ( ! $fresh ) {
			$cached = Cache::get( $this->slug, $cache_target );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['error'] ) ? null : $cached;
			}
		}

		$payload          = $descriptor['payload'];
		$payload['fresh'] = $fresh;

		$response = GatewayBridge::call_proxy( 'audio-resolve', $payload );

		// Unwrap ProxyRouter envelope (`{ ok: true, data: { ok: true, manifest: ... } }`) or direct payload.
		$inner = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;
		if ( empty( $response['ok'] ) || empty( $inner['ok'] ) || empty( $inner['manifest'] ) || ! is_array( $inner['manifest'] ) ) {
			Logging::log(
				'info',
				'Gateway audio-resolve returned no manifest',
				array(
					'provider' => $this->slug,
					'code'     => (string) ( $inner['code'] ?? $response['code'] ?? 'not_found' ),
				)
			);
			Cache::set( $this->slug, $cache_target, array( 'error' => true ), Cache::ERROR_BACKOFF_TTL );
			return null;
		}

		$manifest                  = $inner['manifest'];
		$manifest['provider']      = $this->slug;
		$manifest['providerLabel'] = $this->label;
		$manifest['releaseId']     = $post_id;

		$ttl = isset( $manifest['ttl'] ) ? max( 300, (int) $manifest['ttl'] ) : 3600;
		Cache::set( $this->slug, $cache_target, $manifest, $ttl );

		return $manifest;
	}

	/**
	 * Extracts the local release metadata payload and cache key for this provider.
	 *
	 * @param int $post_id Release post ID.
	 * @return array{cache_target: string, payload: array<string, mixed>}|null
	 */
	private function build_request_descriptor( int $post_id ): ?array {
		switch ( $this->slug ) {
			case 'apple_music':
			case 'deezer':
			case 'musicfetch':
				$upc = ReleaseMeta::get_upc( $post_id );
				if ( '' === $upc ) {
					return null;
				}
				return array(
					'cache_target' => $upc,
					'payload'      => array(
						'provider'   => $this->slug,
						'release_id' => $post_id,
						'upc'        => $upc,
					),
				);

			case 'bandcamp':
				$album_id = ReleaseMeta::get_bandcamp_album_id( $post_id );
				if ( $album_id <= 0 ) {
					return null;
				}
				$embed_fallback = ProviderOptions::get_bool( 'bandcamp', 'embed_fallback', true );
				return array(
					'cache_target' => (string) $album_id . ( $embed_fallback ? ':embed' : ':native' ),
					'payload'      => array(
						'provider'       => 'bandcamp',
						'release_id'     => $post_id,
						'album_id'       => $album_id,
						'url'            => ReleaseMeta::get_bandcamp_album_url( $post_id ),
						'embed_fallback' => $embed_fallback,
					),
				);

			case 'audius':
				$url = ReleaseMeta::get_audius_url( $post_id );
				return '' !== $url ? array(
					'cache_target' => $url,
					'payload'      => array(
						'provider'   => 'audius',
						'release_id' => $post_id,
						'url'        => $url,
					),
				) : null;

			case 'soundcloud':
				$url = ReleaseMeta::get_soundcloud_url( $post_id );
				return '' !== $url ? array(
					'cache_target' => $url,
					'payload'      => array(
						'provider'   => 'soundcloud',
						'release_id' => $post_id,
						'url'        => $url,
					),
				) : null;

			case 'jamendo':
				$target = ReleaseMeta::get_jamendo_target( $post_id );
				return null !== $target ? array(
					'cache_target' => $target['type'] . ':' . $target['id'],
					'payload'      => array(
						'provider'   => 'jamendo',
						'release_id' => $post_id,
						'url'        => $target['url'],
					),
				) : null;

			case 'spotify':
				$upc            = ReleaseMeta::get_upc( $post_id );
				$embed_fallback = ProviderOptions::get_bool( 'spotify', 'embed_fallback', true );
				$direct         = $embed_fallback ? ReleaseMeta::get_spotify_embed_target( $post_id ) : null;
				if ( '' === $upc && null === $direct ) {
					return null;
				}
				$base_target = '' !== $upc ? $upc : $direct['type'] . ':' . $direct['id'];
				return array(
					'cache_target' => $base_target . ( $embed_fallback ? ':embed' : ':native' ),
					'payload'      => array(
						'provider'       => 'spotify',
						'release_id'     => $post_id,
						'upc'            => $upc,
						'url'            => null !== $direct ? $direct['url'] : '',
						'embed_fallback' => $embed_fallback,
					),
				);

			case 'tidal':
				$direct = ReleaseMeta::get_tidal_target( $post_id );
				$upc    = ReleaseMeta::get_upc( $post_id );
				if ( null === $direct && '' === $upc ) {
					return null;
				}
				return array(
					'cache_target' => null !== $direct ? $direct['type'] . ':' . $direct['id'] : 'upc:' . $upc,
					'payload'      => array(
						'provider'   => 'tidal',
						'release_id' => $post_id,
						'upc'        => $upc,
						'url'        => null !== $direct ? $direct['url'] : '',
					),
				);

			case 'mixcloud':
				$url = ReleaseMeta::get_mixcloud_url( $post_id );
				return '' !== $url ? array(
					'cache_target' => $url,
					'payload'      => array(
						'provider'   => 'mixcloud',
						'release_id' => $post_id,
						'url'        => $url,
					),
				) : null;

			case 'audiomack':
				$url = ReleaseMeta::get_audiomack_url( $post_id );
				return '' !== $url ? array(
					'cache_target' => $url,
					'payload'      => array(
						'provider'   => 'audiomack',
						'release_id' => $post_id,
						'url'        => $url,
					),
				) : null;
		}

		return null;
	}
}
