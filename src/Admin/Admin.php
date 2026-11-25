<?php
/**
 * Release meta box, Bandcamp URL verification, "Use Bandcamp URL" Commerce button,
 * and SoundCloud URL field for MDS Audio Providers.
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Admin;

use DOMDocument;
use DOMNode;
use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Core\Dependencies;
use MDS\AudioProviders\Providers\BandcampProvider;
use MDS\AudioProviders\Providers\SoundCloudProvider;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the Audio Providers Release metabox and Bandcamp Commerce button.
 *
 * Preserves legacy Bandcamp meta keys (`mds_bandcamp_integration`, `bandcamp_album_id`,
 * `bandcamp_album_url`) so existing releases require zero migration.
 *
 * @since 2.0.0
 */
final class Admin {

	/**
	 * Registers metabox, save, media-format, and AJAX verification hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'mds_rm_register_custom_meta_boxes', array( self::class, 'register_meta_box' ) );
		add_action( 'mds_rm_save_release_meta', array( self::class, 'save_release_meta' ), 10, 2 );
		add_action( 'mds_rm_after_buy_now_field', array( self::class, 'render_buy_now_button' ) );
		add_filter( 'mds_rm_default_media_formats', array( self::class, 'register_default_media_formats' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_metabox_assets' ) );
		add_action( 'wp_ajax_mds_bc_verify_url', array( self::class, 'ajax_verify_url' ) );
	}

	/**
	 * Registers the "Digital Download" default media format with Core.
	 *
	 * @param array<string, array{label: string, icon: string}> $formats Default media format registry.
	 * @return array<string, array{label: string, icon: string}> Updated registry.
	 */
	public static function register_default_media_formats( array $formats ): array {
		if ( ! isset( $formats['digital-download'] ) ) {
			$formats['digital-download'] = array(
				'label' => __( 'Digital Download', 'mds-audio-providers' ),
				'icon'  => 'fas fa-download',
			);
		}
		return $formats;
	}

	/**
	 * Outputs the "Use Bandcamp URL" helper button next to the Release Manager Buy Now repeater controls.
	 *
	 * @return void
	 */
	public static function render_buy_now_button(): void {
		if ( ! Dependencies::is_release_manager_active() ) {
			return;
		}

		echo '<button type="button" class="button button-secondary" id="mds_use_bandcamp_url_btn" disabled>'
			. esc_html__( 'Use Bandcamp URL', 'mds-audio-providers' )
			. '</button>';
	}

	/**
	 * Registers the Audio Providers meta box on Release posts.
	 *
	 * @param WP_Post|null $post Post being edited.
	 * @return void
	 */
	public static function register_meta_box( ?WP_Post $post ): void {
		if ( ! Dependencies::is_release_manager_active() || ! $post || 'release' !== $post->post_type ) {
			return;
		}

		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) && ! \MDS\ReleaseManager\Api\ExtensionApi::is_hub() ) {
			return;
		}

		add_meta_box(
			'mds_ap_release_providers',
			__( 'Audio Providers (Bandcamp & SoundCloud)', 'mds-audio-providers' ),
			array( self::class, 'render_meta_box' ),
			'release',
			'normal',
			'default'
		);
	}

	/**
	 * Renders the Audio Providers Release metabox.
	 *
	 * @param WP_Post $post Release post object.
	 * @return void
	 */
	public static function render_meta_box( WP_Post $post ): void {
		$bc_url         = BandcampProvider::get_album_url( (int) $post->ID );
		$bc_album_id    = BandcampProvider::get_album_id( (int) $post->ID );
		$soundcloud_url = SoundCloudProvider::get_soundcloud_url( (int) $post->ID );

		wp_nonce_field( 'mds_ap_save_release', 'mds_ap_nonce' );
		?>
		<div class="mds-editor-box">
			<div class="mds-section-info" role="note">
				<span class="dashicons dashicons-info-outline mds-section-info__icon" aria-hidden="true"></span>
				<div class="mds-section-info__body">
					<strong class="mds-section-info__title"><?php esc_html_e( 'Automatic & Provider-Specific Audio Sources', 'mds-audio-providers' ); ?></strong>
					<p class="mds-section-info__summary">
						<?php esc_html_e( 'Apple Music, Deezer, and Spotify resolve automatically from this Release’s Barcode (UPC/EAN). Configure Bandcamp or SoundCloud below to enable those providers for this release.', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>

			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'Bandcamp', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-grid-2col">
					<div class="mds-style-field">
						<label class="mds-style-field__label" for="mds_bc_url"><?php esc_html_e( 'Bandcamp Release URL', 'mds-audio-providers' ); ?></label>
						<div class="mds-buy-now-actions">
							<input
								type="url"
								id="mds_bc_url"
								name="mds_bc_url"
								value="<?php echo esc_attr( $bc_url ); ?>"
								class="regular-text"
								placeholder="https://artist.bandcamp.com/album/release-name"
							/>
							<button type="button" id="mds_bc_verify_btn" class="button button-secondary">
								<?php esc_html_e( 'Verify URL', 'mds-audio-providers' ); ?>
							</button>
						</div>
						<p id="mds_bc_status" class="mds-style-field__help" aria-live="polite"></p>
					</div>
					<div class="mds-style-field">
						<label class="mds-style-field__label" for="mds_bc_album_id"><?php esc_html_e( 'Bandcamp Album ID', 'mds-audio-providers' ); ?></label>
						<input
							type="number"
							id="mds_bc_album_id"
							name="mds_bc_album_id"
							value="<?php echo $bc_album_id > 0 ? esc_attr( (string) $bc_album_id ) : ''; ?>"
							class="regular-text"
							readonly
						/>
						<p class="mds-style-field__help">
							<?php esc_html_e( 'Populated automatically when you verify a Bandcamp album URL.', 'mds-audio-providers' ); ?>
						</p>
					</div>
				</div>
			</div>

			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'SoundCloud', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-field">
					<label class="mds-style-field__label" for="mds_ap_soundcloud_url"><?php esc_html_e( 'SoundCloud Track / Playlist URL', 'mds-audio-providers' ); ?></label>
					<input
						type="url"
						id="mds_ap_soundcloud_url"
						name="mds_ap_soundcloud_url"
						value="<?php echo esc_attr( $soundcloud_url ); ?>"
						class="regular-text"
						placeholder="https://soundcloud.com/artist/track-or-set"
					/>
					<p class="mds-style-field__help">
						<?php esc_html_e( 'Optional. Requires SoundCloud API credentials under Releases → Settings → Audio Providers.', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves Bandcamp and SoundCloud meta on `mds_rm_save_release_meta`.
	 *
	 * @param int     $post_id Release post ID.
	 * @param WP_Post $post    Release post object.
	 * @return void
	 */
	public static function save_release_meta( int $post_id, WP_Post $post ): void {
		unset( $post );
		if ( ! isset( $_POST['mds_ap_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['mds_ap_nonce'] ) ), 'mds_ap_save_release' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save Bandcamp meta (preserving legacy keys for backward compatibility).
		$album_id = isset( $_POST['mds_bc_album_id'] ) ? absint( wp_unslash( (string) $_POST['mds_bc_album_id'] ) ) : 0;
		$bc_url   = isset( $_POST['mds_bc_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['mds_bc_url'] ) ) : '';

		if ( $album_id > 0 ) {
			update_post_meta(
				$post_id,
				'mds_bandcamp_integration',
				array(
					'url'      => $bc_url,
					'album_id' => $album_id,
					'saved_at' => time(),
				)
			);
			update_post_meta( $post_id, 'bandcamp_album_id', (string) $album_id );
			update_post_meta( $post_id, 'bandcamp_album_url', $bc_url );
			Cache::delete( 'bandcamp', (string) $album_id );
		} else {
			delete_post_meta( $post_id, 'mds_bandcamp_integration' );
			delete_post_meta( $post_id, 'bandcamp_album_id' );
			delete_post_meta( $post_id, 'bandcamp_album_url' );
		}

		// Save SoundCloud URL.
		$sc_url = isset( $_POST['mds_ap_soundcloud_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['mds_ap_soundcloud_url'] ) ) : '';
		if ( '' !== $sc_url ) {
			update_post_meta( $post_id, 'mds_ap_soundcloud_url', $sc_url );
			Cache::delete( 'soundcloud', $sc_url );
		} else {
			delete_post_meta( $post_id, 'mds_ap_soundcloud_url' );
		}
	}

	/**
	 * Enqueues the metabox verification and "Use Bandcamp URL" script on Release edit screens.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public static function enqueue_metabox_assets( string $hook_suffix ): void {
		global $post;

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! $post || 'release' !== $post->post_type ) {
			return;
		}

		$script_rel = 'js/metabox-verify.js';
		wp_enqueue_script(
			'mds-bc-metabox',
			MDS_AP_URL . $script_rel,
			array( 'jquery', 'wp-a11y' ),
			file_exists( MDS_AP_PATH . $script_rel ) ? (string) filemtime( MDS_AP_PATH . $script_rel ) : MDS_AP_VERSION,
			true
		);
		wp_set_script_translations( 'mds-bc-metabox', 'mds-audio-providers' );

		wp_add_inline_script(
			'mds-bc-metabox',
			'const mdsBcMetabox = ' . wp_json_encode(
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'mds_bc_verify_nonce' ),
					'messages' => array(
						'storeName'            => __( 'Bandcamp', 'mds-audio-providers' ),
						'digitalDownloadLabel' => __( 'Digital Download', 'mds-audio-providers' ),
						'buyNowAdded'          => __( 'Added Bandcamp URL to Buy Now formats.', 'mds-audio-providers' ),
						'buyNowExists'         => __( 'This Bandcamp URL is already listed in Buy Now.', 'mds-audio-providers' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * AJAX handler for verifying a Bandcamp Release URL and extracting its Album ID.
	 *
	 * @return void
	 */
	public static function ajax_verify_url(): void {
		check_ajax_referer( 'mds_bc_verify_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mds-audio-providers' ) ) );
		}

		$raw_url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['url'] ) ) : '';
		$url     = esc_url_raw( $raw_url );
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'mds-audio-providers' ) ) );
		}

		$html = BandcampProvider::fetch_page_html( $url );
		if ( is_array( $html ) && ! empty( $html['error'] ) ) {
			wp_send_json_error( array( 'message' => $html['message'] ?? __( 'Failed to fetch Bandcamp page.', 'mds-audio-providers' ) ) );
		}

		$album_id = self::extract_album_id( (string) $html );
		if ( ! $album_id || $album_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Could not find a Bandcamp Album ID on this page.', 'mds-audio-providers' ) ) );
		}

		wp_send_json_success( array( 'album_id' => $album_id ) );
	}

	/**
	 * Extracts the numeric Bandcamp Album ID from page HTML.
	 *
	 * @param string $html Bandcamp page HTML.
	 * @return int|false Positive album ID, or false.
	 */
	private static function extract_album_id( string $html ): int|false {
		if ( '' === $html ) {
			return false;
		}
		if ( preg_match( '/<!--\s*album\s+id\s+(\d+)\s*-->/i', $html, $matches ) ) {
			return absint( $matches[1] );
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( $html );
		libxml_use_internal_errors( false );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMDocument property.
		foreach ( $dom->childNodes as $node ) {
			$found = self::find_album_id_comment( $node );
			if ( $found ) {
				return $found;
			}
		}

		foreach ( $dom->getElementsByTagName( 'meta' ) as $meta ) {
			if ( 'album_id' === $meta->getAttribute( 'name' ) || 'albumId' === $meta->getAttribute( 'property' ) ) {
				$id = absint( $meta->getAttribute( 'content' ) );
				if ( $id > 0 ) {
					return $id;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively searches DOM comment nodes for a Bandcamp album ID.
	 *
	 * @param DOMNode $node DOM node.
	 * @return int|false Album ID or false.
	 */
	private static function find_album_id_comment( DOMNode $node ): int|false {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMNode property.
		if ( XML_COMMENT_NODE === $node->nodeType && preg_match( '/album\s+id\s+(\d+)/i', (string) $node->nodeValue, $matches ) ) {
			$id = absint( $matches[1] );
			if ( $id > 0 ) {
				return $id;
			}
		}
		if ( $node->hasChildNodes() ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMNode property.
			foreach ( $node->childNodes as $child ) {
				$found = self::find_album_id_comment( $child );
				if ( $found ) {
					return $found;
				}
			}
		}
		return false;
	}
}
