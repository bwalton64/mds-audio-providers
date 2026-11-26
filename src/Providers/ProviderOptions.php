<?php
/**
 * Per-provider behavioral options (e.g. iframe embed fallback toggles) for MDS Audio Providers.
 *
 * @package MDS\AudioProviders
 * @since   2.1.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and retrieves per-provider feature toggles in a single non-autoloaded option.
 *
 * @since 2.1.0
 */
final class ProviderOptions {

	/**
	 * Option key storing per-provider settings.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'mds_ap_provider_options';

	/**
	 * Schema of supported per-provider boolean options and their defaults.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private const DEFAULTS = array(
		'bandcamp' => array(
			'embed_fallback' => true,
		),
		'spotify'  => array(
			'embed_fallback' => true,
		),
	);

	/**
	 * Returns all provider options merged with defaults.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function get_all(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$result = self::DEFAULTS;
		foreach ( self::DEFAULTS as $slug => $defaults ) {
			if ( ! isset( $stored[ $slug ] ) || ! is_array( $stored[ $slug ] ) ) {
				continue;
			}
			foreach ( $defaults as $key => $default_val ) {
				if ( array_key_exists( $key, $stored[ $slug ] ) ) {
					$result[ $slug ][ $key ] = (bool) $stored[ $slug ][ $key ];
				}
			}
		}

		return $result;
	}

	/**
	 * Reads a single boolean provider option.
	 *
	 * @param string $provider Provider slug.
	 * @param string $key      Option key (e.g. `embed_fallback`).
	 * @param bool   $fallback Fallback when undefined.
	 * @return bool Option value.
	 */
	public static function get_bool( string $provider, string $key, bool $fallback = false ): bool {
		$slug = sanitize_key( $provider );
		$all  = self::get_all();
		if ( isset( $all[ $slug ] ) && array_key_exists( $key, $all[ $slug ] ) ) {
			return (bool) $all[ $slug ][ $key ];
		}
		return $fallback;
	}

	/**
	 * Sanitizes and persists per-provider options.
	 *
	 * @param array<string, mixed> $incoming Incoming provider options map.
	 * @return array<string, array<string, bool>> Saved options.
	 */
	public static function save_all( array $incoming ): array {
		$current = self::get_all();

		foreach ( self::DEFAULTS as $slug => $defaults ) {
			if ( ! isset( $incoming[ $slug ] ) || ! is_array( $incoming[ $slug ] ) ) {
				continue;
			}
			foreach ( array_keys( $defaults ) as $key ) {
				if ( array_key_exists( $key, $incoming[ $slug ] ) ) {
					$current[ $slug ][ $key ] = (bool) $incoming[ $slug ][ $key ];
				}
			}
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $current, false );
		}

		return $current;
	}
}
