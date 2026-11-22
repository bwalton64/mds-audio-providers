<?php
/**
 * Server-side rendering for the Bandcamp miniplayer block.
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

	$album_id             = $attributes['albumID'] ?? false;
	$art_opacity          = isset( $attributes['artOpacity'] ) && is_numeric( $attributes['artOpacity'] ) ? (float) $attributes['artOpacity'] : 0.5;
	$art_opacity_hover    = isset( $attributes['artOpacityHover'] ) && is_numeric( $attributes['artOpacityHover'] ) ? (float) $attributes['artOpacityHover'] : 0.2;
	$aspect_ratio_height  = $attributes['aspectRatioHeight'] ?? 1;
	$aspect_ratio_width   = $attributes['aspectRatioWidth'] ?? 1;
	$background_color     = $attributes['backgroundColor'] ?? '#171717';
	$button_color         = $attributes['buttonColor'] ?? '#ffffff';
	$display_album_artist = ! empty( $attributes['display_album_artist'] );
	$display_album_title  = ! empty( $attributes['display_album_title'] );
	$display_album_link   = $attributes['display_album_link'] ?? true;
	$display_progress_bar = $attributes['display_progress_bar'] ?? true;
	$display_track_title  = ! empty( $attributes['display_track_title'] );
	$progress_bar_color   = $attributes['progressBarColor'] ?? '#000000';
	$text_color           = $attributes['textColor'] ?? '#ffffff';
	$track_number         = $attributes['trackNumber'] ?? false;
	$url                  = $attributes['url'] ?? false;

	// Fall back to the current release's verified Bandcamp album when the block has no selection.
	if ( empty( $album_id ) ) {
		$context_id    = \MDS\Bandcamp\Core\Helpers::resolve_context_release_id( $attributes, $block );
		$context_album = $context_id ? \MDS\Bandcamp\Core\Helpers::get_album_id( $context_id ) : 0;
		if ( $context_album > 0 ) {
			$album_id = (string) $context_album;
			$url      = \MDS\Bandcamp\Core\Helpers::get_release_url( $context_id );
		}
	}

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'id' => $anchor,
		)
	);

	$css_display_progress_bar = $display_progress_bar ? 'block' : 'none';
	$css_width_progress_bar   = $is_editor ? '50%' : '0';
	$block_id                 = sanitize_html_class( $block_id );

	$safe_bg       = sanitize_hex_color( (string) $background_color );
	$safe_btn      = sanitize_hex_color( (string) $button_color );
	$safe_text     = sanitize_hex_color( (string) $text_color );
	$safe_progress = sanitize_hex_color( (string) $progress_bar_color );

	$css = "
#{$block_id}.bandcamp-miniplayer {background-color:" . ( $safe_bg ? $safe_bg : 'transparent' ) . ';aspect-ratio:' . absint( $aspect_ratio_width ) . '/' . absint( $aspect_ratio_height ) . ";}
#{$block_id}.bandcamp-miniplayer .bandcamp-miniplayer-play svg path {fill:" . ( $safe_btn ? $safe_btn : 'currentColor' ) . " !important;}
#{$block_id}.bandcamp-miniplayer .bandcamp-miniplayer-art {opacity:" . $art_opacity . ";}
#{$block_id}.bandcamp-miniplayer:hover .bandcamp-miniplayer-art {opacity:" . $art_opacity_hover . ";}
#{$block_id}.bandcamp-miniplayer .bandcamp-miniplayer-albumlink a path {fill:" . esc_attr( \MDS\Bandcamp\Core\Utilities::get_contrast_color( (string) $button_color ) ) . ";}
#{$block_id}.bandcamp-miniplayer h3,
#{$block_id}.bandcamp-miniplayer h4,
#{$block_id}.bandcamp-miniplayer p {color:" . ( $safe_text ? $safe_text : 'inherit' ) . ";padding:5px 0;}
#{$block_id}.bandcamp-miniplayer .bandcamp-miniplayer-progress {background:" . ( $safe_progress ? $safe_progress : 'transparent' ) . ';display:' . esc_attr( $css_display_progress_bar ) . ';width:' . esc_attr( $css_width_progress_bar ) . ';}
';

	echo '<style>' . wp_kses( $css, array() ) . '</style>';

	if ( ! $url || ! $album_id ) {
		echo wp_kses(
			\MDS\Bandcamp\Api\BandcampApi::render_notice_html(
				array(
					'error'   => true,
					'message' => __( 'BANDCAMP ALBUM ID REQUIRED', 'mds-bandcamp-integration' ),
				)
			),
			'post'
		);
		return;
	}

	$data = \MDS\Bandcamp\Api\BandcampApi::fetch(
		array(
			'endpoint' => 'album',
			'id'       => (string) $album_id,
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

	$data['featured_track_mp3']    = $data['featured_track_mp3'] ?? '';
	$data['featured_track_title']  = $data['featured_track_title'] ?? '';
	$data['featured_track_artist'] = $data['featured_track_artist'] ?? ( $data['artist'] ?? '' );

	if ( ! empty( $data['tracks'] ) && is_array( $data['tracks'] ) ) {
		foreach ( $data['tracks'] as $track ) {
			if ( ! empty( $track_number ) && (string) $track['tracknum'] === (string) $track_number ) {
				$data['featured_track_mp3']    = $track['mp3'];
				$data['featured_track_title']  = $track['title'];
				$data['featured_track_artist'] = $track['artist'];
			} elseif ( empty( $track_number ) && (string) ( $data['featured_track_id'] ?? '' ) === (string) $track['id'] ) {
				$data['featured_track_mp3']    = $track['mp3'];
				$data['featured_track_title']  = $track['title'];
				$data['featured_track_artist'] = $track['artist'];
			}
		}
	}
	?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : '' ); ?>>
	<?php if ( $data ) : ?>
	<section id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-miniplayer">
		<audio id="<?php echo esc_attr( $block_id . '-audio' ); ?>" preload="auto" tabindex="0" controls="" type="audio/mpeg" style="display:none">
			<source type="audio/mp3" src="<?php echo esc_url( $data['featured_track_mp3'] ); ?>">
			<?php echo esc_html__( 'Sorry, your browser does not support HTML5 audio.', 'mds-bandcamp-integration' ); ?>
		</audio>
		<button type="button" class="bandcamp-miniplayer-play" aria-label="<?php echo esc_attr__( 'Play Track', 'mds-bandcamp-integration' ); ?>" aria-controls="<?php echo esc_attr( $block_id . '-audio' ); ?>" data-play-label="<?php echo esc_attr__( 'Play Track', 'mds-bandcamp-integration' ); ?>" data-pause-label="<?php echo esc_attr__( 'Pause Track', 'mds-bandcamp-integration' ); ?>" aria-pressed="false">
			<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 800 800" style="enable-background:new 0 0 800 800;" xml:space="preserve">
				<path class="play" d="M553.4,386.6L342.3,242.2c-6.6-4.8-24.1-4.8-25.5,13.4v288.8c1.6,18.3,19.4,18.6,25.5,13.4l211.1-144.4 C558.9,410.2,566.4,396.7,553.4,386.6L553.4,386.6z M349.5,513.6V286.4L515.3,400L349.5,513.6z"/>
				<path class="pause" style="display:none" d="M363.3,560.9h-37.6c-14.9,0-27-12.1-27-27V265c0-14.9,12.1-27,27-27h37.6c14.9,0,27,12.1,27,27v268.9 C390.3,548.8,378.2,560.9,363.3,560.9z M328.7,530.9h31.6V268h-31.6V530.9z M474.3,560.9h-37.6c-14.9,0-27-12.1-27-27V265 c0-14.9,12.1-27,27-27h37.6c14.9,0,27,12.1,27,27v268.9C501.3,548.8,489.2,560.9,474.3,560.9z M439.7,530.9h31.6V268h-31.6V530.9z" />
				<path class="circle" d="M400,17.2C188.9,17.2,17.2,188.9,17.2,400S188.9,782.8,400,782.8S782.8,611.1,782.8,400S611.1,17.2,400,17.2z M400,750.2c-193.1,0-350.2-157-350.2-350.2S206.9,49.8,400,49.8s350.2,157,350.2,350.2S593.1,750.2,400,750.2z"/>
			</svg>
		</button>
		<div class="bandcamp-miniplayer-text">
			<?php echo $display_track_title ? '<h3><i>' . esc_html( $data['featured_track_title'] ) . '</i></h3>' : ''; ?>
			<?php echo $display_album_artist ? '<h4>' . esc_html( $data['featured_track_artist'] ) . '</h4>' : ''; ?>
			<?php echo $display_album_title ? '<p>' . esc_html__( 'from', 'mds-bandcamp-integration' ) . ' <i>"' . esc_html( $data['album_title'] ) . '"</i></p>' : ''; ?>
		</div>
		<div class="bandcamp-miniplayer-art" style="background-image:url(<?php echo esc_url( $data['album_art_medium'] ); ?>);"></div>
		<div class="bandcamp-miniplayer-albumlink">
			<?php if ( $display_album_link ) : ?>
			<a href="<?php echo esc_url( $data['album_link'] ); ?>" class="album-link" target="_blank">
				<div>
					<?php echo esc_html__( 'Listen on Bandcamp', 'mds-bandcamp-integration' ); ?>
					<svg version="1.1" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" xml:space="preserve">
						<path d="M10.1,8.9l-3.3,6h7l3.3-6H10.1z M21.6,11.9c0-5.3-4.3-9.6-9.6-9.6s-9.6,4.3-9.6,9.6s4.3,9.6,9.6,9.6 S21.6,17.2,21.6,11.9z M12,20.9c-5,0-9-4-9-9s4-9,9-9s9,4,9,9S17,20.9,12,20.9z"/>
					</svg>
				</div>
			</a>
			<?php endif; ?>
		</div>
		<div class="bandcamp-miniplayer-progress-wrap" role="slider" tabindex="0" aria-label="<?php echo esc_attr__( 'Seek audio', 'mds-bandcamp-integration' ); ?>" aria-controls="<?php echo esc_attr( $block_id . '-audio' ); ?>" aria-orientation="horizontal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="<?php echo esc_attr__( '00:00 / 00:00', 'mds-bandcamp-integration' ); ?>">
			<div class="bandcamp-miniplayer-progress-buffer"></div>
			<div class="bandcamp-miniplayer-progress"></div>
		</div>
	</section>
	<?php endif; ?>
</div>
	<?php
} )( is_array( $attributes ?? null ) ? $attributes : array(), $block ?? null );
