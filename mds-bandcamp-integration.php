<?php
/**
 * Plugin Name: MDS Bandcamp Integration
 * Description: Bandcamp blocks, shortcode, caching, REST metadata, and optional MDS Release Manager integration.
 * Version: 1.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Text Domain: mds-bandcamp-integration
 * Author: Musty Dungeon Studios
 *
 * @category Core
 * @package  MDS_Bandcamp_Integration
 * @author   Musty Dungeon Studios <mds@briangwalton.com>
 * @copyright 2026 Walton International Business Group LLC
 * @link     https://mustydungeon.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MDS_BANDCAMP_VERSION' ) ) {
	define( 'MDS_BANDCAMP_VERSION', '1.0.0' );
}

if ( ! defined( 'MDS_BANDCAMP_PATH' ) ) {
	define( 'MDS_BANDCAMP_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MDS_BANDCAMP_URL' ) ) {
	define( 'MDS_BANDCAMP_URL', plugin_dir_url( __FILE__ ) );
}

define( 'MDS_BANDCAMP_PLUGIN_VERSION', MDS_BANDCAMP_VERSION );
define( 'MDS_BANDCAMP_PLUGIN_PATH', untrailingslashit( MDS_BANDCAMP_PATH ) );
define( 'MDS_BANDCAMP_PLUGIN_URL', untrailingslashit( MDS_BANDCAMP_URL ) );
define( 'MDS_BANDCAMP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MDS_BANDCAMP_PLUGIN_SLUG', basename( MDS_BANDCAMP_PLUGIN_PATH ) );

require_once MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-dependencies.php';
require_once MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-logging.php';
require_once MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-cache.php';
require_once MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-utilities.php';
require_once MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-helpers.php';
require_once MDS_BANDCAMP_PATH . 'includes/core/class-mds-bandcamp-templates.php';
require_once MDS_BANDCAMP_PATH . 'includes/api/class-mds-bandcamp-api.php';
require_once MDS_BANDCAMP_PATH . 'includes/api/class-mds-bandcamp-rest-api.php';
require_once MDS_BANDCAMP_PATH . 'includes/admin/class-mds-bandcamp-admin.php';
require_once MDS_BANDCAMP_PATH . 'includes/admin/class-mds-bandcamp-admin-interface.php';
require_once MDS_BANDCAMP_PATH . 'includes/blocks/class-mds-bandcamp-blocks.php';
require_once MDS_BANDCAMP_PATH . 'includes/frontend/class-mds-bandcamp-frontend.php';
require_once MDS_BANDCAMP_PATH . 'includes/frontend/class-mds-bandcamp-assets.php';
require_once MDS_BANDCAMP_PATH . 'includes/migrations/class-mds-bandcamp-migration.php';
require_once MDS_BANDCAMP_PATH . 'includes/migrations/class-mds-bandcamp-block-migration.php';

MDS_Bandcamp_Admin::register_hooks();
MDS_Bandcamp_Admin_Interface::register_hooks();
MDS_Bandcamp_Frontend::register_hooks();
MDS_Bandcamp_Assets::register_hooks();
MDS_Bandcamp_Migration::register_hooks();
MDS_Bandcamp_Block_Migration::register_hooks();
MDS_Bandcamp_Cache::register_hooks();
MDS_Bandcamp_Blocks::register_hooks();
MDS_Bandcamp_REST_API::register_hooks();
MDS_Bandcamp_Utilities::register_hooks();
