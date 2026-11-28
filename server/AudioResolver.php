<?php
/**
 * Proprietary DSP Audio Resolver for MDS Cloud Gateway.
 *
 * Orchestrates all 11 cloud-backed streaming audio providers with:
 * - Per-provider license tier enforcement (`free` vs `pro`)
 * - Centralized MDS OAuth / API-key management via `GatewayCredentials`
 * - Cross-customer shared caching (`mds_cg_proxy` object cache group + transient fallback)
 *
 * @package   MDS\AudioProviders\Server
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   Proprietary — Musty Dungeon Studios Commercial Server Module
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves normalized AudioManifest payloads on MDS Cloud Gateway.
 */
final class AudioResolver {

	/**
	 * Object cache group shared with MDS Cloud Gateway.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'mds_cg_proxy';

	/**
	 * Standard catalog lookup cache TTL (12 hours).
	 *
	 * @var int
	 */
	private const LONG_TTL = 43200;

	/**
	 * Signed stream manifest TTL (15 minutes).
	 *
	 * @var int
	 */
	private const SHORT_TTL = 900;

	/**
	 * Medium stream TTL (1 hour).
	 *
	 * @var int
	 */
	private const MEDIUM_TTL = 3600;

	/**
	 * Minimum license tier required for each gateway-backed provider.
	 *
	 * Zero-cost public providers (`apple_music`, `deezer`, `bandcamp`, `audius`, `mixcloud`, `audiomack`)
	 * are included in the Free tier; OAuth / API-key / commercial providers require Pro+.
	 *
	 * @var array<string, string>
	 */
	public const PROVIDER_MIN_TIERS = array(
		'apple_music' => 'free',
		'deezer'      => 'free',
		'bandcamp'    => 'free',
		'audius'      => 'free',
		'mixcloud'    => 'free',
		'audiomack'   => 'free',
		'spotify'     => 'pro',
		'soundcloud'  => 'pro',
		'tidal'       => 'pro',
		'jamendo'     => 'pro',
		'musicfetch'  => 'pro',
	);

	/**
	 * Tier rank ordering matching `MDS\CloudGateway\Core\PackageRepository`.
	 *
	 * @var array<string, int>
	 */
	private const TIER_RANK = array(
		'free'   => 10,
		'pro'    => 20,
		'label'  => 30,
		'agency' => 40,
	);

	/**
	 * Entry point invoked by `ProxyRouter` for `/mds-gw/v1/proxy/audio-resolve`.
	 *
	 * @param array<string, mixed> $payload      Request payload (`provider`, `release_id`, `upc`, `url`, `album_id`, `embed_fallback`, `fresh`).
	 * @param array<string, mixed> $token_claims Verified Bearer token claims (`tier`, `lid`, etc.).
	 * @return array<string, mixed> Gateway response payload (`ok`, `manifest`, `cached`, or error).
	 */
	public static function handle_request( array $payload, array $token_claims ): array {
		$provider = isset( $payload['provider'] ) ? sanitize_key( (string) $payload['provider'] ) : '';
		if ( '' === $provider || ! isset( self::PROVIDER_MIN_TIERS[ $provider ] ) ) {
			return array(
				'ok'    => false,
				'code'  => 'unsupported_provider',
				'error' => __( 'Unsupported audio provider.', 'mds-audio-providers' ),
			);
		}

		$required_tier = self::PROVIDER_MIN_TIERS[ $provider ];
		$caller_tier   = strtolower( trim( (string) ( $token_claims['tier'] ?? 'free' ) ) );
		if ( ! self::tier_satisfies( $caller_tier, $required_tier ) ) {
			return array(
				'ok'            => false,
				'code'          => 'tier_insufficient',
				'required_tier' => $required_tier,
				'error'         => __( 'Upgrade to MDS Pro to unlock this audio provider.', 'mds-audio-providers' ),
			);
		}

		$release_id     = isset( $payload['release_id'] ) ? abs( (int) $payload['release_id'] ) : 0;
		$upc            = isset( $payload['upc'] ) && is_string( $payload['upc'] ) ? (string) preg_replace( '/\D+/', '', $payload['upc'] ) : '';
		$url            = isset( $payload['url'] ) && is_string( $payload['url'] ) ? trim( $payload['url'] ) : '';
		$album_id       = isset( $payload['album_id'] ) ? abs( (int) $payload['album_id'] ) : 0;
		$embed_fallback = ! array_key_exists( 'embed_fallback', $payload ) || (bool) $payload['embed_fallback'];
		$fresh          = ! empty( $payload['fresh'] );

		$cache_key = self::build_cache_key( $provider, $upc, $url, $album_id, $embed_fallback );
		if ( ! $fresh ) {
			$cached = self::cache_get( $cache_key );
			if ( is_array( $cached ) ) {
				$cached['releaseId'] = $release_id;
				return array(
					'ok'       => true,
					'cached'   => true,
					'manifest' => $cached,
				);
			}
		}

		$manifest = match ( $provider ) {
			'apple_music' => self::resolve_apple_music( $release_id, $upc ),
			'deezer'      => self::resolve_deezer( $release_id, $upc ),
			'bandcamp'    => BandcampScraper::resolve_album( $release_id, $album_id, $url, $embed_fallback ),
			'audius'      => self::resolve_audius( $release_id, $url ),
			'mixcloud'    => self::resolve_mixcloud( $release_id, $url ),
			'audiomack'   => self::resolve_audiomack( $release_id, $url ),
			'spotify'     => self::resolve_spotify( $release_id, $upc, $url, $embed_fallback ),
			'soundcloud'  => self::resolve_soundcloud( $release_id, $url ),
			'tidal'       => self::resolve_tidal( $release_id, $upc, $url ),
			'jamendo'     => self::resolve_jamendo( $release_id, $url ),
			'musicfetch'  => self::resolve_musicfetch( $release_id, $upc ),
			default       => null,
		};

		if ( ! is_array( $manifest ) ) {
			return array(
				'ok'       => false,
				'code'     => 'not_found',
				'manifest' => null,
			);
		}

		$ttl = isset( $manifest['ttl'] ) ? max( 300, (int) $manifest['ttl'] ) : self::MEDIUM_TTL;
		self::cache_set( $cache_key, $manifest, $ttl );

		return array(
			'ok'       => true,
			'cached'   => false,
			'manifest' => $manifest,
		);
	}

	/**
	 * Checks whether a caller tier satisfies the minimum required tier.
	 *
	 * @param string $actual_tier   Caller's active license tier.
	 * @param string $required_tier Minimum required tier.
	 * @return bool
	 */
	public static function tier_satisfies( string $actual_tier, string $required_tier ): bool {
		$actual   = self::TIER_RANK[ strtolower( trim( $actual_tier ) ) ] ?? 0;
		$required = self::TIER_RANK[ strtolower( trim( $required_tier ) ) ] ?? 999;
		return $actual >= $required;
	}

	/**
	 * Resolves Apple Music / iTunes 30-second `.m4a` previews by UPC.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $upc        Digits-only UPC/EAN.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_apple_music( int $release_id, string $upc ): ?array {
		if ( '' === $upc || ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}

		$url      = add_query_arg(
			array(
				'upc'    => $upc,
				'entity' => 'song',
			),
			'https://itunes.apple.com/lookup'
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$results = is_array( $decoded ) && isset( $decoded['results'] ) && is_array( $decoded['results'] ) ? $decoded['results'] : array();
		if ( empty( $results ) ) {
			return null;
		}

		$album_title = '';
		$artist_name = '';
		$artwork_url = '';
		$source_url  = '';
		$tracks      = array();

		foreach ( $results as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$wrapper = (string) ( $item['wrapperType'] ?? '' );
			if ( 'collection' === $wrapper ) {
				$album_title = (string) ( $item['collectionName'] ?? '' );
				$artist_name = (string) ( $item['artistName'] ?? '' );
				$artwork_url = (string) ( $item['artworkUrl100'] ?? '' );
				$source_url  = (string) ( $item['collectionViewUrl'] ?? '' );
				continue;
			}
			if ( 'track' === $wrapper && ! empty( $item['previewUrl'] ) && is_string( $item['previewUrl'] ) ) {
				if ( '' === $artist_name && ! empty( $item['artistName'] ) ) {
					$artist_name = (string) $item['artistName'];
				}
				if ( '' === $artwork_url && ! empty( $item['artworkUrl100'] ) ) {
					$artwork_url = (string) $item['artworkUrl100'];
				}
				$tracks[] = array(
					'index'          => isset( $item['trackNumber'] ) ? (int) $item['trackNumber'] : ( count( $tracks ) + 1 ),
					'title'          => (string) ( $item['trackName'] ?? '' ),
					'artist'         => (string) ( $item['artistName'] ?? $artist_name ),
					'duration'       => 30.0,
					'streamUrl'      => $item['previewUrl'],
					'mimeType'       => 'audio/mp4',
					'isTimedPreview' => true,
					'sourceUrl'      => (string) ( $item['trackViewUrl'] ?? $source_url ),
				);
			}
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		return array(
			'provider'       => 'apple_music',
			'providerLabel'  => __( 'Apple Music', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => $album_title,
			'artist'         => $artist_name,
			'artworkUrl'     => str_replace( '100x100bb', '300x300bb', $artwork_url ),
			'sourceUrl'      => $source_url,
			'isTimedPreview' => true,
			'ttl'            => self::LONG_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Resolves Deezer 30-second `.mp3` previews by UPC.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $upc        Digits-only UPC/EAN.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_deezer( int $release_id, string $upc ): ?array {
		if ( '' === $upc || ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}

		$response = wp_safe_remote_get(
			'https://api.deezer.com/album/upc:' . rawurlencode( $upc ),
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || ! empty( $decoded['error'] ) ) {
			return null;
		}

		$raw_tracks  = isset( $decoded['tracks']['data'] ) && is_array( $decoded['tracks']['data'] ) ? $decoded['tracks']['data'] : array();
		$artist_name = isset( $decoded['artist']['name'] ) ? (string) $decoded['artist']['name'] : '';
		$source_url  = isset( $decoded['link'] ) ? (string) $decoded['link'] : '';
		$tracks      = array();

		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['preview'] ) || ! is_string( $item['preview'] ) ) {
				continue;
			}
			$tracks[] = array(
				'index'          => $idx + 1,
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => isset( $item['artist']['name'] ) ? (string) $item['artist']['name'] : $artist_name,
				'duration'       => 30.0,
				'streamUrl'      => $item['preview'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => true,
				'sourceUrl'      => (string) ( $item['link'] ?? $source_url ),
			);
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		return array(
			'provider'       => 'deezer',
			'providerLabel'  => __( 'Deezer', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => (string) ( $decoded['title'] ?? '' ),
			'artist'         => $artist_name,
			'artworkUrl'     => (string) ( $decoded['cover_medium'] ?? $decoded['cover'] ?? '' ),
			'sourceUrl'      => $source_url,
			'isTimedPreview' => true,
			'ttl'            => self::LONG_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Resolves Audius Open Audio Protocol streams from a validated Audius URL.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $audius_url Validated `https://audius.co/...` URL.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_audius( int $release_id, string $audius_url ): ?array {
		$parsed = wp_parse_url( $audius_url );
		if (
			'' === $audius_url
			|| empty( $parsed['scheme'] )
			|| 'https' !== strtolower( (string) $parsed['scheme'] )
			|| empty( $parsed['host'] )
			|| ! preg_match( '/(^|\.)audius\.co$/i', (string) $parsed['host'] )
			|| ! function_exists( 'wp_safe_remote_get' )
		) {
			return null;
		}

		$api_base    = 'https://api.audius.co/v1';
		$app_name    = 'mds_cloud_gateway';
		$resolve_url = add_query_arg(
			array(
				'url'      => $audius_url,
				'app_name' => $app_name,
			),
			$api_base . '/resolve'
		);

		$response = wp_safe_remote_get(
			$resolve_url,
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data    = is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : null;
		if ( null === $data ) {
			return null;
		}

		$entity     = isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : $data;
		$raw_tracks = array();
		if ( isset( $entity['tracks'] ) && is_array( $entity['tracks'] ) ) {
			$raw_tracks = array_slice( $entity['tracks'], 0, 12 );
		} elseif ( ! empty( $entity['id'] ) && isset( $entity['title'] ) ) {
			$raw_tracks = array( $entity );
		}

		$default_artist = isset( $entity['user']['name'] ) ? (string) $entity['user']['name'] : '';
		$tracks         = array();

		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			if ( isset( $item['is_streamable'] ) && false === $item['is_streamable'] ) {
				continue;
			}
			$track_id = sanitize_text_field( (string) $item['id'] );
			if ( '' === $track_id ) {
				continue;
			}
			$stream_url = add_query_arg(
				array( 'app_name' => $app_name ),
				$api_base . '/tracks/' . rawurlencode( $track_id ) . '/stream'
			);
			$permalink  = isset( $item['permalink'] ) && is_string( $item['permalink'] ) && '' !== $item['permalink']
				? 'https://audius.co' . $item['permalink']
				: $audius_url;

			$tracks[] = array(
				'index'          => $idx + 1,
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => isset( $item['user']['name'] ) ? (string) $item['user']['name'] : $default_artist,
				'duration'       => isset( $item['duration'] ) ? (float) $item['duration'] : 0.0,
				'streamUrl'      => $stream_url,
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => false,
				'sourceUrl'      => $permalink,
			);
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		$artwork = '';
		if ( isset( $entity['artwork'] ) && is_array( $entity['artwork'] ) ) {
			$artwork = (string) ( $entity['artwork']['480x480'] ?? $entity['artwork']['150x150'] ?? '' );
		}

		return array(
			'provider'       => 'audius',
			'providerLabel'  => __( 'Audius', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => (string) ( $entity['playlist_name'] ?? $entity['title'] ?? '' ),
			'artist'         => $default_artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $audius_url,
			'isTimedPreview' => false,
			'ttl'            => self::MEDIUM_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Resolves Mixcloud show URLs into official Mixcloud iframe widgets with oEmbed metadata.
	 *
	 * @param int    $release_id   Release post ID.
	 * @param string $mixcloud_url Validated `https://mixcloud.com/...` URL.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_mixcloud( int $release_id, string $mixcloud_url ): ?array {
		$parsed = wp_parse_url( $mixcloud_url );
		if (
			'' === $mixcloud_url
			|| empty( $parsed['scheme'] )
			|| 'https' !== strtolower( (string) $parsed['scheme'] )
			|| empty( $parsed['host'] )
			|| ! preg_match( '/(^|\.)mixcloud\.com$/i', (string) $parsed['host'] )
		) {
			return null;
		}

		$path     = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		if ( count( $segments ) < 2 ) {
			return null;
		}
		$feed_path  = '/' . rawurlencode( $segments[0] ) . '/' . rawurlencode( $segments[1] ) . '/';
		$iframe_src = 'https://player-widget.mixcloud.com/widget/iframe/?hide_cover=1&light=1&feed=' . rawurlencode( $feed_path );

		$title   = '';
		$artist  = '';
		$artwork = '';
		if ( function_exists( 'wp_safe_remote_get' ) ) {
			$oembed_url = add_query_arg(
				array(
					'url'    => $mixcloud_url,
					'format' => 'json',
				),
				'https://app.mixcloud.com/oembed/'
			);
			$response   = wp_safe_remote_get(
				$oembed_url,
				array(
					'timeout' => 6,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( is_array( $decoded ) ) {
					$title   = (string) ( $decoded['title'] ?? '' );
					$artist  = (string) ( $decoded['author_name'] ?? '' );
					$artwork = (string) ( $decoded['image'] ?? '' );
				}
			}
		}

		$title_attr = function_exists( 'esc_attr__' ) ? esc_attr__( 'Mixcloud Audio Player', 'mds-audio-providers' ) : 'Mixcloud Audio Player';
		$src_attr   = function_exists( 'esc_url' ) ? esc_url( $iframe_src ) : $iframe_src;

		return array(
			'provider'       => 'mixcloud',
			'providerLabel'  => __( 'Mixcloud', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => $title,
			'artist'         => $artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $mixcloud_url,
			'isTimedPreview' => false,
			'ttl'            => self::LONG_TTL,
			'embedHtml'      => '<iframe title="' . $title_attr . '" width="100%" height="120" style="border:0;" src="' . $src_attr . '" allow="encrypted-media; fullscreen; autoplay; idle-detection; speaker-selection; web-share;" loading="lazy"></iframe>',
			'tracks'         => array(),
		);
	}

	/**
	 * Resolves Audiomack song/album/playlist URLs into official iframe embeds.
	 *
	 * @param int    $release_id    Release post ID.
	 * @param string $audiomack_url Validated `https://audiomack.com/...` URL.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_audiomack( int $release_id, string $audiomack_url ): ?array {
		$parsed = wp_parse_url( $audiomack_url );
		if (
			'' === $audiomack_url
			|| empty( $parsed['scheme'] )
			|| 'https' !== strtolower( (string) $parsed['scheme'] )
			|| empty( $parsed['host'] )
			|| ! preg_match( '/(^|\.)audiomack\.com$/i', (string) $parsed['host'] )
		) {
			return null;
		}

		$path     = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		if ( count( $segments ) < 3 ) {
			return null;
		}
		if ( 'embed' === strtolower( $segments[0] ) && count( $segments ) >= 4 ) {
			array_shift( $segments );
		}

		$artist = self::clean_slug( $segments[0] );
		$type   = strtolower( $segments[1] );
		$slug   = self::clean_slug( $segments[2] );
		if ( '' === $artist || '' === $slug || ! in_array( $type, array( 'song', 'album', 'playlist' ), true ) ) {
			return null;
		}

		$embed_url  = sprintf( 'https://audiomack.com/embed/%s/%s/%s?background=1', $artist, $type, $slug );
		$height     = 'song' === $type ? 252 : 352;
		$title_attr = function_exists( 'esc_attr__' ) ? esc_attr__( 'Audiomack Audio Player', 'mds-audio-providers' ) : 'Audiomack Audio Player';
		$src_attr   = function_exists( 'esc_url' ) ? esc_url( $embed_url ) : $embed_url;

		return array(
			'provider'       => 'audiomack',
			'providerLabel'  => __( 'Audiomack', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'sourceUrl'      => $audiomack_url,
			'isTimedPreview' => false,
			'ttl'            => self::LONG_TTL,
			'embedHtml'      => '<iframe title="' . $title_attr . '" src="' . $src_attr . '" scrolling="no" width="100%" height="' . (int) $height . '" style="border:0;" allow="autoplay; encrypted-media; fullscreen" loading="lazy"></iframe>',
			'tracks'         => array(),
		);
	}

	/**
	 * Resolves Spotify Web API UPC lookups with optional official Spotify iframe fallback.
	 *
	 * @param int    $release_id     Release post ID.
	 * @param string $upc            Digits-only UPC/EAN.
	 * @param string $spotify_url    Optional Spotify URL.
	 * @param bool   $embed_fallback Whether iframe fallback is permitted.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_spotify( int $release_id, string $upc, string $spotify_url, bool $embed_fallback ): ?array {
		$direct_target = $embed_fallback ? self::parse_spotify_url( $spotify_url ) : null;
		$token         = '' !== $upc ? self::get_oauth_token( 'spotify', 'https://accounts.spotify.com/api/token' ) : '';

		if ( '' === $token ) {
			if ( null !== $direct_target ) {
				return self::build_spotify_embed( $release_id, $direct_target['type'], $direct_target['id'], $direct_target['url'] );
			}
			return null;
		}

		$search = self::oauth_api_get(
			'https://api.spotify.com/v1/search',
			array(
				'q'     => 'upc:' . $upc,
				'type'  => 'album',
				'limit' => 1,
			),
			$token
		);

		$album = is_array( $search ) && isset( $search['albums']['items'][0] ) && is_array( $search['albums']['items'][0] )
			? $search['albums']['items'][0]
			: null;

		if ( null === $album || empty( $album['id'] ) ) {
			return null !== $direct_target
				? self::build_spotify_embed( $release_id, $direct_target['type'], $direct_target['id'], $direct_target['url'] )
				: null;
		}

		$album_id    = sanitize_text_field( (string) $album['id'] );
		$tracks_resp = self::oauth_api_get(
			'https://api.spotify.com/v1/albums/' . rawurlencode( $album_id ) . '/tracks',
			array( 'limit' => 50 ),
			$token
		);
		$raw_tracks  = is_array( $tracks_resp ) && isset( $tracks_resp['items'] ) && is_array( $tracks_resp['items'] )
			? $tracks_resp['items']
			: array();

		$artist_name = isset( $album['artists'][0]['name'] ) ? (string) $album['artists'][0]['name'] : '';
		$source_url  = isset( $album['external_urls']['spotify'] ) ? (string) $album['external_urls']['spotify'] : '';
		$artwork     = isset( $album['images'][0]['url'] ) ? (string) $album['images'][0]['url'] : '';
		$album_title = (string) ( $album['name'] ?? '' );
		$tracks      = array();

		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['preview_url'] ) || ! is_string( $item['preview_url'] ) ) {
				continue;
			}
			$tracks[] = array(
				'index'          => isset( $item['track_number'] ) ? (int) $item['track_number'] : ( $idx + 1 ),
				'title'          => (string) ( $item['name'] ?? '' ),
				'artist'         => isset( $item['artists'][0]['name'] ) ? (string) $item['artists'][0]['name'] : $artist_name,
				'duration'       => 30.0,
				'streamUrl'      => $item['preview_url'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => true,
				'sourceUrl'      => isset( $item['external_urls']['spotify'] ) ? (string) $item['external_urls']['spotify'] : $source_url,
			);
		}

		if ( empty( $tracks ) ) {
			return $embed_fallback
				? self::build_spotify_embed( $release_id, 'album', $album_id, $source_url, $album_title, $artist_name, $artwork )
				: null;
		}

		return array(
			'provider'       => 'spotify',
			'providerLabel'  => __( 'Spotify', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => $album_title,
			'artist'         => $artist_name,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $source_url,
			'isTimedPreview' => true,
			'ttl'            => self::LONG_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Resolves SoundCloud OAuth 2.1 track/playlist previews.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $sc_url     SoundCloud URL.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_soundcloud( int $release_id, string $sc_url ): ?array {
		$parsed = wp_parse_url( $sc_url );
		if ( '' === $sc_url || empty( $parsed['host'] ) || ! preg_match( '/(^|\.)soundcloud\.com$/i', (string) $parsed['host'] ) ) {
			return null;
		}

		$token = self::get_oauth_token( 'soundcloud', 'https://secure.soundcloud.com/oauth/token' );
		if ( '' === $token ) {
			return null;
		}

		$resolved = self::oauth_api_get( 'https://api.soundcloud.com/resolve', array( 'url' => $sc_url ), $token );
		if ( ! is_array( $resolved ) ) {
			return null;
		}

		$kind       = (string) ( $resolved['kind'] ?? '' );
		$raw_tracks = array();
		if ( 'playlist' === $kind && isset( $resolved['tracks'] ) && is_array( $resolved['tracks'] ) ) {
			$raw_tracks = array_slice( $resolved['tracks'], 0, 8 );
		} elseif ( 'track' === $kind ) {
			$raw_tracks = array( $resolved );
		}

		$tracks   = array();
		$deadline = microtime( true ) + 6.0;
		foreach ( $raw_tracks as $idx => $item ) {
			if ( ! empty( $tracks ) && microtime( true ) >= $deadline ) {
				break;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$urn = isset( $item['urn'] ) && is_string( $item['urn'] )
				? $item['urn']
				: ( ! empty( $item['id'] ) ? 'soundcloud:tracks:' . abs( (int) $item['id'] ) : '' );
			if ( '' === $urn ) {
				continue;
			}

			$stream_info = self::resolve_soundcloud_track_stream( $urn, $token );
			if ( null === $stream_info ) {
				continue;
			}

			$tracks[] = array(
				'index'          => $idx + 1,
				'title'          => (string) ( $item['title'] ?? '' ),
				'artist'         => isset( $item['user']['username'] ) ? (string) $item['user']['username'] : '',
				'duration'       => $stream_info['isTimedPreview']
					? 30.0
					: ( isset( $item['duration'] ) ? ( (float) $item['duration'] / 1000.0 ) : 0.0 ),
				'streamUrl'      => $stream_info['url'],
				'mimeType'       => $stream_info['mime'],
				'isTimedPreview' => $stream_info['isTimedPreview'],
				'sourceUrl'      => (string) ( $item['permalink_url'] ?? $sc_url ),
			);
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		$artwork = (string) ( $resolved['artwork_url'] ?? '' );
		return array(
			'provider'       => 'soundcloud',
			'providerLabel'  => __( 'SoundCloud', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => (string) ( $resolved['title'] ?? '' ),
			'artist'         => isset( $resolved['user']['username'] ) ? (string) $resolved['user']['username'] : '',
			'artworkUrl'     => str_replace( '-large.', '-t300x300.', $artwork ),
			'sourceUrl'      => (string) ( $resolved['permalink_url'] ?? $sc_url ),
			'isTimedPreview' => (bool) $tracks[0]['isTimedPreview'],
			'ttl'            => self::SHORT_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Resolves TIDAL Open API v2 UPC lookup or TIDAL URL into official iframe embed.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $upc        Digits-only UPC/EAN.
	 * @param string $tidal_url  Optional TIDAL URL.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_tidal( int $release_id, string $upc, string $tidal_url ): ?array {
		$direct = self::parse_tidal_url( $tidal_url );
		if ( null !== $direct ) {
			return self::build_tidal_embed( $release_id, $direct['type'], $direct['id'], $direct['url'] );
		}

		if ( '' === $upc || ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}

		$token = self::get_oauth_token( 'tidal', 'https://auth.tidal.com/v1/oauth2/token' );
		if ( '' === $token ) {
			return null;
		}

		$lookup_url = add_query_arg(
			array(
				'filter[barcodeId]' => $upc,
				'countryCode'       => 'US',
			),
			'https://openapi.tidal.com/v2/albums'
		);
		$response   = wp_safe_remote_get(
			$lookup_url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/vnd.api+json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$album   = is_array( $decoded ) && isset( $decoded['data'][0] ) && is_array( $decoded['data'][0] )
			? $decoded['data'][0]
			: null;
		if ( null === $album || empty( $album['id'] ) ) {
			return null;
		}

		$album_id = (string) preg_replace( '/\D+/', '', (string) $album['id'] );
		if ( '' === $album_id ) {
			return null;
		}

		$attrs = isset( $album['attributes'] ) && is_array( $album['attributes'] ) ? $album['attributes'] : array();
		$title = (string) ( $attrs['title'] ?? '' );

		return self::build_tidal_embed( $release_id, 'album', $album_id, 'https://tidal.com/browse/album/' . $album_id, $title );
	}

	/**
	 * Resolves Jamendo API v3.0 direct MP3 streams.
	 *
	 * @param int    $release_id  Release post ID.
	 * @param string $jamendo_url Jamendo album or track URL.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_jamendo( int $release_id, string $jamendo_url ): ?array {
		$target = self::parse_jamendo_url( $jamendo_url );
		$creds  = GatewayCredentials::get( 'jamendo' );
		if ( null === $target || '' === $creds['client_id'] || ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}

		$query = array(
			'client_id'   => $creds['client_id'],
			'format'      => 'json',
			'audioformat' => 'mp32',
			'limit'       => 20,
		);
		if ( 'album' === $target['type'] ) {
			$query['album_id'] = $target['id'];
		} else {
			$query['id'] = $target['id'];
		}

		$response = wp_safe_remote_get(
			add_query_arg( $query, 'https://api.jamendo.com/v3.0/tracks/' ),
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$results = is_array( $decoded ) && isset( $decoded['results'] ) && is_array( $decoded['results'] ) ? $decoded['results'] : array();

		$tracks      = array();
		$album_title = '';
		$artist_name = '';
		$artwork     = '';

		foreach ( $results as $idx => $item ) {
			if ( ! is_array( $item ) || empty( $item['audio'] ) || ! is_string( $item['audio'] ) ) {
				continue;
			}
			if ( '' === $album_title && ! empty( $item['album_name'] ) ) {
				$album_title = (string) $item['album_name'];
			}
			if ( '' === $artist_name && ! empty( $item['artist_name'] ) ) {
				$artist_name = (string) $item['artist_name'];
			}
			if ( '' === $artwork && ! empty( $item['album_image'] ) ) {
				$artwork = (string) $item['album_image'];
			}
			$tracks[] = array(
				'index'          => isset( $item['position'] ) ? max( 1, (int) $item['position'] ) : ( $idx + 1 ),
				'title'          => (string) ( $item['name'] ?? '' ),
				'artist'         => (string) ( $item['artist_name'] ?? $artist_name ),
				'duration'       => isset( $item['duration'] ) ? (float) $item['duration'] : 0.0,
				'streamUrl'      => $item['audio'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => false,
				'sourceUrl'      => (string) ( $item['shareurl'] ?? $target['url'] ),
			);
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		return array(
			'provider'       => 'jamendo',
			'providerLabel'  => __( 'Jamendo', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => $album_title,
			'artist'         => $artist_name,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $target['url'],
			'isTimedPreview' => false,
			'ttl'            => self::LONG_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Resolves Musicfetch multi-DSP UPC lookups using MDS's centralized `x-token`.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $upc        Digits-only UPC/EAN.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_musicfetch( int $release_id, string $upc ): ?array {
		$creds = GatewayCredentials::get( 'musicfetch' );
		if ( '' === $upc || '' === $creds['client_secret'] || ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}

		$url      = add_query_arg(
			array(
				'upc'      => $upc,
				'services' => 'appleMusic,deezer,spotify',
			),
			'https://api.musicfetch.io/upc'
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'  => 'application/json',
					'x-token' => $creds['client_secret'],
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$result     = is_array( $decoded ) && isset( $decoded['result'] ) && is_array( $decoded['result'] )
			? $decoded['result']
			: ( is_array( $decoded ) ? $decoded : array() );
		$title      = (string) ( $result['name'] ?? $result['title'] ?? '' );
		$artist     = isset( $result['artists'][0]['name'] ) ? (string) $result['artists'][0]['name'] : '';
		$artwork    = isset( $result['image']['url'] ) ? (string) $result['image']['url'] : '';
		$source_url = (string) ( $result['link'] ?? '' );
		$tracks     = array();

		if ( isset( $result['tracks'] ) && is_array( $result['tracks'] ) ) {
			foreach ( $result['tracks'] as $idx => $item ) {
				if ( ! is_array( $item ) || empty( $item['previewUrl'] ) || ! is_string( $item['previewUrl'] ) ) {
					continue;
				}
				$tracks[] = array(
					'index'          => $idx + 1,
					'title'          => (string) ( $item['name'] ?? $item['title'] ?? '' ),
					'artist'         => isset( $item['artists'][0]['name'] ) ? (string) $item['artists'][0]['name'] : $artist,
					'duration'       => 30.0,
					'streamUrl'      => $item['previewUrl'],
					'mimeType'       => 'audio/mpeg',
					'isTimedPreview' => true,
					'sourceUrl'      => (string) ( $item['link'] ?? $source_url ),
				);
			}
		} elseif ( ! empty( $result['previewUrl'] ) && is_string( $result['previewUrl'] ) ) {
			$tracks[] = array(
				'index'          => 1,
				'title'          => $title,
				'artist'         => $artist,
				'duration'       => 30.0,
				'streamUrl'      => $result['previewUrl'],
				'mimeType'       => 'audio/mpeg',
				'isTimedPreview' => true,
				'sourceUrl'      => $source_url,
			);
		}

		if ( empty( $tracks ) ) {
			return null;
		}

		return array(
			'provider'       => 'musicfetch',
			'providerLabel'  => __( 'Musicfetch', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => $title,
			'artist'         => $artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => $source_url,
			'isTimedPreview' => true,
			'ttl'            => self::LONG_TTL,
			'tracks'         => $tracks,
		);
	}

	/**
	 * Resolves a single SoundCloud track URN to its preview or progressive stream URL.
	 *
	 * @param string $urn   SoundCloud track URN.
	 * @param string $token OAuth Bearer token.
	 * @return array{url: string, mime: string, isTimedPreview: bool}|null
	 */
	private static function resolve_soundcloud_track_stream( string $urn, string $token ): ?array {
		$encoded = rawurlencode( $urn );
		$preview = self::oauth_api_get( "https://api.soundcloud.com/tracks/{$encoded}/preview", array(), $token );
		if ( is_array( $preview ) && ! empty( $preview['preview_mp3_128_url'] ) && is_string( $preview['preview_mp3_128_url'] ) ) {
			return array(
				'url'            => $preview['preview_mp3_128_url'],
				'mime'           => 'audio/mpeg',
				'isTimedPreview' => true,
			);
		}

		$streams = self::oauth_api_get( "https://api.soundcloud.com/tracks/{$encoded}/streams", array(), $token );
		if ( is_array( $streams ) ) {
			if ( ! empty( $streams['http_mp3_128_url'] ) && is_string( $streams['http_mp3_128_url'] ) ) {
				return array(
					'url'            => $streams['http_mp3_128_url'],
					'mime'           => 'audio/mpeg',
					'isTimedPreview' => false,
				);
			}
			if ( ! empty( $streams['hls_aac_160_url'] ) && is_string( $streams['hls_aac_160_url'] ) ) {
				return array(
					'url'            => $streams['hls_aac_160_url'],
					'mime'           => 'application/x-mpegURL',
					'isTimedPreview' => false,
				);
			}
		}

		return null;
	}

	/**
	 * Requests and caches an OAuth Client Credentials token on the gateway server.
	 *
	 * @param string $provider  Provider slug (`spotify`, `soundcloud`, `tidal`).
	 * @param string $token_url OAuth token endpoint URL.
	 * @return string Bearer token, or empty string.
	 */
	private static function get_oauth_token( string $provider, string $token_url ): string {
		$cache_key = 'oauth_' . sanitize_key( $provider );
		$cached    = self::cache_get( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$creds = GatewayCredentials::get( $provider );
		if ( '' === $creds['client_id'] || '' === $creds['client_secret'] || ! function_exists( 'wp_safe_remote_post' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard HTTP Basic Auth for OAuth Client Credentials.
		$auth     = base64_encode( $creds['client_id'] . ':' . $creds['client_secret'] );
		$response = wp_safe_remote_post(
			$token_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token   = is_array( $decoded ) && ! empty( $decoded['access_token'] ) && is_string( $decoded['access_token'] )
			? $decoded['access_token']
			: '';
		if ( '' === $token ) {
			return '';
		}

		$expires_in = isset( $decoded['expires_in'] ) ? max( 60, (int) $decoded['expires_in'] - 60 ) : 3000;
		self::cache_set( $cache_key, $token, $expires_in );
		return $token;
	}

	/**
	 * Performs an authenticated GET request against an upstream OAuth API.
	 *
	 * @param string               $endpoint API endpoint URL.
	 * @param array<string, mixed> $query    Query args.
	 * @param string               $token    OAuth Bearer token.
	 * @return array<string, mixed>|null
	 */
	private static function oauth_api_get( string $endpoint, array $query, string $token ): ?array {
		if ( ! function_exists( 'wp_safe_remote_get' ) ) {
			return null;
		}
		$url      = ! empty( $query ) ? add_query_arg( $query, $endpoint ) : $endpoint;
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Builds an official Spotify iframe embed manifest.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $type       Entity type (`album`, `track`, `playlist`).
	 * @param string $entity_id  Spotify base62 ID.
	 * @param string $source_url Canonical Spotify URL.
	 * @param string $title      Optional title.
	 * @param string $artist     Optional artist.
	 * @param string $artwork    Optional artwork URL.
	 * @return array<string, mixed>
	 */
	private static function build_spotify_embed(
		int $release_id,
		string $type,
		string $entity_id,
		string $source_url = '',
		string $title = '',
		string $artist = '',
		string $artwork = ''
	): array {
		$clean_type = in_array( $type, array( 'album', 'track', 'playlist' ), true ) ? $type : 'album';
		$clean_id   = (string) preg_replace( '/[^A-Za-z0-9]/', '', $entity_id );
		$height     = 'track' === $clean_type ? 152 : 352;
		$iframe_src = sprintf( 'https://open.spotify.com/embed/%s/%s?utm_source=generator', $clean_type, $clean_id );
		$title_attr = function_exists( 'esc_attr__' ) ? esc_attr__( 'Spotify Audio Player', 'mds-audio-providers' ) : 'Spotify Audio Player';
		$src_attr   = function_exists( 'esc_url' ) ? esc_url( $iframe_src ) : $iframe_src;

		return array(
			'provider'       => 'spotify',
			'providerLabel'  => __( 'Spotify', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => $title,
			'artist'         => $artist,
			'artworkUrl'     => $artwork,
			'sourceUrl'      => '' !== $source_url ? $source_url : sprintf( 'https://open.spotify.com/%s/%s', $clean_type, $clean_id ),
			'isTimedPreview' => true,
			'ttl'            => self::LONG_TTL,
			'embedHtml'      => '<iframe title="' . $title_attr . '" style="border:0;border-radius:12px;width:100%;height:' . (int) $height . 'px;" src="' . $src_attr . '" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>',
			'tracks'         => array(),
		);
	}

	/**
	 * Builds an official TIDAL iframe embed manifest.
	 *
	 * @param int    $release_id Release post ID.
	 * @param string $type       Entity type (`album`, `track`, `playlist`).
	 * @param string $entity_id  TIDAL entity ID.
	 * @param string $source_url Canonical TIDAL URL.
	 * @param string $title      Optional title.
	 * @return array<string, mixed>
	 */
	private static function build_tidal_embed(
		int $release_id,
		string $type,
		string $entity_id,
		string $source_url = '',
		string $title = ''
	): array {
		$path_map   = array(
			'album'    => array(
				'segment' => 'albums',
				'height'  => 275,
			),
			'track'    => array(
				'segment' => 'tracks',
				'height'  => 120,
			),
			'playlist' => array(
				'segment' => 'playlists',
				'height'  => 380,
			),
		);
		$config     = $path_map[ $type ] ?? $path_map['album'];
		$clean_id   = (string) preg_replace( '/[^A-Za-z0-9\-]/', '', $entity_id );
		$iframe_src = sprintf( 'https://embed.tidal.com/%s/%s', $config['segment'], $clean_id );
		$title_attr = function_exists( 'esc_attr__' ) ? esc_attr__( 'TIDAL Audio Player', 'mds-audio-providers' ) : 'TIDAL Audio Player';
		$src_attr   = function_exists( 'esc_url' ) ? esc_url( $iframe_src ) : $iframe_src;

		return array(
			'provider'       => 'tidal',
			'providerLabel'  => __( 'TIDAL', 'mds-audio-providers' ),
			'releaseId'      => $release_id,
			'title'          => $title,
			'sourceUrl'      => '' !== $source_url ? $source_url : sprintf( 'https://tidal.com/browse/%s/%s', $type, $clean_id ),
			'isTimedPreview' => true,
			'ttl'            => self::LONG_TTL,
			'embedHtml'      => '<iframe title="' . $title_attr . '" src="' . $src_attr . '" width="100%" height="' . (int) $config['height'] . '" style="border:0;" allow="encrypted-media; fullscreen; clipboard-write; web-share" loading="lazy"></iframe>',
			'tracks'         => array(),
		);
	}

	/**
	 * Parses a Spotify album/track/playlist URL.
	 *
	 * @param string $url Spotify URL.
	 * @return array{type: string, id: string, url: string}|null
	 */
	private static function parse_spotify_url( string $url ): ?array {
		if ( '' === $url ) {
			return null;
		}
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) || ! preg_match( '/(^|\.)open\.spotify\.com$/i', (string) $parsed['host'] ) ) {
			return null;
		}
		$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( preg_match( '#^(?:intl-[a-z-]+/)?(album|track|playlist)/([A-Za-z0-9]{10,40})(?:/|$)#i', $path, $matches ) ) {
			return array(
				'type' => strtolower( $matches[1] ),
				'id'   => $matches[2],
				'url'  => $url,
			);
		}
		return null;
	}

	/**
	 * Parses a TIDAL album/track/playlist URL.
	 *
	 * @param string $url TIDAL URL.
	 * @return array{type: string, id: string, url: string}|null
	 */
	private static function parse_tidal_url( string $url ): ?array {
		if ( '' === $url ) {
			return null;
		}
		$parsed = wp_parse_url( $url );
		if (
			empty( $parsed['scheme'] )
			|| 'https' !== strtolower( (string) $parsed['scheme'] )
			|| empty( $parsed['host'] )
			|| ! preg_match( '/(^|\.)tidal\.com$/i', (string) $parsed['host'] )
		) {
			return null;
		}
		$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( preg_match( '#^(?:browse/)?(album|track|playlist)s?/([A-Za-z0-9\-]{4,64})(?:/|$)#i', $path, $matches ) ) {
			return array(
				'type' => strtolower( $matches[1] ),
				'id'   => $matches[2],
				'url'  => $url,
			);
		}
		return null;
	}

	/**
	 * Parses a Jamendo album/track URL.
	 *
	 * @param string $url Jamendo URL.
	 * @return array{type: string, id: string, url: string}|null
	 */
	private static function parse_jamendo_url( string $url ): ?array {
		if ( '' === $url ) {
			return null;
		}
		$parsed = wp_parse_url( $url );
		if (
			empty( $parsed['scheme'] )
			|| 'https' !== strtolower( (string) $parsed['scheme'] )
			|| empty( $parsed['host'] )
			|| ! preg_match( '/(^|\.)(jamendo\.com|jamen\.do)$/i', (string) $parsed['host'] )
		) {
			return null;
		}
		$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( preg_match( '#^(album|track|a|t)/(\d+)(?:/|$)#i', $path, $matches ) ) {
			$raw_type = strtolower( $matches[1] );
			return array(
				'type' => ( 'a' === $raw_type || 'album' === $raw_type ) ? 'album' : 'track',
				'id'   => $matches[2],
				'url'  => $url,
			);
		}
		return null;
	}

	/**
	 * Builds a deterministic cross-customer cache key for a provider lookup.
	 *
	 * @param string $provider       Provider slug.
	 * @param string $upc            UPC/EAN.
	 * @param string $url            Provider URL.
	 * @param int    $album_id       Bandcamp album ID.
	 * @param bool   $embed_fallback Embed fallback flag.
	 * @return string
	 */
	private static function build_cache_key( string $provider, string $upc, string $url, int $album_id, bool $embed_fallback ): string {
		return 'ap_' . $provider . '_' . md5( $upc . '|' . $url . '|' . $album_id . '|' . ( $embed_fallback ? '1' : '0' ) );
	}

	/**
	 * Reads a value from the shared gateway object cache (with transient fallback).
	 *
	 * @param string $key Cache key.
	 * @return mixed
	 */
	private static function cache_get( string $key ): mixed {
		if ( function_exists( 'wp_cache_get' ) ) {
			$val = wp_cache_get( $key, self::CACHE_GROUP );
			if ( false !== $val ) {
				return $val;
			}
		}
		if ( function_exists( 'get_transient' ) ) {
			return get_transient( 'mds_cg_' . $key );
		}
		return false;
	}

	/**
	 * Stores a value in the shared gateway object cache (and transient fallback).
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL in seconds.
	 * @return void
	 */
	private static function cache_set( string $key, mixed $value, int $ttl ): void {
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $key, $value, self::CACHE_GROUP, $ttl );
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'mds_cg_' . $key, $value, $ttl );
		}
	}

	/**
	 * Lightweight slug sanitizer usable both inside WordPress and standalone CLI tests.
	 *
	 * @param string $raw Raw path segment.
	 * @return string
	 */
	private static function clean_slug( string $raw ): string {
		if ( function_exists( 'sanitize_title' ) ) {
			return sanitize_title( $raw );
		}
		return trim( (string) preg_replace( '/[^a-z0-9\-]+/', '-', strtolower( $raw ) ), '-' );
	}
}
