<?php
/**
 * Admin settings for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, WordPress.Files.FileName.InvalidClassFileName -- Global scope and the existing filename are required for plugin compatibility.
/**
 * Provides admin functionality for Bandcamp release settings.
 */
final class MDS_Bandcamp_Admin {

	/**
	 * Register the Bandcamp meta box for release posts.
	 *
	 * @param object $post Post being processed.
	 * @return void
	 */
	public static function register_meta_box( $post ): void {
		if ( ! MdsBandcampDependencies::is_mds_active() ) {
			return;
		}

		if ( ! $post || 'release' !== $post->post_type ) {
			return;
		}

		add_meta_box(
			'mds_bandcamp_settings',
			'Bandcamp Integration',
			'mds_bandcamp_meta_box_render',
			'release',
			'normal',
			'default'
		);
	}

	/**
	 * Render the Bandcamp meta box.
	 *
	 * @param object $post Post being processed.
	 * @return void
	 */
	public static function render_meta_box( $post ): void {
		$shortcode          = self::get_shortcode( $post->ID );
		$is_bandcamp_active = MdsBandcampDependencies::is_bandcamp_active();

		$description = $is_bandcamp_active
			? __( 'Paste your Bandcamp shortcode or data here.', 'mds-bandcamp-integration' )
			: __(
				'Only available if the Blocks for Bandcamp plugin is installed and active.',
				'mds-bandcamp-integration'
			);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values.
		echo mds_bandcamp_get_template(
			'admin-settings.html',
			array(
				'nonce_field'       => wp_nonce_field( 'mds_bandcamp_save', 'mds_bandcamp_nonce', false, false ),
				'label'             => esc_html__( 'Bandcamp Data / Shortcode', 'mds-bandcamp-integration' ),
				'shortcode'         => esc_attr( $shortcode ),
				'disabled'          => esc_attr( $is_bandcamp_active ? '' : 'disabled' ),
				'description_class' => esc_attr( $is_bandcamp_active ? '' : 'mds-bandcamp-dependency-notice' ),
				'description'       => esc_html( $description ),
			)
		);
	}

	/**
	 * Save the Bandcamp shortcode when the request is valid.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $post_data Submitted post data.
	 * @return void
	 */
	public static function save_meta( $post_id, $post_data ): void {
		if ( ! MdsBandcampDependencies::is_mds_active() || ! self::has_valid_nonce( $post_data ) ) {
			return;
		}

		self::save_shortcode( $post_id, $post_data );
	}

	/**
	 * Read the shortcode from post meta, using an empty string when unset.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function get_shortcode( $post_id ): string {
		$shortcode = get_post_meta( $post_id, 'bandcamp_shortcode', true );

		if ( ! is_scalar( $shortcode ) || ! $shortcode ) {
			return '';
		}

		return (string) $shortcode;
	}

	/**
	 * Check the save nonce in submitted post data.
	 *
	 * @param array $post_data Submitted post data.
	 * @return bool
	 */
	private static function has_valid_nonce( $post_data ): bool {
		return isset( $post_data['mds_bandcamp_nonce'] )
			&& wp_verify_nonce( $post_data['mds_bandcamp_nonce'], 'mds_bandcamp_save' );
	}

	/**
	 * Sanitize and save the shortcode when it was submitted.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $post_data Submitted post data.
	 * @return void
	 */
	private static function save_shortcode( $post_id, $post_data ): void {
		if ( ! isset( $post_data['bandcamp_shortcode'] ) ) {
			return;
		}

		update_post_meta(
			$post_id,
			'bandcamp_shortcode',
			sanitize_text_field( wp_unslash( $post_data['bandcamp_shortcode'] ) )
		);
	}
}
