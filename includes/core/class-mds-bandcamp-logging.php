<?php
/**
 * Debug logging for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Writes plugin messages to the WordPress-configured PHP error log.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Logging
{
    /**
     * Write a plugin message when WordPress debug logging is enabled.
     *
     * @since 1.0.0
     * @param string $level   Logging level.
     * @param string $message Message text to log.
     * @param array  $context Optional additional context.
     * @return bool Whether the complete message was written.
     */
    public static function log( string $level, string $message, array $context = array() ): bool
    {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
            return false;
        }

        $entry = sprintf( '[MDS Bandcamp Integration] [%s] %s', strtoupper( $level ), $message );
        if ( $context ) {
            $encoded_context = wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
            if ( false === $encoded_context ) {
                error_log( $entry . ' [Context could not be encoded]' );
                return false;
            }
            $entry .= ' ' . $encoded_context;
        }

        return error_log( $entry );
    }
}
