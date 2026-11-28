<?php
/**
 * WordPress 7.x Abilities & MCP registration for MDS Audio Providers.
 *
 * @package   MDS\AudioProviders
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers read-only MCP-discoverable audio preview abilities (`mds-ap/*`).
 */
final class Abilities {

	/**
	 * Hook ability registration onto WordPress 7.0 `wp_abilities_api_init`.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	/**
	 * Register read-only audio provider abilities with MCP behavioral hints.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'mds-ap/list-providers',
			array(
				'label'               => __( 'List MDS Audio Preview Providers', 'mds-audio-providers' ),
				'description'         => __( 'Returns all registered streaming audio preview providers and their priority order.', 'mds-audio-providers' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'execute_callback'    => static function (): array {
					$providers = apply_filters( 'mds_rm_audio_providers', array() );
					return array(
						'providers' => is_array( $providers ) ? array_keys( $providers ) : array(),
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array( 'public' => true ),
				),
			)
		);

		wp_register_ability(
			'mds-ap/resolve-preview',
			array(
				'label'               => __( 'Resolve Release Audio Preview', 'mds-audio-providers' ),
				'description'         => __( 'Resolves the playable audio preview manifest for a Release post ID via MDS Release Manager Unified Audio API.', 'mds-audio-providers' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'release_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'release_id' ),
				),
				'execute_callback'    => static function ( array $input ): array {
					$release_id = absint( $input['release_id'] ?? 0 );
					if ( $release_id <= 0 || ! class_exists( \MDS\ReleaseManager\Core\Audio\AudioApi::class ) ) {
						return array( 'ok' => false, 'error' => 'Invalid release ID or AudioApi unavailable.' );
					}
					$manifest = \MDS\ReleaseManager\Core\Audio\AudioApi::resolve_manifest( $release_id );
					return array(
						'ok'       => is_array( $manifest ) && ! empty( $manifest ),
						'manifest' => is_array( $manifest ) ? $manifest : array(),
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array( 'public' => true ),
				),
			)
		);
	}
}
