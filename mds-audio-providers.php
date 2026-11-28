<?php
/**
 * Plugin Name: MDS Audio Providers
 * Plugin URI: https://mustydungeon.com
 * Description: Audio preview providers (Apple Music, Deezer, Bandcamp, Audius, SoundCloud, Jamendo, Funkwhale, Spotify, TIDAL, Mixcloud, Audiomack, and Musicfetch) for MDS Release Manager's Unified Audio API.
 * Version: 2.1.2
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: mds-release-manager
 * Update URI: https://api.mustydungeon.com
 * Text Domain: mds-audio-providers
 * Author: Musty Dungeon Studios
 * Author URI: https://mustydungeon.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @category  Core
 * @package   MDS\AudioProviders
 * @author    Musty Dungeon Studios <mds@briangwalton.com>
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 * @link      https://mustydungeon.com
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MDS_AP_VERSION' ) ) {
	define( 'MDS_AP_VERSION', '2.1.2' );
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
	\MDS\AudioProviders\Providers\Credentials::register_hooks();
	\MDS\AudioProviders\Providers\ProviderRegistry::register_hooks();
	\MDS\AudioProviders\Core\Cache::register_hooks();
	\MDS\AudioProviders\Admin\Admin::register_hooks();
	\MDS\AudioProviders\Admin\AdminInterface::register_hooks();
	\MDS\AudioProviders\Api\RestApi::register_hooks();
	if ( class_exists( \MDS\AudioProviders\Api\Abilities::class ) ) {
		\MDS\AudioProviders\Api\Abilities::register_hooks();
	}
}

add_action(
	'mds_rm_loaded',
	static function (): void {
		if ( class_exists( \MDS\AudioProviders\Gateway\GatewayBridge::class ) ) {
			\MDS\AudioProviders\Gateway\GatewayBridge::register_updater();
		}
	}
);
