<?php
/**
 * Debug logging for the MDS Bandcamp Integration plugin.
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
 * Writes plugin messages to the WordPress-configured PHP error log.
 *
 * @since 1.0.0
 */
final class Logging {
	/**
	 * Write a plugin message when WordPress debug logging is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $level   Logging level.
	 * @param string               $message Message text to log.
	 * @param array<string, mixed> $context Optional additional context.
	 * @return bool Whether the complete message was written.
	 */
	public static function log( string $level, string $message, array $context = array() ): bool {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return false;
		}

		$entry = sprintf( '[MDS Bandcamp Integration] [%s] %s', strtoupper( $level ), $message );
		if ( $context ) {
			$encoded_context = wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
			if ( false === $encoded_context ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional WP_DEBUG_LOG logging.
				error_log( $entry . ' [Context could not be encoded]' );
				return false;
			}
			$entry .= ' ' . $encoded_context;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional WP_DEBUG_LOG logging.
		return error_log( $entry );
	}
}
