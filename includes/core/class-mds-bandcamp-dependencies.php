<?php
/**
 * Dependency checks for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Checks whether the plugins required by this integration are active.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Dependencies
{
    /**
     * Check whether MDS Release Manager is active.
     *
     * @since 1.0.0
     * @return bool Whether MDS Release Manager is active.
     */
    public static function is_mds_active(): bool
    {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( 'mds-release-manager/mds-release-manager.php' );
    }

    /**
     * Check whether the integrated Bandcamp block features are available.
     *
     * @since 1.0.0
     * @return bool Whether Bandcamp block features are available.
     */
    public static function is_bandcamp_active(): bool
    {
        return true;
    }

}

// Preserve the legacy class name for third-party references.
class_alias( MDS_Bandcamp_Dependencies::class, 'MdsBandcampDependencies' );
