<?php
/**
 * Unified server-side renderer for Bandcamp blocks, shortcodes, Elementor widgets, and Admin live previews.
 *
 * @package MDS\Bandcamp
 * @since   1.0.2
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Blocks;

use MDS\Bandcamp\Api\BandcampApi;
use MDS\Bandcamp\Core\Helpers;
use MDS\Bandcamp\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Bandcamp Album, Discography, and Miniplayer components consistently across all surfaces.
 *
 * @since 1.0.2
 */
final class BlockRenderer {
	/**
	 * Enqueue shared frontend assets for a rendered Bandcamp component.
	 *
	 * @since 1.0.2
	 *
	 * @param string $slug Component slug (`bandcamp-album`, `bandcamp-discography`, `bandcamp-miniplayer`).
	 * @return void
	 */
	public static function enqueue_component_assets( string $slug ): void {
		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::enqueue_font_awesome();
		}

		wp_enqueue_style( 'mds-bc-blocks', MDS_BC_PLUGIN_URL . '/css/blocks.css', array(), MDS_BC_PLUGIN_VERSION );

		$css_rel = 'blocks/' . $slug . '/frontend.css';
		if ( file_exists( MDS_BC_PATH . $css_rel ) ) {
			wp_enqueue_style(
				'mds-bc-' . $slug . '-frontend',
				MDS_BC_URL . $css_rel,
				array( 'mds-bc-blocks' ),
				(string) filemtime( MDS_BC_PATH . $css_rel )
			);
		}

		$js_rel = 'blocks/' . $slug . '/frontend.js';
		if ( file_exists( MDS_BC_PATH . $js_rel ) ) {
			wp_enqueue_script(
				'mds-bc-' . $slug . '-frontend',
				MDS_BC_URL . $js_rel,
				array(),
				(string) filemtime( MDS_BC_PATH . $js_rel ),
				true
			);
		}

		if ( 'bandcamp-album' === $slug ) {
			wp_enqueue_script( 'mds-bc-blocks', MDS_BC_URL . 'js/blocks.js', array(), MDS_BC_PLUGIN_VERSION, true );
		}
	}

	/**
	 * Resolve a Bandcamp album ID and URL from explicit attributes, release ID/UPC, or current post context.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $attributes Component attributes.
	 * @param mixed                $block      Optional WP_Block instance.
	 * @return array{album_id: string, url: string} Resolved album ID and URL.
	 */
	private static function resolve_album_target( array $attributes, $block ): array {
		$album_id = ! empty( $attributes['albumID'] ) ? trim( (string) $attributes['albumID'] ) : '';
		$url      = ! empty( $attributes['url'] ) ? trim( (string) $attributes['url'] ) : '';

		if ( '' === $album_id && ! empty( $attributes['album_id'] ) ) {
			$album_id = trim( (string) $attributes['album_id'] );
		}

		if ( '' === $album_id ) {
			$release_id = ! empty( $attributes['release_id'] ) ? absint( $attributes['release_id'] ) : 0;
			if ( $release_id <= 0 && ! empty( $attributes['upc'] ) && class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
				$found_post = \MDS\ReleaseManager\Api\ExtensionApi::find_release_by_upc( (string) $attributes['upc'] );
				if ( $found_post && ! empty( $found_post->ID ) ) {
					$release_id = (int) $found_post->ID;
				}
			}

			if ( $release_id <= 0 ) {
				$release_id = Helpers::resolve_context_release_id( $attributes, $block );
			}

			if ( $release_id > 0 ) {
				$context_album = Helpers::get_album_id( $release_id );
				if ( $context_album > 0 ) {
					$album_id = (string) $context_album;
					if ( '' === $url ) {
						$url = Helpers::get_release_url( $release_id );
					}
				}
			}
		}

		return array(
			'album_id' => $album_id,
			'url'      => $url,
		);
	}

	/**
	 * Render the Bandcamp Album component.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $attributes Component attributes.
	 * @param mixed                $block      Optional WP_Block instance.
	 * @param bool                 $is_block   Whether invoked from Gutenberg block render.php.
	 * @return string Rendered HTML.
	 */
	public static function render_album( array $attributes, $block = null, bool $is_block = false ): string {
		if ( ! $is_block ) {
			self::enqueue_component_assets( 'bandcamp-album' );
		} elseif ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::enqueue_font_awesome();
		}

		$attrs = BlockDefaults::resolve( 'bandcamp-album', $attributes );

		$anchor   = ! empty( $attrs['anchor'] ) ? esc_attr( (string) $attrs['anchor'] ) : esc_attr( 'wp-anchor-' . wp_rand() );
		$block_id = ! empty( $attrs['blockID'] ) ? esc_attr( (string) $attrs['blockID'] ) : esc_attr( 'wp-block-' . wp_rand() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gutenberg ServerSideRender context flag.
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];

		$target   = self::resolve_album_target( $attrs, $block );
		$album_id = $target['album_id'];
		$url      = $target['url'];

		$background_color      = (string) ( $attrs['backgroundColor'] ?? '#171717' );
		$button_color          = (string) ( $attrs['buttonColor'] ?? '#000000' );
		$display_album_art     = ! empty( $attrs['display_album_art'] );
		$display_album_artist  = ! empty( $attrs['display_album_artist'] );
		$display_album_link    = ! empty( $attrs['display_album_link'] );
		$display_album_title   = ! empty( $attrs['display_album_title'] );
		$display_audio         = ! empty( $attrs['display_audio'] );
		$display_merch         = ! empty( $attrs['display_merch'] );
		$display_playlist      = ! empty( $attrs['display_playlist'] );
		$embed_type            = (string) ( $attrs['embedType'] ?? 'custom' );
		$player_controls_color = (string) ( $attrs['playerControlsColor'] ?? '#ffffff' );
		$progress_bar_color    = (string) ( $attrs['progressBarColor'] ?? '#ffffff' );
		$text_color            = (string) ( $attrs['textColor'] ?? '#ffffff' );
		$track_color           = (string) ( $attrs['trackColor'] ?? '#ffffff' );

		$wrapper_attributes = $is_block && function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'id' => $anchor ) )
			: 'id="' . $anchor . '" class="wp-block-mds-bandcamp-bandcamp-album"';

		$button_text_color      = Utilities::get_contrast_color( $button_color );
		$block_id               = sanitize_html_class( $block_id );
		$css_width_progress_bar = $is_editor ? '50%' : '0';

		$safe_bg       = sanitize_hex_color( $background_color );
		$safe_text     = sanitize_hex_color( $text_color );
		$safe_track    = sanitize_hex_color( $track_color );
		$safe_btn      = sanitize_hex_color( $button_color );
		$safe_btn_text = sanitize_hex_color( $button_text_color );
		$safe_controls = sanitize_hex_color( $player_controls_color );
		$safe_progress = sanitize_hex_color( $progress_bar_color );

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
#{$block_id}.bandcamp-album a.album-link div,
#{$block_id}.bandcamp-album a.album-link .fab {color:" . ( $safe_btn_text ? $safe_btn_text : 'inherit' ) . " !important;}
#{$block_id}.bandcamp-album .bandcamp-player-time {color:" . ( $safe_text ? $safe_text : 'inherit' ) . "}
#{$block_id}.bandcamp-album .bandcamp-player-play svg path {fill:" . ( $safe_controls ? $safe_controls : 'currentColor' ) . " !important;}
#{$block_id}.bandcamp-album .bandcamp-player-progress {background:" . ( $safe_progress ? $safe_progress : 'transparent' ) . ';width:' . esc_attr( $css_width_progress_bar ) . ";}
#{$block_id}.bandcamp-album .bandcamp-player-progress-wrap {background:rgba(255,255,255,0.2);transition:.3s ease;}
";

		ob_start();
		echo '<style>' . wp_kses( $css, array() ) . '</style>';

		if ( '' === $album_id ) {
			echo wp_kses(
				BandcampApi::render_notice_html(
					array(
						'error'   => true,
						'message' => __( 'BANDCAMP ALBUM ID REQUIRED', 'mds-bandcamp-integration' ),
					)
				),
				'post'
			);
			return (string) ob_get_clean();
		}

		$data = BandcampApi::fetch(
			array(
				'endpoint' => 'album',
				'id'       => $album_id,
			)
		);

		if ( ! empty( $data['error'] ) ) {
			echo wp_kses( BandcampApi::render_error_html( $data ), 'post' );
			return (string) ob_get_clean();
		}

		if ( empty( $data ) ) {
			echo wp_kses(
				BandcampApi::render_error_html(
					array(
						'error'   => true,
						'message' => __( 'BANDCAMP URL IS NOT VALID', 'mds-bandcamp-integration' ),
					)
				),
				'post'
			);
			return (string) ob_get_clean();
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

		unset( $url );
		?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : '' ); ?>>
	<section id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-album">
		<div class="bandcamp-album-header">
			<div class="bandcamp-album-header-wrap">
				<?php if ( $display_album_art ) : ?>
				<div class="bandcamp-album-artwork">
					<img src="<?php echo esc_url( $data['album_art_medium'] ); ?>" alt="<?php /* translators: %s: Bandcamp album title. */ echo esc_attr( sprintf( __( '%s Cover Art', 'mds-bandcamp-integration' ), $data['album_title'] ) ); ?>">
				</div>
				<?php endif; ?>
				<div class="bandcamp-album-titles">
					<div>
						<h3><i><?php echo esc_html( $display_album_title ? $data['album_title'] : '' ); ?></i></h3>
						<h4><?php echo esc_html( $display_album_artist ? $data['artist'] : '' ); ?></h4>
						<?php if ( $display_album_link ) : ?>
						<a href="<?php echo esc_url( $data['album_link'] ); ?>" class="album-link" target="_blank" rel="noopener noreferrer">
							<div>
								<?php echo esc_html__( 'Listen on Bandcamp', 'mds-bandcamp-integration' ); ?>
								<i class="fab fa-bandcamp" aria-hidden="true"></i>
							</div>
						</a>
						<?php endif; ?>
						<?php if ( $display_audio && 'bandcamp' === $embed_type ) : ?>
						<iframe src="<?php echo esc_url( 'https://bandcamp.com/EmbeddedPlayer/album=' . rawurlencode( (string) $data['album_id'] ) . '/size=small/bgcol=' . rawurlencode( str_replace( '#', '', $background_color ) ) . '/linkcol=' . rawurlencode( str_replace( '#', '', Utilities::get_contrast_color( $background_color ) ) ) . '/artwork=none/transparent=true/' ); ?>" seamless></iframe>
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
					<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
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
									<a target="_blank" rel="noopener noreferrer" class="album-link" href="<?php echo esc_url( $package['url'] ); ?>">
										<div>
											<?php echo esc_html__( 'Purchase on Bandcamp', 'mds-bandcamp-integration' ); ?>
											<i class="fab fa-bandcamp" aria-hidden="true"></i>
										</div>
									</a>
									<?php else : ?>
									<a target="_blank" rel="noopener noreferrer" class="album-link" disabled style="color:red !important;" href="<?php echo esc_url( $package['url'] ); ?>"><?php echo esc_html__( 'Sold Out', 'mds-bandcamp-integration' ); ?></a>
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
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the Bandcamp Discography component.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $attributes Component attributes.
	 * @param mixed                $block      Optional WP_Block instance.
	 * @param bool                 $is_block   Whether invoked from Gutenberg block render.php.
	 * @return string Rendered HTML.
	 */
	public static function render_discography( array $attributes, $block = null, bool $is_block = false ): string {
		if ( ! $is_block ) {
			self::enqueue_component_assets( 'bandcamp-discography' );
		} elseif ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::enqueue_font_awesome();
		}

		$attrs = BlockDefaults::resolve( 'bandcamp-discography', $attributes );

		if ( empty( $attrs['artistId'] ) && ! empty( $attrs['artist_id'] ) ) {
			$attrs['artistId'] = absint( $attrs['artist_id'] );
		}

		$anchor   = ! empty( $attrs['anchor'] ) ? esc_attr( (string) $attrs['anchor'] ) : esc_attr( 'wp-anchor-' . wp_rand() );
		$block_id = ! empty( $attrs['blockID'] ) ? esc_attr( (string) $attrs['blockID'] ) : esc_attr( 'wp-block-' . wp_rand() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gutenberg ServerSideRender context flag.
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];

		$artist_url        = Helpers::resolve_discography_subdomain( $attrs, $block );
		$button_color      = (string) ( $attrs['button_color'] ?? '#000000' );
		$button_text_color = (string) ( $attrs['button_text_color'] ?? '#ffffff' );
		$display_album_art = ! empty( $attrs['display_album_art'] );
		$display_artist    = ! empty( $attrs['display_album_artist'] );
		$display_link      = ! empty( $attrs['display_album_link'] );
		$display_title     = ! empty( $attrs['display_album_title'] );
		$layout_grid_gap   = (string) ( $attrs['layout_grid_gap'] ?? '2rem' );
		$text_color        = (string) ( $attrs['text_color'] ?? '#000000' );

		$wrapper_attributes = $is_block && function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'id' => $anchor ) )
			: 'id="' . $anchor . '" class="wp-block-mds-bandcamp-bandcamp-discography"';

		$allowed_border_styles = array( 'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset' );
		$allowed_text_aligns   = array( 'left', 'center', 'right', 'justify' );

		$release_border = '';
		if ( ! empty( $attrs['release_border'] ) && is_array( $attrs['release_border'] ) ) {
			$rb_color        = ! empty( $attrs['release_border']['color'] ) ? sanitize_hex_color( (string) $attrs['release_border']['color'] ) : '';
			$rb_style        = ! empty( $attrs['release_border']['style'] ) && in_array( $attrs['release_border']['style'], $allowed_border_styles, true ) ? $attrs['release_border']['style'] : '';
			$rb_width        = ! empty( $attrs['release_border']['width'] ) ? BlockDefaults::sanitize_css_unit( $attrs['release_border']['width'], '' ) : '';
			$rb_radius       = ! empty( $attrs['release_border']['radius'] ) ? BlockDefaults::sanitize_css_unit( $attrs['release_border']['radius'], '' ) : '';
			$release_border .= $rb_color ? 'border-color:' . $rb_color . ';' : '';
			$release_border .= $rb_style ? 'border-style:' . $rb_style . ';' : '';
			$release_border .= '' !== $rb_width ? 'border-width:' . $rb_width . ';' : '';
			$release_border .= '' !== $rb_radius ? 'border-radius:' . $rb_radius . ';' : '';
		}

		$release_titles_align   = isset( $attrs['release_titles_align'] ) && in_array( $attrs['release_titles_align'], $allowed_text_aligns, true ) ? $attrs['release_titles_align'] : 'left';
		$release_titles_padding = '';
		if ( ! empty( $attrs['release_titles_padding'] ) && is_array( $attrs['release_titles_padding'] ) ) {
			$release_titles_padding .= 'padding:'
				. BlockDefaults::sanitize_css_unit( $attrs['release_titles_padding']['top'] ?? '0' ) . ' '
				. BlockDefaults::sanitize_css_unit( $attrs['release_titles_padding']['right'] ?? '0' ) . ' '
				. BlockDefaults::sanitize_css_unit( $attrs['release_titles_padding']['bottom'] ?? '0' ) . ' '
				. BlockDefaults::sanitize_css_unit( $attrs['release_titles_padding']['left'] ?? '0' ) . ';';
		}

		$release_image_padding = '';
		if ( ! empty( $attrs['release_image_padding'] ) && is_array( $attrs['release_image_padding'] ) ) {
			$release_image_padding .= 'padding:'
				. BlockDefaults::sanitize_css_unit( $attrs['release_image_padding']['top'] ?? '0' ) . ' '
				. BlockDefaults::sanitize_css_unit( $attrs['release_image_padding']['right'] ?? '0' ) . ' '
				. BlockDefaults::sanitize_css_unit( $attrs['release_image_padding']['bottom'] ?? '0' ) . ' '
				. BlockDefaults::sanitize_css_unit( $attrs['release_image_padding']['left'] ?? '0' ) . ';';
		}

		$block_id      = sanitize_html_class( $block_id );
		$safe_grid_gap = BlockDefaults::sanitize_css_unit( $layout_grid_gap, '2rem' );
		$safe_text     = sanitize_hex_color( $text_color );
		$safe_btn      = sanitize_hex_color( $button_color );
		$safe_btn_text = sanitize_hex_color( $button_text_color );

		$css = "
#{$block_id} .bandcamp-release {" . ( '' !== $release_border ? $release_border : 'border:1px solid #ddd;' ) . "}
#{$block_id} .bandcamp-release h5,
#{$block_id} .bandcamp-release h6,
#{$block_id} .bandcamp-release p,
#{$block_id} .bandcamp-release small,
#{$block_id} .bandcamp-release span,
#{$block_id} .bandcamp-release a {color:" . ( $safe_text ? $safe_text : 'inherit' ) . ";}
#{$block_id} .bandcamp-release a.bandcamp-release-button {background-color:" . ( $safe_btn ? $safe_btn : 'transparent' ) . ';color:' . ( $safe_btn_text ? $safe_btn_text : 'inherit' ) . " !important;}
#{$block_id} .bandcamp-release a.bandcamp-release-button div,
#{$block_id} .bandcamp-release a.bandcamp-release-button .fab {color:" . ( $safe_btn_text ? $safe_btn_text : 'inherit' ) . " !important;}
#{$block_id} .bandcamp-release .bandcamp-release-image {" . ( '' !== $release_image_padding ? $release_image_padding : 'padding:1rem;' ) . "}
#{$block_id} .bandcamp-release .bandcamp-release-titles {" . ( '' !== $release_titles_padding ? $release_titles_padding : 'padding:1rem;' ) . "text-align:{$release_titles_align};}
#{$block_id} .bandcamp-grid {grid-gap:{$safe_grid_gap};}
";

		ob_start();
		echo '<style>' . wp_kses( $css, array() ) . '</style>';

		$data = BandcampApi::fetch(
			array(
				'endpoint' => 'discography',
				'id'       => $artist_url,
			)
		);

		if ( ! empty( $data['error'] ) ) {
			echo wp_kses( BandcampApi::render_error_html( $data ), 'post' );
			return (string) ob_get_clean();
		}

		if ( empty( $data ) ) {
			echo wp_kses(
				BandcampApi::render_error_html(
					array(
						'error'   => true,
						'message' => __( 'BANDCAMP URL IS NOT VALID', 'mds-bandcamp-integration' ),
					)
				),
				'post'
			);
			return (string) ob_get_clean();
		}

		if ( $is_editor && ! empty( $data['cached'] ) ) {
			echo '<span style="position:absolute;z-index:99;top:10px;right:10px;background:#1da0c3;color:#fff;font-size:10px;padding:3px 10px;border-radius:20px;display:inline-block;">' . esc_html__( 'CACHE', 'mds-bandcamp-integration' ) . '</span>';
		}

		$cols        = max( 1, min( 6, absint( $attrs['layout_columns'] ?? 3 ) ) );
		$cols_mobile = max( 1, min( 6, absint( $attrs['layout_columns_mobile'] ?? 2 ) ) );
		?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : '' ); ?>>
		<?php if ( ! empty( $data['data']['releases'] ) && is_array( $data['data']['releases'] ) ) : ?>
	<section id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-discography">
		<div class="bandcamp-grid bandcamp-columns-<?php echo esc_attr( (string) $cols ); ?> bandcamp-columns-mobile-<?php echo esc_attr( (string) $cols_mobile ); ?>">
			<?php foreach ( $data['data']['releases'] as $release ) : ?>
			<div class="bandcamp-release">
				<?php if ( $display_album_art ) : ?>
				<div class="bandcamp-release-image">
					<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $release['link'] ); ?>">
						<img loading="lazy" src="<?php echo esc_url( $release['image'] ); ?>" alt="<?php /* translators: %s: Bandcamp release title. */ echo esc_attr( sprintf( __( '%s Cover Art', 'mds-bandcamp-integration' ), $release['title'] ) ); ?>">
					</a>
				</div>
				<?php endif; ?>
				<div class="bandcamp-release-titles">
					<h5><?php echo esc_html( $display_artist ? $release['artist'] : '' ); ?></h5>
					<h6><i><?php echo esc_html( $display_title ? $release['title'] : '' ); ?></i></h6>
					<?php if ( $display_link ) : ?>
					<a href="<?php echo esc_url( $release['link'] ); ?>" class="bandcamp-release-button" target="_blank" rel="noopener noreferrer">
						<div>
							<?php echo esc_html__( 'Listen on Bandcamp', 'mds-bandcamp-integration' ); ?>
							<i class="fab fa-bandcamp" aria-hidden="true"></i>
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
		return (string) ob_get_clean();
	}

	/**
	 * Render the Bandcamp Miniplayer component.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $attributes Component attributes.
	 * @param mixed                $block      Optional WP_Block instance.
	 * @param bool                 $is_block   Whether invoked from Gutenberg block render.php.
	 * @return string Rendered HTML.
	 */
	public static function render_miniplayer( array $attributes, $block = null, bool $is_block = false ): string {
		if ( ! $is_block ) {
			self::enqueue_component_assets( 'bandcamp-miniplayer' );
		} elseif ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::enqueue_font_awesome();
		}

		$attrs = BlockDefaults::resolve( 'bandcamp-miniplayer', $attributes );

		$anchor   = ! empty( $attrs['anchor'] ) ? esc_attr( (string) $attrs['anchor'] ) : esc_attr( 'wp-anchor-' . wp_rand() );
		$block_id = ! empty( $attrs['blockID'] ) ? esc_attr( (string) $attrs['blockID'] ) : esc_attr( 'wp-block-' . wp_rand() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gutenberg ServerSideRender context flag.
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];

		$target   = self::resolve_album_target( $attrs, $block );
		$album_id = $target['album_id'];

		$art_opacity          = isset( $attrs['artOpacity'] ) && is_numeric( $attrs['artOpacity'] ) ? (float) $attrs['artOpacity'] : 0.5;
		$art_opacity_hover    = isset( $attrs['artOpacityHover'] ) && is_numeric( $attrs['artOpacityHover'] ) ? (float) $attrs['artOpacityHover'] : 0.2;
		$aspect_ratio_height  = $attrs['aspectRatioHeight'] ?? 1;
		$aspect_ratio_width   = $attrs['aspectRatioWidth'] ?? 1;
		$background_color     = (string) ( $attrs['backgroundColor'] ?? '#171717' );
		$button_color         = (string) ( $attrs['buttonColor'] ?? '#000000' );
		$display_album_artist = ! empty( $attrs['display_album_artist'] );
		$display_album_title  = ! empty( $attrs['display_album_title'] );
		$display_album_link   = ! empty( $attrs['display_album_link'] );
		$display_progress_bar = ! empty( $attrs['display_progress_bar'] );
		$display_track_title  = ! empty( $attrs['display_track_title'] );
		$progress_bar_color   = (string) ( $attrs['progressBarColor'] ?? '#ffffff' );
		$text_color           = (string) ( $attrs['textColor'] ?? '#ffffff' );
		$track_number         = $attrs['trackNumber'] ?? '1';

		$wrapper_attributes = $is_block && function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'id' => $anchor ) )
			: 'id="' . $anchor . '" class="wp-block-mds-bandcamp-bandcamp-miniplayer"';

		$css_display_progress_bar = $display_progress_bar ? 'block' : 'none';
		$css_width_progress_bar   = $is_editor ? '50%' : '0';
		$block_id                 = sanitize_html_class( $block_id );

		$safe_bg       = sanitize_hex_color( $background_color );
		$safe_btn      = sanitize_hex_color( $button_color );
		$safe_text     = sanitize_hex_color( $text_color );
		$safe_progress = sanitize_hex_color( $progress_bar_color );

		$css = "
#{$block_id}.bandcamp-miniplayer {background-color:" . ( $safe_bg ? $safe_bg : 'transparent' ) . ';aspect-ratio:' . max( 1, absint( $aspect_ratio_width ) ) . '/' . max( 1, absint( $aspect_ratio_height ) ) . ";}
#{$block_id}.bandcamp-miniplayer .bandcamp-miniplayer-play svg path {fill:" . ( $safe_btn ? $safe_btn : 'currentColor' ) . " !important;}
#{$block_id}.bandcamp-miniplayer .bandcamp-miniplayer-art {opacity:" . $art_opacity . ";}
#{$block_id}.bandcamp-miniplayer:hover .bandcamp-miniplayer-art {opacity:" . $art_opacity_hover . ";}
#{$block_id}.bandcamp-miniplayer h3,
#{$block_id}.bandcamp-miniplayer h4,
#{$block_id}.bandcamp-miniplayer p {color:" . ( $safe_text ? $safe_text : 'inherit' ) . ";padding:5px 0;}
#{$block_id}.bandcamp-miniplayer .bandcamp-miniplayer-progress {background:" . ( $safe_progress ? $safe_progress : 'transparent' ) . ';display:' . esc_attr( $css_display_progress_bar ) . ';width:' . esc_attr( $css_width_progress_bar ) . ';}
';

		ob_start();
		echo '<style>' . wp_kses( $css, array() ) . '</style>';

		if ( '' === $album_id ) {
			echo wp_kses(
				BandcampApi::render_notice_html(
					array(
						'error'   => true,
						'message' => __( 'BANDCAMP ALBUM ID REQUIRED', 'mds-bandcamp-integration' ),
					)
				),
				'post'
			);
			return (string) ob_get_clean();
		}

		$data = BandcampApi::fetch(
			array(
				'endpoint' => 'album',
				'id'       => $album_id,
			)
		);

		if ( ! empty( $data['error'] ) ) {
			echo wp_kses( BandcampApi::render_error_html( $data ), 'post' );
			return (string) ob_get_clean();
		}

		if ( empty( $data ) ) {
			echo wp_kses(
				BandcampApi::render_error_html(
					array(
						'error'   => true,
						'message' => __( 'BANDCAMP URL IS NOT VALID', 'mds-bandcamp-integration' ),
					)
				),
				'post'
			);
			return (string) ob_get_clean();
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
					$data['featured_track_mp3']    = $track['mp3'] ?? '';
					$data['featured_track_title']  = $track['title'] ?? '';
					$data['featured_track_artist'] = $track['artist'] ?? $data['featured_track_artist'];
				} elseif ( empty( $track_number ) && (string) ( $data['featured_track_id'] ?? '' ) === (string) $track['id'] ) {
					$data['featured_track_mp3']    = $track['mp3'] ?? '';
					$data['featured_track_title']  = $track['title'] ?? '';
					$data['featured_track_artist'] = $track['artist'] ?? $data['featured_track_artist'];
				}
			}
		}
		?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : '' ); ?>>
	<section id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-miniplayer">
		<audio id="<?php echo esc_attr( $block_id . '-audio' ); ?>" preload="auto" tabindex="0" controls="" type="audio/mpeg" style="display:none">
			<source type="audio/mp3" src="<?php echo esc_url( $data['featured_track_mp3'] ); ?>">
			<?php echo esc_html__( 'Sorry, your browser does not support HTML5 audio.', 'mds-bandcamp-integration' ); ?>
		</audio>
		<button type="button" class="bandcamp-miniplayer-play" aria-label="<?php echo esc_attr__( 'Play Track', 'mds-bandcamp-integration' ); ?>" aria-controls="<?php echo esc_attr( $block_id . '-audio' ); ?>" data-play-label="<?php echo esc_attr__( 'Play Track', 'mds-bandcamp-integration' ); ?>" data-pause-label="<?php echo esc_attr__( 'Pause Track', 'mds-bandcamp-integration' ); ?>" aria-pressed="false">
			<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800">
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
			<a href="<?php echo esc_url( $data['album_link'] ); ?>" class="album-link" target="_blank" rel="noopener noreferrer">
				<div>
					<?php echo esc_html__( 'Listen on Bandcamp', 'mds-bandcamp-integration' ); ?>
					<i class="fab fa-bandcamp" aria-hidden="true"></i>
				</div>
			</a>
			<?php endif; ?>
		</div>
		<div class="bandcamp-miniplayer-progress-wrap" role="slider" tabindex="0" aria-label="<?php echo esc_attr__( 'Seek audio', 'mds-bandcamp-integration' ); ?>" aria-controls="<?php echo esc_attr( $block_id . '-audio' ); ?>" aria-orientation="horizontal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="<?php echo esc_attr__( '00:00 / 00:00', 'mds-bandcamp-integration' ); ?>">
			<div class="bandcamp-miniplayer-progress-buffer"></div>
			<div class="bandcamp-miniplayer-progress"></div>
		</div>
	</section>
</div>
		<?php
		return (string) ob_get_clean();
	}
}
