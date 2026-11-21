<?php
/**
 * Standalone checks for plugin bootstrap and hook registration.
 *
 * Run with: php tests/bootstrap.php
 */

define( 'ABSPATH', __DIR__ );
$hooks = array();

function plugin_dir_path( $file )
{
    return dirname( $file ) . DIRECTORY_SEPARATOR;
}

function plugin_dir_url( $file )
{
    return 'https://example.test/plugins/mds-bandcamp-integration/';
}

function untrailingslashit( $value )
{
    return rtrim( $value, '/\\' );
}

function plugin_basename( $file )
{
    return 'mds-bandcamp-integration/' . basename( $file );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 )
{
    global $hooks;
    $hooks[] = array( 'action', $hook, $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 )
{
    global $hooks;
    $hooks[] = array( 'filter', $hook, $callback, $priority, $accepted_args );
}

function add_shortcode( $tag, $callback )
{
    global $hooks;
    $hooks[] = array( 'shortcode', $tag, $callback, 10, 1 );
}

require __DIR__ . '/../mds-bandcamp-integration.php';

$expected = array(
    array( 'action', 'mds_rm_register_custom_meta_boxes', array( 'MDS_Bandcamp_Admin', 'register_meta_box' ), 10, 1 ),
    array( 'action', 'mds_rm_save_custom_meta', array( 'MDS_Bandcamp_Admin', 'save_meta' ), 10, 2 ),
    array( 'action', 'admin_enqueue_scripts', array( 'MDS_Bandcamp_Admin', 'enqueue_metabox_assets' ), 10, 1 ),
    array( 'action', 'wp_ajax_mds_bandcamp_verify_url', array( 'MDS_Bandcamp_Admin', 'ajax_verify_url' ), 10, 1 ),
    array( 'action', 'admin_init', array( 'MDS_Bandcamp_Admin_Interface', 'register_settings' ), 10, 1 ),
    array( 'action', 'admin_menu', array( 'MDS_Bandcamp_Admin_Interface', 'add_settings_menu' ), 10, 1 ),
    array( 'filter', 'mds_rm_site_settings_tabs', array( 'MDS_Bandcamp_Admin_Interface', 'register_release_manager_tab' ), 10, 1 ),
    array( 'action', 'mds_rm_after_release_meta', array( 'MDS_Bandcamp_Frontend', 'render' ), 10, 1 ),
    array( 'action', 'admin_enqueue_scripts', array( 'MDS_Bandcamp_Assets', 'enqueue_admin_styles' ), 10, 1 ),
    array( 'action', 'enqueue_block_editor_assets', array( 'MDS_Bandcamp_Assets', 'enqueue_block_styles' ), 10, 1 ),
    array( 'action', 'wp_enqueue_scripts', array( 'MDS_Bandcamp_Assets', 'enqueue_frontend_assets' ), 10, 1 ),
    array( 'action', 'admin_init', array( 'MDS_Bandcamp_Migration', 'maybe_migrate_legacy_data' ), 10, 1 ),
    array( 'action', 'admin_init', array( 'MDS_Bandcamp_Block_Migration', 'run' ), 10, 1 ),
    array( 'action', 'admin_notices', array( 'MDS_Bandcamp_Block_Migration', 'show_standalone_plugin_notice' ), 10, 1 ),
    array( 'filter', 'render_block_data', array( 'MDS_Bandcamp_Block_Migration', 'prepare_legacy_block' ), 10, 1 ),
    array( 'action', 'admin_notices', array( 'MDS_Bandcamp_Cache', 'show_clear_cache_notice' ), 10, 1 ),
    array( 'action', 'admin_post_mds_bandcamp_clear_cache', array( 'MDS_Bandcamp_Cache', 'handle_clear_cache' ), 10, 1 ),
    array( 'action', 'init', array( 'MDS_Bandcamp_Blocks', 'required_notice' ), 10, 1 ),
    array( 'action', 'init', array( 'MDS_Bandcamp_Blocks', 'load_blocks' ), 10, 1 ),
    array( 'filter', 'block_categories_all', array( 'MDS_Bandcamp_Blocks', 'custom_block_category' ), 10, 1 ),
    array( 'filter', 'plugin_action_links_' . MDS_BANDCAMP_PLUGIN_BASENAME, array( 'MDS_Bandcamp_Blocks', 'plugin_links' ), 10, 1 ),
    array( 'action', 'rest_api_init', array( 'MDS_Bandcamp_REST_API', 'register_meta_route' ), 10, 1 ),
    array( 'shortcode', 'bandcamp', array( 'MDS_Bandcamp_Utilities', 'register_bandcamp_shortcode' ), 10, 1 ),
    array( 'filter', 'wp_kses_allowed_html', array( 'MDS_Bandcamp_Utilities', 'kses_additions' ), 10, 2 ),
);

if ( $hooks !== $expected ) {
    throw new RuntimeException( 'Plugin hook registration changed.' );
}

foreach ( $hooks as $hook ) {
    if ( ! is_callable( $hook[2] ) ) {
        throw new RuntimeException( 'Hook callback is not callable: ' . implode( '::', $hook[2] ) );
    }
}

foreach ( array( 'MDS_Bandcamp_API', 'MDS_Bandcamp_Logging', 'MDS_Bandcamp_Dependencies', 'MdsBandcampDependencies', 'MDS_Bandcamp_Helpers', 'MDS_Bandcamp_Templates' ) as $class ) {
    if ( ! class_exists( $class, false ) ) {
        throw new RuntimeException( 'Plugin class is not loaded: ' . $class );
    }
}

echo "Plugin bootstrap and hook registration tests passed.\n";
