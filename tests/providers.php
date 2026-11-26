<?php
/**
 * Standalone unit tests for MDS Audio Providers (Credentials, ProviderOptions,
 * ProviderRegistry, supports_release checks, embed fallbacks, and Cache).
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

use MDS\AudioProviders\Admin\Admin;
use MDS\AudioProviders\Core\Cache;
use MDS\AudioProviders\Providers\AppleMusicProvider;
use MDS\AudioProviders\Providers\AudiomackProvider;
use MDS\AudioProviders\Providers\AudiusProvider;
use MDS\AudioProviders\Providers\BandcampProvider;
use MDS\AudioProviders\Providers\Credentials;
use MDS\AudioProviders\Providers\DeezerProvider;
use MDS\AudioProviders\Providers\FunkwhaleProvider;
use MDS\AudioProviders\Providers\JamendoProvider;
use MDS\AudioProviders\Providers\MixcloudProvider;
use MDS\AudioProviders\Providers\MusicfetchProvider;
use MDS\AudioProviders\Providers\ProviderOptions;
use MDS\AudioProviders\Providers\ProviderRegistry;
use MDS\AudioProviders\Providers\SoundCloudProvider;
use MDS\AudioProviders\Providers\SpotifyProvider;
use MDS\AudioProviders\Providers\TidalProvider;

function assert_ap( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
}

// 1. ProviderRegistry registers all 12 Phase 1–3 providers implementing AudioProviderInterface.
$registered = ProviderRegistry::register_providers( array() );
assert_ap( 12 === count( $registered ), 'ProviderRegistry should register 12 audio providers.' );
foreach ( array( 'apple_music', 'deezer', 'bandcamp', 'audius', 'soundcloud', 'jamendo', 'funkwhale', 'spotify', 'tidal', 'mixcloud', 'audiomack', 'musicfetch' ) as $slug ) {
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

// 3. ProviderOptions embed_fallback toggle defaults and persistence.
assert_ap( true === ProviderOptions::get_bool( 'spotify', 'embed_fallback', false ), 'Spotify embed_fallback should default to true.' );
assert_ap( true === ProviderOptions::get_bool( 'bandcamp', 'embed_fallback', false ), 'Bandcamp embed_fallback should default to true.' );
ProviderOptions::save_all( array( 'spotify' => array( 'embed_fallback' => false ) ) );
assert_ap( false === ProviderOptions::get_bool( 'spotify', 'embed_fallback', true ), 'Spotify embed_fallback should persist false.' );
ProviderOptions::save_all( array( 'spotify' => array( 'embed_fallback' => true ) ) );

// 4. supports_release checks & Core Release Links fallback.
$GLOBALS['mds_ap_meta'][50] = array(
	'upc_ean'               => '012345678905',
	'bandcamp_album_id'     => '987654321',
	'mds_ap_soundcloud_url' => 'https://soundcloud.com/artist/demo-track',
	'audius_url'            => 'https://audius.co/artist/album/demo-release',
	'mixcloud_url'          => 'https://www.mixcloud.com/djdemo/live-set-01/',
	'audiomack_url'         => 'https://audiomack.com/artist/album/demo-tape',
);

assert_ap( ( new AppleMusicProvider() )->supports_release( 50 ), 'AppleMusicProvider should support release with UPC.' );
assert_ap( ( new DeezerProvider() )->supports_release( 50 ), 'DeezerProvider should support release with UPC.' );
assert_ap( ( new BandcampProvider() )->supports_release( 50 ), 'BandcampProvider should support release with Bandcamp album ID.' );
assert_ap( ( new AudiusProvider() )->supports_release( 50 ), 'AudiusProvider should support release via Core audius_url fallback.' );
assert_ap( ( new SoundCloudProvider() )->supports_release( 50 ), 'SoundCloudProvider should support release with creds + SoundCloud URL.' );
assert_ap( ( new MixcloudProvider() )->supports_release( 50 ), 'MixcloudProvider should support release via Core mixcloud_url fallback.' );
assert_ap( ( new AudiomackProvider() )->supports_release( 50 ), 'AudiomackProvider should support release via Core audiomack_url fallback.' );
assert_ap( ! ( new SpotifyProvider() )->supports_release( 50 ), 'SpotifyProvider should return false without API creds or spotify_url.' );

// 5. Spotify URL embed fallback without API credentials respects embed_fallback toggle.
$GLOBALS['mds_ap_meta'][51] = array(
	'spotify_url' => 'https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy',
);
$spotify = new SpotifyProvider();
assert_ap( $spotify->supports_release( 51 ), 'SpotifyProvider should support spotify_url when embed_fallback=true.' );
$sp_manifest = $spotify->resolve( 51 );
assert_ap( is_array( $sp_manifest ) && str_contains( (string) $sp_manifest['embedHtml'], 'https://open.spotify.com/embed/album/4aawyAB9vmqN3uQ7FjRGTy' ), 'SpotifyProvider should return official embed iframe.' );

ProviderOptions::save_all( array( 'spotify' => array( 'embed_fallback' => false ) ) );
assert_ap( ! $spotify->supports_release( 51 ), 'SpotifyProvider should return false for URL-only release when embed_fallback=false.' );
ProviderOptions::save_all( array( 'spotify' => array( 'embed_fallback' => true ) ) );

Credentials::save( 'spotify', 'sp_client_123456', 'sp_secret_abcdef' );
assert_ap( $spotify->supports_release( 50 ), 'SpotifyProvider should return true once credentials + UPC exist.' );

// 6. Mixcloud, Audiomack, TIDAL, Jamendo, and Funkwhale resolution & URL parsing.
$mc_manifest = ( new MixcloudProvider() )->resolve( 50 );
assert_ap( is_array( $mc_manifest ) && str_contains( (string) $mc_manifest['embedHtml'], 'https://player-widget.mixcloud.com/widget/iframe/' ), 'MixcloudProvider should build official widget iframe.' );

$am_manifest = ( new AudiomackProvider() )->resolve( 50 );
assert_ap( is_array( $am_manifest ) && str_contains( (string) $am_manifest['embedHtml'], 'https://audiomack.com/embed/artist/album/demo-tape?background=1' ), 'AudiomackProvider should build official embed iframe.' );

$GLOBALS['mds_ap_meta'][52] = array(
	'tidal_url'     => 'https://tidal.com/browse/album/251380836',
	'jamendo_url'   => 'https://www.jamendo.com/album/166109/demo-album',
	'funkwhale_url' => 'https://open.audio/library/albums/9876',
);

$tidal = new TidalProvider();
assert_ap( $tidal->supports_release( 52 ), 'TidalProvider should support release via Core tidal_url fallback.' );
$tidal_manifest = $tidal->resolve( 52 );
assert_ap( is_array( $tidal_manifest ) && str_contains( (string) $tidal_manifest['embedHtml'], 'https://embed.tidal.com/albums/251380836' ), 'TidalProvider should build official TIDAL album embed iframe.' );

$jamendo = new JamendoProvider();
assert_ap( ! $jamendo->supports_release( 52 ), 'JamendoProvider should require client_id.' );
Credentials::save( 'jamendo', 'jamendo_client_abc123' );
assert_ap( $jamendo->supports_release( 52 ), 'JamendoProvider should support release once single-key client_id is saved.' );

$funkwhale = new FunkwhaleProvider();
assert_ap( $funkwhale->supports_release( 52 ), 'FunkwhaleProvider should support public pod URL zero-auth.' );
$fw_target = FunkwhaleProvider::get_funkwhale_target( 52 );
assert_ap( is_array( $fw_target ) && 'album' === $fw_target['type'] && 'https://open.audio' === $fw_target['origin'] && '9876' === $fw_target['id'], 'FunkwhaleProvider should parse pod origin, type, and ID.' );

// 6b. Musicfetch encrypted secret-only API key and UPC support.
$musicfetch = new MusicfetchProvider();
assert_ap( ! $musicfetch->supports_release( 50 ), 'MusicfetchProvider should return false without API key.' );
Credentials::save( 'musicfetch', '', 'mf_live_token_987654321' );
assert_ap( $musicfetch->supports_release( 50 ), 'MusicfetchProvider should return true once encrypted API key + UPC exist.' );
$mf_masked = Credentials::get_masked_status()['musicfetch'];
assert_ap( true === $mf_masked['configured'] && str_contains( $mf_masked['client_id_masked'], '•' ), 'Musicfetch masked status should report configured=true with masked key.' );

// 7. Admin conditional metabox provider toggle helper.
assert_ap( Admin::is_provider_enabled( 'mixcloud' ), 'Mixcloud should be enabled by default.' );
update_option( 'mds_rm_audio_disabled_providers', array( 'mixcloud', 'audiomack' ) );
assert_ap( ! Admin::is_provider_enabled( 'mixcloud' ), 'Disabled provider should report false.' );
assert_ap( Admin::is_provider_enabled( 'audius' ), 'Enabled provider should remain true.' );

// 8. Cache set/get/summary/clear.
Cache::set( 'apple_music', '012345678905', array( 'ok' => true ), 3600 );
$summary = Cache::get_summary();
assert_ap( $summary['count'] >= 1, 'Cache summary should report active entries.' );
$deleted = Cache::clear_all( false );
assert_ap( $deleted >= 1, 'Cache::clear_all should delete tracked entries.' );
assert_ap( false === Cache::get( 'apple_music', '012345678905' ), 'Cleared entry should no longer exist.' );

echo "MDS Audio Providers Phase 1–3 tests passed.\n";
