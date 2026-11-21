<?php
/**
 * REST API endpoints for MDS Bandcamp Integration.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST API routes for Bandcamp data fetching.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_REST_API {
	/**
	 * Register REST API hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_meta_route' ) );
	}

	/**
	 * Register the custom meta route.
	 *
	 * @return void
	 */
	public static function register_meta_route(): void {
		register_rest_route(
			'mds-bandcamp/v1',
			'/meta',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( self::class, 'check_permissions' ),
				'args'                => array(
					'url'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
				'callback'            => array( self::class, 'handle_meta_request' ),
			)
		);
	}

	/**
	 * Check whether the current user can access Bandcamp metadata.
	 *
	 * @return bool
	 */
	public static function check_permissions(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Fetch requested meta-tag content.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_meta_request( WP_REST_Request $request ) {
		$url  = (string) $request->get_param( 'url' );
		$name = sanitize_key( (string) $request->get_param( 'name' ) );
		$parsed = wp_parse_url( $url );

		if ( empty( $parsed['scheme'] ) || 'https' !== strtolower( $parsed['scheme'] ) ) {
			return new WP_Error( 'bad_scheme', 'Only https URLs are allowed.', array( 'status' => 400 ) );
		}

		if ( empty( $parsed['host'] ) || ! preg_match( '/(^|\.)bandcamp\.com$/i', $parsed['host'] ) ) {
			return new WP_Error( 'bad_host', 'Only bandcamp.com URLs are allowed.', array( 'status' => 400 ) );
		}

		if ( '' === $name ) {
			return new WP_Error( 'missing_name', 'Meta name is required.', array( 'status' => 400 ) );
		}

		$html = MDS_Bandcamp_API::fetch_bandcamp_page( $url );
		if ( is_array( $html ) && isset( $html['error'] ) ) {
			return new WP_Error( 'bandcamp_fetch_failed', $html['message'] ?? 'Bandcamp request failed.', array( 'status' => 502 ) );
		}

		$value = MDS_Bandcamp_API::get_meta_tag_content( $html, $name );
		if ( false === $value ) {
			return new WP_Error( 'meta_not_found', 'Meta tag not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'name'  => $name,
				'value' => $value,
			)
		);
	}
}

