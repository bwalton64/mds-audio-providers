<?php
/**
 * Admin settings interface for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Admin;

use MDS\Bandcamp\Blocks\BlockDefaults;
use MDS\ReleaseManager\Api\ExtensionApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the plugin settings page, Release Manager tab mount point, and option registration.
 *
 * @since 1.0.0
 */
final class AdminInterface {
	/**
	 * Settings option prefix preserved for backwards compatibility with `mds_bandcamp__settings`.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $plugin_key = 'mds_bandcamp';

	/**
	 * Register settings and admin menu hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_menu', array( self::class, 'add_settings_menu' ) );
		add_filter( 'mds_rm_site_settings_tabs', array( self::class, 'register_release_manager_tab' ) );
	}

	/**
	 * Register the plugin settings options and Release Manager extension meta.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		if ( class_exists( ExtensionApi::class ) ) {
			ExtensionApi::register_extension_meta( 'bandcamp_album_id' );
			ExtensionApi::register_extension_meta(
				'bandcamp_album_url',
				array( 'sanitize_callback' => 'esc_url_raw' )
			);
			ExtensionApi::register_extension_meta(
				'bandcamp_artist_url',
				array( 'sanitize_callback' => 'esc_url_raw' ),
				'artist'
			);
			ExtensionApi::register_extension_meta(
				'bandcamp_artist_subdomain',
				array(),
				'artist'
			);
		}

		register_setting(
			self::$plugin_key,
			self::$plugin_key . '__settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
			)
		);

		register_setting(
			self::$plugin_key,
			BlockDefaults::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( BlockDefaults::class, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize the plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $settings Submitted settings.
	 * @return array{transient?: int} Sanitized settings.
	 */
	public static function sanitize_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$transient = (int) ( $settings['transient'] ?? 60 );
		$transient = max( 1, min( 1440, $transient ) );

		return array(
			'transient' => $transient,
		);
	}

	/**
	 * Add the standalone settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function add_settings_menu(): void {
		add_submenu_page(
			'options-general.php',
			__( 'MDS Bandcamp Integration', 'mds-bandcamp-integration' ),
			__( 'MDS Bandcamp Integration', 'mds-bandcamp-integration' ),
			'manage_options',
			self::$plugin_key,
			array( self::class, 'render_settings_page' ),
			99
		);
	}

	/**
	 * Render the standalone settings page React mount point.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Bandcamp settings.', 'mds-bandcamp-integration' ) );
		}
		?>
		<div class="wrap mds-bc-admin-wrap">
			<div id="mds-bandcamp-integration-admin-root" data-standalone="1"></div>
		</div>
		<?php
	}

	/**
	 * Add the Bandcamp tab to MDS Release Manager settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing settings tabs.
	 * @return array<string, array<string, mixed>> Settings tabs including Bandcamp.
	 */
	public static function register_release_manager_tab( array $tabs ): array {
		$tabs['bandcamp'] = array(
			'label'  => __( 'Bandcamp Integration', 'mds-bandcamp-integration' ),
			'render' => array( self::class, 'render_settings_content' ),
		);
		return $tabs;
	}

	/**
	 * Render the React mount point inside the MDS Release Manager settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_settings_content(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Bandcamp settings.', 'mds-bandcamp-integration' ) );
		}
		?>
		<div id="mds-bandcamp-integration-admin-root" data-standalone="0"></div>
		<?php
	}
}
