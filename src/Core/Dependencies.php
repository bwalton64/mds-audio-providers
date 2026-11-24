<?php
/**
 * Dependency checks for the MDS Bandcamp Integration plugin.
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
 * Checks whether the plugins required by this integration are active.
 *
 * @since 1.0.0
 */
final class Dependencies {
	/**
	 * Check whether MDS Release Manager is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether MDS Release Manager is active.
	 */
	public static function is_mds_active(): bool {
		return defined( 'MDS_RM_API_VERSION' ) && class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class );
	}

	/**
	 * Check whether the integrated Bandcamp block features are available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether Bandcamp block features are available.
	 */
	public static function is_bandcamp_active(): bool {
		return true;
	}
}
