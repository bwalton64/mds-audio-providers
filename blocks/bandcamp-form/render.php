<?php
/**
 * Server-side rendering for the Bandcamp download-code form block.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function ( array $attributes ): void {
	$anchor   = ! empty( $attributes['anchor'] ) ? esc_attr( (string) $attributes['anchor'] ) : esc_attr( 'wp-block-' . wp_rand() );
	$block_id = ! empty( $attributes['blockID'] ) ? esc_attr( (string) $attributes['blockID'] ) : esc_attr( 'wp-block-' . wp_rand() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gutenberg ServerSideRender context flag.
	$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];

	$button_text            = $attributes['button_text'] ?? 'Redeem Download';
	$input_placeholder_text = $attributes['input_placeholder_text'] ?? 'XXXX-XXXX';
	$layout_style           = $attributes['layout_style'] ?? 'stacked';

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'id' => $anchor,
		)
	);

	$normalize_padding_value = static function ( $raw ): string {
		if ( ! is_scalar( $raw ) ) {
			return '0px';
		}
		$raw = trim( (string) $raw );
		if ( '' === $raw || '0' === $raw ) {
			return '0px';
		}
		if ( preg_match( '/^\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)$/', $raw ) ) {
			return $raw;
		}
		if ( preg_match( '/^\d+(?:\.\d+)?$/', $raw ) ) {
			return $raw . 'px';
		}
		return '0px';
	};

	$sanitize_css_dimension = static function ( $raw ): string {
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$raw = trim( (string) $raw );
		if ( '0' === $raw || preg_match( '/^\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)$/', $raw ) ) {
			return $raw;
		}
		return '';
	};

	$sanitize_css_font_family = static function ( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		return trim( (string) preg_replace( '/[^a-zA-Z0-9\s,\'"-]/', '', $raw ) );
	};

	$allowed_font_styles   = array( 'normal', 'italic', 'oblique' );
	$allowed_text_aligns   = array( 'left', 'center', 'right', 'justify' );
	$allowed_border_styles = array( 'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset' );
	$allowed_font_units    = array( 'px', 'em', 'rem', '%' );

	$css_button       = '';
	$css_button_hover = '';
	$css_input        = '';
	$css_input_hover  = '';

	// Input style attributes.
	$input_bg   = ! empty( $attributes['inputBackgroundColor'] ) ? sanitize_hex_color( (string) $attributes['inputBackgroundColor'] ) : '';
	$input_fg   = ! empty( $attributes['inputTextColor'] ) ? sanitize_hex_color( (string) $attributes['inputTextColor'] ) : '';
	$input_unit = isset( $attributes['inputFontUnit'] ) && in_array( $attributes['inputFontUnit'], $allowed_font_units, true ) ? $attributes['inputFontUnit'] : 'px';
	$css_input .= $input_bg ? 'background-color:' . $input_bg . ';' : '';
	$css_input .= $input_fg ? 'color:' . $input_fg . ';' : '';
	$css_input .= ! empty( $attributes['inputFontSize'] ) && is_numeric( $attributes['inputFontSize'] ) ? 'font-size:' . floatval( $attributes['inputFontSize'] ) . $input_unit . ';' : '';
	$input_ff   = ! empty( $attributes['inputFontFamily'] ) ? $sanitize_css_font_family( $attributes['inputFontFamily'] ) : '';
	$css_input .= '' !== $input_ff ? 'font-family:' . $input_ff . ';' : '';
	$css_input .= ! empty( $attributes['inputFontStyle'] ) && in_array( $attributes['inputFontStyle'], $allowed_font_styles, true ) ? 'font-style:' . $attributes['inputFontStyle'] . ';' : '';
	$css_input .= ! empty( $attributes['inputLineHeight'] ) && is_numeric( $attributes['inputLineHeight'] ) ? 'line-height:' . floatval( $attributes['inputLineHeight'] ) . ';' : '';
	$css_input .= ! empty( $attributes['inputTextAlign'] ) && in_array( $attributes['inputTextAlign'], $allowed_text_aligns, true ) ? 'text-align:' . $attributes['inputTextAlign'] . ';' : '';

	// Button style attributes.
	$btn_bg      = ! empty( $attributes['buttonBackgroundColor'] ) ? sanitize_hex_color( (string) $attributes['buttonBackgroundColor'] ) : '';
	$btn_fg      = ! empty( $attributes['buttonTextColor'] ) ? sanitize_hex_color( (string) $attributes['buttonTextColor'] ) : '';
	$btn_unit    = isset( $attributes['buttonFontUnit'] ) && in_array( $attributes['buttonFontUnit'], $allowed_font_units, true ) ? $attributes['buttonFontUnit'] : 'px';
	$css_button .= $btn_bg ? 'background-color:' . $btn_bg . ';' : '';
	$css_button .= $btn_fg ? 'color:' . $btn_fg . ';' : '';
	$css_button .= ! empty( $attributes['buttonFontSize'] ) && is_numeric( $attributes['buttonFontSize'] ) ? 'font-size:' . floatval( $attributes['buttonFontSize'] ) . $btn_unit . ';' : '';
	$btn_ff      = ! empty( $attributes['buttonFontFamily'] ) ? $sanitize_css_font_family( $attributes['buttonFontFamily'] ) : '';
	$css_button .= '' !== $btn_ff ? 'font-family:' . $btn_ff . ';' : '';
	$css_button .= ! empty( $attributes['buttonFontStyle'] ) && in_array( $attributes['buttonFontStyle'], $allowed_font_styles, true ) ? 'font-style:' . $attributes['buttonFontStyle'] . ';' : '';
	$css_button .= ! empty( $attributes['buttonLineHeight'] ) && is_numeric( $attributes['buttonLineHeight'] ) ? 'line-height:' . floatval( $attributes['buttonLineHeight'] ) . ';' : '';
	$css_button .= ! empty( $attributes['buttonTextAlign'] ) && in_array( $attributes['buttonTextAlign'], $allowed_text_aligns, true ) ? 'text-align:' . $attributes['buttonTextAlign'] . ';' : '';

	// Input border.
	if ( ! empty( $attributes['inputBorder'] ) && is_array( $attributes['inputBorder'] ) ) {
		$ib               = $attributes['inputBorder'];
		$ib_color         = ! empty( $ib['color'] ) ? sanitize_hex_color( (string) $ib['color'] ) : '';
		$ib_width         = ! empty( $ib['width'] ) ? $sanitize_css_dimension( $ib['width'] ) : '';
		$ib_radius        = ! empty( $ib['radius'] ) ? $sanitize_css_dimension( $ib['radius'] ) : '';
		$css_input_hover .= $ib_color ? 'border-color:' . $ib_color . ';' : '';
		$css_input_hover .= ! empty( $ib['style'] ) && in_array( $ib['style'], $allowed_border_styles, true ) ? 'border-style:' . $ib['style'] . ';' : '';
		$css_input_hover .= '' !== $ib_width ? 'border-width:' . $ib_width . ';' : '';
		$css_input_hover .= '' !== $ib_radius ? 'border-radius:' . $ib_radius . ';' : '';
	}

	// Button border.
	if ( ! empty( $attributes['buttonBorder'] ) && is_array( $attributes['buttonBorder'] ) ) {
		$bb                = $attributes['buttonBorder'];
		$bb_color          = ! empty( $bb['color'] ) ? sanitize_hex_color( (string) $bb['color'] ) : '';
		$bb_width          = ! empty( $bb['width'] ) ? $sanitize_css_dimension( $bb['width'] ) : '';
		$bb_radius         = ! empty( $bb['radius'] ) ? $sanitize_css_dimension( $bb['radius'] ) : '';
		$css_button_hover .= $bb_color ? 'border-color:' . $bb_color . ';' : '';
		$css_button_hover .= ! empty( $bb['style'] ) && in_array( $bb['style'], $allowed_border_styles, true ) ? 'border-style:' . $bb['style'] . ';' : '';
		$css_button_hover .= '' !== $bb_width ? 'border-width:' . $bb_width . ';' : '';
		$css_button_hover .= '' !== $bb_radius ? 'border-radius:' . $bb_radius . ';' : '';
	}

	// Input padding.
	if ( ! empty( $attributes['inputPadding'] ) && is_array( $attributes['inputPadding'] ) ) {
		$ip         = $attributes['inputPadding'];
		$top        = $normalize_padding_value( $ip['top'] ?? '' );
		$right      = $normalize_padding_value( $ip['right'] ?? '' );
		$bottom     = $normalize_padding_value( $ip['bottom'] ?? '' );
		$left       = $normalize_padding_value( $ip['left'] ?? '' );
		$css_input .= "padding:{$top} {$right} {$bottom} {$left};";
	}

	// Button padding.
	if ( ! empty( $attributes['buttonPadding'] ) && is_array( $attributes['buttonPadding'] ) ) {
		$bp          = $attributes['buttonPadding'];
		$top         = $normalize_padding_value( $bp['top'] ?? '' );
		$right       = $normalize_padding_value( $bp['right'] ?? '' );
		$bottom      = $normalize_padding_value( $bp['bottom'] ?? '' );
		$left        = $normalize_padding_value( $bp['left'] ?? '' );
		$css_button .= "padding:{$top} {$right} {$bottom} {$left};";
	}

	// Hover colors.
	$input_hover_bg = ! empty( $attributes['inputHoverColor'] ) ? sanitize_hex_color( (string) $attributes['inputHoverColor'] ) : '';
	if ( $input_hover_bg ) {
		$css_input_hover .= 'background-color: ' . $input_hover_bg . ' !important;';
	}
	$btn_hover_bg = ! empty( $attributes['buttonHoverColor'] ) ? sanitize_hex_color( (string) $attributes['buttonHoverColor'] ) : '';
	if ( $btn_hover_bg ) {
		$css_button_hover .= 'background-color: ' . $btn_hover_bg . ' !important;';
	}

	$block_id = sanitize_html_class( $block_id );

	$css = "
#{$block_id}.bandcamp-form button { {$css_button} }
#{$block_id}.bandcamp-form button:hover { {$css_button_hover} }
#{$block_id}.bandcamp-form input { {$css_input} }
#{$block_id}.bandcamp-form input:hover { {$css_input_hover} }
";

	echo '<style>' . wp_kses( $css, array() ) . '</style>';
	?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : 'id="' . $anchor . '"' ); ?>>
	<div id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-form bandcamp-form-layout-<?php echo esc_attr( (string) $layout_style ); ?>">
		<form action="<?php echo esc_url( 'https://www.bandcamp.com/yum?' ); ?>" method="get">
			<label for="<?php echo esc_attr( $block_id ); ?>-bandcamp-code"><?php esc_html_e( 'Download Code', 'mds-bandcamp-integration' ); ?></label>
			<input id="<?php echo esc_attr( $block_id ); ?>-bandcamp-code" type="text" name="code" placeholder="<?php echo esc_attr( (string) $input_placeholder_text ); ?>" />
			<button <?php echo esc_attr( $is_editor ? 'disabled' : '' ); ?> class="wp-element-button" type="submit">
				<?php echo esc_html( (string) $button_text ); ?>
			</button>
		</form>
	</div>
</div>
	<?php
} )( is_array( $attributes ?? null ) ? $attributes : array() );
