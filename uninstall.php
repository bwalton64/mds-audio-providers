<?php
/**
 * Uninstall cleanup for MDS Audio Providers.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( static function (): void {
	delete_option( 'mds_ap_credentials' );
	delete_option( 'mds_ap_cached_keys' );
	delete_transient( 'mds_ap_soundcloud_token' );
	delete_transient( 'mds_ap_spotify_token' );
} )();
