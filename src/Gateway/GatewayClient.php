<?php
/**
 * Bundled zero-dependency client SDK for MDS Cloud Gateway (`mds-gw/v1`).
 *
 * Scoped under `MDS\AudioProviders\Gateway` to prevent class collisions when
 * multiple MDS plugins run side-by-side, while sharing the suite-wide license
 * option (`mds_suite_gateway_license`) and Bearer token cache (`mds_suite_gateway_token`).
 *
 * @package   MDS\AudioProviders
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Gateway;

use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight client for communicating with MDS Cloud Gateway (`mds-gw/v1`).
 *
 * @since 2.2.0
 */
final class GatewayClient {

	public const OPTION_KEY   = 'mds_suite_gateway_license';
	public const TOKEN_KEY    = 'mds_suite_gateway_token';
	private const UPDATE_HOST = 'api.mustydungeon.com';
	private const REST_BASE   = '/wp-json/mds-gw/v1';

	/**
	 * Plugin slug (e.g. `mds-audio-providers`).
	 *
	 * @var string
	 */
	private string $plugin_slug;

	/**
	 * Plugin basename (e.g. `mds-audio-providers/mds-audio-providers.php`).
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * Current plugin SemVer version.
	 *
	 * @var string
	 */
	private string $plugin_version;

	/**
	 * Gateway API base URL (auto-detected from active site URL).
	 *
	 * @var string
	 */
	private string $api_base_url;

	/**
	 * Auto-detects the MDS Cloud Gateway URL based on the site where this plugin is active.
	 *
	 * - LocalWP / `.local` / `.test` / `localhost` -> `http://api.bgw-dev.local`
	 * - SQA / `staging.*` / `sqa.*`                -> `https://mustydungeon.com`
	 * - Production customer sites                  -> `https://api.mustydungeon.com`
	 *
	 * @param string $site_url Optional site URL override (defaults to `home_url()`).
	 * @return string Gateway base URL without trailing slash.
	 */
	public static function detect_api_base_url( string $site_url = '' ): string {
		$url  = '' !== $site_url ? $site_url : ( function_exists( 'home_url' ) ? (string) home_url() : '' );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		if (
			in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ||
			'.local' === substr( $host, -6 ) ||
			'.test' === substr( $host, -5 )
		) {
			return 'http://api.bgw-dev.local';
		}

		if (
			0 === strpos( $host, 'sqa.' ) ||
			0 === strpos( $host, 'staging.' ) ||
			'mustydungeon.com' === $host ||
			( function_exists( 'wp_get_environment_type' ) && 'staging' === wp_get_environment_type() )
		) {
			return 'https://mustydungeon.com';
		}

		return 'https://api.mustydungeon.com';
	}

	/**
	 * Constructor.
	 *
	 * @param string $plugin_slug     Plugin slug.
	 * @param string $plugin_basename Plugin basename.
	 * @param string $plugin_version  Current plugin version.
	 * @param string $api_base_url    Optional explicit API base URL override.
	 */
	public function __construct(
		string $plugin_slug,
		string $plugin_basename,
		string $plugin_version,
		string $api_base_url = ''
	) {
		$this->plugin_slug     = sanitize_key( $plugin_slug );
		$this->plugin_basename = $plugin_basename;
		$this->plugin_version  = $plugin_version;
		$this->api_base_url    = '' !== $api_base_url
			? rtrim( $api_base_url, '/' )
			: self::detect_api_base_url();
	}

	/**
	 * Returns the resolved Gateway API base URL.
	 *
	 * @return string
	 */
	public function get_api_base_url(): string {
		return $this->api_base_url;
	}

	/**
	 * Registers native WordPress `Update URI` and `plugins_api` hooks.
	 *
	 * @return void
	 */
	public function register_updater(): void {
		add_filter( 'update_plugins_' . self::UPDATE_HOST, array( $this, 'filter_update_plugins' ), 10, 4 );

		$detected_host = (string) wp_parse_url( $this->api_base_url, PHP_URL_HOST );
		if ( '' !== $detected_host && self::UPDATE_HOST !== $detected_host ) {
			add_filter( 'update_plugins_' . $detected_host, array( $this, 'filter_update_plugins' ), 10, 4 );
		}

		add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 10, 3 );
	}

	/**
	 * Returns safe, masked license status for the Admin UI and runtime tier checks.
	 *
	 * @return array{activated: bool, tier: string, expires_at: int, key_masked: string, api_base_url: string}
	 */
	public function get_status(): array {
		$license_key = trim( (string) $this->read_option( self::OPTION_KEY, '' ) );
		$cached      = function_exists( 'get_transient' ) ? get_transient( self::TOKEN_KEY ) : false;
		$tier        = '';
		$expires_at  = 0;

		if ( is_array( $cached ) && ! empty( $cached['tier'] ) && is_string( $cached['tier'] ) ) {
			$tier       = strtolower( trim( $cached['tier'] ) );
			$expires_at = (int) ( $cached['expires_at'] ?? 0 );
		} elseif ( '' !== $license_key ) {
			$tier = self::infer_tier_from_key( $license_key );
		}

		return array(
			'activated'    => '' !== $license_key,
			'tier'         => '' !== $license_key ? ( '' !== $tier ? $tier : 'free' ) : '',
			'expires_at'   => $expires_at,
			'key_masked'   => self::mask_key( $license_key ),
			'api_base_url' => $this->api_base_url,
		);
	}

	/**
	 * Activates a license key on MDS Cloud Gateway and caches the signed Bearer token.
	 *
	 * @param string $license_key Plaintext license key entered by the site admin.
	 * @return array<string, mixed>
	 */
	public function activate( string $license_key ): array {
		$clean_key = trim( $license_key );
		if ( '' === $clean_key ) {
			return array(
				'ok'    => false,
				'error' => __( 'Enter a valid MDS license key.', 'mds-audio-providers' ),
			);
		}

		$response = wp_remote_post(
			$this->api_base_url . self::REST_BASE . '/licenses/activate',
			array(
				'timeout' => 8,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'license_key'    => $clean_key,
						'site_url'       => home_url(),
						'plugin_slug'    => $this->plugin_slug,
						'plugin_version' => $this->plugin_version,
						'wp_version'     => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
						'php_version'    => PHP_VERSION,
						'is_multisite'   => function_exists( 'is_multisite' ) && is_multisite(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['ok'] ) || empty( $data['token'] ) ) {
			return array(
				'ok'    => false,
				'error' => is_array( $data ) && ! empty( $data['error'] )
					? (string) $data['error']
					: __( 'License activation failed.', 'mds-audio-providers' ),
			);
		}

		$this->store_option( self::OPTION_KEY, $clean_key );
		$this->cache_token( $data );

		return $data;
	}

	/**
	 * Deactivates the current site and clears local token caches.
	 *
	 * @return bool
	 */
	public function deactivate(): bool {
		$license_key = (string) $this->read_option( self::OPTION_KEY, '' );
		$this->delete_option( self::OPTION_KEY );
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::TOKEN_KEY );
		}

		if ( '' === $license_key || ! function_exists( 'wp_remote_post' ) ) {
			return true;
		}

		$response = wp_remote_post(
			$this->api_base_url . self::REST_BASE . '/licenses/deactivate',
			array(
				'timeout' => 8,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key' => $license_key,
						'site_url'    => home_url(),
						'plugin_slug' => $this->plugin_slug,
					)
				),
			)
		);

		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Calls a licensed proxy service on MDS Cloud Gateway.
	 *
	 * @param string               $service Service slug (e.g., `audio-resolve`).
	 * @param array<string, mixed> $payload JSON payload.
	 * @return array<string, mixed>
	 */
	public function call_proxy( string $service, array $payload = array() ): array {
		$token = $this->get_valid_token();
		if ( '' === $token ) {
			return array(
				'ok'    => false,
				'code'  => 'not_activated',
				'error' => __( 'MDS Cloud Gateway license is not activated on this site.', 'mds-audio-providers' ),
			);
		}

		// Prevent Windows LocalWP php-cgi loopback deadlocks when the gateway is a blog in the same .local Multisite.
		$local_result = $this->try_local_multisite_proxy( $service, $payload, $token );
		if ( null !== $local_result ) {
			return $local_result;
		}

		if ( ! function_exists( 'wp_remote_post' ) ) {
			return array(
				'ok'    => false,
				'code'  => 'http_unavailable',
				'error' => __( 'WordPress HTTP API is unavailable.', 'mds-audio-providers' ),
			);
		}

		$response = wp_remote_post(
			$this->api_base_url . self::REST_BASE . '/proxy/' . sanitize_key( $service ),
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'X-MDS-Site'    => home_url(),
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'code'  => 'network_error',
				'error' => $response->get_error_message(),
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded )
			? $decoded
			: array(
				'ok'   => false,
				'code' => 'invalid_json',
			);
	}

	/**
	 * WordPress `update_plugins_{$hostname}` callback.
	 *
	 * @param array<string, mixed>|false $update      Existing update data.
	 * @param array<string, mixed>       $plugin_data Plugin header data.
	 * @param string                     $plugin_file Plugin basename.
	 * @param string[]                   $locales     Installed locales.
	 * @return array<string, mixed>|false
	 */
	public function filter_update_plugins( array|bool $update, array $plugin_data, string $plugin_file, array $locales ): array|bool {
		unset( $locales );

		if ( $plugin_file !== $this->plugin_basename ) {
			return $update;
		}

		$remote = $this->fetch_update_metadata();
		if ( null === $remote || empty( $remote['new_version'] ) ) {
			return $update;
		}

		$current = (string) ( $plugin_data['Version'] ?? $this->plugin_version );
		if ( ! version_compare( $current, (string) $remote['new_version'], '<' ) ) {
			return $update;
		}

		return array(
			'id'           => $this->api_base_url . '/' . $this->plugin_slug,
			'slug'         => $this->plugin_slug,
			'plugin'       => $plugin_file,
			'version'      => (string) $remote['new_version'],
			'new_version'  => (string) $remote['new_version'],
			'url'          => (string) ( $remote['url'] ?? 'https://mustydungeon.com' ),
			'package'      => (string) ( $remote['package'] ?? '' ),
			'tested'       => (string) ( $remote['tested'] ?? '7.0' ),
			'requires_php' => (string) ( $remote['requires_php'] ?? '8.0' ),
			'icons'        => isset( $remote['icons'] ) && is_array( $remote['icons'] ) ? $remote['icons'] : array(),
		);
	}

	/**
	 * WordPress `plugins_api` callback for the "View Details" modal.
	 *
	 * @param false|object|array<string, mixed> $res    Default response.
	 * @param string                            $action API action.
	 * @param object                            $args   Request arguments.
	 * @return false|object|array<string, mixed>
	 */
	public function filter_plugins_api( bool|object|array $res, string $action, object $args ): bool|object|array {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $res;
		}

		$remote = $this->fetch_update_metadata();
		if ( null === $remote ) {
			return $res;
		}

		$info                = new stdClass();
		$info->name          = $this->plugin_slug;
		$info->slug          = $this->plugin_slug;
		$info->version       = (string) ( $remote['new_version'] ?? $this->plugin_version );
		$info->author        = '<a href="https://mustydungeon.com">Musty Dungeon Studios</a>';
		$info->homepage      = (string) ( $remote['url'] ?? 'https://mustydungeon.com' );
		$info->requires      = (string) ( $remote['requires'] ?? '7.0' );
		$info->tested        = (string) ( $remote['tested'] ?? '7.0' );
		$info->requires_php  = (string) ( $remote['requires_php'] ?? '8.0' );
		$info->download_link = (string) ( $remote['package'] ?? '' );
		$info->sections      = array(
			'changelog' => nl2br( esc_html( (string) ( $remote['changelog'] ?? '' ) ) ),
		);

		return $info;
	}

	/**
	 * Queries `/updates/check` using the cached Bearer token.
	 *
	 * @return array<string, mixed>|null
	 */
	private function fetch_update_metadata(): ?array {
		$token = $this->get_valid_token();
		if ( '' === $token ) {
			return null;
		}

		$url = add_query_arg(
			array(
				'slug'    => $this->plugin_slug,
				'version' => $this->plugin_version,
			),
			$this->api_base_url . self::REST_BASE . '/updates/check'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'X-MDS-Site'    => home_url(),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) && ! empty( $decoded['ok'] ) ? $decoded : null;
	}

	/**
	 * Returns a valid cached Bearer token, refreshing automatically when near expiry.
	 *
	 * @return string
	 */
	private function get_valid_token(): string {
		$cached = function_exists( 'get_transient' ) ? get_transient( self::TOKEN_KEY ) : false;
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && (int) ( $cached['expires_at'] ?? 0 ) > ( time() + 300 ) ) {
			return (string) $cached['token'];
		}

		$license_key = trim( (string) $this->read_option( self::OPTION_KEY, '' ) );
		if ( '' === $license_key ) {
			return '';
		}

		$refreshed = $this->activate( $license_key );
		if ( ! empty( $refreshed['ok'] ) && ! empty( $refreshed['token'] ) ) {
			return (string) $refreshed['token'];
		}

		return '';
	}

	/**
	 * Caches token payload in a WordPress transient.
	 *
	 * @param array<string, mixed> $data Activation response data.
	 * @return void
	 */
	private function cache_token( array $data ): void {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		$ttl = max( 300, (int) ( $data['expires_at'] ?? ( time() + 3600 ) ) - time() );
		set_transient(
			self::TOKEN_KEY,
			array(
				'token'      => (string) $data['token'],
				'expires_at' => (int) ( $data['expires_at'] ?? ( time() + $ttl ) ),
				'tier'       => (string) ( $data['tier'] ?? 'free' ),
			),
			$ttl
		);
	}

	/**
	 * Infers tier from key prefix when cached token is expired/absent (never trusted server-side).
	 *
	 * @param string $key Raw license key.
	 * @return string
	 */
	private static function infer_tier_from_key( string $key ): string {
		$upper = strtoupper( $key );
		if ( str_starts_with( $upper, 'MDS-AGY-' ) ) {
			return 'agency';
		}
		if ( str_starts_with( $upper, 'MDS-LBL-' ) ) {
			return 'label';
		}
		if ( str_starts_with( $upper, 'MDS-PRO-' ) ) {
			return 'pro';
		}
		return 'free';
	}

	/**
	 * Masks a license key for safe display in the Admin UI.
	 *
	 * @param string $key Raw license key.
	 * @return string
	 */
	private static function mask_key( string $key ): string {
		if ( '' === $key ) {
			return '';
		}
		$parts = explode( '-', $key );
		if ( count( $parts ) >= 4 ) {
			return $parts[0] . '-' . $parts[1] . '-••••-••••-' . end( $parts );
		}
		$len = strlen( $key );
		return $len <= 8 ? str_repeat( '•', $len ) : substr( $key, 0, 4 ) . '••••' . substr( $key, -4 );
	}

	/**
	 * Dispatches a proxy call in-process ONLY when running on a `.local` / `localhost`
	 * Multisite dev checkout where `server/` is present locally and the gateway host is
	 * another blog in the same WordPress instance.
	 *
	 * Prevents single-worker Windows LocalWP `php-cgi.exe` loopback deadlocks and stays
	 * completely decoupled from `mds-cloud-gateway` internal class layout. Production and
	 * SQA customer sites never contain `server/` and always call HTTPS `wp_remote_post()`.
	 *
	 * @param string               $service Service slug.
	 * @param array<string, mixed> $payload Request payload.
	 * @param string               $token   Signed Bearer token.
	 * @return array<string, mixed>|null Result array, or null to fall back to HTTP.
	 */
	private function try_local_multisite_proxy( string $service, array $payload, string $token ): ?array {
		if ( '' === $token || ! function_exists( 'is_multisite' ) || ! is_multisite() || ! function_exists( 'get_blog_id_from_url' ) ) {
			return null;
		}

		$api_host = strtolower( (string) wp_parse_url( $this->api_base_url, PHP_URL_HOST ) );
		$is_local = in_array( $api_host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| str_ends_with( $api_host, '.local' )
			|| str_ends_with( $api_host, '.test' );

		if ( ! $is_local ) {
			return null;
		}

		$gw_blog_id = (int) get_blog_id_from_url( $api_host );
		if ( $gw_blog_id <= 0 || get_current_blog_id() === $gw_blog_id ) {
			return null;
		}

		$server_dir = defined( 'MDS_AP_PATH' ) ? MDS_AP_PATH . 'server/' : '';
		if ( '' === $server_dir || ! is_readable( $server_dir . 'AudioResolver.php' ) ) {
			return null;
		}

		require_once $server_dir . 'GatewayCredentials.php';
		require_once $server_dir . 'BandcampScraper.php';
		require_once $server_dir . 'AudioResolver.php';

		$status = $this->get_status();
		$claims = array(
			'tier' => '' !== $status['tier'] ? $status['tier'] : 'free',
		);

		$slug = sanitize_key( $service );
		if ( 'audio-resolve' === $slug ) {
			return array(
				'ok'   => true,
				'data' => \MDS\AudioProviders\Server\AudioResolver::handle_request( $payload, $claims ),
			);
		}
		if ( 'audio-bandcamp-verify' === $slug ) {
			$url = isset( $payload['url'] ) && is_string( $payload['url'] ) ? trim( $payload['url'] ) : '';
			return array(
				'ok'   => true,
				'data' => \MDS\AudioProviders\Server\BandcampScraper::verify_url( $url ),
			);
		}

		return null;
	}

	/**
	 * Reads option at network level on Multisite or site level on single-site.
	 *
	 * @param string $key           Option key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	private function read_option( string $key, mixed $default_value = '' ): mixed {
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			return get_site_option( $key, $default_value );
		}
		return function_exists( 'get_option' ) ? get_option( $key, $default_value ) : $default_value;
	}

	/**
	 * Stores option at network level on Multisite or site level on single-site.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private function store_option( string $key, mixed $value ): void {
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'update_site_option' ) ) {
			update_site_option( $key, $value );
			return;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( $key, $value, false );
		}
	}

	/**
	 * Deletes option at network level on Multisite or site level on single-site.
	 *
	 * @param string $key Option key.
	 * @return void
	 */
	private function delete_option( string $key ): void {
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'delete_site_option' ) ) {
			delete_site_option( $key );
			return;
		}
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $key );
		}
	}
}
