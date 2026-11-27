<?php
/**
 * WordPress 7.x React Admin Interface for MDS Audio Providers.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Admin;

use MDS\AudioProviders\Api\RestApi;
use MDS\AudioProviders\Core\Dependencies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Audio Providers settings page and enqueues the React admin UI.
 *
 * @since 2.0.0
 */
final class AdminInterface {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'mds-audio-providers';

	/**
	 * Stored hook suffix for the registered admin page.
	 *
	 * @var string
	 */
	private static string $hook_suffix = '';

	/**
	 * Registers admin settings tab (or standalone fallback menu) and asset hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'mds_rm_site_settings_tabs', array( self::class, 'register_settings_tab' ) );
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Registers the Audio Providers tab inside MDS Release Manager → Settings.
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing extension tabs.
	 * @return array<string, array<string, mixed>> Updated tabs.
	 */
	public static function register_settings_tab( array $tabs ): array {
		$tabs['audio-providers'] = array(
			'label'  => __( 'Audio Providers', 'mds-audio-providers' ),
			'render' => array( self::class, 'render_tab' ),
		);
		return $tabs;
	}

	/**
	 * Adds a standalone Settings fallback submenu only when MDS Release Manager is not active.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		if ( Dependencies::is_release_manager_active() ) {
			return;
		}

		$hook = add_submenu_page(
			'options-general.php',
			__( 'Audio Providers', 'mds-audio-providers' ),
			__( 'Audio Providers', 'mds-audio-providers' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);

		self::$hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Renders the React root mount point inside the Release Manager Settings tab pane.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public static function render_tab(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="mds-panel mds-ap-admin-wrap">
			<div id="mds-audio-providers-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Renders the standalone React root mount point.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap mds-ap-admin-wrap">
			<div id="mds-audio-providers-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Enqueues the React Admin UI script and styles on the Release Settings screen (or standalone fallback).
	 *
	 * @param string $hook_suffix Current admin screen hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		$is_rm_settings = str_ends_with( $hook_suffix, '_page_mds-release-settings' );
		$is_standalone  = '' !== self::$hook_suffix && $hook_suffix === self::$hook_suffix;
		if ( ! $is_rm_settings && ! $is_standalone ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );
		if ( file_exists( MDS_AP_PATH . 'css/admin.css' ) ) {
			wp_enqueue_style(
				'mds-ap-admin',
				MDS_AP_URL . 'css/admin.css',
				array( 'wp-components' ),
				(string) filemtime( MDS_AP_PATH . 'css/admin.css' )
			);
		}

		$script_rel = 'js/admin-settings.js';
		wp_enqueue_script(
			'mds-ap-admin',
			MDS_AP_URL . $script_rel,
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y' ),
			file_exists( MDS_AP_PATH . $script_rel ) ? (string) filemtime( MDS_AP_PATH . $script_rel ) : MDS_AP_VERSION,
			true
		);
		wp_set_script_translations( 'mds-ap-admin', 'mds-audio-providers' );

		$boot_data = array(
			'restBase' => '/' . RestApi::NAMESPACE,
			'hasCore'  => Dependencies::has_unified_audio_api(),
			'initial'  => RestApi::build_payload(),
		);

		$encoded = wp_json_encode( $boot_data, JSON_INVALID_UTF8_SUBSTITUTE );
		wp_add_inline_script(
			'mds-ap-admin',
			'window.mdsAudioProvidersAdmin = ' . ( is_string( $encoded ) ? $encoded : '{}' ) . ';',
			'before'
		);
	}
}
