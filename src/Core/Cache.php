<?php
/**
 * Cache management for the MDS Bandcamp Integration plugin.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages transient cache for Bandcamp API data.
 *
 * @since 1.0.0
 */
final class Cache {
	/**
	 * Legacy plugin key preserved for backwards-compatible option and cache lookups.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $plugin_key = 'mds_bandcamp';

	/**
	 * Register cache management hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_notices', array( self::class, 'show_clear_cache_notice' ) );
		add_action( 'admin_post_mds_bc_clear_cache', array( self::class, 'handle_clear_cache' ) );
	}

	/**
	 * Get a JSON array of cached transients for the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search Optional search filter.
	 * @return string JSON-encoded transient information.
	 */
	public static function get_transients_list( string $search = '' ): string {
		global $wpdb;

		$prefix         = '_transient_';
		$timeout_prefix = '_transient_timeout_';

		if ( ! empty( $search ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin cache inspection table.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value
					FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s
					ORDER BY option_name ASC
					LIMIT 250",
					$wpdb->esc_like( $prefix . $search ) . '%',
					$wpdb->esc_like( $timeout_prefix . $search ) . '%'
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin cache inspection table.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value
					FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s
					ORDER BY option_name ASC
					LIMIT 250",
					$wpdb->esc_like( $prefix ) . '%',
					$wpdb->esc_like( $timeout_prefix ) . '%'
				)
			);
		}

		$transients = array();

		foreach ( $results as $row ) {
			if ( str_starts_with( $row->option_name, $timeout_prefix ) ) {
				$key     = str_replace( $timeout_prefix, '', $row->option_name );
				$expires = (int) $row->option_value;

				if ( ! isset( $transients[ $key ] ) ) {
					$transients[ $key ] = array( 'key' => $key );
				}

				$transients[ $key ]['expires'] = $expires ? wp_date( 'Y-m-d H:i:s', $expires ) : 'never';
			} elseif ( str_starts_with( $row->option_name, $prefix ) ) {
				$key       = str_replace( $prefix, '', $row->option_name );
				$searchkey = str_replace( $prefix . $search . '_', '', $row->option_name );

				if ( ! isset( $transients[ $key ] ) ) {
					$transients[ $key ] = array( 'key' => $key );
				}

				$keys                           = explode( '_', $searchkey, 3 );
				$transients[ $key ]['endpoint'] = $keys[1] ?? '';
				$transients[ $key ]['id']       = $keys[2] ?? '';

				$raw_value  = maybe_serialize( $row->option_value );
				$size_bytes = strlen( $raw_value );

				$transients[ $key ]['size_bytes'] = $size_bytes;
				$transients[ $key ]['size_human'] = size_format( $size_bytes );
			}
		}

		return (string) wp_json_encode( array_values( $transients ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Handle "Clear Cache" button submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_clear_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user', 'mds-bandcamp-integration' ) );
		}

		check_admin_referer( 'mds_bc_clear_cache_action', 'mds_bc_clear_cache_nonce' );

		global $wpdb;
		$like_legacy_val     = $wpdb->esc_like( '_transient_' . self::$plugin_key ) . '%';
		$like_legacy_timeout = $wpdb->esc_like( '_transient_timeout_' . self::$plugin_key ) . '%';
		$like_new_val        = $wpdb->esc_like( '_transient_mds_bc_' ) . '%';
		$like_new_timeout    = $wpdb->esc_like( '_transient_timeout_mds_bc_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing plugin transients directly.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s
				OR option_name LIKE %s
				OR option_name LIKE %s",
				$like_legacy_val,
				$like_legacy_timeout,
				$like_new_val,
				$like_new_timeout
			)
		);

		wp_cache_flush();

		set_transient( 'mds_bc_clear_cache_notice', true, 30 );

		Logging::log( 'info', 'Transient cache manually cleared', array( 'function' => 'handle_clear_cache' ) );

		wp_safe_redirect( add_query_arg( 'page', self::$plugin_key, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Show notice after cache is cleared.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function show_clear_cache_notice(): void {
		if ( ! get_transient( 'mds_bc_clear_cache_notice' ) ) {
			return;
		}

		delete_transient( 'mds_bc_clear_cache_notice' );
		echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html__( 'Cache cleared successfully!', 'mds-bandcamp-integration' ) .
			'</p></div>';
	}
}
