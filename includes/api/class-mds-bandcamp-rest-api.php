<?php
/**
 * REST API endpoints for MDS Bandcamp Integration.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST API routes for Bandcamp data fetching.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_REST_API {
	/**
	 * Register REST API hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_filter( 'mds_rm_release_picker_item', array( self::class, 'enrich_release_picker_item' ), 10, 2 );
		add_filter( 'mds_rm_artist_picker_item', array( self::class, 'enrich_artist_picker_item' ), 10, 2 );
	}

	/**
	 * Enriches preloaded Release Manager editor catalog records with Bandcamp album metadata.
	 *
	 * @since 1.1.0
	 * @param array $item Picker record with id, title, artist, and upc.
	 * @param mixed $post Release WP_Post object.
	 * @return array Enriched picker record.
	 */
	public static function enrich_release_picker_item( array $item, $post ): array {
		$post_id = isset( $post->ID ) ? (int) $post->ID : (int) ( $item['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return $item;
		}
		$album_id          = MDS_Bandcamp_Helpers::get_album_id( $post_id );
		$item['album_id']  = $album_id > 0 ? (string) $album_id : '';
		$item['url']       = $album_id > 0 ? MDS_Bandcamp_Helpers::get_release_url( $post_id ) : '';
		$item['subdomain'] = $album_id > 0 ? MDS_Bandcamp_Helpers::get_release_subdomain( $post_id ) : '';
		return $item;
	}

	/**
	 * Enriches preloaded Release Manager editor Artist records with Bandcamp Artist URL metadata.
	 *
	 * @since 1.1.0
	 * @param array $item   Artist picker record with id, title, and uuid.
	 * @param mixed $artist Artist WP_Post object.
	 * @return array Enriched Artist picker record.
	 */
	public static function enrich_artist_picker_item( array $item, $artist ): array {
		$artist_id = isset( $artist->ID ) ? (int) $artist->ID : (int) ( $item['id'] ?? 0 );
		if ( $artist_id <= 0 ) {
			return $item;
		}
		$item['artist_url'] = MDS_Bandcamp_Helpers::get_artist_url( $artist_id );
		$item['subdomain']  = MDS_Bandcamp_Helpers::get_artist_subdomain( $artist_id );
		return $item;
	}

	/**
	 * Register the editor release-search route.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			'mds-bandcamp/v1',
			'/releases',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( self::class, 'check_permissions' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'   => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
				'callback'            => array( self::class, 'handle_releases_request' ),
			)
		);
	}

	/**
	 * Check whether the current user can access Bandcamp metadata.
	 *
	 * @return bool
	 */
	public static function check_permissions(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * List published Releases that have a verified Bandcamp album.
	 *
	 * Serves the block editor release pickers. Release matching delegates to
	 * the Release Manager search service so title and Artist-name semantics
	 * stay identical to the core pickers; this endpoint only adds the
	 * Bandcamp data each item carries.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_releases_request( WP_REST_Request $request ) {
		$items = array();
		$pages = 0;

		if ( class_exists( 'MDS_RM_Release_Search' ) ) {
			$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
			$page   = max( 1, absint( $request->get_param( 'page' ) ) );
			$found  = array();
			foreach ( MDS_RM_Release_Search::query( $search ) as $post ) {
				if ( MDS_Bandcamp_Helpers::get_album_id( $post->ID ) > 0 ) {
					$found[] = $post;
				}
			}
			$paged = MDS_RM_Release_Search::paginate( $found, $page );
			$pages = $paged['pages'];
			foreach ( MDS_RM_Release_Search::describe( $paged['posts'] ) as $item ) {
				$items[] = array(
					'id'        => $item['id'],
					'title'     => $item['title'],
					'artist'    => $item['artist'],
					'album_id'  => (string) MDS_Bandcamp_Helpers::get_album_id( (int) $item['id'] ),
					'url'       => MDS_Bandcamp_Helpers::get_release_url( (int) $item['id'] ),
					'subdomain' => MDS_Bandcamp_Helpers::get_release_subdomain( (int) $item['id'] ),
				);
			}
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'pages' => $pages,
			)
		);
	}
}

