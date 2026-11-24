<?php
/**
 * Standalone unit tests for MDS Audio Providers (Credentials, ProviderRegistry,
 * supports_release checks, and Cache).
 * Run with PHP: php tests/providers.php
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

$GLOBALS['mds_ap_options']    = array();
$GLOBALS['mds_ap_transients'] = array();
$GLOBALS['mds_ap_meta']       = array();

function __( string $text, string $_domain = '' ): string {
	return $text;
}
function sanitize_key( string $key ): string {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
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

use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Providers\AppleMusicProvider;
use MDS\AudioProviders\Providers\BandcampProvider;
use MDS\AudioProviders\Providers\Credentials;
use MDS\AudioProviders\Providers\DeezerProvider;
use MDS\AudioProviders\Providers\ProviderRegistry;
use MDS\AudioProviders\Providers\SoundCloudProvider;
use MDS\AudioProviders\Providers\SpotifyProvider;

function assert_ap( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
}

// 1. ProviderRegistry registers all 5 providers implementing AudioProviderInterface.
$registered = ProviderRegistry::register_providers( array() );
assert_ap( 5 === count( $registered ), 'ProviderRegistry should register 5 audio providers.' );
foreach ( array( 'apple_music', 'deezer', 'bandcamp', 'soundcloud', 'spotify' ) as $slug ) {
	assert_ap( isset( $registered[ $slug ] ), "Provider {$slug} should be registered." );
}

// 2. Credentials encryption, decryption, and masking.
Credentials::save( 'soundcloud', 'sc_client_123456789', 'sc_secret_xyz' );
$sc_creds = Credentials::get( 'soundcloud' );
assert_ap( 'sc_client_123456789' === $sc_creds['client_id'], 'SoundCloud client_id should round-trip.' );
assert_ap( 'sc_secret_xyz' === $sc_creds['client_secret'], 'SoundCloud client_secret should decrypt cleanly.' );
$masked = Credentials::get_masked_status();
assert_ap( true === $masked['soundcloud']['configured'], 'SoundCloud should report configured=true.' );
assert_ap( false === $masked['spotify']['configured'], 'Spotify should report configured=false before saving.' );

// 3. supports_release checks.
$GLOBALS['mds_ap_meta'][50] = array(
	'upc_ean'               => '012345678905',
	'bandcamp_album_id'     => '987654321',
	'mds_ap_soundcloud_url' => 'https://soundcloud.com/artist/demo-track',
);

assert_ap( ( new AppleMusicProvider() )->supports_release( 50 ), 'AppleMusicProvider should support release with UPC.' );
assert_ap( ( new DeezerProvider() )->supports_release( 50 ), 'DeezerProvider should support release with UPC.' );
assert_ap( ( new BandcampProvider() )->supports_release( 50 ), 'BandcampProvider should support release with Bandcamp album ID.' );
assert_ap( ( new SoundCloudProvider() )->supports_release( 50 ), 'SoundCloudProvider should support release with creds + SoundCloud URL.' );
assert_ap( ! ( new SpotifyProvider() )->supports_release( 50 ), 'SpotifyProvider should return false without API credentials.' );

Credentials::save( 'spotify', 'sp_client_123456', 'sp_secret_abcdef' );
assert_ap( ( new SpotifyProvider() )->supports_release( 50 ), 'SpotifyProvider should return true once credentials + UPC exist.' );

// 4. Cache set/get/summary/clear.
Cache::set( 'apple_music', '012345678905', array( 'ok' => true ), 3600 );
$summary = Cache::get_summary();
assert_ap( 1 === $summary['count'], 'Cache summary should report 1 active entry.' );
$deleted = Cache::clear_all( false );
assert_ap( $deleted >= 1, 'Cache::clear_all should delete tracked entries.' );
assert_ap( false === Cache::get( 'apple_music', '012345678905' ), 'Cleared entry should no longer exist.' );

echo "MDS Audio Providers tests passed.\n";
