<?php
/**
 * Plugin Name: MDS Release Manager Bandcamp Integration
 * Description: Extends MDS Release Manager by integrating Blocks for Bandcamp syncing.
 * Version: 0.9.0
 * Requires PHP: 7.4
 * Author: Musty Dungeon Studios
 *
 * @category Core
 * @package  MDS_Bandcamp_Integration
 * @author   Musty Dungeon Studios <mds@briangwalton.com>
 * @license  ©2026 Walton International Business Group LLC
 * @link     https://mustydungeon.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MDS_BANDCAMP_VERSION' ) ) {
	define( 'MDS_BANDCAMP_VERSION', '0.9.0' );
}

if ( ! defined( 'MDS_BANDCAMP_PATH' ) ) {
	define( 'MDS_BANDCAMP_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MDS_BANDCAMP_URL' ) ) {
	define( 'MDS_BANDCAMP_URL', plugin_dir_url( __FILE__ ) );
}

require_once MDS_BANDCAMP_PATH . 'includes/class-mds-bandcamp-dependencies.php';
require_once MDS_BANDCAMP_PATH . 'includes/template-loader.php';
require_once MDS_BANDCAMP_PATH . 'includes/class-mds-bandcamp-admin.php';
require_once MDS_BANDCAMP_PATH . 'includes/class-mds-bandcamp-frontend.php';
require_once MDS_BANDCAMP_PATH . 'includes/enqueue-styles.php';
require_once MDS_BANDCAMP_PATH . 'includes/helpers.php';
require_once MDS_BANDCAMP_PATH . 'includes/admin-settings.php';
require_once MDS_BANDCAMP_PATH . 'includes/frontend.php';
