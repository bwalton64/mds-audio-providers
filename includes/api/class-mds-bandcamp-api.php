<?php
/**
 * Bandcamp data and REST APIs for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fetches and parses data from Bandcamp API and pages.
 *
 * @since 1.0.0
 */
final class MDS_Bandcamp_API
{

    /**
     * Plugin key for settings and cache.
     *
     * @since 1.0.0
     * @var string
     */
    private static $plugin_key = 'mds_bandcamp';

    /**
     * Retrieve and cache Bandcamp data.
     *
     * @since 1.0.0
     *
     * @param array $args {
     *     Arguments for the API call.
     *
     *     @type string $endpoint The API endpoint (album, merch, discography, embed).
     *     @type string $id       The ID or URL to fetch data for.
     * }
     * @return array API response with cached status and data.
     */
    public static function fetch( array $args ): array
    {
        $key = self::cache_key( $args );

        // 1. Try cache first
        $cached = get_transient( $key );
        if ( false !== $cached ) {
            $cached['cached'] = true;
            return $cached;
        }

        // 2. Make API request
        $data = match ( $args['endpoint'] ) {
            'album'       => self::parse_album_data( self::get_album_data( $args['id'] ) ),
            'merch'       => self::parse_album_data( self::get_album_data( $args['id'], 'merch' ) ),
            'discography' => self::get_discography_data( $args['id'] ),
            'embed'       => self::get_embed_data( $args['id'] ),
            default       => array( 'error' => true, 'message' => 'Invalid endpoint' ),
        };

        // 3. Return error message if failed
        if ( ! empty( $data['error'] ) ) {
            return $data;
        }

        // 4. Cache and return data
        if ( ! empty( $data ) ) {
            $settings = get_option( self::$plugin_key . '__settings', array() );
            $cache_ttl = isset( $settings['transient'] ) ? (int) $settings['transient'] * MINUTE_IN_SECONDS : 60 * MINUTE_IN_SECONDS;
            set_transient( $key, $data, $cache_ttl );
        }

        return $data;
    }

    /**
     * Fetch HTML of a Bandcamp page by URL.
     *
     * @since 1.0.0
     *
     * @param string $url URL of page to parse.
     * @return string|array Full HTML content of page, or error array.
     */
    public static function fetch_bandcamp_page( $url )
    {
        // Basic SSRF hardening: require https://*.bandcamp.com.
        $p = wp_parse_url( $url );
        if ( empty( $p['scheme'] ) || 'https' !== strtolower( $p['scheme'] ) ) {
            return array( 'error' => true, 'message' => 'bad_scheme | Only https URLs are allowed.' );
        }

        if ( empty( $p['host'] ) || ! preg_match( '/(^|\.)bandcamp\.com$/i', $p['host'] ) ) {
            return array( 'error' => true, 'message' => 'bad_host | Only bandcamp.com URLs are allowed.' );
        }

        $args = array(
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Referer'    => isset( $_SERVER['HTTP_HOST'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : home_url(),
            ),
            'timeout' => 10,
        );

        $response = wp_safe_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            MDS_Bandcamp_Logging::log( 'error', 'Bandcamp page fetch failed', array( 'url' => $url, 'error' => $response->get_error_message() ) );
            return array( 'error' => true, 'message' => 'Request Error: ' . $response->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( 404 === $response_code ) {
            return array( 'error' => true, 'message' => 'Bandcamp URL does not exist' );
        }

        return wp_remote_retrieve_body( $response );
    }

    /**
     * Parse a URL for meta tag content by name.
     *
     * @since 1.0.0
     *
     * @param string $html      Fetched HTML content of page to parse.
     * @param string $meta_name Name of meta tag to capture.
     * @param string $url       Optional URL to fetch if HTML is empty.
     * @return string|false Value of meta field or false if doesn't exist.
     */
    public static function get_meta_tag_content( $html, $meta_name, $url = null )
    {
        if ( ! empty( $url ) ) {
            $html = self::fetch_bandcamp_page( $url );
            if ( isset( $html['error'] ) ) {
                MDS_Bandcamp_Logging::log( 'error', 'Embed data sync unsuccessful', array( 'url' => $url, 'message' => $html['message'] ) );
                return false;
            }
            MDS_Bandcamp_Logging::log( 'info', 'Embed data sync successful', array( 'url' => $url ) );
        }

        if ( false === $html || empty( $html ) ) {
            return false;
        }

        libxml_use_internal_errors( true );
        $doc = new DOMDocument();
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
        $doc->encoding = 'UTF-8';
        $metas         = $doc->getElementsByTagName( 'meta' );

        foreach ( $metas as $meta ) {
            if ( $meta->getAttribute( 'name' ) === $meta_name ) {
                return $meta->getAttribute( 'content' );
            }
        }

        return false;
    }

    /**
     * Get album data based on album ID.
     *
     * @since 1.0.0
     *
     * @param string $album_id Album ID from Bandcamp.
     * @param string $endpoint Optional endpoint (merch or null).
     * @return array Array of album data or error response.
     */
    private static function get_album_data( $album_id, $endpoint = null ): array
    {
        if ( empty( $album_id ) ) {
            return array( 'error' => true, 'message' => 'Album ID is required' );
        }

        $response = array(
            'error'   => true,
            'message' => '',
            'data'    => array(),
        );

        $url  = 'https://bandcamp.com/EmbeddedPlayer/album=' . $album_id;
        $html = self::fetch_bandcamp_page( $url );

        if ( empty( $html ) || isset( $html['error'] ) ) {
            $response['message'] = 'Could not fetch album page data.';
            MDS_Bandcamp_Logging::log( 'error', 'Bandcamp album data was empty', array( 'album_id' => $album_id ) );
            return $response;
        }

        // Step 1: Find the raw encoded data-player-data content.
        if ( preg_match( '/data-player-data="([^"]+)"/', $html, $matches ) ) {
            // Step 2: Unescape HTML entities.
            $json_escaped = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

            // Step 3: Decode JSON.
            $json_decoded = json_decode( $json_escaped, true );

            if ( JSON_ERROR_NONE === json_last_error() ) {
                $response['data']    = $json_decoded;
                $response['error']   = false;
                $response['message'] = 'Tracks exist';
            } else {
                MDS_Bandcamp_Logging::log( 'error', 'Bandcamp album data JSON decode error', array( 'album_id' => $album_id, 'error' => json_last_error_msg() ) );
                $response['message'] = 'JSON decode error: ' . json_last_error_msg();
                return $response;
            }
        } else {
            $response['message'] = 'Could not find data-player-data attribute.';
            return $response;
        }

        $data_type = ! empty( $endpoint ) ? ucwords( $endpoint ) : 'Album';
        MDS_Bandcamp_Logging::log( 'info', $data_type . ' data sync successful', array( 'album_id' => $album_id ) );

        return $response;
    }

    /**
     * Parse Bandcamp album data into formatted array for front-end output.
     *
     * @since 1.0.0
     *
     * @param array $bandcamp_album_data Array of raw data from Bandcamp album.
     * @return array Array of formatted album data.
     */
    private static function parse_album_data( $bandcamp_album_data ): array
    {
        if ( empty( $bandcamp_album_data['data'] ) ) {
            return $bandcamp_album_data;
        }

        $raw = $bandcamp_album_data['data'];
        $data = array(
            'artist'               => $raw['artist'] ?? '',
            'band_url'             => $raw['band_url'] ?? '',
            'album_id'             => $raw['album_id'] ?? '',
            'album_title'          => $raw['album_title'] ?? '',
            'album_art_thumb'      => $raw['album_art'] ?? '',
            'album_art_small'      => $raw['album_art_lg'] ?? '',
            'album_art_medium'     => 'https://f4.bcbits.com/img/a' . ( $raw['album_art_id'] ?? '' ) . '_16.jpg',
            'album_art_large'      => 'https://f4.bcbits.com/img/a' . ( $raw['album_art_id'] ?? '' ) . '_10.jpg',
            'album_link'           => $raw['linkback'] ?? '',
            'album_release_date'   => $raw['release_date'] ?? 20990101,
            'publish_date'         => $raw['publish_date'] ?? '',
            'featured_track_id'    => $raw['featured_track_id'] ?? '',
            'tracks'               => array(),
            'packages'             => array(),
        );

        // Loop through tracks.
        if ( ! empty( $raw['tracks'] ) ) {
            $t = 1;
            foreach ( $raw['tracks'] as $track ) {
                $data['tracks'][ $t ] = array(
                    'artist'       => $track['artist'] ?? '',
                    'title'        => $track['title'] ?? '',
                    'album_title'  => $track['album_title'] ?? '',
                    'id'           => $track['id'] ?? '',
                    'duration'     => gmdate( 'i:s', (int) ( $track['duration'] ?? 0 ) ),
                    'tracknum'     => ( $track['tracknum'] ?? 0 ) + 1,
                    'link'         => $track['title_link'] ?? '',
                    'mp3'          => $track['file']['mp3-128'] ?? '',
                );
                $t++;
            }
        }

        // Loop through packages.
        if ( ! empty( $raw['packages'] ) ) {
            $p = 0;
            foreach ( $raw['packages'] as $package ) {
                $data['packages'][ $p ] = array(
                    'id'                   => $package['id'] ?? '',
                    'url'                  => self::make_absolute_url( $package['url'] ?? '', $data['band_url'] ),
                    'type'                 => $package['type_name'] ?? '',
                    'title'                => $package['title'] ?? '',
                    'description'          => $package['description'] ?? '',
                    'price'                => number_format( $package['price'] ?? 0, 2, '.', ',' ),
                    'currency'             => $package['currency'] ?? '',
                    'quantity_total'       => $package['quantity'] ?? 9999,
                    'quantity_available'   => $package['quantity_available'] ?? 0,
                    'edition_size'         => $package['edition_size'] ?? '',
                    'album_id'             => $package['album_id'] ?? '',
                    'album_title'          => $package['album_title'] ?? '',
                    'album_artist'         => $package['album_artist'] ?? '',
                    'album_release_date'   => $package['album_release_date'] ?? '',
                    'photos'               => array(),
                );

                // Loop through photos.
                if ( ! empty( $package['arts'] ) ) {
                    $ph = 0;
                    foreach ( $package['arts'] as $photo ) {
                        $image_id = str_pad( $photo['image_id'] ?? 0, 10, '0', STR_PAD_LEFT );
                        $data['packages'][ $p ]['photos'][ $ph ] = array(
                            'thumb'  => 'https://f4.bcbits.com/img/' . $image_id . '_7.jpg',
                            'small'  => 'https://f4.bcbits.com/img/' . $image_id . '_2.jpg',
                            'medium' => 'https://f4.bcbits.com/img/' . $image_id . '_16.jpg',
                            'large'  => 'https://f4.bcbits.com/img/' . $image_id . '_10.jpg',
                        );
                        $ph++;
                    }
                }

                $p++;
            }
        }

        return $data;
    }

    /**
     * Get all albums from an account's music page.
     *
     * @since 1.0.0
     *
     * @param string $artist_url Subdomain/URL of artist/account from Bandcamp.
     * @return array Array of releases data.
     */
    private static function get_discography_data( $artist_url ): array
    {
        if ( empty( $artist_url ) ) {
            return array( 'error' => true, 'message' => 'Artist URL Required' );
        }

        // Set up the response array.
        $response = array(
            'error'   => true,
            'message' => '',
            'data'    => array(),
        );

        // Set the URL to fetch.
        $url = 'https://' . $artist_url . '.bandcamp.com/music';

        // Fetch the page HTML.
        $html = self::fetch_bandcamp_page( $url );

        // Return an error if the page was not fetched successfully.
        if ( empty( $html ) || isset( $html['error'] ) ) {
            MDS_Bandcamp_Logging::log( 'error', 'Data fetched from Bandcamp account was empty', array( 'artist_url' => $artist_url ) );
            return array( 'error' => true, 'message' => 'Could not fetch discography page data' );
        }

        // Build the URL for later use.
        $parsed = wp_parse_url( $url );
        $base   = $parsed['scheme'] . '://' . $parsed['host'];

        // Initialize libxml and DOM parsing.
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
        $dom->encoding = 'UTF-8';
        $xpath         = new DOMXPath( $dom );

        // Set variables for later use.
        $releases = array();
        $source   = '';
        $node     = null;

        // Keep only one container: #music-grid or #indexpage.
        $music_grid = $xpath->query( '//*[@id="music-grid"]' );
        $indexpage  = $xpath->query( '//*[@id="indexpage"]' );

        // Determine the page type and data source.
        if ( $music_grid && $music_grid->length ) {
            $node   = $music_grid->item( 0 );
            $source = 'music-grid';
        } elseif ( $indexpage && $indexpage->length ) {
            $node   = $indexpage->item( 0 );
            $source = 'indexpage';
        }

        // Return an error if neither element exists.
        if ( ! $node ) {
            return array( 'error' => true, 'message' => 'Album data not found.' );
        }

        if ( 'music-grid' === $source ) {
            $items = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " music-grid-item ")]', $node );
            if ( $items && $items->length ) {
                foreach ( $items as $item ) {
                    $release = self::parse_music_grid_item( $xpath, $item, $base );
                    if ( ! empty( $release['title'] ) || ! empty( $release['link'] ) ) {
                        $releases[] = $release;
                    }
                }
            }
        } elseif ( 'indexpage' === $source ) {
            $sets = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " ipCellSet ")]', $node );
            if ( $sets && $sets->length ) {
                foreach ( $sets as $set ) {
                    $release = self::parse_indexpage_item( $xpath, $set, $html, $base );
                    if ( ! empty( $release['title'] ) || ! empty( $release['link'] ) ) {
                        $releases[] = $release;
                    }
                }
            }
        }

        if ( empty( $releases ) ) {
            return array( 'error' => true, 'message' => 'No releases found on the Bandcamp page.' );
        }

        $response['data']['releases'] = $releases;
        $response['error']            = false;
        $response['message']          = 'Discography data sync successful';

        MDS_Bandcamp_Logging::log( 'info', 'Discography data sync successful', array( 'artist_url' => $artist_url ) );

        return $response;
    }

    /**
     * Get embed data for a URL.
     *
     * @since 1.0.0
     *
     * @param string $url URL to get embed data for.
     * @return array Embed data or error response.
     */
    private static function get_embed_data( $url ): array
    {
        $data = self::get_meta_tag_content( '', 'bc-page-properties', $url );
        if ( ! empty( $data ) ) {
            $decoded = json_decode( $data, true );
            return is_array( $decoded ) ? $decoded : array( 'error' => true, 'message' => 'Invalid embed data' );
        }
        return array( 'error' => true, 'message' => 'Could not fetch embed data' );
    }

    /**
     * Parse a music-grid item from DOM.
     *
     * @since 1.0.0
     *
     * @param DOMXPath $xpath XPath object.
     * @param DOMNode  $item  Item node to parse.
     * @param string   $base  Base URL for relative URL conversion.
     * @return array Parsed release data.
     */
    private static function parse_music_grid_item( DOMXPath $xpath, DOMNode $item, $base ): array
    {
        $release = array(
            'image'  => '',
            'title'  => '',
            'link'   => '',
            'artist' => '',
        );

        $link_nodes = $xpath->query( './/a[@href]', $item );
        if ( $link_nodes && $link_nodes->length ) {
            $release['link'] = self::make_absolute_url( $link_nodes->item( 0 )->getAttribute( 'href' ), $base );
        }

        $img_nodes = $xpath->query( './/img[@src]', $item );
        if ( $img_nodes && $img_nodes->length ) {
            $img_src = $img_nodes->item( 0 )->getAttribute( 'data-original' ) ?: $img_nodes->item( 0 )->getAttribute( 'src' );
            $release['image'] = self::make_absolute_url( $img_src, $base );
            $release['image'] = str_replace( array( '_2.jpg', '_9.jpg', '_10.jpg' ), '_16.jpg', $release['image'] );
        }

        $title_nodes = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " title ")]', $item );
        if ( $title_nodes && $title_nodes->length ) {
            $release['title'] = trim( preg_replace( '/\s+/', ' ', $title_nodes->item( 0 )->textContent ) );
        }

        return $release;
    }

    /**
     * Parse an indexpage item from DOM.
     *
     * @since 1.0.0
     *
     * @param DOMXPath $xpath XPath object.
     * @param DOMNode  $set   Item node to parse.
     * @param string   $html  Page HTML for artist extraction.
     * @param string   $base  Base URL for relative URL conversion.
     * @return array Parsed release data.
     */
    private static function parse_indexpage_item( DOMXPath $xpath, DOMNode $set, $html, $base ): array
    {
        $release = array(
            'image'  => '',
            'title'  => '',
            'link'   => '',
            'artist' => '',
        );

        $img_nodes = $xpath->query( './/img[@src]', $set );
        if ( $img_nodes && $img_nodes->length ) {
            $release['image'] = self::make_absolute_url( $img_nodes->item( 0 )->getAttribute( 'src' ), $base );
            $release['image'] = str_replace( array( '_2.jpg', '_9.jpg', '_10.jpg' ), '_16.jpg', $release['image'] );
        }

        $title_nodes = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " ipCellLabel1 ")]//a[@href]', $set );
        if ( $title_nodes && $title_nodes->length ) {
            $title_node       = $title_nodes->item( 0 );
            $release['title'] = trim( $title_node->textContent );
            $release['link']  = self::make_absolute_url( $title_node->getAttribute( 'href' ), $base );
        }

        $artist_nodes = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " ipCellLabel2 ")]//a', $set );
        if ( $artist_nodes && $artist_nodes->length ) {
            $release['artist'] = trim( $artist_nodes->item( 0 )->textContent );
        } else {
            $release['artist'] = self::get_meta_tag_content( $html, 'title' ) ?: '';
        }

        return $release;
    }

    /**
     * Convert relative URLs to absolute.
     *
     * @since 1.0.0
     *
     * @param string $value URL to convert.
     * @param string $base  Base URL for conversion.
     * @return string Absolute URL.
     */
    private static function make_absolute_url( $value, $base ): string
    {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return '';
        }

        if ( 0 === strpos( $value, '//' ) ) {
            $scheme = wp_parse_url( $base, PHP_URL_SCHEME );
            return $scheme . ':' . $value;
        }

        if ( preg_match( '#^https?://#i', $value ) ) {
            return $value;
        }

        if ( '/' === substr( $value, 0, 1 ) ) {
            return $base . $value;
        }

        return $base . '/' . ltrim( $value, '/' );
    }

    /**
     * Generate a safe transient key.
     *
     * @since 1.0.0
     *
     * @param array $args API call arguments.
     * @return string Safe cache key.
     */
    private static function cache_key( array $args ): string
    {
        $cache_key_raw = sanitize_title( self::$plugin_key . '__' . $args['endpoint'] . '_' . $args['id'] );
        return substr( $cache_key_raw, 0, 150 );
    }

    /**
     * Render a nice HTML block for errors.
     *
     * @since 1.0.0
     *
     * @param array $data Error data.
     * @return string Error HTML.
     */
    public static function render_error_html( array $data ): string
    {
        $msg = esc_html( $data['message'] ?? 'Unknown error' );
        return '
            <div class="bandcamp-block-message bandcamp-block-error">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M320 64C334.7 64 348.2 72.1 355.2 85L571.2 485C577.9 497.4 577.6 512.4 570.4 524.5C563.2 536.6 550.1 544 536 544L104 544C89.9 544 76.8 536.6 69.6 524.5C62.4 512.4 62.1 497.4 68.8 485L284.8 85C291.8 72.1 305.3 64 320 64zM320 416C302.3 416 288 430.3 288 448C288 465.7 302.3 480 320 480C337.7 480 352 465.7 352 448C352 430.3 337.7 416 320 416zM320 224C301.8 224 287.3 239.5 288.6 257.7L296 361.7C296.9 374.2 307.4 384 319.9 384C332.5 384 342.9 374.3 343.8 361.7L351.2 257.7C352.5 239.5 338.1 224 319.8 224z"/></svg>
                <span>' . $msg . '</span>
            </div>';
    }

    /**
     * Render a nice HTML block for notices.
     *
     * @since 1.0.0
     *
     * @param array $data Notice data.
     * @return string Notice HTML.
     */
    public static function render_notice_html( array $data ): string
    {
        $msg = esc_html( $data['message'] ?? 'Unknown' );
        return '
            <div class="bandcamp-block-message">
                <svg version="1.1" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" xml:space="preserve"> <path d="M10.1,8.9l-3.3,6h7l3.3-6H10.1z M21.6,11.9c0-5.3-4.3-9.6-9.6-9.6s-9.6,4.3-9.6,9.6s4.3,9.6,9.6,9.6     S21.6,17.2,21.6,11.9z M12,20.9c-5,0-9-4-9-9s4-9,9-9s9,4,9,9S17,20.9,12,20.9z"/> </svg>
                <span>' . $msg . '</span>
            </div>';
    }
}

