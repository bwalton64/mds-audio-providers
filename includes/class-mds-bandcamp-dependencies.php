<?php
/**
 * Dependency checks for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- Global scope is required for this plugin.
/**
 * Checks whether the plugins required by this integration are active.
 */
final class Mds_Bandcamp_Dependencies {

	/**
	 * Check whether MDS Release Manager is active.
	 *
	 * @return bool
	 */
	public static function is_mds_active(): bool {
		return self::is_plugin_active( 'mds-release-manager/mds-release-manager.php' );
	}

	/**
	 * Check whether Blocks for Bandcamp is active.
	 *
	 * @return bool
	 */
	public static function is_bandcamp_active(): bool {
		return self::is_plugin_active( 'blocks-for-bandcamp/blocks-for-bandcamp.php' );
	}

	/**
	 * Determine whether a plugin is active on this site or network.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	private static function is_plugin_active( $plugin_file ): bool {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

		return in_array( $plugin_file, $active_plugins, true )
			|| self::is_network_plugin_active( $plugin_file );
	}

	/**
	 * Determine whether a plugin is active on the network.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	private static function is_network_plugin_active( $plugin_file ): bool {
		return is_multisite()
			&& array_key_exists( $plugin_file, get_site_option( 'active_sitewide_plugins', array() ) );
	}
}

// Preserve the original class name for existing references.
class_alias( Mds_Bandcamp_Dependencies::class, 'MdsBandcampDependencies' );
