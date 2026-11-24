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
 * Manages transient cache for Bandcamp API data and coordinates with MDS_RM_Cache when active.
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
	 * Option key tracking cached Bandcamp transient metadata for Redis/Memcached compatibility.
	 *
	 * @since 1.0.2
	 * @var string
	 */
	public const INDEX_OPTION = 'mds_bc_cached_keys';

	/**
	 * Register cache management hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'heartbeat_received', array( self::class, 'handle_heartbeat_received' ), 10, 2 );
		add_filter( 'mds_rm_cache_adapters', array( self::class, 'register_release_manager_adapter' ) );
	}

	/**
	 * Register an optional Bandcamp API transient cache adapter in MDS Release Manager's Cache Manager.
	 *
	 * Defaults to `disabled` so routine post saves do not force unnecessary Bandcamp scrapes, while
	 * allowing administrators to opt into purging Bandcamp API transients during Release Manager flushes.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, array<string, mixed>> $adapters Registered Release Manager cache adapters.
	 * @return array<string, array<string, mixed>> Adapters including Bandcamp API Cache.
	 */
	public static function register_release_manager_adapter( array $adapters ): array {
		$adapters['mds_bandcamp_api'] = array(
			'slug'         => 'mds_bandcamp_api',
			'label'        => __( 'Bandcamp API Transient Cache', 'mds-bandcamp-integration' ),
			'tier'         => __( 'Companion', 'mds-bandcamp-integration' ),
			'detect'       => static fn(): bool => true,
			'default_mode' => 'disabled',
			'modes'        => array(
				'disabled' => __( 'Disabled (manage separately in Bandcamp Integration)', 'mds-bandcamp-integration' ),
				'all'      => __( 'Purge Bandcamp API Transients on Cache Flush', 'mds-bandcamp-integration' ),
			),
			'purge'        => static function ( string $mode ): bool {
				if ( 'all' !== $mode ) {
					return false;
				}
				self::clear_all( false );
				return true;
			},
		);

		return $adapters;
	}

	/**
	 * Record a cached Bandcamp transient key in the lightweight index option.
	 *
	 * Ensures cache inspection and selective invalidation work on both database-backed
	 * and persistent external object cache (Redis/Memcached) environments.
	 *
	 * @since 1.0.2
	 *
	 * @param string $key        Transient key.
	 * @param string $endpoint   Endpoint slug (`album` or `discography`).
	 * @param string $id         Bandcamp album ID or artist subdomain.
	 * @param int    $ttl        Time-to-live in seconds.
	 * @param int    $size_bytes Approximate serialized payload size in bytes.
	 * @return void
	 */
	public static function track_key( string $key, string $endpoint, string $id, int $ttl, int $size_bytes ): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}

		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}

		$now = time();
		foreach ( $index as $existing_key => $meta ) {
			if ( ! is_array( $meta ) || ( isset( $meta['expires'] ) && (int) $meta['expires'] > 0 && (int) $meta['expires'] < $now ) ) {
				unset( $index[ $existing_key ] );
			}
		}

		$index[ $key ] = array(
			'key'        => $key,
			'endpoint'   => $endpoint,
			'id'         => $id,
			'expires'    => $ttl > 0 ? $now + $ttl : 0,
			'size_bytes' => max( 0, $size_bytes ),
		);

		if ( count( $index ) > 250 ) {
			$index = array_slice( $index, -250, null, true );
		}

		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Delete a single cached Bandcamp transient by endpoint and target ID without touching other caches.
	 *
	 * @since 1.0.2
	 *
	 * @param string $endpoint Endpoint slug (`album` or `discography`).
	 * @param string $id       Bandcamp album ID or artist subdomain.
	 * @return void
	 */
	public static function delete_for_target( string $endpoint, string $id ): void {
		$id = trim( $id );
		if ( '' === $endpoint || '' === $id ) {
			return;
		}

		$key = substr( sanitize_title( self::$plugin_key . '__' . $endpoint . '_' . $id ), 0, 150 );
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $key );
		}

		if ( function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
			$index = get_option( self::INDEX_OPTION, array() );
			if ( is_array( $index ) && isset( $index[ $key ] ) ) {
				unset( $index[ $key ] );
				update_option( self::INDEX_OPTION, $index, false );
			}
		}
	}

	/**
	 * Get a JSON array of cached transients for the settings page.
	 *
	 * Uses SQL `LENGTH(option_value)` to avoid transferring full serialized blobs on Heartbeat ticks
	 * and merges tracked object-cache entries for Redis/Memcached compatibility.
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
		$transients     = array();

		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
			if ( ! empty( $search ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin cache inspection table using LENGTH() to avoid transferring full blobs.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_name,
							CASE WHEN option_name LIKE %s THEN option_value ELSE NULL END AS timeout_value,
							LENGTH(option_value) AS size_bytes
						FROM {$wpdb->options}
						WHERE option_name LIKE %s
						OR option_name LIKE %s
						ORDER BY option_name ASC
						LIMIT 250",
						$wpdb->esc_like( $timeout_prefix ) . '%',
						$wpdb->esc_like( $prefix . $search ) . '%',
						$wpdb->esc_like( $timeout_prefix . $search ) . '%'
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin cache inspection table using LENGTH() to avoid transferring full blobs.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_name,
							CASE WHEN option_name LIKE %s THEN option_value ELSE NULL END AS timeout_value,
							LENGTH(option_value) AS size_bytes
						FROM {$wpdb->options}
						WHERE option_name LIKE %s
						OR option_name LIKE %s
						ORDER BY option_name ASC
						LIMIT 250",
						$wpdb->esc_like( $timeout_prefix ) . '%',
						$wpdb->esc_like( $prefix ) . '%',
						$wpdb->esc_like( $timeout_prefix ) . '%'
					)
				);
			}

			if ( is_array( $results ) ) {
				foreach ( $results as $row ) {
					if ( str_starts_with( $row->option_name, $timeout_prefix ) ) {
						$key         = str_replace( $timeout_prefix, '', $row->option_name );
						$raw_timeout = property_exists( $row, 'timeout_value' ) && null !== $row->timeout_value
							? $row->timeout_value
							: ( $row->option_value ?? 0 );
						$expires     = (int) $raw_timeout;

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

						if ( property_exists( $row, 'size_bytes' ) && null !== $row->size_bytes ) {
							$size_bytes = (int) $row->size_bytes;
						} else {
							$size_bytes = strlen( (string) ( $row->option_value ?? '' ) );
						}

						$transients[ $key ]['size_bytes'] = $size_bytes;
						$transients[ $key ]['size_human'] = size_format( $size_bytes );
					}
				}
			}
		}

		// Merge tracked index entries so sites using persistent object caches (Redis/Memcached) still display active entries.
		if ( function_exists( 'get_option' ) ) {
			$index = get_option( self::INDEX_OPTION, array() );
			if ( is_array( $index ) ) {
				$now = time();
				foreach ( $index as $key => $meta ) {
					if ( ! is_string( $key ) || ! is_array( $meta ) ) {
						continue;
					}
					if ( '' !== $search && ! str_starts_with( $key, $search ) ) {
						continue;
					}
					$expires = (int) ( $meta['expires'] ?? 0 );
					if ( $expires > 0 && $expires < $now ) {
						continue;
					}
					if ( isset( $transients[ $key ] ) ) {
						continue;
					}
					$size_bytes         = max( 0, (int) ( $meta['size_bytes'] ?? 0 ) );
					$transients[ $key ] = array(
						'key'        => $key,
						'endpoint'   => (string) ( $meta['endpoint'] ?? '' ),
						'id'         => (string) ( $meta['id'] ?? '' ),
						'expires'    => $expires ? wp_date( 'Y-m-d H:i:s', $expires ) : 'never',
						'size_bytes' => $size_bytes,
						'size_human' => size_format( $size_bytes ),
					);
				}
			}
		}

		return (string) wp_json_encode( array_values( $transients ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Return structured transient cache summary for admin display and Heartbeat updates.
	 *
	 * @since 1.1.0
	 *
	 * @return array{transients: array<int, array<string, mixed>>, total_size: string} Cache summary payload.
	 */
	public static function get_cache_summary(): array {
		$cached_json = self::get_transients_list( self::$plugin_key );
		$decoded     = ! empty( $cached_json ) ? json_decode( $cached_json, true ) : array();
		$transients  = is_array( $decoded ) ? array_reverse( $decoded ) : array();
		$total_bytes = is_array( $decoded ) ? (int) array_sum( array_column( $decoded, 'size_bytes' ) ) : 0;

		return array(
			'transients' => $transients,
			'total_size' => (string) size_format( $total_bytes ),
		);
	}

	/**
	 * Append live cache inspection data to WordPress Heartbeat responses when requested.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $response Heartbeat response data.
	 * @param array<string, mixed> $data     Incoming Heartbeat payload from the browser.
	 * @return array<string, mixed> Heartbeat response with optional `mds_bc_cache` summary.
	 */
	public static function handle_heartbeat_received( array $response, array $data ): array {
		if ( empty( $data['mds_bc_refresh_cache'] ) || ! current_user_can( 'manage_options' ) ) {
			return $response;
		}

		$response['mds_bc_cache'] = self::get_cache_summary();

		return $response;
	}

	/**
	 * Delete all Bandcamp API transients selectively (without calling global `wp_cache_flush()`)
	 * and notify MDS Release Manager's cache manager to purge downstream page caches.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $trigger_rm_purge Whether to fire `mds_rm_purge_cache` for downstream page/CDN caches.
	 * @return int Number of deleted transient entries/rows.
	 */
	public static function clear_all( bool $trigger_rm_purge = true ): int {
		$deleted = 0;

		// 1. Delete tracked transients via WordPress API (works on both DB and Redis/Memcached).
		if ( function_exists( 'get_option' ) && function_exists( 'delete_transient' ) ) {
			$index = get_option( self::INDEX_OPTION, array() );
			if ( is_array( $index ) ) {
				foreach ( array_keys( $index ) as $key ) {
					if ( is_string( $key ) && '' !== $key ) {
						delete_transient( $key );
						++$deleted;
					}
				}
			}
			if ( function_exists( 'delete_option' ) ) {
				delete_option( self::INDEX_OPTION );
			}
		}

		// 2. Clean up any legacy or untracked Bandcamp API transient rows in wp_options.
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			$like_legacy_val     = $wpdb->esc_like( '_transient_' . self::$plugin_key . '__' ) . '%';
			$like_legacy_timeout = $wpdb->esc_like( '_transient_timeout_' . self::$plugin_key . '__' ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted cleanup of Bandcamp API transients only.
			$db_deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s",
					$like_legacy_val,
					$like_legacy_timeout
				)
			);

			if ( is_int( $db_deleted ) && $db_deleted > 0 ) {
				$deleted += $db_deleted;
			}
		}

		// 3. Notify MDS Release Manager's centralized cache manager (safe no-op when standalone).
		if ( $trigger_rm_purge && function_exists( 'do_action' ) ) {
			/**
			 * Requests MDS Release Manager to purge downstream page/CDN caches.
			 *
			 * @param string $context Trigger context.
			 */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking MDS Release Manager core cache purge hook.
			do_action( 'mds_rm_purge_cache', 'bandcamp_cache_clear' );
		}

		Logging::log( 'info', 'Bandcamp transient cache cleared', array( 'deleted' => $deleted ) );

		return $deleted;
	}
}
