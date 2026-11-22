<?php
/**
 * Standalone checks for plugin bootstrap and hook registration.
 *
 * Run with: php tests/bootstrap.php
 */

define( 'ABSPATH', __DIR__ );
$hooks = array();

function plugin_dir_path( $file ) {
	return dirname( $file ) . DIRECTORY_SEPARATOR;
}

function plugin_dir_url( $file ) {
	return 'https://example.test/plugins/mds-bandcamp-integration/';
}

function untrailingslashit( $value ) {
	return rtrim( $value, '/\\' );
}

function plugin_basename( $file ) {
	return 'mds-bandcamp-integration/' . basename( $file );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $hooks;
	$hooks[] = array( 'action', $hook, $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $hooks;
	$hooks[] = array( 'filter', $hook, $callback, $priority, $accepted_args );
}

function add_shortcode( $tag, $callback ) {
	global $hooks;
	$hooks[] = array( 'shortcode', $tag, $callback, 10, 1 );
}

require __DIR__ . '/../mds-bandcamp-integration.php';

$expected = array(
	array( 'action', 'mds_rm_register_custom_meta_boxes', array( MDS\Bandcamp\Admin\Admin::class, 'register_meta_box' ), 10, 1 ),
	array( 'action', 'mds_rm_save_custom_meta', array( MDS\Bandcamp\Admin\Admin::class, 'save_meta' ), 10, 2 ),
	array( 'action', 'admin_enqueue_scripts', array( MDS\Bandcamp\Admin\Admin::class, 'enqueue_metabox_assets' ), 10, 1 ),
	array( 'action', 'wp_ajax_mds_bc_verify_url', array( MDS\Bandcamp\Admin\Admin::class, 'ajax_verify_url' ), 10, 1 ),
	array( 'action', 'admin_init', array( MDS\Bandcamp\Admin\AdminInterface::class, 'register_settings' ), 10, 1 ),
	array( 'action', 'admin_menu', array( MDS\Bandcamp\Admin\AdminInterface::class, 'add_settings_menu' ), 10, 1 ),
	array( 'filter', 'mds_rm_site_settings_tabs', array( MDS\Bandcamp\Admin\AdminInterface::class, 'register_release_manager_tab' ), 10, 1 ),
	array( 'filter', 'mds_rm_register_audio_players', array( MDS\Bandcamp\Frontend\Frontend::class, 'register_audio_player' ), 10, 1 ),
	array( 'filter', 'mds_rm_release_links_audio_html', array( MDS\Bandcamp\Frontend\Frontend::class, 'filter_release_links_audio' ), 10, 3 ),
	array( 'action', 'admin_enqueue_scripts', array( MDS\Bandcamp\Frontend\Assets::class, 'enqueue_admin_styles' ), 10, 1 ),
	array( 'action', 'enqueue_block_editor_assets', array( MDS\Bandcamp\Frontend\Assets::class, 'enqueue_block_styles' ), 10, 1 ),
	array( 'action', 'wp_enqueue_scripts', array( MDS\Bandcamp\Frontend\Assets::class, 'enqueue_frontend_assets' ), 10, 1 ),
	array( 'action', 'admin_init', array( MDS\Bandcamp\Migrations\Migration::class, 'maybe_migrate_legacy_data' ), 10, 1 ),
	array( 'action', 'admin_init', array( MDS\Bandcamp\Migrations\BlockMigration::class, 'run' ), 10, 1 ),
	array( 'action', 'admin_notices', array( MDS\Bandcamp\Migrations\BlockMigration::class, 'show_standalone_plugin_notice' ), 10, 1 ),
	array( 'filter', 'render_block_data', array( MDS\Bandcamp\Migrations\BlockMigration::class, 'prepare_legacy_block' ), 10, 1 ),
	array( 'action', 'admin_notices', array( MDS\Bandcamp\Core\Cache::class, 'show_clear_cache_notice' ), 10, 1 ),
	array( 'action', 'admin_post_mds_bc_clear_cache', array( MDS\Bandcamp\Core\Cache::class, 'handle_clear_cache' ), 10, 1 ),
	array( 'filter', 'heartbeat_received', array( MDS\Bandcamp\Core\Cache::class, 'handle_heartbeat_received' ), 10, 2 ),
	array( 'action', 'init', array( MDS\Bandcamp\Blocks\Blocks::class, 'required_notice' ), 10, 1 ),
	array( 'action', 'init', array( MDS\Bandcamp\Blocks\Blocks::class, 'load_blocks' ), 10, 1 ),
	array( 'filter', 'block_categories_all', array( MDS\Bandcamp\Blocks\Blocks::class, 'custom_block_category' ), 10, 1 ),
	array( 'filter', 'plugin_action_links_' . MDS_BC_PLUGIN_BASENAME, array( MDS\Bandcamp\Blocks\Blocks::class, 'plugin_links' ), 10, 1 ),
	array( 'action', 'rest_api_init', array( MDS\Bandcamp\Api\RestApi::class, 'register_routes' ), 10, 1 ),
	array( 'filter', 'mds_rm_release_picker_item', array( MDS\Bandcamp\Api\RestApi::class, 'enrich_release_picker_item' ), 10, 2 ),
	array( 'filter', 'mds_rm_artist_picker_item', array( MDS\Bandcamp\Api\RestApi::class, 'enrich_artist_picker_item' ), 10, 2 ),
	array( 'shortcode', 'bandcamp', array( MDS\Bandcamp\Core\Utilities::class, 'register_bandcamp_shortcode' ), 10, 1 ),
	array( 'filter', 'wp_kses_allowed_html', array( MDS\Bandcamp\Core\Utilities::class, 'kses_additions' ), 10, 2 ),
);

if ( $hooks !== $expected ) {
	throw new RuntimeException( 'Plugin hook registration changed.' );
}

foreach ( $hooks as $hook ) {
	if ( ! is_callable( $hook[2] ) ) {
		throw new RuntimeException( 'Hook callback is not callable: ' . implode( '::', $hook[2] ) );
	}
}

foreach ( array(
	MDS\Bandcamp\Api\BandcampApi::class,
	MDS\Bandcamp\Core\Logging::class,
	MDS\Bandcamp\Core\Dependencies::class,
	MDS\Bandcamp\Core\Helpers::class,
	MDS\Bandcamp\Core\Templates::class,
) as $class ) {
	if ( ! class_exists( $class ) ) {
		throw new RuntimeException( 'Plugin class could not be autoloaded: ' . $class );
	}
}

echo "Plugin bootstrap and hook registration tests passed.\n";
