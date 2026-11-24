<?php
/**
 * Standalone checks for WordPress API integration.
 *
 * Run with: php tests/native-api.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'MDS_BC_PLUGIN_BASENAME', 'mds-bandcamp-integration/mds-bandcamp-integration.php' );

$hooks = array();
$registered_settings = array();
$settings_group = null;
$active_plugin = null;
$active = false;
$requests = array();
$http_response = array( 'code' => 200, 'body' => '<html>OK</html>' );
$rest_route = null;
$rest_routes = array();
unset( $_SERVER['HTTP_HOST'] );

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 )
{
    global $hooks;
    $hooks[ $hook ][] = $callback;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 )
{
    add_action( $hook, $callback, $priority, $accepted_args );
}

function current_user_can( $capability )
{
    return 'manage_options' === $capability;
}

function __( $value, $domain )
{
    return $value;
}

function register_setting( $group, $name, $args )
{
    global $registered_settings;
    $registered_settings[ $name ] = array( $group, $name, $args );
}

function settings_fields( $group )
{
    global $settings_group;
    $settings_group = $group;
    echo '<input name="option_page" value="' . $group . '">';
}

function checked( $checked, $current = true, $display = true )
{
    $result = (string) $checked === (string) $current ? " checked='checked'" : '';
    if ( $display ) {
        echo $result;
    }
    return $result;
}

function get_option( $name, $default = false )
{
    return 'mds_bandcamp__settings' === $name ? array( 'transient' => 75 ) : $default;
}

function admin_url( $path )
{
    return 'https://example.test/wp-admin/' . $path;
}

function esc_url( $value )
{
    return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_attr( $value )
{
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_html( $value )
{
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
}

function esc_attr__( $value, $domain )
{
    return esc_attr( $value );
}

function esc_html__( $value, $domain )
{
    return esc_html( $value );
}

function esc_html_e( $value, $domain )
{
    echo esc_html__( $value, $domain );
}

function add_query_arg( $key, $value, $url )
{
    return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function wp_nonce_field( $action, $name = '_wpnonce' )
{
    echo '<input name="' . esc_attr( $name ) . '">';
}

function size_format( $bytes )
{
    return $bytes . ' B';
}

function is_plugin_active( $plugin )
{
    global $active, $active_plugin;
    $active_plugin = $plugin;
    return $active;
}

function wp_parse_url( $url, $component = -1 )
{
    return parse_url( $url, $component );
}

function home_url()
{
    return 'https://example.test/';
}

function wp_safe_remote_get( $url, $args )
{
    global $requests, $http_response;
    $requests[] = array( $url, $args );
    return $http_response;
}

function is_wp_error( $value )
{
    return $value instanceof WP_Error;
}

function wp_remote_retrieve_response_code( $response )
{
    return $response['code'];
}

function wp_remote_retrieve_body( $response )
{
    return $response['body'];
}

function register_rest_route( $namespace, $route, $args )
{
    global $rest_route, $rest_routes;
    $rest_route = array( $namespace, $route, $args );
    $rest_routes[ $route ] = array( $namespace, $route, $args );
}

final class WP_Error
{
    public function get_error_message()
    {
        return 'Connection failed';
    }
}

final class WP_REST_Server
{
    const CREATABLE = 'POST';
    const READABLE  = 'GET';
}

function wp_json_encode( $data, $flags = 0 )
{
    return json_encode( $data, $flags );
}

function wp_date( $format, $timestamp )
{
    return gmdate( $format, $timestamp );
}

function maybe_serialize( $value )
{
    return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
}

final class Test_Native_WPDB
{
    public $options = 'wp_options';
    public function esc_like( $value ) { return $value; }
    public function prepare( $query, ...$args ) { return $query; }
    public function get_results( $query ) { return array(); }
}

$wpdb = new Test_Native_WPDB();

require __DIR__ . '/../vendor/autoload.php';

MDS\Bandcamp\Admin\AdminInterface::register_hooks();
if ( ! in_array( array( MDS\Bandcamp\Admin\AdminInterface::class, 'register_release_manager_tab' ), $hooks['mds_rm_site_settings_tabs'], true ) ) {
    throw new RuntimeException( 'Release Manager settings tab hook is missing.' );
}
$tabs = MDS\Bandcamp\Admin\AdminInterface::register_release_manager_tab( array() );
if ( ! isset( $tabs['bandcamp']['render'] ) || ! is_callable( $tabs['bandcamp']['render'] ) ) {
    throw new RuntimeException( 'Bandcamp settings tab is not callable.' );
}
if ( ! in_array( array( MDS\Bandcamp\Admin\AdminInterface::class, 'register_settings' ), $hooks['admin_init'], true ) ) {
    throw new RuntimeException( 'Settings registration hook is missing.' );
}
MDS\Bandcamp\Admin\AdminInterface::register_settings();
if (
    ( $registered_settings['mds_bandcamp__settings'] ?? null ) !== array(
        'mds_bandcamp',
        'mds_bandcamp__settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => array( MDS\Bandcamp\Admin\AdminInterface::class, 'sanitize_settings' ),
        ),
    )
    || ( $registered_settings['mds_bc_block_defaults'] ?? null ) !== array(
        'mds_bandcamp',
        'mds_bc_block_defaults',
        array(
            'type'              => 'array',
            'sanitize_callback' => array( MDS\Bandcamp\Blocks\BlockDefaults::class, 'sanitize' ),
        ),
    )
) {
    throw new RuntimeException( 'Expected settings options were not registered.' );
}
if (
    MDS\Bandcamp\Admin\AdminInterface::sanitize_settings( array( 'transient' => 5000 ) ) !== array( 'transient' => 1440 )
    || MDS\Bandcamp\Admin\AdminInterface::sanitize_settings( array( 'transient' => 0 ) ) !== array( 'transient' => 1 )
    || MDS\Bandcamp\Admin\AdminInterface::sanitize_settings( array( 'transient' => 60 ) ) !== array( 'transient' => 60 )
) {
    throw new RuntimeException( 'Settings sanitization bounds changed.' );
}
ob_start();
MDS\Bandcamp\Admin\AdminInterface::render_settings_page();
$settings_html = ob_get_clean();
if (
    false === strpos( $settings_html, 'id="mds-bandcamp-integration-admin-root"' )
    || false === strpos( $settings_html, 'data-standalone="1"' )
) {
    throw new RuntimeException( 'Settings page did not render the React Admin UI mount point.' );
}

if ( MDS\Bandcamp\Core\Dependencies::is_mds_active() || 'mds-release-manager/mds-release-manager.php' !== $active_plugin ) {
    throw new RuntimeException( 'Inactive dependency check failed.' );
}
$active = true;
if ( ! MDS\Bandcamp\Core\Dependencies::is_mds_active() ) {
    throw new RuntimeException( 'Active dependency check failed.' );
}

$links = MDS\Bandcamp\Blocks\Blocks::plugin_links( array( 'existing' ) );
if ( false === strpos( $links[0], 'https://example.test/wp-admin/options-general.php?page=mds_bandcamp' ) || 'existing' !== $links[1] ) {
    throw new RuntimeException( 'Settings link was not generated from the admin URL.' );
}

$body = MDS\Bandcamp\Api\BandcampApi::fetch_bandcamp_page( 'https://artist.bandcamp.com/album/example' );
if ( '<html>OK</html>' !== $body || count( $requests ) !== 1 || 10 !== $requests[0][1]['timeout'] ) {
    throw new RuntimeException( 'Safe Bandcamp request failed.' );
}
$bad = MDS\Bandcamp\Api\BandcampApi::fetch_bandcamp_page( 'https://example.test/' );
if ( empty( $bad['error'] ) || count( $requests ) !== 1 ) {
    throw new RuntimeException( 'Non-Bandcamp URL was not rejected.' );
}
$bad = MDS\Bandcamp\Api\BandcampApi::fetch_bandcamp_page( 'http://artist.bandcamp.com/' );
if ( empty( $bad['error'] ) || count( $requests ) !== 1 ) {
    throw new RuntimeException( 'Non-HTTPS Bandcamp URL was not rejected.' );
}
$http_response = array( 'code' => 404, 'body' => '' );
$error = MDS\Bandcamp\Api\BandcampApi::fetch_bandcamp_page( 'https://artist.bandcamp.com/album/example' );
if ( empty( $error['error'] ) || 'Bandcamp URL does not exist' !== $error['message'] ) {
    throw new RuntimeException( 'HTTP 404 was not handled.' );
}
$http_response = array( 'code' => 503, 'body' => 'Service Unavailable' );
$error = MDS\Bandcamp\Api\BandcampApi::fetch_bandcamp_page( 'https://artist.bandcamp.com/album/example' );
if ( empty( $error['error'] ) || 'Bandcamp HTTP error: 503' !== $error['message'] ) {
    throw new RuntimeException( 'Non-2xx HTTP status was not handled.' );
}
$http_response = new WP_Error();
$error = MDS\Bandcamp\Api\BandcampApi::fetch_bandcamp_page( 'https://artist.bandcamp.com/album/example' );
if ( empty( $error['error'] ) || 'Request Error: Connection failed' !== $error['message'] ) {
    throw new RuntimeException( 'HTTP errors were not propagated.' );
}

MDS\Bandcamp\Api\RestApi::register_routes();
if (
    'mds-bandcamp/v1' !== $rest_route[0]
    || '/releases' !== $rest_route[1]
    || WP_REST_Server::READABLE !== $rest_route[2]['methods']
    || array( MDS\Bandcamp\Api\RestApi::class, 'check_permissions' ) !== $rest_route[2]['permission_callback']
    || 'sanitize_text_field' !== $rest_route[2]['args']['search']['sanitize_callback']
    || 'absint' !== $rest_route[2]['args']['page']['sanitize_callback']
) {
    throw new RuntimeException( 'Release search route registration changed.' );
}
if (
    ! isset( $rest_routes['/settings'], $rest_routes['/cache/clear'] )
    || WP_REST_Server::READABLE !== ( $rest_routes['/settings'][2][0]['methods'] ?? '' )
    || array( MDS\Bandcamp\Api\RestApi::class, 'check_manage_options' ) !== ( $rest_routes['/settings'][2][0]['permission_callback'] ?? null )
    || WP_REST_Server::CREATABLE !== ( $rest_routes['/settings'][2][1]['methods'] ?? '' )
    || array( MDS\Bandcamp\Api\RestApi::class, 'check_manage_options' ) !== ( $rest_routes['/settings'][2][1]['permission_callback'] ?? null )
    || WP_REST_Server::CREATABLE !== $rest_routes['/cache/clear'][2]['methods']
    || array( MDS\Bandcamp\Api\RestApi::class, 'check_manage_options' ) !== $rest_routes['/cache/clear'][2]['permission_callback']
) {
    throw new RuntimeException( 'Admin settings or cache clear REST route registration failed.' );
}

$subdomain_cases = array(
    'https://brianwalton.bandcamp.com/album/what-the-gold' => 'brianwalton',
    'https://Artist-Name.bandcamp.com/'                    => 'artist-name',
    'https://www.bandcamp.com/discover'                    => '',
    'https://bandcamp.com/'                                => '',
    'https://example.test/album/x'                         => '',
    ''                                                     => '',
);
foreach ( $subdomain_cases as $subdomain_url => $expected_subdomain ) {
    if ( MDS\Bandcamp\Core\Helpers::parse_bandcamp_subdomain( (string) $subdomain_url ) !== $expected_subdomain ) {
        throw new RuntimeException( 'Subdomain derivation failed for: ' . $subdomain_url );
    }
}

echo "WordPress API integration tests passed.\n";
