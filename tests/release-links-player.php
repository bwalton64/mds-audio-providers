<?php
/**
 * Standalone checks for the Release Links player filter callback.
 *
 * Run with: php tests/release-links-player.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'MDS_BANDCAMP_VERSION', '1.0.0' );
define( 'MDS_BANDCAMP_PATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

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

require MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-helpers.php';
require MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-dependencies.php';
require MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-utilities.php';
require MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-templates.php';
require MDS_BANDCAMP_PATH . 'includes/frontend/class-mds-bandcamp-frontend.php';

function check_player( $condition, $message )
{
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

// Markup supplied by an earlier extension is returned unchanged.
$GLOBALS['album_meta'] = array( 'album_id' => 2334859609 );
check_player(
    '<audio></audio>' === MDS_Bandcamp_Frontend::filter_release_links_audio( '<audio></audio>', 92 ),
    'Earlier extension markup must be preserved.'
);

// A release without a saved Bandcamp album supplies no player.
$GLOBALS['album_meta'] = array();
check_player(
    '' === MDS_Bandcamp_Frontend::filter_release_links_audio( '', 92 ),
    'Releases without Bandcamp data must not render a player.'
);

// A saved album renders the small player through the frontend-widget template.
$GLOBALS['album_meta'] = array( 'album_id' => 2334859609 );
$player = MDS_Bandcamp_Frontend::filter_release_links_audio( '', 92 );
foreach ( array( 'mds-bandcamp-player-wrapper', 'album=2334859609', 'size=small', 'artwork=none', 'height: 42px' ) as $fragment ) {
    check_player( false !== strpos( $player, $fragment ), 'Player output is missing: ' . $fragment );
}

// The release-page player setting disables the output when unchecked.
$GLOBALS['settings_option'] = array( 'transient' => 60, 'release_player' => false );
check_player(
    '' === MDS_Bandcamp_Frontend::filter_release_links_audio( '', 92 ),
    'The disabled setting must suppress the player.'
);

// Saved settings predating the toggle keep the player enabled.
$GLOBALS['settings_option'] = array( 'transient' => 60 );
check_player(
    false !== strpos( MDS_Bandcamp_Frontend::filter_release_links_audio( '', 92 ), 'album=2334859609' ),
    'The player must default to enabled for existing settings.'
);

echo "Release Links player filter tests passed.\n";
