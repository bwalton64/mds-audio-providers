<?php
/**
 * REST API endpoints for MDS Audio Providers settings, connection testing, and cache management.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Api;

use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Providers\Credentials;
use MDS\AudioProviders\Providers\SoundCloudProvider;
use MDS\AudioProviders\Providers\SpotifyProvider;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST routes under `mds-ap/v1`.
 *
 * @since 2.0.0
 */
final class RestApi {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'mds-ap/v1';

	/**
	 * Registers REST route initialization hook.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers admin REST endpoints.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$admin_check = static fn(): bool => current_user_can( 'manage_options' );

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_settings' ),
					'permission_callback' => $admin_check,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'save_settings' ),
					'permission_callback' => $admin_check,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'test_connection' ),
				'permission_callback' => $admin_check,
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clear-cache',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'clear_cache' ),
				'permission_callback' => $admin_check,
			)
		);
	}

	/**
	 * Returns current provider priority, enabled states, masked credentials, and cache summary.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_settings(): WP_REST_Response {
		return new WP_REST_Response( self::build_payload(), 200 );
	}

	/**
	 * Saves provider priority, disabled list, and optional API credentials.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'mds_ap_invalid_payload', __( 'Invalid settings payload.', 'mds-audio-providers' ), array( 'status' => 400 ) );
		}

		if ( isset( $params['priority'] ) && is_array( $params['priority'] ) ) {
			$clean_priority = array_values( array_unique( array_filter( array_map( 'sanitize_key', $params['priority'] ) ) ) );
			update_option( 'mds_rm_audio_provider_priority', $clean_priority, false );
		}

		if ( isset( $params['disabled'] ) && is_array( $params['disabled'] ) ) {
			$clean_disabled = array_values( array_unique( array_filter( array_map( 'sanitize_key', $params['disabled'] ) ) ) );
			update_option( 'mds_rm_audio_disabled_providers', $clean_disabled, false );
		}

		if ( isset( $params['credentials'] ) && is_array( $params['credentials'] ) ) {
			foreach ( array( 'soundcloud', 'spotify' ) as $slug ) {
				if ( ! isset( $params['credentials'][ $slug ] ) || ! is_array( $params['credentials'][ $slug ] ) ) {
					continue;
				}
				$entry = $params['credentials'][ $slug ];
				$clear = ! empty( $entry['clear'] );
				$cid   = isset( $entry['client_id'] ) && is_string( $entry['client_id'] ) ? $entry['client_id'] : '';
				$sec   = isset( $entry['client_secret'] ) && is_string( $entry['client_secret'] ) ? $entry['client_secret'] : '';
				if ( $clear || '' !== trim( $cid ) || '' !== trim( $sec ) ) {
					Credentials::save( $slug, $cid, $sec, $clear );
				}
			}
		}

		if ( class_exists( '\\MDS\\ReleaseManager\\Core\\Audio\\AudioApi' ) ) {
			\MDS\ReleaseManager\Core\Audio\AudioApi::reset_cache();
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking MDS Release Manager core cache purge hook.
		do_action( 'mds_rm_purge_cache', 'audio_providers_settings' );

		return new WP_REST_Response(
			array_merge(
				self::build_payload(),
				array(
					'saved'   => true,
					'message' => __( 'Audio provider settings saved.', 'mds-audio-providers' ),
				)
			),
			200
		);
	}

	/**
	 * Tests OAuth credentials for SoundCloud or Spotify.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function test_connection( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );

		$result = match ( $provider ) {
			'soundcloud' => SoundCloudProvider::test_connection(),
			'spotify'    => SpotifyProvider::test_connection(),
			default      => new WP_Error( 'mds_ap_unknown_provider', __( 'Unsupported provider for connection testing.', 'mds-audio-providers' ), array( 'status' => 400 ) ),
		};

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Clears all MDS Audio Providers transients and triggers Release Manager page cache purge.
	 *
	 * @return WP_REST_Response
	 */
	public static function clear_cache(): WP_REST_Response {
		$deleted = Cache::clear_all( true );
		return new WP_REST_Response(
			array(
				'deleted' => $deleted,
				'cache'   => Cache::get_summary(),
				'message' => sprintf(
					/* translators: %d: Number of deleted cache entries. */
					__( 'Cleared %d cached audio provider entries.', 'mds-audio-providers' ),
					$deleted
				),
			),
			200
		);
	}

	/**
	 * Builds the full admin state payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_payload(): array {
		$providers = class_exists( '\\MDS\\ReleaseManager\\Core\\Audio\\AudioApi' )
			? \MDS\ReleaseManager\Core\Audio\AudioApi::get_provider_descriptors()
			: array();

		return array(
			'providers'   => $providers,
			'credentials' => Credentials::get_masked_status(),
			'cache'       => Cache::get_summary(),
		);
	}
}
