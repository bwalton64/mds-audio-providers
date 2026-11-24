<?php
/**
 * Plugin Name: MDS Audio Providers
 * Description: Audio preview providers (Apple Music, Deezer, Bandcamp, SoundCloud, Spotify) for MDS Release Manager's Unified Audio API.
 * Version: 2.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: mds-release-manager
 * Text Domain: mds-audio-providers
 * Author: Musty Dungeon Studios
 *
 * @category Core
 * @package  MDS\AudioProviders
 * @author   Musty Dungeon Studios <mds@briangwalton.com>
 * @copyright 2026 Walton International Business Group LLC
 * @link     https://mustydungeon.com
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MDS_AP_VERSION' ) ) {
	define( 'MDS_AP_VERSION', '2.0.0' );
}
if ( ! defined( 'MDS_AP_FILE' ) ) {
	define( 'MDS_AP_FILE', __FILE__ );
}
if ( ! defined( 'MDS_AP_PATH' ) ) {
	define( 'MDS_AP_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MDS_AP_URL' ) ) {
	define( 'MDS_AP_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MDS_AP_BASENAME' ) ) {
	define( 'MDS_AP_BASENAME', plugin_basename( __FILE__ ) );
}

// Legacy constant aliases retained for backward compatibility with existing integrations.
if ( ! defined( 'MDS_BC_VERSION' ) ) {
	define( 'MDS_BC_VERSION', MDS_AP_VERSION );
}
if ( ! defined( 'MDS_BC_PATH' ) ) {
	define( 'MDS_BC_PATH', MDS_AP_PATH );
}
if ( ! defined( 'MDS_BC_URL' ) ) {
	define( 'MDS_BC_URL', MDS_AP_URL );
}

$mds_ap_autoload = MDS_AP_PATH . 'vendor/autoload.php';
if ( is_readable( $mds_ap_autoload ) ) {
	require_once $mds_ap_autoload;
}

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'mds-audio-providers', false, dirname( MDS_AP_BASENAME ) . '/languages' );
	}
);

if ( class_exists( \MDS\AudioProviders\Providers\ProviderRegistry::class ) ) {
	\MDS\AudioProviders\Providers\ProviderRegistry::register_hooks();
	\MDS\AudioProviders\Core\Cache::register_hooks();
	\MDS\AudioProviders\Admin\Admin::register_hooks();
	\MDS\AudioProviders\Admin\AdminInterface::register_hooks();
	\MDS\AudioProviders\Api\RestApi::register_hooks();
}
