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

( static function ( array $attributes, $block ): void {
	$anchor   = ! empty( $attributes['anchor'] ) ? esc_attr( (string) $attributes['anchor'] ) : esc_attr( 'wp-anchor-' . wp_rand() );
	$block_id = ! empty( $attributes['blockID'] ) ? esc_attr( (string) $attributes['blockID'] ) : esc_attr( 'wp-block-' . wp_rand() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gutenberg ServerSideRender context flag.
	$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];

	$album_id              = $attributes['albumID'] ?? false;
	$background_color      = $attributes['backgroundColor'] ?? '#171717';
	$button_color          = $attributes['buttonColor'] ?? '#171717';
	$display_album_art     = ! empty( $attributes['display_album_art'] );
	$display_album_artist  = ! empty( $attributes['display_album_artist'] );
	$display_album_link    = $attributes['display_album_link'] ?? true;
	$display_album_title   = ! empty( $attributes['display_album_title'] );
	$display_audio         = $attributes['display_audio'] ?? true;
	$display_merch         = $attributes['display_merch'] ?? true;
	$display_playlist      = $attributes['display_playlist'] ?? true;
	$embed_type            = $attributes['embedType'] ?? 'custom';
	$player_controls_color = $attributes['playerControlsColor'] ?? '#ffffff';
	$progress_bar_color    = $attributes['progressBarColor'] ?? '#ffffff';
	$text_color            = $attributes['textColor'] ?? '#ffffff';
	$track_color           = $attributes['trackColor'] ?? '#ffffff';
	$url                   = $attributes['url'] ?? false;

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

	$button_text_color      = \MDS\Bandcamp\Core\Utilities::get_contrast_color( (string) $button_color );
	$block_id               = sanitize_html_class( $block_id );
	$css_width_progress_bar = $is_editor ? '50%' : '0';

	$safe_bg       = sanitize_hex_color( (string) $background_color );
	$safe_text     = sanitize_hex_color( (string) $text_color );
	$safe_track    = sanitize_hex_color( (string) $track_color );
	$safe_btn      = sanitize_hex_color( (string) $button_color );
	$safe_btn_text = sanitize_hex_color( $button_text_color );
	$safe_controls = sanitize_hex_color( (string) $player_controls_color );
	$safe_progress = sanitize_hex_color( (string) $progress_bar_color );

	$css = "
#{$block_id}.bandcamp-album {background-color:" . ( $safe_bg ? $safe_bg : 'transparent' ) . ";}
#{$block_id}.bandcamp-album h3,
#{$block_id}.bandcamp-album h4,
#{$block_id}.bandcamp-album p,
#{$block_id}.bandcamp-album small,
#{$block_id}.bandcamp-album span,
#{$block_id}.bandcamp-album a {color:" . ( $safe_text ? $safe_text : 'inherit' ) . ";}
#{$block_id}.bandcamp-album .playlist-tracks * {color:" . ( $safe_track ? $safe_track : 'inherit' ) . ";}
#{$block_id}.bandcamp-album a.album-link {background-color:" . ( $safe_btn ? $safe_btn : 'transparent' ) . ';color:' . ( $safe_btn_text ? $safe_btn_text : 'inherit' ) . " !important;}
#{$block_id}.bandcamp-album a.album-link div {color:" . ( $safe_btn_text ? $safe_btn_text : 'inherit' ) . " !important;}
#{$block_id}.bandcamp-album a.album-link path {fill:" . ( $safe_btn_text ? $safe_btn_text : 'inherit' ) . ";}
#{$block_id}.bandcamp-album .bandcamp-player-time {color:" . ( $safe_text ? $safe_text : 'inherit' ) . "}
#{$block_id}.bandcamp-album .bandcamp-player-play svg path {fill:" . ( $safe_controls ? $safe_controls : 'currentColor' ) . " !important;}
#{$block_id}.bandcamp-album .bandcamp-player-progress {background:" . ( $safe_progress ? $safe_progress : 'transparent' ) . ';width:' . esc_attr( $css_width_progress_bar ) . ";}
#{$block_id}.bandcamp-album .bandcamp-player-progress-wrap {background:rgba(255,255,255,0.2);transition:.3s ease;}
";

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

	$data['featured_track_mp3']    = '';
	$data['featured_track_title']  = '';
	$data['featured_track_artist'] = $data['artist'] ?? '';

	if ( ! empty( $data['tracks'] ) && is_array( $data['tracks'] ) ) {
		foreach ( $data['tracks'] as $track ) {
			if ( isset( $track['id'] ) && (string) ( $data['featured_track_id'] ?? '' ) === (string) $track['id'] ) {
				$data['featured_track_mp3']    = $track['mp3'] ?? '';
				$data['featured_track_title']  = $track['title'] ?? '';
				$data['featured_track_artist'] = $track['artist'] ?? $data['featured_track_artist'];
				break;
			}
		}

		if ( '' === $data['featured_track_mp3'] ) {
			foreach ( $data['tracks'] as $track ) {
				if ( ! empty( $track['mp3'] ) ) {
					$data['featured_track_mp3']    = $track['mp3'];
					$data['featured_track_title']  = $track['title'] ?? '';
					$data['featured_track_artist'] = $track['artist'] ?? $data['featured_track_artist'];
					break;
				}
			}
		}
	}
	?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : '' ); ?>>
	<?php if ( $data ) : ?>
	<section id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-album">
		<div class="bandcamp-album-header">
			<div class="bandcamp-album-header-wrap">
				<?php if ( $display_album_art ) : ?>
				<div class="bandcamp-album-artwork">
					<img src="<?php echo esc_url( $data['album_art_medium'] ); ?>" alt="
					<?php
					/* translators: %s: Bandcamp album title. */
					echo esc_attr( sprintf( __( '%s Cover Art', 'mds-bandcamp-integration' ), $data['album_title'] ) );
					?>
					">
				</div>
				<?php endif; ?>
				<div class="bandcamp-album-titles">
					<div>
						<h3><i><?php echo esc_html( $display_album_title ? $data['album_title'] : '' ); ?></i></h3>
						<h4><?php echo esc_html( $display_album_artist ? $data['artist'] : '' ); ?></h4>
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
						<?php if ( $display_audio && 'bandcamp' === $embed_type ) : ?>
						<iframe src="<?php echo esc_url( 'https://bandcamp.com/EmbeddedPlayer/album=' . rawurlencode( (string) $data['album_id'] ) . '/size=small/bgcol=' . rawurlencode( str_replace( '#', '', (string) $background_color ) ) . '/linkcol=' . rawurlencode( str_replace( '#', '', \MDS\Bandcamp\Core\Utilities::get_contrast_color( (string) $background_color ) ) ) . '/artwork=none/transparent=true/' ); ?>" seamless></iframe>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<?php if ( $display_audio && 'custom' === $embed_type ) : ?>
			<div class="bandcamp-album-audio bandcamp-player">
				<audio id="<?php echo esc_attr( $block_id . '-audio' ); ?>" preload="auto" tabindex="0" controls="" type="audio/mpeg" style="display:none">
					<source type="audio/mp3" src="<?php echo esc_url( $data['featured_track_mp3'] ); ?>">
					<?php echo esc_html__( 'Sorry, your browser does not support HTML5 audio.', 'mds-bandcamp-integration' ); ?>
				</audio>
				<button type="button" class="bandcamp-player-play" aria-label="<?php echo esc_attr__( 'Play Track', 'mds-bandcamp-integration' ); ?>" aria-controls="<?php echo esc_attr( $block_id . '-audio' ); ?>" data-play-label="<?php echo esc_attr__( 'Play Track', 'mds-bandcamp-integration' ); ?>" data-pause-label="<?php echo esc_attr__( 'Pause Track', 'mds-bandcamp-integration' ); ?>" aria-pressed="false">
					<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 100 100" xml:space="preserve">
						<path class="play" d="M 25,15 L 85,50 L 25,85 Z" />
						<path class="pause" style="display:none" d="M 20,20 H 40 V 80 H 20 Z M 60,20 H 80 V 80 H 60 Z" />
					</svg>
				</button>
				<div class="bandcamp-player-progress-wrap" role="slider" tabindex="0" aria-label="<?php echo esc_attr__( 'Seek audio', 'mds-bandcamp-integration' ); ?>" aria-controls="<?php echo esc_attr( $block_id . '-audio' ); ?>" aria-orientation="horizontal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="<?php echo esc_attr__( '00:00 / 00:00', 'mds-bandcamp-integration' ); ?>">
					<div class="bandcamp-player-progress-buffer"></div>
					<div class="bandcamp-player-progress"></div>
				</div>
				<div class="bandcamp-player-time" aria-live="off">00:00 / 00:00</div>
			</div>
			<?php endif; ?>

			<div class="bandcamp-album-background" style="background-image:url(<?php echo esc_url( $display_album_art ? $data['album_art_large'] : '' ); ?>);"></div>
		</div>

		<?php if ( ! empty( $data['tracks'] ) && $display_playlist && $display_audio && 'custom' === $embed_type ) : ?>
		<div class="bandcamp-album-tracks">
			<ul>
				<?php
				foreach ( $data['tracks'] as $track ) {
					if ( isset( $track['mp3'] ) ) {
						$active      = (string) ( $data['featured_track_id'] ?? '' ) === (string) $track['id'] ? 'active' : '';
						$track_label = sprintf(
							/* translators: 1: track title, 2: track artist */
							esc_attr__( 'Play %1$s by %2$s', 'mds-bandcamp-integration' ),
							$track['title'],
							$track['artist']
						);
						echo '
						<li class="' . esc_attr( $active ) . '">
							<span class="track-number">' . esc_html( (string) $track['tracknum'] ) . '</span>
							<button type="button" class="track-link" data-track="' . esc_url( $track['mp3'] ) . '" aria-label="' . esc_attr( $track_label ) . '" aria-current="' . esc_attr( 'active' === $active ? 'true' : 'false' ) . '">' . esc_html( $track['artist'] ) . ' : ' . esc_html( $track['title'] ) . '</button>
							<span class="track-duration">' . esc_html( $track['duration'] ) . '</span>
						</li>';
					} else {
						$unavailable_label = sprintf(
							/* translators: 1: track title, 2: track artist */
							esc_attr__( 'Track %1$s by %2$s is not available', 'mds-bandcamp-integration' ),
							$track['title'],
							$track['artist']
						);
						echo '
						<li>
							<span class="track-number">' . esc_html( (string) $track['tracknum'] ) . '</span>
							<button type="button" class="track-link" aria-label="' . esc_attr( $unavailable_label ) . '" disabled>' . esc_html( $track['artist'] ) . ' : ' . esc_html( $track['title'] ) . '</button>
							<span class="track-duration"></span>
						</li>';
					}
				}
				?>
			</ul>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $data['packages'] ) && $display_merch ) : ?>
		<div class="bandcamp-album-merch">
			<ul>
				<?php
				$package_index = 0;
				foreach ( $data['packages'] as $package ) {
					if ( (string) $package['album_id'] === (string) $data['album_id'] ) :
						?>
						<li>
							<div class="bandcamp-album-merch-photos">
								<?php foreach ( $package['photos'] as $photo ) : ?>
								<a href="<?php echo esc_url( $photo['large'] ); ?>" target="_blank" rel="noopener noreferrer" class="bandcamp-lightbox" data-bandcamp-gallery="<?php echo esc_attr( $package['album_title'] ? $package['album_title'] . '-' . $package_index : $package['title'] . '-' . $package_index ); ?>">
									<img loading="lazy" src="<?php echo esc_url( $photo['small'] ); ?>" alt="<?php echo esc_attr( $package['album_title'] ?? $package['title'] ); ?>">
								</a>
								<?php endforeach; ?>
							</div>
							<div class="bandcamp-album-merch-info">
								<h3><?php echo esc_html( $package['album_title'] ?? $package['title'] ); ?></h3>
								<span><?php echo esc_html( $package['title'] ); ?> | $<?php echo esc_html( $package['price'] ); ?></span>
								<small><i><?php echo esc_html( $package['description'] ); ?></i></small>
								<small><strong><?php echo esc_html( $package['type'] ); ?><?php echo esc_html( $package['edition_size'] ? ' | Edition of ' . $package['edition_size'] : '' ); ?></strong></small>
								<span>
									<?php if ( $package['quantity_available'] > 0 ) : ?>
									<a target="_blank" class="album-link" href="<?php echo esc_url( $package['url'] ); ?>">
										<div>
											<?php echo esc_html__( 'Purchase on Bandcamp', 'mds-bandcamp-integration' ); ?>
											<svg version="1.1" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" xml:space="preserve">
												<path d="M10.1,8.9l-3.3,6h7l3.3-6H10.1z M21.6,11.9c0-5.3-4.3-9.6-9.6-9.6s-9.6,4.3-9.6,9.6s4.3,9.6,9.6,9.6 S21.6,17.2,21.6,11.9z M12,20.9c-5,0-9-4-9-9s4-9,9-9s9,4,9,9S17,20.9,12,20.9z"/>
											</svg>
										</div>
									</a>
									<?php else : ?>
									<a target="_blank" class="album-link" disabled style="color:red !important;" href="<?php echo esc_url( $package['url'] ); ?>"><?php echo esc_html__( 'Sold Out', 'mds-bandcamp-integration' ); ?></a>
									<?php endif; ?>
								</span>
							</div>
						</li>
						<?php
					endif;
					++$package_index;
				}
				?>
			</ul>
		</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>
</div>
	<?php
} )( is_array( $attributes ?? null ) ? $attributes : array(), $block ?? null );
