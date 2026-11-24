<?php
/**
 * Server-side rendering for the Bandcamp album block.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BlockRenderer::render_album() escapes all dynamic output by context.
echo \MDS\Bandcamp\Blocks\BlockRenderer::render_album( is_array( $attributes ?? null ) ? $attributes : array(), $block ?? null, true );
