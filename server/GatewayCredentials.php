<?php
/**
 * Centralized DSP credential resolver for MDS Cloud Gateway's Audio Providers server module.
 *
 * Reads MDS-managed upstream API credentials from PHP constants, environment variables,
 * or the gateway's `mds_cg_audio_credentials` option — never exposed to customer sites.
 *
 * @package   MDS\AudioProviders\Server
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   Proprietary — Musty Dungeon Studios Commercial Server Module
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves central MDS API keys for OAuth / API-key streaming providers.
 */
final class GatewayCredentials {

	/**
	 * Gateway option storing centralized DSP credentials when not set via constants/ENV.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'mds_cg_audio_credentials';

	/**
	 * Constant / environment variable map per provider.
	 *
	 * @var array<string, array{client_id: string, client_secret: string}>
	 */
	private const MAP = array(
		'spotify'    => array(
			'client_id'     => 'MDS_CG_SPOTIFY_CLIENT_ID',
			'client_secret' => 'MDS_CG_SPOTIFY_CLIENT_SECRET',
		),
		'soundcloud' => array(
			'client_id'     => 'MDS_CG_SOUNDCLOUD_CLIENT_ID',
			'client_secret' => 'MDS_CG_SOUNDCLOUD_CLIENT_SECRET',
		),
		'tidal'      => array(
			'client_id'     => 'MDS_CG_TIDAL_CLIENT_ID',
			'client_secret' => 'MDS_CG_TIDAL_CLIENT_SECRET',
		),
		'jamendo'    => array(
			'client_id'     => 'MDS_CG_JAMENDO_CLIENT_ID',
			'client_secret' => 'MDS_CG_JAMENDO_CLIENT_SECRET',
		),
		'musicfetch' => array(
			'client_id'     => '',
			'client_secret' => 'MDS_CG_MUSICFETCH_API_KEY',
		),
	);

	/**
	 * Returns central credentials for the requested DSP provider.
	 *
	 * Precedence: PHP constant → environment variable → gateway DB option.
	 *
	 * @param string $provider Provider slug.
	 * @return array{client_id: string, client_secret: string}
	 */
	public static function get( string $provider ): array {
		$slug = sanitize_key( $provider );
		$spec = self::MAP[ $slug ] ?? null;
		if ( null === $spec ) {
			return array(
				'client_id'     => '',
				'client_secret' => '',
			);
		}

		$client_id     = self::read_key( $spec['client_id'] );
		$client_secret = self::read_key( $spec['client_secret'] );

		if ( '' === $client_id || '' === $client_secret ) {
			$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
			if ( is_array( $stored ) && isset( $stored[ $slug ] ) && is_array( $stored[ $slug ] ) ) {
				if ( '' === $client_id && ! empty( $stored[ $slug ]['client_id'] ) && is_string( $stored[ $slug ]['client_id'] ) ) {
					$client_id = trim( $stored[ $slug ]['client_id'] );
				}
				if ( '' === $client_secret && ! empty( $stored[ $slug ]['client_secret'] ) && is_string( $stored[ $slug ]['client_secret'] ) ) {
					$client_secret = trim( $stored[ $slug ]['client_secret'] );
				}
			}
		}

		return array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);
	}

	/**
	 * Reads a single credential value from PHP constant or environment variable.
	 *
	 * @param string $name Constant / env var name.
	 * @return string Trimmed value, or empty string.
	 */
	private static function read_key( string $name ): string {
		if ( '' === $name ) {
			return '';
		}
		if ( defined( $name ) ) {
			$val = constant( $name );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				return trim( $val );
			}
		}
		$env = getenv( $name );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return trim( $env );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reading raw server environment secret.
		if ( isset( $_ENV[ $name ] ) && is_string( $_ENV[ $name ] ) && '' !== trim( $_ENV[ $name ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw API secret value.
			return trim( $_ENV[ $name ] );
		}
		return '';
	}
}
