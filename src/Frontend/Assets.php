<?php
/**
 * Frontend and block asset registration for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Frontend;

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
	 * Enqueue shared admin styles and release-editor styles when applicable.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_styles( string $hook_suffix ): void {
		wp_enqueue_style( 'mds-bc-blocks', MDS_BC_PLUGIN_URL . '/css/blocks.css', array(), MDS_BC_PLUGIN_VERSION );

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
	 * Enqueue shared styles and the release picker in the block editor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue_block_styles(): void {
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
		wp_add_inline_script( 'mds-bc-release-picker', 'window.mdsBcDebug = ' . wp_json_encode( defined( 'WP_DEBUG' ) && WP_DEBUG ) . ';', 'before' );
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
			'mds-bandcamp/bandcamp-embed',
			'mds-bandcamp/bandcamp-form',
			'mds-bandcamp/bandcamp-merch',
			'mds-bandcamp/bandcamp-miniplayer',
		);

		foreach ( $blocks as $block_name ) {
			if ( has_block( $block_name, $post_id ) ) {
				wp_enqueue_style( 'mds-bc-blocks', MDS_BC_PLUGIN_URL . '/css/blocks.css', array(), MDS_BC_PLUGIN_VERSION );
				break;
			}
		}

		if ( has_block( 'mds-bandcamp/bandcamp-album', $post_id ) || has_block( 'mds-bandcamp/bandcamp-merch', $post_id ) ) {
			wp_enqueue_script( 'mds-bc-blocks', MDS_BC_URL . 'js/blocks.js', array(), MDS_BC_PLUGIN_VERSION, true );
		}
	}
}
