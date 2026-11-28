<?php
/**
 * Standalone unit tests for MDS Audio Providers thin-client architecture
 * (GatewayBridge tier gating, ProviderRegistry, GatewayProxyProvider,
 * ReleaseMeta, FunkwhaleProvider local execution, Credentials, ProviderOptions, and Cache).
 *
 * Run with PHP: php tests/providers.php
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'MDS_AP_VERSION', '2.2.0' );
define( 'MDS_AP_BASENAME', 'mds-audio-providers/mds-audio-providers.php' );

$GLOBALS['mds_ap_options']    = array();
$GLOBALS['mds_ap_transients'] = array();
$GLOBALS['mds_ap_meta']       = array();

function __( string $text, string $_domain = '' ): string {
	return $text;
}
function esc_attr__( string $text, string $_domain = '' ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function sanitize_key( string $key ): string {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}
function sanitize_title( string $title ): string {
	return trim( (string) preg_replace( '/[^a-z0-9\-]+/', '-', strtolower( $title ) ), '-' );
}
function sanitize_text_field( string $str ): string {
	return trim( strip_tags( $str ) );
}
function absint( $val ): int {
	return abs( (int) $val );
}
function esc_url_raw( string $url ): string {
	return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}
function esc_url( string $url ): string {
	return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' );
}
function home_url( string $path = '' ): string {
	return 'http://bgw-dev.local' . $path;
}
function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}
function get_option( string $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['mds_ap_options'] ) ? $GLOBALS['mds_ap_options'][ $name ] : $default;
}
function update_option( string $name, $value, $_autoload = null ): bool {
	$GLOBALS['mds_ap_options'][ $name ] = $value;
	return true;
}
function delete_option( string $name ): bool {
	unset( $GLOBALS['mds_ap_options'][ $name ] );
	return true;
}
function get_transient( string $key ) {
	return array_key_exists( $key, $GLOBALS['mds_ap_transients'] ) ? $GLOBALS['mds_ap_transients'][ $key ] : false;
}
function set_transient( string $key, $value, int $_ttl = 0 ): bool {
	$GLOBALS['mds_ap_transients'][ $key ] = $value;
	return true;
}
function delete_transient( string $key ): bool {
	unset( $GLOBALS['mds_ap_transients'][ $key ] );
	return true;
}
function get_post_meta( int $post_id, string $key, bool $_single = true ) {
	return $GLOBALS['mds_ap_meta'][ $post_id ][ $key ] ?? '';
}
function maybe_serialize( $data ): string {
	return is_scalar( $data ) ? (string) $data : serialize( $data );
}
function size_format( int $bytes ): string {
	return $bytes . ' B';
}
function wp_date( string $format, int $timestamp ): string {
	return gmdate( $format, $timestamp );
}

require __DIR__ . '/../../mds-release-manager/vendor/autoload.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../server/GatewayCredentials.php';
require __DIR__ . '/../server/BandcampScraper.php';
require __DIR__ . '/../server/AudioResolver.php';

use MDS\AudioProviders\Admin\Admin;
use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Gateway\GatewayBridge;
use MDS\AudioProviders\Gateway\GatewayClient;
use MDS\AudioProviders\Providers\Credentials;
use MDS\AudioProviders\Providers\FunkwhaleProvider;
use MDS\AudioProviders\Providers\GatewayProxyProvider;
use MDS\AudioProviders\Providers\ProviderOptions;
use MDS\AudioProviders\Providers\ProviderRegistry;
use MDS\AudioProviders\Server\AudioResolver;
use MDS\AudioProviders\Server\BandcampScraper;

function assert_ap( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
}

// 1. ProviderRegistry registers all 12 providers (11 GatewayProxyProvider + 1 local FunkwhaleProvider).
$registered = ProviderRegistry::register_providers( array() );
assert_ap( 12 === count( $registered ), 'ProviderRegistry should register 12 audio providers.' );
foreach ( array( 'apple_music', 'deezer', 'bandcamp', 'audius', 'soundcloud', 'jamendo', 'funkwhale', 'spotify', 'tidal', 'mixcloud', 'audiomack', 'musicfetch' ) as $slug ) {
	assert_ap( isset( $registered[ $slug ] ), "Provider {$slug} should be registered." );
}
assert_ap( $registered['apple_music'] instanceof GatewayProxyProvider, 'Apple Music should use GatewayProxyProvider.' );
assert_ap( $registered['funkwhale'] instanceof FunkwhaleProvider, 'Funkwhale should stay local via FunkwhaleProvider.' );

// 2. Release metadata fixtures.
$GLOBALS['mds_ap_meta'][50] = array(
	'upc_ean'               => '012345678905',
	'bandcamp_album_id'     => '987654321',
	'bandcamp_album_url'    => 'https://artist.bandcamp.com/album/demo',
	'mds_ap_soundcloud_url' => 'https://soundcloud.com/artist/demo-track',
	'audius_url'            => 'https://audius.co/artist/album/demo-release',
	'mixcloud_url'          => 'https://www.mixcloud.com/djdemo/live-set-01/',
	'audiomack_url'         => 'https://audiomack.com/artist/album/demo-tape',
	'spotify_url'           => 'https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy',
	'tidal_url'             => 'https://tidal.com/browse/album/251380836',
	'jamendo_url'           => 'https://www.jamendo.com/album/166109/demo-album',
	'funkwhale_url'         => 'https://open.audio/library/albums/9876',
);

// 3. Unlicensed site blocks all providers cleanly.
GatewayBridge::reset();
assert_ap( ! GatewayBridge::has_tier( 'free' ), 'Unlicensed site should not satisfy free tier.' );
assert_ap( ! $registered['apple_music']->supports_release( 50 ), 'Unlicensed site should block Apple Music.' );
assert_ap( ! $registered['funkwhale']->supports_release( 50 ), 'Unlicensed site should block Funkwhale.' );

// 4. Free-tier license unlocks all 6 zero-cost providers and blocks all 6 Pro providers.
update_option( GatewayClient::OPTION_KEY, 'MDS-FREE-AAAA-BBBB-CCCC-DDDD' );
set_transient(
	GatewayClient::TOKEN_KEY,
	array(
		'token'      => 'test_free_token',
		'expires_at' => time() + 3600,
		'tier'       => 'free',
	),
	3600
);
assert_ap( GatewayBridge::has_tier( 'free' ), 'Free-tier license should satisfy free tier.' );
assert_ap( ! GatewayBridge::has_tier( 'pro' ), 'Free-tier license should NOT satisfy pro tier.' );

foreach ( array( 'apple_music', 'deezer', 'bandcamp', 'audius', 'mixcloud', 'audiomack' ) as $free_slug ) {
	assert_ap( $registered[ $free_slug ]->supports_release( 50 ), "Free license should unlock {$free_slug}." );
}
foreach ( array( 'spotify', 'soundcloud', 'tidal', 'jamendo', 'musicfetch', 'funkwhale' ) as $pro_slug ) {
	assert_ap( ! $registered[ $pro_slug ]->supports_release( 50 ), "Free license should block Pro provider {$pro_slug}." );
}

// 5. Server-side AudioResolver enforces tier gating independently of client checks.
$server_blocked = AudioResolver::handle_request(
	array(
		'provider'   => 'spotify',
		'release_id' => 50,
		'url'        => 'https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy',
	),
	array( 'tier' => 'free' )
);
assert_ap( empty( $server_blocked['ok'] ) && 'tier_insufficient' === $server_blocked['code'], 'Server AudioResolver should block Spotify on Free tier.' );

$server_free_ok = AudioResolver::handle_request(
	array(
		'provider'   => 'audiomack',
		'release_id' => 50,
		'url'        => 'https://audiomack.com/artist/album/demo-tape',
	),
	array( 'tier' => 'free' )
);
assert_ap( ! empty( $server_free_ok['ok'] ) && str_contains( (string) $server_free_ok['manifest']['embedHtml'], 'https://audiomack.com/embed/artist/album/demo-tape?background=1' ), 'Server AudioResolver should resolve Audiomack on Free tier.' );

// 6. Pro-tier license unlocks all 12 providers (including local FunkwhaleProvider).
update_option( GatewayClient::OPTION_KEY, 'MDS-PRO-1111-2222-3333-4444' );
set_transient(
	GatewayClient::TOKEN_KEY,
	array(
		'token'      => 'test_pro_token',
		'expires_at' => time() + 3600,
		'tier'       => 'pro',
	),
	3600
);
foreach ( array_keys( $registered ) as $slug ) {
	assert_ap( $registered[ $slug ]->supports_release( 50 ), "Pro license should unlock {$slug}." );
}

// 7. End-to-end thin-client GatewayProxyProvider -> Server AudioResolver + local cache.
GatewayBridge::set_proxy_override(
	static function ( string $service, array $payload ): array {
		if ( 'audio-resolve' === $service ) {
			return array(
				'ok'   => true,
				'data' => AudioResolver::handle_request( $payload, array( 'tier' => 'pro' ) ),
			);
		}
		return array( 'ok' => false );
	}
);

$sp_manifest = $registered['spotify']->resolve( 50 );
assert_ap( is_array( $sp_manifest ) && str_contains( (string) $sp_manifest['embedHtml'], 'https://open.spotify.com/embed/album/4aawyAB9vmqN3uQ7FjRGTy' ), 'Spotify via GatewayProxyProvider should return official embed iframe.' );
assert_ap( str_contains( (string) $sp_manifest['embedHtml'], 'title="Spotify Audio Player"' ), 'Spotify embed iframe should include accessible title.' );

$tidal_manifest = $registered['tidal']->resolve( 50 );
assert_ap( is_array( $tidal_manifest ) && str_contains( (string) $tidal_manifest['embedHtml'], 'https://embed.tidal.com/albums/251380836' ), 'TIDAL via GatewayProxyProvider should return official album embed iframe.' );

$mc_manifest = $registered['mixcloud']->resolve( 50 );
assert_ap( is_array( $mc_manifest ) && str_contains( (string) $mc_manifest['embedHtml'], 'https://player-widget.mixcloud.com/widget/iframe/' ), 'Mixcloud via GatewayProxyProvider should return official widget iframe.' );

// 8. BandcampScraper Album ID extraction.
assert_ap( 3456789012 === BandcampScraper::extract_album_id( '<html><!-- album id 3456789012 --></html>' ), 'BandcampScraper should extract album ID from HTML comment.' );
assert_ap( 987654321 === BandcampScraper::extract_album_id( '{"album_id":987654321}' ), 'BandcampScraper should extract album ID from JSON.' );

// 9. Local Funkwhale origin auth guard & Credentials vault.
Credentials::save( 'funkwhale', 'https://open.audio', 'fw_secret_token_abc' );
$fw_creds = Credentials::get( 'funkwhale' );
assert_ap( 'https://open.audio' === $fw_creds['client_id'] && 'fw_secret_token_abc' === $fw_creds['client_secret'], 'Funkwhale credentials should round-trip.' );
assert_ap( FunkwhaleProvider::should_send_auth_token( 'https://open.audio', 'https://open.audio/' ), 'Funkwhale Bearer token should be sent to matching HTTPS pod origin.' );
assert_ap( ! FunkwhaleProvider::should_send_auth_token( 'https://evil.example', 'https://open.audio' ), 'Funkwhale Bearer token must never be sent to mismatched host.' );

// 10. ProviderOptions embed_fallback toggle defaults and persistence.
assert_ap( true === ProviderOptions::get_bool( 'spotify', 'embed_fallback', false ), 'Spotify embed_fallback should default to true.' );
ProviderOptions::save_all( array( 'spotify' => array( 'embed_fallback' => false ) ) );
assert_ap( false === ProviderOptions::get_bool( 'spotify', 'embed_fallback', true ), 'Spotify embed_fallback should persist false.' );
ProviderOptions::save_all( array( 'spotify' => array( 'embed_fallback' => true ) ) );

// 11. Admin conditional metabox provider toggle helper & Cache lifecycle.
assert_ap( Admin::is_provider_enabled( 'mixcloud' ), 'Mixcloud should be enabled by default.' );
update_option( 'mds_rm_audio_disabled_providers', array( 'mixcloud', 'audiomack' ) );
assert_ap( ! Admin::is_provider_enabled( 'mixcloud' ), 'Disabled provider should report false.' );

$summary = Cache::get_summary();
assert_ap( $summary['count'] >= 1, 'Cache summary should report active entries.' );
$deleted = Cache::clear_all( false );
assert_ap( $deleted >= 1, 'Cache::clear_all should delete tracked entries.' );

GatewayBridge::reset();
echo "MDS Audio Providers Cloud Gateway thin-client tests passed.\n";
