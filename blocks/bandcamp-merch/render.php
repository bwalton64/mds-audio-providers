<?php
/**
 * Server-side rendering for the Bandcamp merch block.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function ( array $attributes, $block ): void {
	$anchor   = ! empty( $attributes['anchor'] ) ? esc_attr( (string) $attributes['anchor'] ) : esc_attr( 'wp-block-' . wp_rand() );
	$block_id = ! empty( $attributes['blockID'] ) ? esc_attr( (string) $attributes['blockID'] ) : esc_attr( 'wp-block-' . wp_rand() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gutenberg ServerSideRender context flag.
	$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];

	$album_id                        = $attributes['albumID'] ?? false;
	$display_album_artist            = $attributes['display_album_artist'] ?? true;
	$display_album_title             = $attributes['display_album_title'] ?? true;
	$display_description             = $attributes['display_description'] ?? true;
	$display_edition_size            = ! empty( $attributes['display_edition_size'] );
	$display_media_type              = ! empty( $attributes['display_media_type'] );
	$display_photos                  = $attributes['display_photos'] ?? true;
	$display_price                   = ! empty( $attributes['display_price'] );
	$display_purchase_button         = $attributes['display_purchase_button'] ?? true;
	$display_quantity_available      = $attributes['display_quantity_available'] ?? true;
	$display_sold_out_items          = $attributes['display_sold_out_items'] ?? true;
	$layout_style                    = $attributes['layout_style'] ?? 'cards';
	$layout_columns                  = $attributes['layout_columns'] ?? 3;
	$photo_version                   = $attributes['photo_version'] ?? 'cropped';
	$single_or_all                   = $attributes['single_or_all'] ?? 'single';
	$style_purchase_button_bgcolor   = $attributes['style_purchase_button_bgcolor'] ?? '#111111';
	$style_purchase_button_textcolor = $attributes['style_purchase_button_textcolor'] ?? '#ffffff';
	$url                             = $attributes['url'] ?? false;

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
			'endpoint' => 'merch',
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
	?>
<div <?php echo wp_kses_post( ! $is_editor ? $wrapper_attributes : '' ); ?>>
	<section id="<?php echo esc_attr( $block_id ); ?>" class="bandcamp-merch">
		<?php $package_exists = false; ?>
		<?php if ( ! empty( $data['packages'] ) && is_array( $data['packages'] ) ) : ?>
		<ul class="bandcamp-grid bandcamp-merch-layout-<?php echo esc_attr( (string) $layout_style ); ?> bandcamp-columns-<?php echo esc_attr( (string) $layout_columns ); ?>">
			<?php
			$package_index = 0;
			foreach ( $data['packages'] as $package ) :
				if ( ! $display_sold_out_items && $package['quantity_available'] < 1 && null !== $package['quantity_available'] ) :
					++$package_index;
					continue;
				endif;

				if ( 'all' === $single_or_all || ( 'single' === $single_or_all && (string) $package['album_id'] === (string) $data['album_id'] ) ) :
					$package_exists = true;
					$primary_large  = ! empty( $package['photos'][0]['large'] ) ? $package['photos'][0]['large'] : ( $data['album_art_large'] ?? '' );
					$primary_medium = ! empty( $package['photos'][0]['medium'] ) ? $package['photos'][0]['medium'] : ( $data['album_art_medium'] ?? '' );
					$primary_src    = 'uncropped' === $photo_version ? $primary_large : $primary_medium;
					?>
					<li>
						<div class="bandcamp-merch-photos">
							<?php if ( ! empty( $primary_src ) ) : ?>
							<div class="bandcamp-merch-photos-main">
								<?php if ( $display_purchase_button ) : ?>
								<a href="<?php echo esc_url( $primary_large ? $primary_large : $primary_src ); ?>" class="bandcamp-lightbox" data-bandcamp-gallery="<?php echo esc_attr( $package['album_title'] ? $package['album_title'] . '-' . $package_index : $package['title'] . '-' . $package_index ); ?>">
									<img loading="lazy" src="<?php echo esc_url( $primary_src ); ?>" alt="<?php echo esc_attr( $package['album_title'] ?? $package['title'] ); ?>">
								</a>
								<?php else : ?>
								<a href="<?php echo esc_url( $package['url'] ); ?>" title="<?php echo esc_attr__( 'View Item', 'mds-bandcamp-integration' ); ?>" target="_blank" rel="noopener noreferrer">
									<img loading="lazy" src="<?php echo esc_url( $primary_src ); ?>" alt="<?php echo esc_attr( $package['album_title'] ?? $package['title'] ); ?>">
								</a>
								<?php endif; ?>
							</div>
							<?php endif; ?>

							<?php if ( $display_photos && ! empty( $package['photos'] ) && count( $package['photos'] ) > 1 ) : ?>
							<div class="bandcamp-merch-photos-gallery">
								<?php foreach ( $package['photos'] as $photo ) : ?>
								<a href="<?php echo esc_url( $photo['large'] ); ?>" target="_blank" rel="noopener noreferrer" class="bandcamp-lightbox" data-bandcamp-gallery="<?php echo esc_attr( $package['album_title'] ? $package['album_title'] . '-' . $package_index : $package['title'] . '-' . $package_index ); ?>">
									<img loading="lazy" src="<?php echo esc_url( $photo['small'] ); ?>" alt="<?php echo esc_attr( $package['album_title'] ?? $package['title'] ); ?>">
								</a>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>
						</div>

						<div class="bandcamp-merch-information">
							<?php if ( $display_album_title ) : ?>
							<h3><?php echo esc_html( $package['album_title'] ?? $package['title'] ); ?></h3>
							<?php endif; ?>

							<?php if ( $display_album_artist && ! empty( $package['album_artist'] ) ) : ?>
							<h4><?php echo esc_html( $package['album_artist'] ); ?></h4>
							<?php endif; ?>

							<span>
								<?php echo esc_html( $package['title'] ); ?>
								<?php echo esc_html( $display_price ? ' | $' . $package['price'] : '' ); ?>
							</span>

							<?php if ( $display_description && ! empty( $package['description'] ) ) : ?>
							<p><i><?php echo esc_html( $package['description'] ); ?></i></p>
							<?php endif; ?>

							<small>
								<strong>
								<?php echo esc_html( $display_media_type ? $package['type'] : '' ); ?>
								<?php
								if ( $display_edition_size && $package['edition_size'] && $display_media_type ) :
									echo esc_html( __( ' | Edition of ', 'mds-bandcamp-integration' ) ) . esc_html( (string) $package['edition_size'] );
								elseif ( $display_edition_size && $package['edition_size'] && ! $display_media_type ) :
									echo esc_html( __( 'Edition of ', 'mds-bandcamp-integration' ) ) . esc_html( (string) $package['edition_size'] );
								endif;
								?>
								</strong>
							</small>

							<?php if ( $display_purchase_button ) : ?>
							<div>
								<?php if ( $package['quantity_available'] > 0 || null === $package['quantity_available'] ) : ?>
								<a target="_blank" class="bandcamp-merch-button" href="<?php echo esc_url( $package['url'] ); ?>" style="background-color:<?php echo esc_attr( (string) $style_purchase_button_bgcolor ); ?>;color:<?php echo esc_attr( (string) $style_purchase_button_textcolor ); ?>">
									<?php echo esc_html__( 'Purchase on Bandcamp', 'mds-bandcamp-integration' ); ?>
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
										<!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
										<path fill="<?php echo esc_attr( (string) $style_purchase_button_textcolor ); ?>" d="M320 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l82.7 0L201.4 265.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L448 109.3l0 82.7c0 17.7 14.3 32 32 32s32-14.3 32-32l0-160c0-17.7-14.3-32-32-32L320 0zM80 32C35.8 32 0 67.8 0 112L0 432c0 44.2 35.8 80 80 80l320 0c44.2 0 80-35.8 80-80l0-112c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 112c0 8.8-7.2 16-16 16L80 448c-8.8 0-16-7.2-16-16l0-320c0-8.8 7.2-16 16-16l112 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L80 32z"/>
									</svg>
								</a>
								<?php else : ?>
								<a target="_blank" class="bandcamp-merch-button sold-out" href="<?php echo esc_url( $package['url'] ); ?>"><?php echo esc_html__( 'Sold Out', 'mds-bandcamp-integration' ); ?></a>
								<?php endif; ?>
							</div>
							<?php endif; ?>

							<?php if ( $display_quantity_available && ! empty( $package['quantity_available'] ) ) : ?>
								<div><?php echo esc_html( (string) $package['quantity_available'] ) . ' ' . esc_html__( 'Remaining', 'mds-bandcamp-integration' ); ?></div>
							<?php endif; ?>
						</div>
					</li>
					<?php
				endif;
				++$package_index;
			endforeach;
			?>
		</ul>
		<?php endif; ?>

		<?php if ( false === $package_exists ) : ?>
			<div class="bandcamp-block-message bandcamp-block-error">
				<svg version="1.1" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" xml:space="preserve">
					<path d="M10.1,8.9l-3.3,6h7l3.3-6H10.1z M21.6,11.9c0-5.3-4.3-9.6-9.6-9.6s-9.6,4.3-9.6,9.6s4.3,9.6,9.6,9.6 S21.6,17.2,21.6,11.9z M12,20.9c-5,0-9-4-9-9s4-9,9-9s9,4,9,9S17,20.9,12,20.9z"/>
				</svg>
				<span>
					<?php
					if ( 'all' === $single_or_all ) :
						echo esc_html__( 'NO MERCH ITEMS FOR THIS ACCOUNT', 'mds-bandcamp-integration' );
					else :
						echo esc_html__( 'NO MERCH ITEMS FOR THIS ALBUM', 'mds-bandcamp-integration' );
					endif;
					?>
				</span>
			</div>
		<?php endif; ?>
	</section>
</div>
	<?php
} )( is_array( $attributes ?? null ) ? $attributes : array(), $block ?? null );
