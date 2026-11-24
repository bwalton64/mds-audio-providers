<?php
/**
 * Admin settings and Release/Artist meta box for MDS Bandcamp Integration.
 *
 * @package MDS\Bandcamp
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MDS\Bandcamp\Admin;

use DOMDocument;
use DOMNode;
use MDS\Bandcamp\Api\BandcampApi;
use MDS\Bandcamp\Core\Cache;
use MDS\Bandcamp\Core\Dependencies;
use MDS\Bandcamp\Core\Helpers;
use MDS\Bandcamp\Core\Templates;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides admin functionality for Bandcamp Release and Artist meta boxes.
 *
 * @since 1.0.0
 */
final class Admin {
	/**
	 * Register admin hooks for the Bandcamp meta box.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'mds_rm_register_custom_meta_boxes', array( self::class, 'register_meta_box' ) );
		add_action( 'mds_rm_save_release_meta', array( self::class, 'save_release_meta' ), 10, 2 );
		add_action( 'mds_rm_save_artist_meta', array( self::class, 'save_artist_meta' ), 10, 2 );
		add_action( 'mds_rm_after_buy_now_field', array( self::class, 'render_buy_now_button' ) );
		add_filter( 'mds_rm_default_media_formats', array( self::class, 'register_default_media_formats' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_metabox_assets' ) );
		add_action( 'wp_ajax_mds_bc_verify_url', array( self::class, 'ajax_verify_url' ) );
	}

	/**
	 * Registers the "Digital Download" default media format with Core's hash-seeded registry.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, array{label: string, icon: string}> $formats Default media format registry.
	 * @return array<string, array{label: string, icon: string}> Updated registry.
	 */
	public static function register_default_media_formats( array $formats ): array {
		if ( ! isset( $formats['digital-download'] ) ) {
			$formats['digital-download'] = array(
				'label' => __( 'Digital Download', 'mds-bandcamp-integration' ),
				'icon'  => 'fas fa-download',
			);
		}
		return $formats;
	}

	/**
	 * Save Bandcamp Release meta on `mds_rm_save_release_meta`.
	 *
	 * @since 2.0.0
	 *
	 * @param int     $post_id Release post ID.
	 * @param WP_Post $post    Release post object.
	 * @return void
	 */
	public static function save_release_meta( int $post_id, WP_Post $post ): void {
		unset( $post );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified inside save_meta().
		self::save_meta( $post_id, $_POST );
	}

	/**
	 * Save Bandcamp Artist meta on `mds_rm_save_artist_meta`.
	 *
	 * @since 2.0.0
	 *
	 * @param int     $post_id Artist post ID.
	 * @param WP_Post $post    Artist post object.
	 * @return void
	 */
	public static function save_artist_meta( int $post_id, WP_Post $post ): void {
		unset( $post );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified inside save_meta().
		self::save_meta( $post_id, $_POST );
	}

	/**
	 * Output the "Use Bandcamp URL" helper button next to the Release Manager Buy Now repeater controls.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public static function render_buy_now_button(): void {
		if ( ! Dependencies::is_mds_active() ) {
			return;
		}

		echo '<button type="button" class="button button-secondary" id="mds_use_bandcamp_url_btn" disabled>'
			. esc_html__( 'Use Bandcamp URL', 'mds-bandcamp-integration' )
			. '</button>';
	}

	/**
	 * Register the Bandcamp meta box for Release and Artist posts.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|null $post Post being processed.
	 * @return void
	 */
	public static function register_meta_box( $post ): void {
		if ( ! Dependencies::is_mds_active() ) {
			return;
		}

		if ( ! $post || ! in_array( $post->post_type, array( 'release', 'artist' ), true ) ) {
			return;
		}

		add_meta_box(
			'mds_bc_settings',
			__( 'Bandcamp Integration', 'mds-bandcamp-integration' ),
			array( self::class, 'render_meta_box' ),
			$post->post_type,
			'normal',
			'default'
		);
	}

	/**
	 * Render the Bandcamp meta box for a Release or Artist post.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post Post being processed.
	 * @return void
	 */
	public static function render_meta_box( $post ): void {
		$is_bandcamp_active = Dependencies::is_bandcamp_active();
		$is_artist          = isset( $post->post_type ) && 'artist' === $post->post_type;
		$bandcamp_data      = $is_artist ? array() : self::get_bandcamp_data( (int) $post->ID );
		$url                = $is_artist ? Helpers::get_artist_url( (int) $post->ID ) : ( $bandcamp_data['url'] ?? '' );
		$album_id           = $is_artist ? '' : ( $bandcamp_data['album_id'] ?? '' );
		$artist_subdomain   = $is_artist ? Helpers::get_artist_subdomain( (int) $post->ID ) : '';

		$output = Templates::render(
			'admin-settings.php',
			array(
				'nonce_field'        => wp_nonce_field( 'mds_bc_save', 'mds_bc_nonce', false, false ),
				'url'                => $url,
				'album_id'           => $album_id,
				'is_artist_metabox'  => $is_artist,
				'artist_subdomain'   => $artist_subdomain,
				'is_bandcamp_active' => $is_bandcamp_active,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes dynamic values and emits the nonce field.
		echo $output;
	}

	/**
	 * Save the Bandcamp data when the request is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $post_id   Post ID.
	 * @param array<string, mixed> $post_data Submitted post data.
	 * @return void
	 */
	public static function save_meta( $post_id, $post_data ): void {
		if ( ! Dependencies::is_mds_active() || ! self::has_valid_nonce( $post_data ) ) {
			return;
		}

		$post_id   = (int) $post_id;
		$post_type = function_exists( 'get_post_type' ) ? get_post_type( $post_id ) : 'release';
		if ( 'artist' === $post_type || isset( $post_data['mds_bc_artist_url'] ) ) {
			$raw_artist_url = isset( $post_data['mds_bc_artist_url'] ) && is_string( $post_data['mds_bc_artist_url'] )
				? trim( sanitize_text_field( wp_unslash( $post_data['mds_bc_artist_url'] ) ) )
				: '';
			$subdomain      = Helpers::normalize_artist_subdomain( $raw_artist_url );

			if ( '' !== $subdomain ) {
				$canonical_url = 'https://' . $subdomain . '.bandcamp.com';
				update_post_meta(
					$post_id,
					'mds_bandcamp_artist_integration',
					array(
						'url'       => $canonical_url,
						'subdomain' => $subdomain,
						'saved_at'  => time(),
					)
				);
				update_post_meta( $post_id, 'bandcamp_artist_url', $canonical_url );
				update_post_meta( $post_id, 'bandcamp_artist_subdomain', $subdomain );
				Cache::delete_for_target( 'discography', $subdomain );
			} else {
				delete_post_meta( $post_id, 'mds_bandcamp_artist_integration' );
				delete_post_meta( $post_id, 'bandcamp_artist_url' );
				delete_post_meta( $post_id, 'bandcamp_artist_subdomain' );
			}
			return;
		}

		// Store the album ID if provided.
		if ( isset( $post_data['mds_bc_album_id'] ) ) {
			$album_id = sanitize_text_field( wp_unslash( (string) $post_data['mds_bc_album_id'] ) );
			$album_id = absint( $album_id );

			if ( $album_id > 0 ) {
				$url = isset( $post_data['mds_bc_url'] ) ? esc_url_raw( wp_unslash( (string) $post_data['mds_bc_url'] ) ) : '';

				$bandcamp_data = array(
					'url'      => $url,
					'album_id' => $album_id,
					'saved_at' => time(),
				);

				update_post_meta( $post_id, 'mds_bandcamp_integration', $bandcamp_data );
				update_post_meta( $post_id, 'bandcamp_album_id', (string) $album_id );
				update_post_meta( $post_id, 'bandcamp_album_url', $url );
				Cache::delete_for_target( 'album', (string) $album_id );
			} else {
				delete_post_meta( $post_id, 'mds_bandcamp_integration' );
				delete_post_meta( $post_id, 'bandcamp_album_id' );
				delete_post_meta( $post_id, 'bandcamp_album_url' );
			}
		} elseif ( isset( $post_data['mds_bc_url'] ) && empty( $post_data['mds_bc_album_id'] ) ) {
			// Clear album ID if URL is set but album_id is empty.
			delete_post_meta( $post_id, 'mds_bandcamp_integration' );
			delete_post_meta( $post_id, 'bandcamp_album_id' );
			delete_post_meta( $post_id, 'bandcamp_album_url' );
		}
	}

	/**
	 * Retrieve Bandcamp integration data from post meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return array{url?: string, album_id?: int} Bandcamp data array with 'url' and 'album_id' keys.
	 */
	private static function get_bandcamp_data( int $post_id ): array {
		$data = get_post_meta( $post_id, 'mds_bandcamp_integration', true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		return array(
			'url'      => isset( $data['url'] ) ? (string) $data['url'] : '',
			'album_id' => isset( $data['album_id'] ) ? (int) $data['album_id'] : 0,
		);
	}

	/**
	 * Check the save nonce in submitted post data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $post_data Submitted post data.
	 * @return bool Whether the request nonce is valid.
	 */
	private static function has_valid_nonce( $post_data ): bool {
		return is_array( $post_data )
			&& isset( $post_data['mds_bc_nonce'] )
			&& (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $post_data['mds_bc_nonce'] ) ), 'mds_bc_save' );
	}

	/**
	 * Enqueue JavaScript and inline configuration for the meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public static function enqueue_metabox_assets( string $hook_suffix ): void {
		global $post;

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! $post || ! in_array( $post->post_type, array( 'release', 'artist' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'mds-bc-metabox',
			MDS_BC_URL . 'js/metabox-verify.js',
			array( 'jquery', 'wp-a11y' ),
			MDS_BC_VERSION,
			true
		);

		$metabox_data = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'mds_bc_verify_nonce' ),
			'messages' => array(
				'storeName'            => __( 'Bandcamp', 'mds-bandcamp-integration' ),
				'digitalDownloadLabel' => __( 'Digital Download', 'mds-bandcamp-integration' ),
				'buyNowAdded'          => __( 'Added Bandcamp URL to Buy Now formats.', 'mds-bandcamp-integration' ),
				'buyNowExists'         => __( 'This Bandcamp URL is already listed in Buy Now.', 'mds-bandcamp-integration' ),
			),
		);

		wp_add_inline_script(
			'mds-bc-metabox',
			'const mdsBcMetabox = ' . wp_json_encode( $metabox_data ) . ';',
			'before'
		);
	}

	/**
	 * AJAX handler for verifying a Bandcamp Release URL (extracting Album ID) or Artist URL.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function ajax_verify_url(): void {
		check_ajax_referer( 'mds_bc_verify_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mds-bandcamp-integration' ) ) );
		}

		if ( ! isset( $_POST['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No URL provided.', 'mds-bandcamp-integration' ) ) );
		}

		$raw_input = sanitize_text_field( wp_unslash( (string) $_POST['url'] ) );
		$mode      = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : 'release';

		if ( 'artist' === $mode ) {
			$subdomain = Helpers::normalize_artist_subdomain( $raw_input );
			if ( '' === $subdomain ) {
				wp_send_json_error( array( 'message' => __( 'Enter a valid Bandcamp Artist URL (for example https://artist.bandcamp.com).', 'mds-bandcamp-integration' ) ) );
			}
			$canonical_url = 'https://' . $subdomain . '.bandcamp.com';
			$response      = BandcampApi::fetch_bandcamp_page( $canonical_url . '/music' );
			if ( is_array( $response ) && isset( $response['error'] ) ) {
				wp_send_json_error( array( 'message' => $response['message'] ?? __( 'Failed to verify Bandcamp Artist page.', 'mds-bandcamp-integration' ) ) );
			}
			wp_send_json_success(
				array(
					'artist_url' => $canonical_url,
					'subdomain'  => $subdomain,
				)
			);
		}

		$url = esc_url_raw( $raw_input );

		// Validate URL format.
		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'mds-bandcamp-integration' ) ) );
		}

		// Verify HTTPS and bandcamp.com domain.
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || 'https' !== strtolower( $parsed['scheme'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Only HTTPS URLs are allowed.', 'mds-bandcamp-integration' ) ) );
		}

		if ( empty( $parsed['host'] ) || ! preg_match( '/(^|\.)bandcamp\.com$/i', $parsed['host'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Only bandcamp.com URLs are allowed.', 'mds-bandcamp-integration' ) ) );
		}

		// Try to fetch and parse the page.
		$response = BandcampApi::fetch_bandcamp_page( $url );

		if ( is_array( $response ) && isset( $response['error'] ) ) {
			wp_send_json_error( array( 'message' => $response['message'] ?? __( 'Failed to fetch Bandcamp page.', 'mds-bandcamp-integration' ) ) );
		}

		// Extract album ID from HTML comment.
		$album_id = self::extract_album_id( (string) $response );

		if ( ! $album_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not find Album ID on this page. Ensure it\'s a valid Bandcamp album/release page.', 'mds-bandcamp-integration' ) ) );
		}

		// Validate album ID is numeric.
		$album_id = absint( $album_id );
		if ( $album_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Album ID must be a positive number.', 'mds-bandcamp-integration' ) ) );
		}

		wp_send_json_success( array( 'album_id' => $album_id ) );
	}

	/**
	 * Extract album ID from Bandcamp page HTML using DOM parsing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html HTML content of Bandcamp page.
	 * @return int|false Album ID if found, false otherwise.
	 */
	private static function extract_album_id( string $html ): int|false {
		if ( '' === $html ) {
			return false;
		}

		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		$dom->loadHTML( $html );

		libxml_use_internal_errors( false );

		// Look for comment containing "album id".
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMDocument property.
		foreach ( $dom->childNodes as $node ) {
			$comment = self::find_album_id_comment( $node );
			if ( $comment ) {
				return $comment;
			}
		}

		// Check meta tags as fallback.
		$metas = $dom->getElementsByTagName( 'meta' );
		foreach ( $metas as $meta ) {
			if ( 'album_id' === $meta->getAttribute( 'name' ) || 'albumId' === $meta->getAttribute( 'property' ) ) {
				$content = $meta->getAttribute( 'content' );
				$id      = absint( $content );
				if ( $id > 0 ) {
					return $id;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively search DOM nodes for album ID comment.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMNode $node DOM node to search.
	 * @return int|false Album ID if found, false otherwise.
	 */
	private static function find_album_id_comment( DOMNode $node ): int|false {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMNode property.
		if ( XML_COMMENT_NODE === $node->nodeType ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMNode property.
			if ( preg_match( '/album\s+id\s+(\d+)/i', (string) $node->nodeValue, $matches ) ) {
				$id = absint( $matches[1] );
				if ( $id > 0 ) {
					return $id;
				}
			}
		}

		if ( $node->hasChildNodes() ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMNode property.
			foreach ( $node->childNodes as $child ) {
				$result = self::find_album_id_comment( $child );
				if ( $result ) {
					return $result;
				}
			}
		}

		return false;
	}
}
