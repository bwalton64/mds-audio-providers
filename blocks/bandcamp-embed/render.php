<?php
/**
 * Server-side rendering for the Bandcamp embed block.
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

	$text_align = isset( $attributes['textAlign'] ) && in_array( $attributes['textAlign'], array( 'left', 'center', 'right', 'justify' ), true )
		? $attributes['textAlign']
		: 'center';
	$embed_type = $attributes['embedType'] ?? 'shortcode';
	$code       = $attributes['code'] ?? false;

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'id'    => $anchor,
			'style' => 'text-align:' . $text_align . ';',
		)
	);

	if ( ! $code ) {
		echo wp_kses(
			\MDS\Bandcamp\Api\BandcampApi::render_error_html(
				array(
					'error'   => true,
					'message' => __( 'EMBED CODE REQUIRED', 'mds-bandcamp-integration' ),
				)
			),
			'post'
		);
		return;
	}

	$output = '';

	if ( 'url' === $embed_type ) {
		$data = \MDS\Bandcamp\Api\BandcampApi::fetch(
			array(
				'endpoint' => 'embed',
				'id'       => (string) $code,
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

		if ( ! empty( $data['item_type'] ) && 't' === $data['item_type'] ) {
			$data['item_type'] = 'track';
		} else {
			$data['item_type'] = 'album';
		}

		$embed_url = 'https://bandcamp.com/EmbeddedPlayer/' . rawurlencode( (string) $data['item_type'] ) . '=' . rawurlencode( (string) ( $data['item_id'] ?? '' ) ) . '/size=large/bgcol=ffffff/linkcol=111111/tracklist=false/artwork=small/transparent=true/';
		$output    = '
		<iframe style="border:0;width:100%;max-width:700px;height:120px;margin:0px;" src="' . esc_url( $embed_url ) . '" seamless>
			<a href="' . esc_url( (string) $code ) . '">' . esc_html__( 'Listen on Bandcamp', 'mds-bandcamp-integration' ) . '</a>
		</iframe>';
	} elseif ( 'shortcode' === $embed_type ) {
		$output = do_shortcode( (string) $code );
	} elseif ( 'iframe' === $embed_type ) {
		$decoded_iframe = html_entity_decode( (string) $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$valid_iframe   = false;
		if ( preg_match( '/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $decoded_iframe, $iframe_matches ) ) {
			$parsed_src = wp_parse_url( $iframe_matches[1] );
			$src_scheme = isset( $parsed_src['scheme'] ) ? strtolower( $parsed_src['scheme'] ) : '';
			$src_host   = isset( $parsed_src['host'] ) ? strtolower( $parsed_src['host'] ) : '';
			$src_path   = $parsed_src['path'] ?? '';
			if ( 'https' === $src_scheme && 'bandcamp.com' === $src_host && 0 === strpos( $src_path, '/EmbeddedPlayer/' ) ) {
				$valid_iframe = true;
			}
		}

		if ( ! $valid_iframe ) {
			echo wp_kses(
				\MDS\Bandcamp\Api\BandcampApi::render_error_html(
					array(
						'error'   => true,
						'message' => __( 'ONLY BANDCAMP EMBEDDED PLAYER IFRAMES ARE ALLOWED', 'mds-bandcamp-integration' ),
					)
				),
				'post'
			);
			return;
		}

		$output = $decoded_iframe;
	}
	?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : 'style="text-align:' . esc_attr( $text_align ) . '"' ); ?>>
	<div id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-embed">
		<?php echo wp_kses( $output, 'post' ); ?>
	</div>
</div>
	<?php
} )( is_array( $attributes ?? null ) ? $attributes : array() );
