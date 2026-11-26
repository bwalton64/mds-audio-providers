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
use MDS\AudioProviders\Providers\AudiomackProvider;
use MDS\AudioProviders\Providers\AudiusProvider;
use MDS\AudioProviders\Providers\BandcampProvider;
use MDS\AudioProviders\Providers\FunkwhaleProvider;
use MDS\AudioProviders\Providers\JamendoProvider;
use MDS\AudioProviders\Providers\MixcloudProvider;
use MDS\AudioProviders\Providers\SoundCloudProvider;
use MDS\AudioProviders\Providers\TidalProvider;
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
			__( 'Audio Providers', 'mds-audio-providers' ),
			array( self::class, 'render_meta_box' ),
			'release',
			'normal',
			'default'
		);
	}

	/**
	 * Checks whether a provider slug is currently enabled in Release Manager audio settings.
	 *
	 * @param string $slug Provider slug.
	 * @return bool True when enabled (not listed in `mds_rm_audio_disabled_providers`).
	 */
	public static function is_provider_enabled( string $slug ): bool {
		$disabled = function_exists( 'get_option' ) ? get_option( 'mds_rm_audio_disabled_providers', array() ) : array();
		if ( ! is_array( $disabled ) ) {
			return true;
		}
		return ! in_array( sanitize_key( $slug ), $disabled, true );
	}

	/**
	 * Renders the Audio Providers Release metabox, showing only enabled URL-based providers.
	 *
	 * @param WP_Post $post Release post object.
	 * @return void
	 */
	public static function render_meta_box( WP_Post $post ): void {
		$post_id        = (int) $post->ID;
		$bc_enabled     = self::is_provider_enabled( 'bandcamp' );
		$sc_enabled     = self::is_provider_enabled( 'soundcloud' );
		$audius_enabled = self::is_provider_enabled( 'audius' );
		$jm_enabled     = self::is_provider_enabled( 'jamendo' );
		$fw_enabled     = self::is_provider_enabled( 'funkwhale' );
		$tidal_enabled  = self::is_provider_enabled( 'tidal' );
		$mc_enabled     = self::is_provider_enabled( 'mixcloud' );
		$am_enabled     = self::is_provider_enabled( 'audiomack' );

		$bc_url         = BandcampProvider::get_album_url( $post_id );
		$bc_album_id    = BandcampProvider::get_album_id( $post_id );
		$soundcloud_url = SoundCloudProvider::get_soundcloud_url( $post_id );
		$audius_url     = AudiusProvider::get_audius_url( $post_id );
		$jamendo_url    = JamendoProvider::get_jamendo_url( $post_id );
		$funkwhale_url  = FunkwhaleProvider::get_funkwhale_url( $post_id );
		$tidal_url      = TidalProvider::get_tidal_url( $post_id );
		$mixcloud_url   = MixcloudProvider::get_mixcloud_url( $post_id );
		$audiomack_url  = AudiomackProvider::get_audiomack_url( $post_id );

		wp_nonce_field( 'mds_ap_save_release', 'mds_ap_nonce' );
		?>
		<div class="mds-editor-box">
			<div class="mds-section-info" role="note">
				<span class="dashicons dashicons-info-outline mds-section-info__icon" aria-hidden="true"></span>
				<div class="mds-section-info__body">
					<strong class="mds-section-info__title"><?php esc_html_e( 'Automatic & Provider-Specific Audio Sources', 'mds-audio-providers' ); ?></strong>
					<p class="mds-section-info__summary">
						<?php esc_html_e( 'Apple Music, Deezer, Spotify, and TIDAL resolve automatically from this Release’s Barcode (UPC/EAN). Only enabled URL-based providers are shown below; matching URLs from Release Links are also detected automatically.', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>

			<?php if ( $bc_enabled ) : ?>
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
							min="1"
							placeholder="<?php esc_attr_e( 'e.g. 3456789012', 'mds-audio-providers' ); ?>"
						/>
						<p class="mds-style-field__help">
							<?php esc_html_e( 'Populated automatically by Verify URL. If your web host is blocked by Bandcamp, paste Bandcamp’s Share/Embed code into the URL field or enter the numeric album ID manually.', 'mds-audio-providers' ); ?>
						</p>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $audius_enabled ) : ?>
			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'Audius', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-field">
					<label class="mds-style-field__label" for="mds_ap_audius_url"><?php esc_html_e( 'Audius Track / Album / Playlist URL', 'mds-audio-providers' ); ?></label>
					<input
						type="url"
						id="mds_ap_audius_url"
						name="mds_ap_audius_url"
						value="<?php echo esc_attr( $audius_url ); ?>"
						class="regular-text"
						placeholder="https://audius.co/artist/album/release-name"
					/>
					<p class="mds-style-field__help">
						<?php esc_html_e( 'Optional. Resolves native 320kbps HTML5 audio streams via the Audius Open Audio Protocol (zero API keys required).', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $sc_enabled ) : ?>
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
			<?php endif; ?>

			<?php if ( $jm_enabled ) : ?>
			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'Jamendo', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-field">
					<label class="mds-style-field__label" for="mds_ap_jamendo_url"><?php esc_html_e( 'Jamendo Album / Track URL', 'mds-audio-providers' ); ?></label>
					<input
						type="url"
						id="mds_ap_jamendo_url"
						name="mds_ap_jamendo_url"
						value="<?php echo esc_attr( $jamendo_url ); ?>"
						class="regular-text"
						placeholder="https://www.jamendo.com/album/123456/release-name"
					/>
					<p class="mds-style-field__help">
						<?php esc_html_e( 'Optional. Resolves native MP3 streams via Jamendo API v3.0 (requires a free Jamendo Client ID in Audio Providers settings).', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $fw_enabled ) : ?>
			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'Funkwhale', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-field">
					<label class="mds-style-field__label" for="mds_ap_funkwhale_url"><?php esc_html_e( 'Funkwhale Album / Track URL', 'mds-audio-providers' ); ?></label>
					<input
						type="url"
						id="mds_ap_funkwhale_url"
						name="mds_ap_funkwhale_url"
						value="<?php echo esc_attr( $funkwhale_url ); ?>"
						class="regular-text"
						placeholder="https://open.audio/library/albums/12345"
					/>
					<p class="mds-style-field__help">
						<?php esc_html_e( 'Optional. Resolves native HTML5 audio streams directly from any public or authenticated Funkwhale pod.', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $tidal_enabled ) : ?>
			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'TIDAL', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-field">
					<label class="mds-style-field__label" for="mds_ap_tidal_url"><?php esc_html_e( 'TIDAL Album / Track / Playlist URL', 'mds-audio-providers' ); ?></label>
					<input
						type="url"
						id="mds_ap_tidal_url"
						name="mds_ap_tidal_url"
						value="<?php echo esc_attr( $tidal_url ); ?>"
						class="regular-text"
						placeholder="https://tidal.com/browse/album/123456789"
					/>
					<p class="mds-style-field__help">
						<?php esc_html_e( 'Optional. TIDAL also resolves automatically by Barcode (UPC/EAN) when TIDAL OAuth credentials are configured.', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $mc_enabled ) : ?>
			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'Mixcloud', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-field">
					<label class="mds-style-field__label" for="mds_ap_mixcloud_url"><?php esc_html_e( 'Mixcloud Show / Mix URL', 'mds-audio-providers' ); ?></label>
					<input
						type="url"
						id="mds_ap_mixcloud_url"
						name="mds_ap_mixcloud_url"
						value="<?php echo esc_attr( $mixcloud_url ); ?>"
						class="regular-text"
						placeholder="https://www.mixcloud.com/artist/show-name/"
					/>
					<p class="mds-style-field__help">
						<?php esc_html_e( 'Optional. Renders the official Mixcloud embed widget for DJ mixes and radio shows (zero API keys required).', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $am_enabled ) : ?>
			<div class="mds-control-group">
				<h4 class="mds-control-group__title"><?php esc_html_e( 'Audiomack', 'mds-audio-providers' ); ?></h4>
				<div class="mds-style-field">
					<label class="mds-style-field__label" for="mds_ap_audiomack_url"><?php esc_html_e( 'Audiomack Song / Album / Playlist URL', 'mds-audio-providers' ); ?></label>
					<input
						type="url"
						id="mds_ap_audiomack_url"
						name="mds_ap_audiomack_url"
						value="<?php echo esc_attr( $audiomack_url ); ?>"
						class="regular-text"
						placeholder="https://audiomack.com/artist/album/release-name"
					/>
					<p class="mds-style-field__help">
						<?php esc_html_e( 'Optional. Renders the official Audiomack embed player (zero API keys required).', 'mds-audio-providers' ); ?>
					</p>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Saves Bandcamp and URL-based provider meta on `mds_rm_save_release_meta`.
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

		// Save Bandcamp meta only when its fields were rendered (preserving legacy keys).
		if ( isset( $_POST['mds_bc_album_id'] ) || isset( $_POST['mds_bc_url'] ) ) {
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
		}

		// Save URL-based provider fields only when present in POST (respecting conditional visibility).
		$url_fields = array(
			'mds_ap_soundcloud_url' => 'soundcloud',
			'mds_ap_audius_url'     => 'audius',
			'mds_ap_jamendo_url'    => 'jamendo',
			'mds_ap_funkwhale_url'  => 'funkwhale',
			'mds_ap_tidal_url'      => 'tidal',
			'mds_ap_mixcloud_url'   => 'mixcloud',
			'mds_ap_audiomack_url'  => 'audiomack',
		);

		foreach ( $url_fields as $meta_key => $provider_slug ) {
			if ( ! isset( $_POST[ $meta_key ] ) ) {
				continue;
			}

			// Invalidate any previously cached entry for this provider before updating meta.
			self::invalidate_provider_url_cache( $post_id, $provider_slug );

			$url = esc_url_raw( wp_unslash( (string) $_POST[ $meta_key ] ) );
			if ( '' !== $url ) {
				update_post_meta( $post_id, $meta_key, $url );
				self::invalidate_provider_url_cache( $post_id, $provider_slug );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}

	/**
	 * Invalidates the provider-specific transient cache key matching how each provider indexes its cache.
	 *
	 * @param int    $post_id       Release post ID.
	 * @param string $provider_slug Provider slug.
	 * @return void
	 */
	private static function invalidate_provider_url_cache( int $post_id, string $provider_slug ): void {
		if ( 'jamendo' === $provider_slug ) {
			$target = JamendoProvider::get_jamendo_target( $post_id );
			if ( null !== $target ) {
				Cache::delete( 'jamendo', $target['type'] . ':' . $target['id'] );
			}
			return;
		}
		if ( 'tidal' === $provider_slug ) {
			$target = TidalProvider::get_tidal_target( $post_id );
			if ( null !== $target ) {
				Cache::delete( 'tidal', $target['type'] . ':' . $target['id'] );
			}
			return;
		}

		$current_url = match ( $provider_slug ) {
			'soundcloud' => SoundCloudProvider::get_soundcloud_url( $post_id ),
			'audius'     => AudiusProvider::get_audius_url( $post_id ),
			'funkwhale'  => FunkwhaleProvider::get_funkwhale_url( $post_id ),
			'mixcloud'   => MixcloudProvider::get_mixcloud_url( $post_id ),
			'audiomack'  => AudiomackProvider::get_audiomack_url( $post_id ),
			default      => '',
		};
		if ( '' !== $current_url ) {
			Cache::delete( $provider_slug, $current_url );
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
		if ( preg_match( '/&quot;item_id&quot;\s*:\s*(\d+)/i', $html, $matches ) ) {
			$id = absint( $matches[1] );
			if ( $id > 0 ) {
				return $id;
			}
		}
		if ( preg_match( '/"album_id"\s*:\s*(\d+)/i', $html, $matches ) ) {
			$id = absint( $matches[1] );
			if ( $id > 0 ) {
				return $id;
			}
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
