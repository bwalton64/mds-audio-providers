<?php
/**
 * Legacy block and settings migration for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Migrations;

use MDS\Bandcamp\Core\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates saved block names and legacy plugin settings.
 *
 * @since 1.0.0
 */
final class BlockMigration {
	/**
	 * Register migration hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'run' ) );
		add_action( 'admin_notices', array( self::class, 'show_standalone_plugin_notice' ) );
		add_filter( 'render_block_data', array( self::class, 'prepare_legacy_block' ) );
	}

	/**
	 * Prepare a parsed block for rendering, including legacy namespaces.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $parsed_block Parsed block structure.
	 * @return array<string, mixed> Block structure with legacy names converted.
	 */
	public static function prepare_legacy_block( array $parsed_block ): array {
		if (
			isset( $parsed_block['blockName'] )
			&& is_string( $parsed_block['blockName'] )
			&& 0 === strpos( $parsed_block['blockName'], 'blocks-for-bandcamp/' )
		) {
			$parsed_block['blockName'] = 'mds-bandcamp/' . substr( $parsed_block['blockName'], strlen( 'blocks-for-bandcamp/' ) );
			if ( ! is_admin() ) {
				wp_enqueue_style( 'mds-bc-blocks', MDS_BC_PLUGIN_URL . '/css/blocks.css', array(), MDS_BC_PLUGIN_VERSION );
				if ( 'mds-bandcamp/bandcamp-album' === $parsed_block['blockName'] ) {
					wp_enqueue_script( 'mds-bc-blocks', MDS_BC_URL . 'js/blocks.js', array(), MDS_BC_PLUGIN_VERSION, true );
				}
			}
		}

		if ( isset( $parsed_block['innerBlocks'] ) && is_array( $parsed_block['innerBlocks'] ) ) {
			$parsed_block['innerBlocks'] = array_map( array( self::class, 'prepare_legacy_block' ), $parsed_block['innerBlocks'] );
		}

		return $parsed_block;
	}

	/**
	 * Run settings, block-content, and standalone-plugin migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function run(): void {
		self::migrate_settings();
		self::migrate_block_content();
		self::deactivate_standalone_plugin();
	}

	/**
	 * Copy settings from the standalone plugin when no current setting exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function migrate_settings(): void {
		$target = get_option( 'mds_bandcamp__settings', null );
		$source = get_option( 'gf_blocks_for_bandcamp__settings', null );
		if ( null === $target && is_array( $source ) ) {
			update_option( 'mds_bandcamp__settings', $source );
		}
	}

	/**
	 * Deactivate the standalone Blocks for Bandcamp plugin if it is active.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function deactivate_standalone_plugin(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin     = 'blocks-for-bandcamp/blocks-for-bandcamp.php';
		$was_active = is_plugin_active( $plugin );

		if ( $was_active ) {
			deactivate_plugins( $plugin );
		}

		if ( is_multisite() && is_plugin_active_for_network( $plugin ) ) {
			deactivate_plugins( $plugin, false, true );
		}

		if ( $was_active && ! is_plugin_active( $plugin ) && ( ! is_multisite() || ! is_plugin_active_for_network( $plugin ) ) ) {
			set_transient( 'mds_bc_standalone_plugin_deactivated', true, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Display the one-time standalone-plugin deactivation notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function show_standalone_plugin_notice(): void {
		if ( ! get_transient( 'mds_bc_standalone_plugin_deactivated' ) ) {
			return;
		}
		delete_transient( 'mds_bc_standalone_plugin_deactivated' );
		echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html__( 'Blocks for Bandcamp has been deactivated because its features are now included in MDS Bandcamp Integration.', 'mds-bandcamp-integration' ) .
			'</p></div>';
	}

	/**
	 * Migrate saved block namespaces in bounded batches.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function migrate_block_content(): void {
		if ( get_option( 'mds_bandcamp_block_namespace_migrated' ) ) {
			return;
		}

		global $wpdb;
		$cursor = (int) get_option( 'mds_bandcamp_block_migration_cursor', 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded legacy block namespace migration.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content
				FROM {$wpdb->posts}
				WHERE ID > %d
					AND post_type != %s
					AND post_content LIKE %s
				ORDER BY ID ASC
				LIMIT 100",
				$cursor,
				'revision',
				'%' . $wpdb->esc_like( '<!-- wp:blocks-for-bandcamp/' ) . '%'
			)
		);

		if ( ! is_array( $posts ) ) {
			Logging::log( 'error', 'Could not query posts for block-name migration.' );
			return;
		}

		if ( empty( $posts ) ) {
			update_option( 'mds_bandcamp_block_namespace_migrated', 1 );
			delete_option( 'mds_bandcamp_block_migration_cursor' );
			return;
		}

		foreach ( $posts as $post ) {
			$content = preg_replace(
				'/<!--\s*(\/?)wp:blocks-for-bandcamp\//',
				'<!-- $1wp:mds-bandcamp/',
				$post->post_content
			);

			if ( null === $content ) {
				Logging::log( 'error', 'Could not parse block markup during migration.', array( 'post_id' => (int) $post->ID ) );
				return;
			}

			if ( $content !== $post->post_content ) {
				$result = wp_update_post(
					array(
						'ID'           => (int) $post->ID,
						'post_content' => wp_slash( $content ),
					),
					true
				);

				if ( is_wp_error( $result ) ) {
					Logging::log(
						'error',
						'Failed to migrate block names.',
						array(
							'post_id' => (int) $post->ID,
							'error'   => $result->get_error_message(),
						)
					);
					return;
				}
			}

			$cursor = (int) $post->ID;
		}

		update_option( 'mds_bandcamp_block_migration_cursor', $cursor );
	}
}
