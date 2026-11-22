<?php
/**
 * Data migration utilities for MDS Bandcamp Integration.
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
 * Handles migration of deprecated Bandcamp data.
 *
 * @since 1.0.0
 */
final class Migration {
	/**
	 * Register migration hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'maybe_migrate_legacy_data' ) );
	}

	/**
	 * Migrate legacy bandcamp_shortcode post meta to new format.
	 *
	 * Runs once on first admin load after plugin update.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function maybe_migrate_legacy_data(): void {
		self::maybe_activate_audio_player();

		// Check whether migration has already run.
		if ( get_option( 'mds_bandcamp_migration_complete' ) ) {
			return;
		}

		// Get a bounded batch of release posts with deprecated shortcode meta not previously skipped.
		$posts = get_posts(
			array(
				'post_type'        => 'release',
				'posts_per_page'   => 100,
				'no_found_rows'    => true,
				'fields'           => 'ids',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded one-time legacy migration.
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'     => 'bandcamp_shortcode',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_mds_bc_shortcode_unparseable',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			delete_option( 'mds_rm_bandcamp_template' );
			update_option( 'mds_bandcamp_migration_complete', '1' );
			return;
		}

		$skipped = 0;

		foreach ( $posts as $post_id ) {
			$old_shortcode = get_post_meta( $post_id, 'bandcamp_shortcode', true );

			if ( empty( $old_shortcode ) || ! is_string( $old_shortcode ) ) {
				update_post_meta( $post_id, '_mds_bc_shortcode_unparseable', '1' );
				++$skipped;
				continue;
			}

			// Try to extract album ID from the old shortcode.
			$album_id = self::extract_album_id_from_shortcode( $old_shortcode );

			if ( $album_id > 0 ) {
				// Store in the new meta format.
				$bandcamp_data = array(
					'album_id'    => $album_id,
					'url'         => '',
					'migrated'    => 1,
					'migrated_at' => time(),
				);

				update_post_meta( $post_id, 'mds_bandcamp_integration', $bandcamp_data );
				delete_post_meta( $post_id, 'bandcamp_shortcode' );

				// Log the migration.
				Logging::log(
					'info',
					sprintf(
						'Migrated legacy bandcamp_shortcode for post %d. Extracted album_id: %d',
						$post_id,
						$album_id
					)
				);
			} else {
				update_post_meta( $post_id, '_mds_bc_shortcode_unparseable', '1' );
				++$skipped;
			}
		}

		if ( $skipped > 0 ) {
			Logging::log(
				'warning',
				'Skipped unparseable legacy bandcamp_shortcode post meta entries.',
				array( 'post_count' => $skipped )
			);
		}

		// If fewer than a full batch were returned, all remaining posts have been processed.
		if ( count( $posts ) < 100 ) {
			delete_option( 'mds_rm_bandcamp_template' );
			update_option( 'mds_bandcamp_migration_complete', '1' );
			Logging::log( 'info', 'Legacy Bandcamp data migration completed.' );
		}
	}

	/**
	 * Activates the Bandcamp player in the MDS Release Manager Audio Player Manager once.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function maybe_activate_audio_player(): void {
		if ( get_option( 'mds_bandcamp_audio_player_activated' ) ) {
			return;
		}

		update_option( 'mds_rm_audio_players', array( 'bandcamp' ) );
		update_option( 'mds_bandcamp_audio_player_activated', '1' );
	}

	/**
	 * Extract album ID from old-style shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param string $shortcode Old shortcode string.
	 * @return int Album ID if found, 0 otherwise.
	 */
	private static function extract_album_id_from_shortcode( string $shortcode ): int {
		// Try to match album= or album_id= patterns.
		if ( preg_match( '/album(?:_id)?[=\s]+(\d+)/i', $shortcode, $matches ) ) {
			return absint( $matches[1] );
		}

		// Try to extract numeric value if it is just a plain album ID.
		if ( preg_match( '/^\d+$/', trim( $shortcode ) ) ) {
			return absint( $shortcode );
		}

		return 0;
	}
}
