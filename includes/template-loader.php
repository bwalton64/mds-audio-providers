<?php
/**
 * Template loading helpers for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load and populate an HTML template, allowing theme overrides.
 *
 * @param string $template_name Template filename.
 * @param array  $tokens        Escaped token values.
 * @return string
 */
function mds_bandcamp_get_template( $template_name, $tokens = array() ): string {
	if (
		! is_string( $template_name )
		|| basename( $template_name ) !== $template_name
		|| '.html' !== strtolower( substr( $template_name, -5 ) )
	) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Template name must be an HTML filename.', 'mds-bandcamp-integration' ),
			esc_html( MDS_BANDCAMP_VERSION )
		);
		return '';
	}

	if ( ! is_array( $tokens ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Template tokens must be provided as an array.', 'mds-bandcamp-integration' ),
			esc_html( MDS_BANDCAMP_VERSION )
		);
		return '';
	}

	$theme_template = locate_template( 'mds-bandcamp-integration/' . $template_name );
	$template       = $theme_template ? $theme_template : MDS_BANDCAMP_PATH . 'templates/' . $template_name;

	if ( ! is_readable( $template ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s: Template filename. */
				esc_html__( 'The "%s" template could not be found.', 'mds-bandcamp-integration' ),
				esc_html( $template_name )
			),
			esc_html( MDS_BANDCAMP_VERSION )
		);
		return '';
	}

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a validated local template file.
	$template_content = file_get_contents( $template );
	if ( false === $template_content ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'The template file could not be read.', 'mds-bandcamp-integration' ),
			esc_html( MDS_BANDCAMP_VERSION )
		);
		return '';
	}

	$replacements = array();
	foreach ( $tokens as $token_name => $token_value ) {
		if (
			! is_string( $token_name )
			|| ! preg_match( '/^[a-z][a-z0-9_]*$/', $token_name )
			|| ! is_string( $token_value )
		) {
			_doing_it_wrong(
				__FUNCTION__,
				esc_html__( 'Template token names and values must be strings.', 'mds-bandcamp-integration' ),
				esc_html( MDS_BANDCAMP_VERSION )
			);
			return '';
		}

		$replacements[ '{{' . $token_name . '}}' ] = $token_value;
	}

	return strtr( $template_content, $replacements );
}
