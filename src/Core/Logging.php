<?php
/**
 * Lightweight structured logger for MDS Audio Providers.
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
 * Logs diagnostic and error events when WP_DEBUG is enabled.
 *
 * @since 2.0.0
 */
final class Logging {

	/**
	 * Writes a structured log message when debugging is active.
	 *
	 * @param string               $level   Severity level (`info`, `warning`, `error`).
	 * @param string               $message Human-readable message.
	 * @param array<string, mixed> $context Optional structured context.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$suffix = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by WP_DEBUG and WP_DEBUG_LOG.
		error_log( sprintf( '[MDS Audio Providers][%s] %s%s', strtoupper( $level ), $message, $suffix ) );
	}
}
