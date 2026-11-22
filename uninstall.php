<?php
/**
 * Uninstall cleanup for MDS Bandcamp Integration.
 *
 * Removes plugin options and transient cache entries when the plugin is deleted.
 *
 * @package MDS\Bandcamp
 * @since   1.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$mds_bc_options = array(
	'mds_bandcamp__settings',
	'mds_bandcamp_migration_complete',
	'mds_bandcamp_audio_player_activated',
	'mds_bandcamp_block_namespace_migrated',
	'mds_bandcamp_block_migration_cursor',
);

foreach ( $mds_bc_options as $mds_bc_option ) {
	delete_option( $mds_bc_option );
}

global $wpdb;

if ( isset( $wpdb ) && $wpdb instanceof wpdb ) {
	$mds_bc_like_legacy_val     = $wpdb->esc_like( '_transient_mds_bandcamp' ) . '%';
	$mds_bc_like_legacy_timeout = $wpdb->esc_like( '_transient_timeout_mds_bandcamp' ) . '%';
	$mds_bc_like_new_val        = $wpdb->esc_like( '_transient_mds_bc_' ) . '%';
	$mds_bc_like_new_timeout    = $wpdb->esc_like( '_transient_timeout_mds_bc_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall transient cleanup.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s
			OR option_name LIKE %s
			OR option_name LIKE %s",
			$mds_bc_like_legacy_val,
			$mds_bc_like_legacy_timeout,
			$mds_bc_like_new_val,
			$mds_bc_like_new_timeout
		)
	);
}
