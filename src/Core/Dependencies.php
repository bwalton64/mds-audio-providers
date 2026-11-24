<?php
/**
 * Dependency checks for MDS Audio Providers.
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
 * Verifies MDS Release Manager and its Unified Audio API are active.
 *
 * @since 2.0.0
 */
final class Dependencies {

	/**
	 * Checks whether MDS Release Manager core is loaded.
	 *
	 * @return bool True when MDS Release Manager is active.
	 */
	public static function is_release_manager_active(): bool {
		return defined( 'MDS_RM_VERSION' ) || class_exists( '\\MDS\\ReleaseManager\\Plugin' );
	}

	/**
	 * Checks whether the core Unified Audio API interface is available.
	 *
	 * @return bool True when `AudioProviderInterface` exists.
	 */
	public static function has_unified_audio_api(): bool {
		return interface_exists( '\\MDS\\ReleaseManager\\Core\\Audio\\AudioProviderInterface' );
	}
}
