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
	 * Registers admin menu and asset hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Adds the Audio Providers page under MDS Release Manager (or Settings fallback).
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		$parent = Dependencies::is_release_manager_active()
			? 'edit.php?post_type=release'
			: 'options-general.php';

		$hook = add_submenu_page(
			$parent,
			__( 'Audio Providers', 'mds-audio-providers' ),
			__( 'Audio Providers', 'mds-audio-providers' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);

		self::$hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Renders the React root mount point.
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
	 * Enqueues the React Admin UI script and styles on the Audio Providers screen only.
	 *
	 * @param string $hook_suffix Current admin screen hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( '' === self::$hook_suffix || $hook_suffix !== self::$hook_suffix ) {
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
