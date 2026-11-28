<?php
/**
 * Static gateway bridge and provider tier catalog for MDS Audio Providers.
 *
 * Coordinates license activation, tier checks, native Update URI registration
 * (with `.git` dev-checkout protection), and proxy calls to MDS Cloud Gateway.
 *
 * @package   MDS\AudioProviders
 * @since     2.2.0
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facade over `GatewayClient` for MDS Audio Providers.
 *
 * @since 2.2.0
 */
final class GatewayBridge {

	/**
	 * Tier rank ordering matching MDS Cloud Gateway.
	 *
	 * @var array<string, int>
	 */
	private const TIER_RANK = array(
		'free'   => 10,
		'pro'    => 20,
		'label'  => 30,
		'agency' => 40,
	);

	/**
	 * Minimum license tier for every provider in MDS Audio Providers.
	 *
	 * @var array<string, string>
	 */
	public const PROVIDER_TIERS = array(
		'apple_music' => 'free',
		'deezer'      => 'free',
		'bandcamp'    => 'free',
		'audius'      => 'free',
		'mixcloud'    => 'free',
		'audiomack'   => 'free',
		'soundcloud'  => 'pro',
		'jamendo'     => 'pro',
		'funkwhale'   => 'pro',
		'spotify'     => 'pro',
		'tidal'       => 'pro',
		'musicfetch'  => 'pro',
	);

	/**
	 * Singleton client instance.
	 *
	 * @var GatewayClient|null
	 */
	private static ?GatewayClient $client = null;

	/**
	 * Optional test/override callback for `call_proxy()` in unit tests.
	 *
	 * @var callable|null
	 */
	private static $proxy_override = null;

	/**
	 * Returns the singleton `GatewayClient` instance.
	 *
	 * @return GatewayClient
	 */
	public static function client(): GatewayClient {
		if ( null === self::$client ) {
			$basename     = defined( 'MDS_AP_BASENAME' ) ? (string) MDS_AP_BASENAME : 'mds-audio-providers/mds-audio-providers.php';
			$version      = defined( 'MDS_AP_VERSION' ) ? (string) MDS_AP_VERSION : '2.2.0';
			self::$client = new GatewayClient( 'mds-audio-providers', $basename, $version );
		}
		return self::$client;
	}

	/**
	 * Registers the native WordPress `Update URI` updater (skipped on `.git` dev checkouts).
	 *
	 * @return void
	 */
	public static function register_updater(): void {
		if ( defined( 'MDS_AP_PATH' ) && is_dir( MDS_AP_PATH . '.git' ) ) {
			return;
		}
		self::client()->register_updater();
	}

	/**
	 * Returns current license status, tier, masked key, and auto-detected Gateway URL.
	 *
	 * @return array{activated: bool, tier: string, tier_label: string, expires_at: int, key_masked: string, api_base_url: string}
	 */
	public static function get_status(): array {
		$status = self::client()->get_status();
		$labels = array(
			'free'   => __( 'Free Tier', 'mds-audio-providers' ),
			'pro'    => __( 'Pro / Artist', 'mds-audio-providers' ),
			'label'  => __( 'Label / Network', 'mds-audio-providers' ),
			'agency' => __( 'Agency / Enterprise', 'mds-audio-providers' ),
		);

		$tier = (string) ( $status['tier'] ?? '' );

		return array(
			'activated'    => ! empty( $status['activated'] ),
			'tier'         => $tier,
			'tier_label'   => '' !== $tier ? ( $labels[ $tier ] ?? ucfirst( $tier ) ) : __( 'Not Activated', 'mds-audio-providers' ),
			'expires_at'   => (int) ( $status['expires_at'] ?? 0 ),
			'key_masked'   => (string) ( $status['key_masked'] ?? '' ),
			'api_base_url' => (string) ( $status['api_base_url'] ?? '' ),
		);
	}

	/**
	 * Checks whether the current site has an activated license satisfying `$required_tier`.
	 *
	 * @param string $required_tier Minimum tier slug (`free`, `pro`, `label`, `agency`).
	 * @return bool
	 */
	public static function has_tier( string $required_tier ): bool {
		$status = self::client()->get_status();
		if ( empty( $status['activated'] ) ) {
			return false;
		}
		$actual   = self::TIER_RANK[ strtolower( (string) $status['tier'] ) ] ?? 0;
		$required = self::TIER_RANK[ strtolower( trim( $required_tier ) ) ] ?? 999;
		return $actual >= $required;
	}

	/**
	 * Activates a license key via MDS Cloud Gateway.
	 *
	 * @param string $license_key Plaintext license key.
	 * @return array<string, mixed>
	 */
	public static function activate( string $license_key ): array {
		return self::client()->activate( $license_key );
	}

	/**
	 * Deactivates the site license and clears local tokens.
	 *
	 * @return bool
	 */
	public static function deactivate(): bool {
		return self::client()->deactivate();
	}

	/**
	 * Calls a licensed proxy service on MDS Cloud Gateway.
	 *
	 * @param string               $service Service slug (`audio-resolve` or `audio-bandcamp-verify`).
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>
	 */
	public static function call_proxy( string $service, array $payload = array() ): array {
		if ( is_callable( self::$proxy_override ) ) {
			return (array) call_user_func( self::$proxy_override, $service, $payload );
		}
		return self::client()->call_proxy( $service, $payload );
	}

	/**
	 * Sets an optional proxy transport override (used exclusively by standalone unit tests).
	 *
	 * @param callable|null $callback Transport callable `(string $service, array $payload): array`, or null to reset.
	 * @return void
	 */
	public static function set_proxy_override( ?callable $callback ): void {
		self::$proxy_override = $callback;
	}

	/**
	 * Resets the singleton client (used by tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$client         = null;
		self::$proxy_override = null;
	}
}
