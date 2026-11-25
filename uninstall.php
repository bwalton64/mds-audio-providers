<?php
/**
 * Uninstall cleanup for MDS Audio Providers.
 *
 * Removes plugin-owned options and cached provider transients across single-site
 * and multisite installs while preserving Release post metadata.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( static function (): void {
	$cleanup_site = static function (): void {
		delete_option( 'mds_ap_credentials' );
		delete_option( 'mds_ap_cached_keys' );
		delete_transient( 'mds_ap_soundcloud_token' );
		delete_transient( 'mds_ap_spotify_token' );

		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			$like_val     = $wpdb->esc_like( '_transient_mds_ap_' ) . '%';
			$like_timeout = $wpdb->esc_like( '_transient_timeout_mds_ap_' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted uninstall cleanup of MDS Audio Providers transients.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$like_val,
					$like_timeout
				)
			);
		}
	};

	$cleanup_site();

	if ( ! is_multisite() ) {
		return;
	}

	$current_blog_id = get_current_blog_id();
	$site_ids        = get_sites(
		array(
			'network_id' => get_current_network_id(),
			'fields'     => 'ids',
			'number'     => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		$blog_id = (int) $site_id;
		if ( $blog_id === $current_blog_id ) {
			continue;
		}

		switch_to_blog( $blog_id );
		try {
			$cleanup_site();
		} finally {
			restore_current_blog();
		}
	}
} )();
