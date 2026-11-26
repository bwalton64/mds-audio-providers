<?php
/**
 * Registers all MDS Audio Providers with MDS Release Manager's Unified Audio API.
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
 * Wires Apple Music, Deezer, Bandcamp, SoundCloud, and Spotify into `mds_rm_audio_providers`.
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
	 * Appends all 5 audio preview providers to Core's Unified Audio API registry.
	 *
	 * @param array<string, mixed> $providers Existing registered providers.
	 * @return array<string, mixed> Updated providers map.
	 */
	public static function register_providers( array $providers ): array {
		if ( ! Dependencies::has_unified_audio_api() ) {
			return $providers;
		}

		$instances = array(
			new AppleMusicProvider(),
			new DeezerProvider(),
			new BandcampProvider(),
			new AudiusProvider(),
			new SoundCloudProvider(),
			new JamendoProvider(),
			new FunkwhaleProvider(),
			new SpotifyProvider(),
			new TidalProvider(),
			new MixcloudProvider(),
			new AudiomackProvider(),
			new MusicfetchProvider(),
		);

		foreach ( $instances as $provider ) {
			$providers[ $provider->get_slug() ] = $provider;
		}

		return $providers;
	}
}
