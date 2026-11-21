<?php
/**
 * PHP template loading for MDS Bandcamp Integration.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin templates and returns their rendered output.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_Templates
{
	public static function render( $template_name, $args = array() ): string
	{
		if ( ! is_string( $template_name ) || basename( $template_name ) !== $template_name || '.php' !== strtolower( substr( $template_name, -4 ) ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Template name must be a PHP filename.', 'mds-bandcamp-integration' ), esc_html( MDS_BANDCAMP_VERSION ) );
			return '';
		}

		if ( ! is_array( $args ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Template arguments must be provided as an array.', 'mds-bandcamp-integration' ), esc_html( MDS_BANDCAMP_VERSION ) );
			return '';
		}

		$theme_template = locate_template( 'mds-bandcamp-integration/' . $template_name );
		$template_path  = $theme_template ? $theme_template : MDS_BANDCAMP_PATH . 'templates/' . $template_name;

		if ( ! is_readable( $template_path ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: Template filename. */
					esc_html__( 'The "%s" template could not be found.', 'mds-bandcamp-integration' ),
					esc_html( $template_name )
				),
				esc_html( MDS_BANDCAMP_VERSION )
			);
			return '';
		}

		ob_start();
		if ( ! empty( $args ) ) {
			extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}
		include $template_path;
		return (string) ob_get_clean();
	}
}

