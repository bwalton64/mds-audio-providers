<?php
/**
 * Plugin Name: MDS Bandcamp Integration
 * Description: Bandcamp blocks, shortcode, caching, REST metadata, and optional MDS Release Manager integration.
 * Version: 1.0.1
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Text Domain: mds-bandcamp-integration
 * Author: Musty Dungeon Studios
 *
 * @category Core
 * @package  MDS\Bandcamp
 * @author   Musty Dungeon Studios <mds@briangwalton.com>
 * @copyright 2026 Walton International Business Group LLC
 * @link     https://mustydungeon.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MDS_BC_VERSION' ) ) {
	define( 'MDS_BC_VERSION', '1.0.1' );
}

if ( ! defined( 'MDS_BC_PATH' ) ) {
	define( 'MDS_BC_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MDS_BC_URL' ) ) {
	define( 'MDS_BC_URL', plugin_dir_url( __FILE__ ) );
}

define( 'MDS_BC_PLUGIN_VERSION', MDS_BC_VERSION );
define( 'MDS_BC_PLUGIN_PATH', untrailingslashit( MDS_BC_PATH ) );
define( 'MDS_BC_PLUGIN_URL', untrailingslashit( MDS_BC_URL ) );
define( 'MDS_BC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MDS_BC_PLUGIN_SLUG', basename( MDS_BC_PLUGIN_PATH ) );

if ( file_exists( MDS_BC_PATH . 'vendor/autoload.php' ) ) {
	require_once MDS_BC_PATH . 'vendor/autoload.php';
}

MDS\Bandcamp\Admin\Admin::register_hooks();
MDS\Bandcamp\Admin\AdminInterface::register_hooks();
MDS\Bandcamp\Frontend\Frontend::register_hooks();
MDS\Bandcamp\Frontend\Assets::register_hooks();
MDS\Bandcamp\Frontend\Shortcodes::register_hooks();
MDS\Bandcamp\Core\Cache::register_hooks();
MDS\Bandcamp\Blocks\Blocks::register_hooks();
MDS\Bandcamp\Blocks\BlockDefaults::register_hooks();
MDS\Bandcamp\Integrations\Elementor\Elementor::register_hooks();
MDS\Bandcamp\Api\RestApi::register_hooks();
MDS\Bandcamp\Core\Utilities::register_hooks();
