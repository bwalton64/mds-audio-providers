<?php
/**
 * Frontend and block asset registration for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Frontend;

use MDS\Bandcamp\Blocks\BlockDefaults;
use MDS\Bandcamp\Core\Cache;
use MDS\Bandcamp\Core\Dependencies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's stylesheets and scripts.
 *
 * @since 1.0.0
 */
final class Assets {
	/**
	 * Register asset hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_block_styles' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue Font Awesome from MDS Release Manager when available so `fab fa-bandcamp` renders cleanly.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	private static function maybe_enqueue_font_awesome(): void {
		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::enqueue_font_awesome();
		}
	}

	/**
	 * Enqueue shared admin styles, React settings app, and release-editor styles when applicable.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_styles( string $hook_suffix ): void {
		wp_enqueue_style( 'mds-bc-blocks', MDS_BC_PLUGIN_URL . '/css/blocks.css', array(), MDS_BC_PLUGIN_VERSION );

		$screen            = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id         = $screen && isset( $screen->id ) ? (string) $screen->id : '';
		$settings_screens  = array( 'settings_page_mds_bandcamp', 'release_page_mds-release-settings' );
		$is_settings_admin = in_array( $hook_suffix, $settings_screens, true ) || in_array( $screen_id, $settings_screens, true );

		if ( $is_settings_admin ) {
			self::maybe_enqueue_font_awesome();
			wp_enqueue_style( 'wp-components' );
			wp_enqueue_style( 'mds-bc-admin', MDS_BC_URL . 'css/admin.css', array( 'wp-components' ), (string) filemtime( MDS_BC_PATH . 'css/admin.css' ) );

			foreach ( BlockDefaults::SLUGS as $slug ) {
				$css_rel = 'blocks/' . $slug . '/frontend.css';
				if ( file_exists( MDS_BC_PATH . $css_rel ) ) {
					wp_enqueue_style(
						'mds-bc-' . $slug . '-preview',
						MDS_BC_URL . $css_rel,
						array( 'mds-bc-blocks' ),
						(string) filemtime( MDS_BC_PATH . $css_rel )
					);
				}
			}

			wp_enqueue_script(
				'mds-bc-admin-settings',
				MDS_BC_URL . 'js/admin-settings.js',
				array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-a11y', 'jquery', 'heartbeat' ),
				(string) filemtime( MDS_BC_PATH . 'js/admin-settings.js' ),
				true
			);

			$raw_settings = get_option( 'mds_bandcamp__settings', array() );
			$transient    = is_array( $raw_settings ) && isset( $raw_settings['transient'] ) ? (int) $raw_settings['transient'] : 60;
			$transients   = array();
			$raw_cache    = Cache::get_transients_list( 'mds_bandcamp' );
			if ( ! empty( $raw_cache ) ) {
				$decoded = json_decode( $raw_cache, true );
				if ( is_array( $decoded ) ) {
					$transients = array_reverse( $decoded );
				}
			}
			$total_bytes = ! empty( $transients ) ? (int) array_sum( array_column( $transients, 'size_bytes' ) ) : 0;

			$schema_defaults = array();
			foreach ( BlockDefaults::SLUGS as $slug ) {
				$schema_defaults[ $slug ] = BlockDefaults::get_schema_defaults( $slug );
			}

			$settings_config = array(
				'settingsUrl'    => rest_url( 'mds-bandcamp/v1/settings' ),
				'clearCacheUrl'  => rest_url( 'mds-bandcamp/v1/cache/clear' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'settings'       => array( 'transient' => $transient ),
				'blockDefaults'  => BlockDefaults::get_all_effective(),
				'schemaDefaults' => $schema_defaults,
				'cache'          => array(
					'transients' => $transients,
					'totalSize'  => (string) size_format( $total_bytes ),
				),
			);

			$encoded_config = wp_json_encode( $settings_config, JSON_INVALID_UTF8_SUBSTITUTE );
			wp_add_inline_script(
				'mds-bc-admin-settings',
				'window.mdsBcSettings = ' . ( is_string( $encoded_config ) ? $encoded_config : '{}' ) . ';',
				'before'
			);
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'mds-bc-admin-settings', 'mds-bandcamp-integration' );
			}
			return;
		}

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'release', 'artist' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'mds-bc-admin', MDS_BC_URL . 'css/admin.css', array(), (string) filemtime( MDS_BC_PATH . 'css/admin.css' ) );
	}

	/**
	 * Enqueue shared styles, site-wide block defaults, and the release picker in the block editor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue_block_styles(): void {
		self::maybe_enqueue_font_awesome();
		wp_enqueue_style( 'mds-bc-blocks', MDS_BC_PLUGIN_URL . '/css/blocks.css', array(), MDS_BC_PLUGIN_VERSION );
		$deps = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-url', 'wp-i18n', 'wp-html-entities' );
		if ( function_exists( 'wp_script_is' ) && wp_script_is( 'mds-rm-release-picker', 'registered' ) ) {
			$deps[] = 'mds-rm-release-picker';
		}
		wp_enqueue_script(
			'mds-bc-release-picker',
			MDS_BC_URL . 'js/release-picker.js',
			$deps,
			(string) filemtime( MDS_BC_PATH . 'js/release-picker.js' ),
			true
		);
		wp_add_inline_script(
			'mds-bc-release-picker',
			'window.mdsBcDebug = ' . wp_json_encode( defined( 'WP_DEBUG' ) && WP_DEBUG ) . ';'
			. 'window.mdsBcBlockDefaults = ' . wp_json_encode( BlockDefaults::get_all_effective() ) . ';',
			'before'
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'mds-bc-release-picker', 'mds-bandcamp-integration' );
		}
	}

	/**
	 * Enqueue frontend styles and shared block behavior when needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets(): void {
		if ( is_singular( 'release' ) && Dependencies::is_mds_active() && Dependencies::is_bandcamp_active() ) {
			wp_enqueue_style( 'mds-bc-public', MDS_BC_URL . 'css/public.css', array(), (string) filemtime( MDS_BC_PATH . 'css/public.css' ) );
		}

		$post_id = get_the_ID();
		$blocks  = array(
			'mds-bandcamp/bandcamp-album',
			'mds-bandcamp/bandcamp-discography',
			'mds-bandcamp/bandcamp-miniplayer',
		);

		foreach ( $blocks as $block_name ) {
			if ( has_block( $block_name, $post_id ) ) {
				self::maybe_enqueue_font_awesome();
				wp_enqueue_style( 'mds-bc-blocks', MDS_BC_PLUGIN_URL . '/css/blocks.css', array(), MDS_BC_PLUGIN_VERSION );
				break;
			}
		}

		if ( has_block( 'mds-bandcamp/bandcamp-album', $post_id ) ) {
			wp_enqueue_script( 'mds-bc-blocks', MDS_BC_URL . 'js/blocks.js', array(), MDS_BC_PLUGIN_VERSION, true );
		}
	}
}
