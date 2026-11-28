<?php
/**
 * REST API endpoints for MDS Audio Providers settings, license activation,
 * Funkwhale connection testing, and cache management.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Api;

use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Gateway\GatewayBridge;
use MDS\AudioProviders\Providers\Credentials;
use MDS\AudioProviders\Providers\FunkwhaleProvider;
use MDS\AudioProviders\Providers\ProviderOptions;
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
			'/license/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'activate_license' ),
				'permission_callback' => $admin_check,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/license/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'deactivate_license' ),
				'permission_callback' => $admin_check,
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
	 * Returns current provider priority, enabled states, license status, masked credentials, and cache summary.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_settings(): WP_REST_Response {
		return new WP_REST_Response( self::build_payload(), 200 );
	}

	/**
	 * Saves provider priority, disabled list, Funkwhale pod credentials, and embed-fallback toggles.
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

		if ( isset( $params['credentials']['funkwhale'] ) && is_array( $params['credentials']['funkwhale'] ) ) {
			$entry = $params['credentials']['funkwhale'];
			$clear = ! empty( $entry['clear'] );
			$cid   = isset( $entry['client_id'] ) && is_string( $entry['client_id'] ) ? $entry['client_id'] : '';
			$sec   = isset( $entry['client_secret'] ) && is_string( $entry['client_secret'] ) ? $entry['client_secret'] : '';
			if ( $clear || '' !== trim( $cid ) || '' !== trim( $sec ) ) {
				Credentials::save( 'funkwhale', $cid, $sec, $clear );
			}
		}

		if ( isset( $params['provider_options'] ) && is_array( $params['provider_options'] ) ) {
			ProviderOptions::save_all( $params['provider_options'] );
		}

		self::purge_core_audio_caches( 'audio_providers_settings' );

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
	 * Activates a site license key against MDS Cloud Gateway.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function activate_license( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		$key    = is_array( $params ) && isset( $params['license_key'] ) && is_string( $params['license_key'] )
			? sanitize_text_field( $params['license_key'] )
			: '';

		if ( '' === trim( $key ) ) {
			return new WP_Error( 'mds_ap_missing_license', __( 'Enter an MDS license key to activate.', 'mds-audio-providers' ), array( 'status' => 400 ) );
		}

		$result = GatewayBridge::activate( $key );
		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'mds_ap_activation_failed',
				(string) ( $result['error'] ?? __( 'License activation failed.', 'mds-audio-providers' ) ),
				array( 'status' => 400 )
			);
		}

		Cache::clear_all( false );
		self::purge_core_audio_caches( 'audio_providers_license_activate' );

		return new WP_REST_Response(
			array_merge(
				self::build_payload(),
				array(
					'ok'      => true,
					'message' => __( 'MDS Cloud Gateway license activated.', 'mds-audio-providers' ),
				)
			),
			200
		);
	}

	/**
	 * Deactivates the site license and clears local tokens.
	 *
	 * @return WP_REST_Response
	 */
	public static function deactivate_license(): WP_REST_Response {
		GatewayBridge::deactivate();
		Cache::clear_all( false );
		self::purge_core_audio_caches( 'audio_providers_license_deactivate' );

		return new WP_REST_Response(
			array_merge(
				self::build_payload(),
				array(
					'ok'      => true,
					'message' => __( 'MDS Cloud Gateway license deactivated.', 'mds-audio-providers' ),
				)
			),
			200
		);
	}

	/**
	 * Tests local Funkwhale pod connectivity.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function test_connection( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		if ( 'funkwhale' !== $provider ) {
			return new WP_Error( 'mds_ap_unknown_provider', __( 'Connection testing is only needed for local Funkwhale pods.', 'mds-audio-providers' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( FunkwhaleProvider::test_connection(), 200 );
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
		$providers = class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			? \MDS\ReleaseManager\Api\ExtensionApi::get_audio_providers()
			: array();

		return array(
			'providers'        => $providers,
			'provider_tiers'   => GatewayBridge::PROVIDER_TIERS,
			'license'          => GatewayBridge::get_status(),
			'credentials'      => Credentials::get_masked_status(),
			'provider_options' => ProviderOptions::get_all(),
			'cache'            => Cache::get_summary(),
		);
	}

	/**
	 * Resets Core's Unified Audio API cache and notifies Cache Manager.
	 *
	 * @param string $reason Purge reason tag.
	 * @return void
	 */
	private static function purge_core_audio_caches( string $reason ): void {
		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) && method_exists( \MDS\ReleaseManager\Api\ExtensionApi::class, 'reset_audio_cache' ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::reset_audio_cache();
		} elseif ( class_exists( '\\MDS\\ReleaseManager\\Core\\Audio\\AudioApi' ) ) {
			\MDS\ReleaseManager\Core\Audio\AudioApi::reset_cache();
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking MDS Release Manager core cache purge hook.
		do_action( 'mds_rm_purge_cache', $reason );
	}
}
