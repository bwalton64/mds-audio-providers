<?php
/**
 * Server-side rendering for the Bandcamp discography block.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function ( array $attributes, $block ): void {
	$anchor   = ! empty( $attributes['anchor'] ) ? esc_attr( (string) $attributes['anchor'] ) : esc_attr( 'wp-anchor-' . wp_rand() );
	$block_id = ! empty( $attributes['blockID'] ) ? esc_attr( (string) $attributes['blockID'] ) : esc_attr( 'wp-block-' . wp_rand() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gutenberg ServerSideRender context flag.
	$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];

	$artist_url        = \MDS\Bandcamp\Core\Helpers::resolve_discography_subdomain( $attributes, $block );
	$button_color      = $attributes['button_color'] ?? '#171717';
	$button_text_color = $attributes['button_text_color'] ?? '#ffffff';
	$display_album_art = ! empty( $attributes['display_album_art'] );
	$display_artist    = ! empty( $attributes['display_album_artist'] );
	$display_link      = $attributes['display_album_link'] ?? true;
	$display_title     = ! empty( $attributes['display_album_title'] );
	$layout_grid_gap   = $attributes['layout_grid_gap'] ?? '2em';
	$text_color        = $attributes['text_color'] ?? '#171717';

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'id' => $anchor,
		)
	);

	$sanitize_css_unit = static function ( $raw, string $fallback = '0' ): string {
		if ( ! is_scalar( $raw ) ) {
			return $fallback;
		}
		$raw = trim( (string) $raw );
		if ( '0' === $raw || preg_match( '/^\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)$/', $raw ) ) {
			return $raw;
		}
		if ( preg_match( '/^\d+(?:\.\d+)?$/', $raw ) ) {
			return $raw . 'px';
		}
		return $fallback;
	};

	$allowed_border_styles = array( 'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset' );
	$allowed_text_aligns   = array( 'left', 'center', 'right', 'justify' );

	$release_border = '';
	if ( ! empty( $attributes['release_border'] ) && is_array( $attributes['release_border'] ) ) {
		$rb_color        = ! empty( $attributes['release_border']['color'] ) ? sanitize_hex_color( (string) $attributes['release_border']['color'] ) : '';
		$rb_style        = ! empty( $attributes['release_border']['style'] ) && in_array( $attributes['release_border']['style'], $allowed_border_styles, true ) ? $attributes['release_border']['style'] : '';
		$rb_width        = ! empty( $attributes['release_border']['width'] ) ? $sanitize_css_unit( $attributes['release_border']['width'], '' ) : '';
		$release_border .= $rb_color ? 'border-color:' . $rb_color . ';' : '';
		$release_border .= $rb_style ? 'border-style:' . $rb_style . ';' : '';
		$release_border .= '' !== $rb_width ? 'border-width:' . $rb_width . ';' : '';
	}

	$release_titles_align   = isset( $attributes['release_titles_align'] ) && in_array( $attributes['release_titles_align'], $allowed_text_aligns, true ) ? $attributes['release_titles_align'] : 'left';
	$release_titles_padding = '';
	if ( ! empty( $attributes['release_titles_padding'] ) && is_array( $attributes['release_titles_padding'] ) ) {
		$release_titles_padding .= 'padding:'
			. $sanitize_css_unit( $attributes['release_titles_padding']['top'] ?? '0' ) . ' '
			. $sanitize_css_unit( $attributes['release_titles_padding']['right'] ?? '0' ) . ' '
			. $sanitize_css_unit( $attributes['release_titles_padding']['bottom'] ?? '0' ) . ' '
			. $sanitize_css_unit( $attributes['release_titles_padding']['left'] ?? '0' ) . ';';
	}

	$release_image_padding = '';
	if ( ! empty( $attributes['release_image_padding'] ) && is_array( $attributes['release_image_padding'] ) ) {
		$release_image_padding .= 'padding:'
			. $sanitize_css_unit( $attributes['release_image_padding']['top'] ?? '0' ) . ' '
			. $sanitize_css_unit( $attributes['release_image_padding']['right'] ?? '0' ) . ' '
			. $sanitize_css_unit( $attributes['release_image_padding']['bottom'] ?? '0' ) . ' '
			. $sanitize_css_unit( $attributes['release_image_padding']['left'] ?? '0' ) . ';';
	}

	$block_id      = sanitize_html_class( $block_id );
	$safe_grid_gap = $sanitize_css_unit( $layout_grid_gap, '2em' );

	$css = "
#{$block_id} .bandcamp-release {" . ( '' !== $release_border ? $release_border : 'border:1px solid #ddd;' ) . "}
#{$block_id} .bandcamp-release h5,
#{$block_id} .bandcamp-release h6,
#{$block_id} .bandcamp-release p,
#{$block_id} .bandcamp-release small,
#{$block_id} .bandcamp-release span,
#{$block_id} .bandcamp-release a {color:" . ( sanitize_hex_color( (string) $text_color ) ? sanitize_hex_color( (string) $text_color ) : 'inherit' ) . ";}
#{$block_id} .bandcamp-release a.bandcamp-release-button {background-color:" . ( sanitize_hex_color( (string) $button_color ) ? sanitize_hex_color( (string) $button_color ) : 'transparent' ) . ';color:' . ( sanitize_hex_color( (string) $button_text_color ) ? sanitize_hex_color( (string) $button_text_color ) : 'inherit' ) . " !important;}
#{$block_id} .bandcamp-release a.bandcamp-release-button div {color:" . ( sanitize_hex_color( (string) $button_text_color ) ? sanitize_hex_color( (string) $button_text_color ) : 'inherit' ) . " !important;}
#{$block_id} .bandcamp-release a.bandcamp-release-button path {fill:" . ( sanitize_hex_color( (string) $button_text_color ) ? sanitize_hex_color( (string) $button_text_color ) : 'inherit' ) . ";}
#{$block_id} .bandcamp-release .bandcamp-release-image {" . ( '' !== $release_image_padding ? $release_image_padding : 'padding:1rem;' ) . "}
#{$block_id} .bandcamp-release .bandcamp-release-titles {" . ( '' !== $release_titles_padding ? $release_titles_padding : 'padding:1rem;' ) . "text-align:{$release_titles_align};}
#{$block_id} .bandcamp-grid {grid-gap:{$safe_grid_gap};}
";

	echo '<style>' . wp_kses( $css, array() ) . '</style>';

	$data = \MDS\Bandcamp\Api\BandcampApi::fetch(
		array(
			'endpoint' => 'discography',
			'id'       => $artist_url,
		)
	);

	if ( ! empty( $data['error'] ) ) {
		echo wp_kses( \MDS\Bandcamp\Api\BandcampApi::render_error_html( $data ), 'post' );
		return;
	}

	if ( empty( $data ) ) {
		echo wp_kses(
			\MDS\Bandcamp\Api\BandcampApi::render_error_html(
				array(
					'error'   => true,
					'message' => __( 'BANDCAMP URL IS NOT VALID', 'mds-bandcamp-integration' ),
				)
			),
			'post'
		);
		return;
	}

	if ( $is_editor && ! empty( $data['cached'] ) ) {
		echo '<span style="position:absolute;z-index:99;top:10px;right:10px;background:#1da0c3;color:#fff;font-size:10px;padding:3px 10px;border-radius:20px;display:inline-block;">' . esc_html__( 'CACHE', 'mds-bandcamp-integration' ) . '</span>';
	}
	?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : '' ); ?>>
	<?php if ( ! empty( $data['data']['releases'] ) && is_array( $data['data']['releases'] ) ) : ?>
	<section id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-discography">
		<div class="bandcamp-grid bandcamp-columns-<?php echo esc_attr( (string) ( $attributes['layout_columns'] ?? 3 ) ); ?> bandcamp-columns-mobile-<?php echo esc_attr( (string) ( $attributes['layout_columns_mobile'] ?? 2 ) ); ?>">
			<?php foreach ( $data['data']['releases'] as $release ) : ?>
			<div class="bandcamp-release">
				<?php if ( $display_album_art ) : ?>
				<div class="bandcamp-release-image">
					<a target="_blank" href="<?php echo esc_url( $release['link'] ); ?>">
						<img loading="lazy" src="<?php echo esc_url( $release['image'] ); ?>" alt="
						<?php
						/* translators: %s: Bandcamp release title. */
						echo esc_attr( sprintf( __( '%s Cover Art', 'mds-bandcamp-integration' ), $release['title'] ) );
						?>
						">
					</a>
				</div>
				<?php endif; ?>
				<div class="bandcamp-release-titles">
					<h5><?php echo esc_html( $display_artist ? $release['artist'] : '' ); ?></h5>
					<h6><i><?php echo esc_html( $display_title ? $release['title'] : '' ); ?></i></h6>
					<?php if ( $display_link ) : ?>
					<a href="<?php echo esc_url( $release['link'] ); ?>" class="bandcamp-release-button" target="_blank">
						<div>
							<?php echo esc_html__( 'Listen on Bandcamp', 'mds-bandcamp-integration' ); ?>
							<svg version="1.1" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" xml:space="preserve">
								<path d="M10.1,8.9l-3.3,6h7l3.3-6H10.1z M21.6,11.9c0-5.3-4.3-9.6-9.6-9.6s-9.6,4.3-9.6,9.6s4.3,9.6,9.6,9.6 S21.6,17.2,21.6,11.9z M12,20.9c-5,0-9-4-9-9s4-9,9-9s9,4,9,9S17,20.9,12,20.9z"/>
							</svg>
						</div>
					</a>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>
</div>
	<?php
} )( is_array( $attributes ?? null ) ? $attributes : array(), $block ?? null );
