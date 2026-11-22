<?php
/**
 * Standalone checks for the Release Links player filter callback.
 *
 * Run with: php tests/release-links-player.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'MDS_BC_VERSION', '1.0.0' );
define( 'MDS_BC_PATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

$GLOBALS['settings_option'] = array();
$GLOBALS['album_meta']      = array();

function get_option( $name, $default = false )
{
    return 'mds_bandcamp__settings' === $name ? $GLOBALS['settings_option'] : $default;
}

function get_post_meta( $post_id, $key, $single )
{
    return 'mds_bandcamp_integration' === $key ? $GLOBALS['album_meta'] : '';
}

function absint( $value )
{
    return abs( (int) $value );
}

function wp_parse_url( $url, $component = -1 )
{
    return parse_url( $url, $component );
}

function shortcode_atts( $defaults, $atts, $shortcode = '' )
{
    return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) );
}

function esc_attr( $value )
{
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_url( $value )
{
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_html( $value )
{
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_html__( $value, $domain )
{
    return esc_html( $value );
}

function __( $value, $domain = '' )
{
    return $value;
}

function locate_template( $template_name )
{
    return '';
}

function _doing_it_wrong( $function, $message, $version )
{
    throw new RuntimeException( $message );
}

function wp_kses_allowed_html( $context )
{
    return array();
}

function wp_kses( $html, $allowed_html )
{
    return $html;
}

require MDS_BC_PATH . 'vendor/autoload.php';

function check_player( $condition, $message )
{
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

// Markup supplied by an earlier extension is returned unchanged.
$GLOBALS['album_meta'] = array( 'album_id' => 2334859609 );
check_player(
    '<audio></audio>' === MDS\Bandcamp\Frontend\Frontend::filter_release_links_audio( '<audio></audio>', 92 ),
    'Earlier extension markup must be preserved.'
);

// A release without a saved Bandcamp album supplies no player.
$GLOBALS['album_meta'] = array();
check_player(
    '' === MDS\Bandcamp\Frontend\Frontend::filter_release_links_audio( '', 92 ),
    'Releases without Bandcamp data must not render a player.'
);

// A saved album renders the small player through the frontend-widget template.
$GLOBALS['album_meta'] = array( 'album_id' => 2334859609 );
$player = MDS\Bandcamp\Frontend\Frontend::filter_release_links_audio( '', 92 );
foreach ( array( 'mds-bandcamp-player-wrapper', 'album=2334859609', 'size=small', 'artwork=none', 'height: 42px' ) as $fragment ) {
    check_player( false !== strpos( $player, $fragment ), 'Player output is missing: ' . $fragment );
}

// The release subdomain derives from the stored Bandcamp URL.
$GLOBALS['album_meta'] = array( 'album_id' => 2334859609, 'url' => 'https://brianwalton.bandcamp.com/album/what-the-gold' );
check_player(
    'brianwalton' === MDS\Bandcamp\Core\Helpers::get_release_subdomain( 92 ),
    'The release subdomain must derive from the stored Bandcamp URL.'
);
$GLOBALS['album_meta'] = array( 'album_id' => 2334859609 );
check_player(
    '' === MDS\Bandcamp\Core\Helpers::get_release_subdomain( 92 ),
    'A release without a stored URL must have no subdomain.'
);

// Audio Player Manager registration and declarative settings rendering.
$registered = MDS\Bandcamp\Frontend\Frontend::register_audio_player( array() );
check_player(
    isset( $registered['bandcamp'] )
    && is_callable( $registered['bandcamp']['render_callback'] )
    && isset( $registered['bandcamp']['fields']['size'], $registered['bandcamp']['fields']['bgcol'], $registered['bandcamp']['fields']['linkcol'] ),
    'Bandcamp must register its player definition and declarative fields with the Audio Player Manager.'
);

$custom_rendered = MDS\Bandcamp\Frontend\Frontend::render_audio_player(
    92,
    array(
        'size'      => 'large',
        'height'    => 240,
        'bgcol'     => '#112233',
        'linkcol'   => '#AABBCC',
        'artwork'   => 'small',
        'tracklist' => true,
    )
);
foreach ( array( 'album=2334859609', 'size=large', 'bgcol=112233', 'linkcol=aabbcc', 'artwork=small', 'tracklist=true', 'height: 240px' ) as $fragment ) {
    check_player( false !== strpos( $custom_rendered, $fragment ), 'Custom Audio Player Manager settings missing: ' . $fragment );
}

echo "Release Links player filter tests passed.\n";
