<?php
/**
 * Frontend and block asset registration for MDS Bandcamp Integration.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's stylesheets and scripts.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Assets {
	public static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_block_styles' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend_assets' ) );
	}

	public static function enqueue_admin_styles( string $hook_suffix ): void {
		wp_enqueue_style( 'mds-bandcamp-blocks', MDS_BANDCAMP_PLUGIN_URL . '/css/blocks.css', array(), MDS_BANDCAMP_PLUGIN_VERSION );

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'release' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'mds-bandcamp-admin', MDS_BANDCAMP_URL . 'css/admin.css', array(), filemtime( MDS_BANDCAMP_PATH . 'css/admin.css' ) );
	}

	public static function enqueue_block_styles(): void {
		wp_enqueue_style( 'mds-bandcamp-blocks', MDS_BANDCAMP_PLUGIN_URL . '/css/blocks.css', array(), MDS_BANDCAMP_PLUGIN_VERSION );
	}

	public static function enqueue_frontend_assets(): void {
		if ( is_singular( 'release' ) && MDS_BANDCAMP_Dependencies::is_mds_active() && MDS_BANDCAMP_Dependencies::is_bandcamp_active() ) {
			wp_enqueue_style( 'mds-bandcamp-public', MDS_BANDCAMP_URL . 'css/public.css', array(), filemtime( MDS_BANDCAMP_PATH . 'css/public.css' ) );
		}

		$post_id = get_the_ID();
		$blocks = array(
			'mds-bandcamp/bandcamp-album',
			'mds-bandcamp/bandcamp-discography',
			'mds-bandcamp/bandcamp-embed',
			'mds-bandcamp/bandcamp-form',
			'mds-bandcamp/bandcamp-merch',
			'mds-bandcamp/bandcamp-miniplayer',
		);

		foreach ( $blocks as $block_name ) {
			if ( has_block( $block_name, $post_id ) ) {
				wp_enqueue_style( 'mds-bandcamp-blocks', MDS_BANDCAMP_PLUGIN_URL . '/css/blocks.css', array(), MDS_BANDCAMP_PLUGIN_VERSION );
				break;
			}
		}

		if ( has_block( 'mds-bandcamp/bandcamp-album', $post_id ) || has_block( 'mds-bandcamp/bandcamp-merch', $post_id ) ) {
			wp_enqueue_script( 'mds-bandcamp-blocks', MDS_BANDCAMP_URL . 'js/blocks.js', array(), MDS_BANDCAMP_PLUGIN_VERSION, true );
		}
	}
}

