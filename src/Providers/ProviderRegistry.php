<?php
/**
 * Registers all MDS Audio Providers with MDS Release Manager's Unified Audio API.
 *
 * Cloud-backed providers execute via `GatewayProxyProvider` against MDS Cloud Gateway
 * (`free` tier for zero-cost public DSPs; `pro` tier for OAuth / API-key / commercial DSPs).
 * `FunkwhaleProvider` executes locally for private/self-hosted pods (gated to `pro` tier).
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Providers;

use MDS\AudioProviders\Core\Dependencies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires all 12 audio preview providers into `mds_rm_audio_providers`.
 *
 * @since 2.0.0
 */
final class ProviderRegistry {

	/**
	 * Registers the `mds_rm_audio_providers` filter hook.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'mds_rm_audio_providers', array( self::class, 'register_providers' ) );
	}

	/**
	 * Appends all 12 audio preview providers to Core's Unified Audio API registry.
	 *
	 * @param array<string, mixed> $providers Existing registered providers.
	 * @return array<string, mixed> Updated providers map.
	 */
	public static function register_providers( array $providers ): array {
		if ( ! Dependencies::has_unified_audio_api() ) {
			return $providers;
		}

		$instances = array(
			new GatewayProxyProvider( 'apple_music', __( 'Apple Music', 'mds-audio-providers' ), 20, 'free' ),
			new GatewayProxyProvider( 'deezer', __( 'Deezer', 'mds-audio-providers' ), 30, 'free' ),
			new GatewayProxyProvider( 'bandcamp', __( 'Bandcamp', 'mds-audio-providers' ), 40, 'free' ),
			new GatewayProxyProvider( 'audius', __( 'Audius', 'mds-audio-providers' ), 45, 'free' ),
			new GatewayProxyProvider( 'soundcloud', __( 'SoundCloud', 'mds-audio-providers' ), 50, 'pro' ),
			new GatewayProxyProvider( 'jamendo', __( 'Jamendo', 'mds-audio-providers' ), 55, 'pro' ),
			new FunkwhaleProvider(),
			new GatewayProxyProvider( 'spotify', __( 'Spotify', 'mds-audio-providers' ), 60, 'pro' ),
			new GatewayProxyProvider( 'tidal', __( 'TIDAL', 'mds-audio-providers' ), 65, 'pro' ),
			new GatewayProxyProvider( 'mixcloud', __( 'Mixcloud', 'mds-audio-providers' ), 70, 'free' ),
			new GatewayProxyProvider( 'audiomack', __( 'Audiomack', 'mds-audio-providers' ), 75, 'free' ),
			new GatewayProxyProvider( 'musicfetch', __( 'Musicfetch', 'mds-audio-providers' ), 80, 'pro' ),
		);

		foreach ( $instances as $provider ) {
			$providers[ $provider->get_slug() ] = $provider;
		}

		return $providers;
	}
}
