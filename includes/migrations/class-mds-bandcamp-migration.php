<?php
/**
 * Data migration utilities for Bandcamp Integration.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration of deprecated Bandcamp data.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Migration {
	/**
	 * Register migration hooks.
	 *
	 * @since 1.0.0
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
	 * @return void
	 */
	public static function maybe_migrate_legacy_data(): void {
		// Check whether migration has already run.
		if ( get_option( 'mds_bandcamp_migration_complete' ) ) {
			return;
		}

		// Get all release posts with deprecated shortcode meta.
		$posts = get_posts(
			array(
				'post_type'      => 'release',
				'meta_key'       => 'bandcamp_shortcode',
				'numberposts'    => -1,
				'fields'         => 'ids',
				'suppress_filters' => false,
			)
		);

		if ( empty( $posts ) ) {
			update_option( 'mds_bandcamp_migration_complete', '1' );
			return;
		}

		$pending = 0;

		foreach ( $posts as $post_id ) {
			$old_shortcode = get_post_meta( $post_id, 'bandcamp_shortcode', true );

			if ( empty( $old_shortcode ) || ! is_string( $old_shortcode ) ) {
				++$pending;
				continue;
			}

			// Try to extract album ID from the old shortcode.
			$album_id = self::extract_album_id_from_shortcode( $old_shortcode );

			if ( $album_id > 0 ) {
				// Store in the new meta format.
				$bandcamp_data = array(
					'album_id'   => $album_id,
					'url'        => '',
					'migrated'   => 1,
					'migrated_at' => current_time( 'timestamp' ),
				);

				update_post_meta( $post_id, 'mds_bandcamp_integration', $bandcamp_data );
				delete_post_meta( $post_id, 'bandcamp_shortcode' );

				// Log the migration.
				MDS_Bandcamp_Logging::log(
					'info',
					sprintf(
						'Migrated legacy bandcamp_shortcode for post %d. Extracted album_id: %d',
						$post_id,
						$album_id
					)
				);
			} else {
				++$pending;
			}
		}

		if ( $pending > 0 ) {
			MDS_Bandcamp_Logging::log(
				'warning',
				'Legacy Bandcamp data migration remains pending.',
				array( 'post_count' => $pending )
			);
			return;
		}

		// Clean up the deprecated option.
		delete_option( 'mds_rm_bandcamp_template' );

		// Mark migration as complete.
		update_option( 'mds_bandcamp_migration_complete', '1' );

		MDS_Bandcamp_Logging::log( 'info', 'Legacy Bandcamp data migration completed.' );
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
		// Try to match album= or album_id= patterns
		if ( preg_match( '/album(?:_id)?[=\s]+(\d+)/i', $shortcode, $matches ) ) {
			return absint( $matches[1] );
		}

		// Try to extract numeric value if it's just a plain album ID
		if ( preg_match( '/^\d+$/', trim( $shortcode ) ) ) {
			return absint( $shortcode );
		}

		return 0;
	}
}
