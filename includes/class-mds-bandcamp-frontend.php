<?php
/**
 * Frontend output for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- Global scope is required for this plugin.
/**
 * Renders the Bandcamp player after release metadata.
 */
final class MDS_Bandcamp_Frontend {

	/**
	 * Render the Bandcamp player on the frontend.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function render( $post_id ): void {
		if ( ! MDS_Bandcamp_Dependencies::is_mds_active() || ! MDS_Bandcamp_Dependencies::is_bandcamp_active() ) {
			return;
		}

		$shortcode = get_post_meta( $post_id, 'bandcamp_shortcode', true );
		if ( empty( $shortcode ) ) {
			return;
		}

		$shortcode_html = self::get_shortcode_html( $shortcode );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML token is sanitized with an iframe allowlist.
		echo mds_bandcamp_get_template(
			'frontend-widget.html',
			array(
				'shortcode_html' => $shortcode_html,
			)
		);
	}

	/**
	 * Render and sanitize the shortcode output, preserving Bandcamp embeds.
	 *
	 * @param string $shortcode Shortcode to render.
	 * @return string
	 */
	private static function get_shortcode_html( $shortcode ): string {
		$allowed_html           = wp_kses_allowed_html( 'post' );
		$allowed_html['iframe'] = array(
			'allow'             => true,
			'allowfullscreen'   => true,
			'allowtransparency' => true,
			'class'             => true,
			'frameborder'       => true,
			'height'            => true,
			'loading'           => true,
			'name'              => true,
			'referrerpolicy'    => true,
			'scrolling'         => true,
			'seamless'          => true,
			'src'               => true,
			'style'             => true,
			'title'             => true,
			'width'             => true,
		);

		return wp_kses( do_shortcode( $shortcode ), $allowed_html );
	}
}
