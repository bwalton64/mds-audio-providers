<?php
/**
 * Selective transient cache manager for MDS Audio Providers.
 *
 * Coordinates with MDS Release Manager's centralized Cache Manager (`mds_rm_cache_adapters`
 * and `mds_rm_purge_cache`) and maintains a lightweight key index for Redis/Memcached compatibility.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages provider API transients and error-backoff caching.
 *
 * @since 2.0.0
 */
final class Cache {

	/**
	 * Option key tracking active provider transient metadata.
	 *
	 * @var string
	 */
	public const INDEX_OPTION = 'mds_ap_cached_keys';

	/**
	 * Prefix for provider-level transients.
	 *
	 * @var string
	 */
	public const PREFIX = 'mds_ap_';

	/**
	 * Short negative-cache backoff (3 minutes) on upstream HTTP errors.
	 *
	 * @var int
	 */
	public const ERROR_BACKOFF_TTL = 180;

	/**
	 * Registers cache hooks and integrates with Release Manager's Cache Manager.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'mds_rm_cache_adapters', array( self::class, 'register_release_manager_adapter' ) );
	}

	/**
	 * Registers an optional adapter in MDS Release Manager's Cache Manager.
	 *
	 * @param array<string, array<string, mixed>> $adapters Registered cache adapters.
	 * @return array<string, array<string, mixed>> Updated adapters.
	 */
	public static function register_release_manager_adapter( array $adapters ): array {
		$adapters['mds_audio_providers'] = array(
			'slug'         => 'mds_audio_providers',
			'label'        => __( 'MDS Audio Providers Cache', 'mds-audio-providers' ),
			'tier'         => __( 'Companion', 'mds-audio-providers' ),
			'detect'       => static fn(): bool => true,
			'default_mode' => 'disabled',
			'modes'        => array(
				'disabled' => __( 'Disabled (manage separately in Audio Providers)', 'mds-audio-providers' ),
				'all'      => __( 'Purge Audio Provider Transients on Cache Flush', 'mds-audio-providers' ),
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
	 * Reads a cached value by provider and target identifier.
	 *
	 * @param string $provider Provider slug.
	 * @param string $target   Target identifier (UPC, album ID, URL hash).
	 * @return mixed Cached value, or false on miss.
	 */
	public static function get( string $provider, string $target ): mixed {
		if ( ! function_exists( 'get_transient' ) ) {
			return false;
		}
		return get_transient( self::build_key( $provider, $target ) );
	}

	/**
	 * Stores a cached value and records it in the lightweight index.
	 *
	 * @param string $provider Provider slug.
	 * @param string $target   Target identifier.
	 * @param mixed  $value    Payload to cache.
	 * @param int    $ttl      TTL in seconds.
	 * @return void
	 */
	public static function set( string $provider, string $target, mixed $value, int $ttl ): void {
		if ( $ttl <= 0 || ! function_exists( 'set_transient' ) ) {
			return;
		}
		$key = self::build_key( $provider, $target );
		set_transient( $key, $value, $ttl );
		self::track_key( $key, $provider, $target, $ttl, strlen( maybe_serialize( $value ) ) );
	}

	/**
	 * Deletes a single cached provider entry.
	 *
	 * @param string $provider Provider slug.
	 * @param string $target   Target identifier.
	 * @return void
	 */
	public static function delete( string $provider, string $target ): void {
		$key = self::build_key( $provider, $target );
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
	 * Returns a summary of active cached audio provider entries for the admin UI.
	 *
	 * @return array{count: int, total_size: string, entries: array<int, array<string, mixed>>}
	 */
	public static function get_summary(): array {
		$index   = function_exists( 'get_option' ) ? get_option( self::INDEX_OPTION, array() ) : array();
		$entries = array();
		$bytes   = 0;
		$now     = time();

		if ( is_array( $index ) ) {
			foreach ( $index as $key => $meta ) {
				if ( ! is_string( $key ) || ! is_array( $meta ) ) {
					continue;
				}
				$expires = (int) ( $meta['expires'] ?? 0 );
				if ( $expires > 0 && $expires < $now ) {
					continue;
				}
				$size      = max( 0, (int) ( $meta['size_bytes'] ?? 0 ) );
				$bytes    += $size;
				$entries[] = array(
					'key'        => $key,
					'provider'   => (string) ( $meta['provider'] ?? '' ),
					'target'     => (string) ( $meta['target'] ?? '' ),
					'expires'    => $expires > 0 ? wp_date( 'Y-m-d H:i:s', $expires ) : 'never',
					'size_human' => size_format( $size ),
				);
			}
		}

		return array(
			'count'      => count( $entries ),
			'total_size' => (string) size_format( $bytes ),
			'entries'    => array_reverse( $entries ),
		);
	}

	/**
	 * Selectively clears all MDS Audio Providers transients (never calls `wp_cache_flush()`).
	 *
	 * @param bool $trigger_rm_purge Whether to notify MDS Release Manager to purge page caches.
	 * @return int Number of deleted entries.
	 */
	public static function clear_all( bool $trigger_rm_purge = true ): int {
		$deleted = 0;

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

		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			$like_val     = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
			$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted cleanup of MDS Audio Providers transients only.
			$rows = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$like_val,
					$like_timeout
				)
			);
			if ( is_int( $rows ) && $rows > 0 ) {
				$deleted += $rows;
			}
		}

		if ( $trigger_rm_purge && function_exists( 'do_action' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking MDS Release Manager core cache purge hook.
			do_action( 'mds_rm_purge_cache', 'audio_providers_cache_clear' );
		}

		Logging::log( 'info', 'Audio Providers transient cache cleared', array( 'deleted' => $deleted ) );
		return $deleted;
	}

	/**
	 * Builds a bounded WordPress transient key.
	 *
	 * @param string $provider Provider slug.
	 * @param string $target   Target identifier.
	 * @return string Transient key.
	 */
	private static function build_key( string $provider, string $target ): string {
		return substr( self::PREFIX . sanitize_key( $provider ) . '_' . md5( $target ), 0, 150 );
	}

	/**
	 * Records a transient key in the lightweight index option.
	 *
	 * @param string $key        Transient key.
	 * @param string $provider   Provider slug.
	 * @param string $target     Target label.
	 * @param int    $ttl        TTL in seconds.
	 * @param int    $size_bytes Serialized payload size.
	 * @return void
	 */
	private static function track_key( string $key, string $provider, string $target, int $ttl, int $size_bytes ): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}

		$now = time();
		foreach ( $index as $k => $meta ) {
			if ( ! is_array( $meta ) || ( isset( $meta['expires'] ) && (int) $meta['expires'] > 0 && (int) $meta['expires'] < $now ) ) {
				unset( $index[ $k ] );
			}
		}

		$index[ $key ] = array(
			'provider'   => sanitize_key( $provider ),
			'target'     => substr( sanitize_text_field( $target ), 0, 80 ),
			'expires'    => $ttl > 0 ? $now + $ttl : 0,
			'size_bytes' => max( 0, $size_bytes ),
		);

		if ( count( $index ) > 250 ) {
			$index = array_slice( $index, -250, null, true );
		}

		update_option( self::INDEX_OPTION, $index, false );
	}
}
