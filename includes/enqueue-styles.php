<?php
/**
 * Stylesheet registration for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue styles for the release editor.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function mds_bandcamp_enqueue_admin_styles( $hook_suffix ): void {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'release' !== $screen->post_type ) {
		return;
	}

	$style_path = MDS_BANDCAMP_PATH . 'css/admin.css';

	wp_register_style(
		'mds-bandcamp-admin',
		MDS_BANDCAMP_URL . 'css/admin.css',
		array(),
		filemtime( $style_path )
	);
	wp_enqueue_style( 'mds-bandcamp-admin' );
}

/**
 * Enqueue styles on release pages.
 *
 * @return void
 */
function mds_bandcamp_enqueue_frontend_styles(): void {
	if (
		! is_singular( 'release' )
		|| ! MDS_Bandcamp_Dependencies::is_mds_active()
		|| ! MDS_Bandcamp_Dependencies::is_bandcamp_active()
	) {
		return;
	}

	$style_path = MDS_BANDCAMP_PATH . 'css/public.css';

	wp_register_style(
		'mds-bandcamp-public',
		MDS_BANDCAMP_URL . 'css/public.css',
		array(),
		filemtime( $style_path )
	);
	wp_enqueue_style( 'mds-bandcamp-public' );
}

add_action( 'admin_enqueue_scripts', 'mds_bandcamp_enqueue_admin_styles' );
add_action( 'wp_enqueue_scripts', 'mds_bandcamp_enqueue_frontend_styles' );
