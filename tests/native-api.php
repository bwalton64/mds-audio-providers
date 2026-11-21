<?php
/**
 * Standalone checks for WordPress API integration.
 *
 * Run with: php tests/native-api.php
 */

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'MDS_BANDCAMP_PLUGIN_BASENAME', 'mds-bandcamp-integration/mds-bandcamp-integration.php' );

$hooks = array();
$registered_setting = null;
$settings_group = null;
$active_plugin = null;
$active = false;
$requests = array();
$http_response = array( 'code' => 200, 'body' => '<html>OK</html>' );
$rest_route = null;
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
    global $registered_setting;
    $registered_setting = array( $group, $name, $args );
}

function settings_fields( $group )
{
    global $settings_group;
    $settings_group = $group;
    echo '<input name="option_page" value="' . $group . '">';
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
    global $rest_route;
    $rest_route = array( $namespace, $route, $args );
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
}

final class MDS_Bandcamp_Cache
{
    public static function get_transients_list( $search )
    {
        return json_encode( array() );
    }
}

final class MDS_Bandcamp_Logging
{
    public static function log( $level, $message, $context = array() )
    {
        return true;
    }
}

require __DIR__ . '/../includes/admin/class-mds-bandcamp-admin.php';
require __DIR__ . '/../includes/admin/class-mds-bandcamp-admin-interface.php';
require __DIR__ . '/../includes/core/class-mds-bandcamp-dependencies.php';
require __DIR__ . '/../includes/api/class-mds-bandcamp-api.php';
require __DIR__ . '/../includes/api/class-mds-bandcamp-rest-api.php';
require __DIR__ . '/../includes/blocks/class-mds-bandcamp-blocks.php';

MDS_Bandcamp_Admin_Interface::register_hooks();
if ( ! in_array( array( 'MDS_Bandcamp_Admin_Interface', 'register_release_manager_tab' ), $hooks['mds_rm_site_settings_tabs'], true ) ) {
    throw new RuntimeException( 'Release Manager settings tab hook is missing.' );
}
$tabs = MDS_Bandcamp_Admin_Interface::register_release_manager_tab( array() );
if ( ! isset( $tabs['bandcamp']['render'] ) || ! is_callable( $tabs['bandcamp']['render'] ) ) {
    throw new RuntimeException( 'Bandcamp settings tab is not callable.' );
}
if ( ! in_array( array( 'MDS_Bandcamp_Admin_Interface', 'register_settings' ), $hooks['admin_init'], true ) ) {
    throw new RuntimeException( 'Settings registration hook is missing.' );
}
MDS_Bandcamp_Admin_Interface::register_settings();
if (
    $registered_setting !== array(
        'mds_bandcamp',
        'mds_bandcamp__settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => array( 'MDS_Bandcamp_Admin_Interface', 'sanitize_settings' ),
        ),
    )
) {
    throw new RuntimeException( 'Existing settings option was not registered.' );
}
if (
    MDS_Bandcamp_Admin_Interface::sanitize_settings( array( 'transient' => 5000 ) ) !== array( 'transient' => 1440 )
    || MDS_Bandcamp_Admin_Interface::sanitize_settings( array( 'transient' => 0 ) ) !== array( 'transient' => 1 )
) {
    throw new RuntimeException( 'Settings sanitization bounds changed.' );
}
ob_start();
MDS_Bandcamp_Admin_Interface::render_settings_page();
$settings_html = ob_get_clean();
if (
    'mds_bandcamp' !== $settings_group
    || false === strpos( $settings_html, 'https://example.test/wp-admin/options.php' )
    || false === strpos( $settings_html, 'name="mds_bandcamp__settings[transient]" value="75"' )
) {
    throw new RuntimeException( 'Settings form did not preserve its option and value.' );
}

if ( MDS_Bandcamp_Dependencies::is_mds_active() || 'mds-release-manager/mds-release-manager.php' !== $active_plugin ) {
    throw new RuntimeException( 'Inactive dependency check failed.' );
}
$active = true;
if ( ! MDS_Bandcamp_Dependencies::is_mds_active() ) {
    throw new RuntimeException( 'Active dependency check failed.' );
}

$links = MDS_Bandcamp_Blocks::plugin_links( array( 'existing' ) );
if ( false === strpos( $links[0], 'https://example.test/wp-admin/options-general.php?page=mds_bandcamp' ) || 'existing' !== $links[1] ) {
    throw new RuntimeException( 'Settings link was not generated from the admin URL.' );
}

$body = MDS_Bandcamp_API::fetch_bandcamp_page( 'https://artist.bandcamp.com/album/example' );
if ( '<html>OK</html>' !== $body || count( $requests ) !== 1 || 10 !== $requests[0][1]['timeout'] ) {
    throw new RuntimeException( 'Safe Bandcamp request failed.' );
}
$bad = MDS_Bandcamp_API::fetch_bandcamp_page( 'https://example.test/' );
if ( empty( $bad['error'] ) || count( $requests ) !== 1 ) {
    throw new RuntimeException( 'Non-Bandcamp URL was not rejected.' );
}
$bad = MDS_Bandcamp_API::fetch_bandcamp_page( 'http://artist.bandcamp.com/' );
if ( empty( $bad['error'] ) || count( $requests ) !== 1 ) {
    throw new RuntimeException( 'Non-HTTPS Bandcamp URL was not rejected.' );
}
$http_response = array( 'code' => 404, 'body' => '' );
$error = MDS_Bandcamp_API::fetch_bandcamp_page( 'https://artist.bandcamp.com/album/example' );
if ( empty( $error['error'] ) || 'Bandcamp URL does not exist' !== $error['message'] ) {
    throw new RuntimeException( 'HTTP 404 was not handled.' );
}
$http_response = new WP_Error();
$error = MDS_Bandcamp_API::fetch_bandcamp_page( 'https://artist.bandcamp.com/album/example' );
if ( empty( $error['error'] ) || 'Request Error: Connection failed' !== $error['message'] ) {
    throw new RuntimeException( 'HTTP errors were not propagated.' );
}

MDS_Bandcamp_REST_API::register_meta_route();
if ( 'mds-bandcamp/v1' !== $rest_route[0] || '/meta' !== $rest_route[1] || WP_REST_Server::CREATABLE !== $rest_route[2]['methods'] ) {
    throw new RuntimeException( 'REST route registration changed.' );
}

echo "WordPress API integration tests passed.\n";
