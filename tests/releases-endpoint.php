<?php
/**
 * Standalone checks for the editor releases endpoint.
 *
 * Run with: php tests/releases-endpoint.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );

class WP_Post
{
    public $ID;
    public $post_title;
    public function __construct( $id, $title )
    {
        $this->ID         = $id;
        $this->post_title = $title;
    }
}

final class MDS_RM_Release_Search
{
    public static function query( $term )
    {
        $GLOBALS['queried_term'] = $term;
        return $GLOBALS['catalog'];
    }
    public static function paginate( $posts, $page, $per_page = 20 )
    {
        return array(
            'posts' => array_slice( $posts, ( $page - 1 ) * $per_page, $per_page ),
            'pages' => (int) ceil( count( $posts ) / $per_page ),
        );
    }
    public static function describe( $posts )
    {
        return array_map(
            static function ( $post ) {
                return array(
                    'id'     => $post->ID,
                    'title'  => $post->post_title,
                    'artist' => $GLOBALS['artists'][ $post->ID ] ?? '',
                    'upc'    => '',
                );
            },
            $posts
        );
    }
}

class WP_REST_Request
{
    private $params;
    public function __construct( array $params )
    {
        $this->params = $params;
    }
    public function get_param( $key )
    {
        return $this->params[ $key ] ?? null;
    }
}

function sanitize_text_field( $value )
{
    return trim( strip_tags( (string) $value ) );
}
function absint( $value )
{
    return abs( (int) $value );
}
function wp_parse_url( $url, $component = -1 )
{
    return parse_url( $url, $component );
}
function get_post_meta( $post_id, $key, $single )
{
    return 'mds_bandcamp_integration' === $key ? ( $GLOBALS['bandcamp_meta'][ $post_id ] ?? '' ) : '';
}
function rest_ensure_response( $value )
{
    return $value;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}

require dirname( __DIR__ ) . '/vendor/autoload.php';

function check_endpoint( $condition, $message )
{
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

$GLOBALS['catalog'] = array(
    new WP_Post( 92, 'What the .... (Gold)' ),
    new WP_Post( 50, 'No Bandcamp Release' ),
);
$GLOBALS['artists'] = array( 92 => 'Brian Walton' );
$GLOBALS['bandcamp_meta'] = array(
    92 => array( 'album_id' => 2334859609, 'url' => 'https://brianwalton.bandcamp.com/album/what-the-gold' ),
    50 => array( 'url' => 'https://brianwalton.bandcamp.com/album/none' ),
);

$response = MDS\Bandcamp\Api\RestApi::handle_releases_request( new WP_REST_Request( array( 'search' => 'Walton', 'page' => 1 ) ) );
check_endpoint( 'Walton' === $GLOBALS['queried_term'], 'The search term must reach the shared Release search.' );
check_endpoint( 1 === count( $response['items'] ) && 1 === $response['pages'], 'Releases without an album ID must be filtered before pagination.' );
check_endpoint(
    array(
        'id'        => 92,
        'title'     => 'What the .... (Gold)',
        'artist'    => 'Brian Walton',
        'album_id'  => '2334859609',
        'url'       => 'https://brianwalton.bandcamp.com/album/what-the-gold',
        'subdomain' => 'brianwalton',
    ) === $response['items'][0],
    'Items must carry the title, artist, and Bandcamp identifiers.'
);

echo "Releases endpoint checks passed.\n";
