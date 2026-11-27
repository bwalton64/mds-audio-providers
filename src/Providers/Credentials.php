<?php
/**
 * Secure credential storage and masking for authenticated audio providers
 * (SoundCloud OAuth 2.1 and Spotify OAuth 2.0 Client Credentials).
 *
 * @package MDS\AudioProviders
 * @since   2.0.0
 */

declare(strict_types=1);

namespace MDS\AudioProviders\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, encrypts, and masks API credentials for audio providers.
 *
 * Supports `wp-config.php` constants (`MDS_AP_SOUNDCLOUD_CLIENT_ID`,
 * `MDS_AP_SOUNDCLOUD_CLIENT_SECRET`, `MDS_AP_SPOTIFY_CLIENT_ID`,
 * `MDS_AP_SPOTIFY_CLIENT_SECRET`) with fallback to AES-256-CBC encrypted options.
 *
 * @since 2.0.0
 */
final class Credentials {

	/**
	 * Option key storing encrypted provider credentials.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'mds_ap_credentials';

	/**
	 * Constant map for `wp-config.php` overrides.
	 *
	 * @var array<string, array{client_id: string, client_secret: string, require_secret: bool, secret_only?: bool}>
	 */
	private const CONSTANTS = array(
		'soundcloud' => array(
			'client_id'      => 'MDS_AP_SOUNDCLOUD_CLIENT_ID',
			'client_secret'  => 'MDS_AP_SOUNDCLOUD_CLIENT_SECRET',
			'require_secret' => true,
		),
		'spotify'    => array(
			'client_id'      => 'MDS_AP_SPOTIFY_CLIENT_ID',
			'client_secret'  => 'MDS_AP_SPOTIFY_CLIENT_SECRET',
			'require_secret' => true,
		),
		'tidal'      => array(
			'client_id'      => 'MDS_AP_TIDAL_CLIENT_ID',
			'client_secret'  => 'MDS_AP_TIDAL_CLIENT_SECRET',
			'require_secret' => true,
		),
		'jamendo'    => array(
			'client_id'      => 'MDS_AP_JAMENDO_CLIENT_ID',
			'client_secret'  => 'MDS_AP_JAMENDO_CLIENT_SECRET',
			'require_secret' => false,
		),
		'funkwhale'  => array(
			'client_id'      => 'MDS_AP_FUNKWHALE_POD_URL',
			'client_secret'  => 'MDS_AP_FUNKWHALE_TOKEN',
			'require_secret' => false,
		),
		'musicfetch' => array(
			'client_id'      => '',
			'client_secret'  => 'MDS_AP_MUSICFETCH_API_KEY',
			'require_secret' => true,
			'secret_only'    => true,
		),
	);

	/**
	 * Registers audio provider schemas with MDS Release Manager's centralized Credentials page.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'mds_rm_register_secret_schemas', array( self::class, 'register_secret_schemas' ) );
	}

	/**
	 * Registers SoundCloud, Spotify, TIDAL, Jamendo, Funkwhale, and Musicfetch schemas.
	 *
	 * Canonical namespaces (`spotify`, `musicfetch`) merge automatically with sibling plugins.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, array<string, mixed>> $schemas Existing schemas.
	 * @return array<string, array<string, mixed>> Updated schemas.
	 */
	public static function register_secret_schemas( array $schemas ): array {
		$plugin = __( 'MDS Audio Providers', 'mds-audio-providers' );

		$schemas['soundcloud'] = array(
			'namespace'     => 'soundcloud',
			'label'         => __( 'SoundCloud', 'mds-audio-providers' ),
			'description'   => __( 'OAuth 2.1 Client Credentials for SoundCloud track and playlist previews.', 'mds-audio-providers' ),
			'plugin'        => $plugin,
			'docs_url'      => 'https://soundcloud.com/you/apps',
			'test_callback' => array( SoundCloudProvider::class, 'test_connection' ),
			'fields'        => array(
				'client_id'     => array(
					'label'     => __( 'SoundCloud Client ID', 'mds-audio-providers' ),
					'is_secret' => false,
					'const'     => array( 'MDS_AP_SOUNDCLOUD_CLIENT_ID' ),
					'env'       => array( 'MDS_AP_SOUNDCLOUD_CLIENT_ID' ),
				),
				'client_secret' => array(
					'label'     => __( 'SoundCloud Client Secret', 'mds-audio-providers' ),
					'is_secret' => true,
					'const'     => array( 'MDS_AP_SOUNDCLOUD_CLIENT_SECRET' ),
					'env'       => array( 'MDS_AP_SOUNDCLOUD_CLIENT_SECRET' ),
				),
			),
		);

		$schemas['spotify'] = array(
			'namespace'     => 'spotify',
			'label'         => __( 'Spotify', 'mds-audio-providers' ),
			'description'   => __( 'OAuth 2.0 Client Credentials shared across MDS Audio Providers and MDS Metadata.', 'mds-audio-providers' ),
			'plugin'        => $plugin,
			'docs_url'      => 'https://developer.spotify.com/dashboard',
			'test_callback' => array( SpotifyProvider::class, 'test_connection' ),
			'fields'        => array(
				'client_id'     => array(
					'label'     => __( 'Spotify Client ID', 'mds-audio-providers' ),
					'is_secret' => false,
					'const'     => array( 'MDS_AP_SPOTIFY_CLIENT_ID' ),
					'env'       => array( 'MDS_AP_SPOTIFY_CLIENT_ID' ),
				),
				'client_secret' => array(
					'label'     => __( 'Spotify Client Secret', 'mds-audio-providers' ),
					'is_secret' => true,
					'const'     => array( 'MDS_AP_SPOTIFY_CLIENT_SECRET' ),
					'env'       => array( 'MDS_AP_SPOTIFY_CLIENT_SECRET' ),
				),
			),
		);

		$schemas['tidal'] = array(
			'namespace'     => 'tidal',
			'label'         => __( 'TIDAL', 'mds-audio-providers' ),
			'description'   => __( 'OAuth 2.0 Client Credentials for TIDAL Open API v2 UPC album lookups.', 'mds-audio-providers' ),
			'plugin'        => $plugin,
			'docs_url'      => 'https://developer.tidal.com/',
			'test_callback' => array( TidalProvider::class, 'test_connection' ),
			'fields'        => array(
				'client_id'     => array(
					'label'     => __( 'TIDAL Client ID', 'mds-audio-providers' ),
					'is_secret' => false,
					'const'     => array( 'MDS_AP_TIDAL_CLIENT_ID' ),
					'env'       => array( 'MDS_AP_TIDAL_CLIENT_ID' ),
				),
				'client_secret' => array(
					'label'     => __( 'TIDAL Client Secret', 'mds-audio-providers' ),
					'is_secret' => true,
					'const'     => array( 'MDS_AP_TIDAL_CLIENT_SECRET' ),
					'env'       => array( 'MDS_AP_TIDAL_CLIENT_SECRET' ),
				),
			),
		);

		$schemas['jamendo'] = array(
			'namespace'     => 'jamendo',
			'label'         => __( 'Jamendo', 'mds-audio-providers' ),
			'description'   => __( 'Free Jamendo API v3.0 Client ID for direct MP3 streams.', 'mds-audio-providers' ),
			'plugin'        => $plugin,
			'docs_url'      => 'https://devportal.jamendo.com/',
			'test_callback' => array( JamendoProvider::class, 'test_connection' ),
			'fields'        => array(
				'client_id' => array(
					'label'     => __( 'Jamendo Client ID', 'mds-audio-providers' ),
					'is_secret' => false,
					'const'     => array( 'MDS_AP_JAMENDO_CLIENT_ID' ),
					'env'       => array( 'MDS_AP_JAMENDO_CLIENT_ID' ),
				),
			),
		);

		$schemas['funkwhale'] = array(
			'namespace'     => 'funkwhale',
			'label'         => __( 'Funkwhale', 'mds-audio-providers' ),
			'description'   => __( 'Optional Pod Base URL and Bearer token for private Funkwhale pods.', 'mds-audio-providers' ),
			'plugin'        => $plugin,
			'test_callback' => array( FunkwhaleProvider::class, 'test_connection' ),
			'fields'        => array(
				'client_id'     => array(
					'label'       => __( 'Funkwhale Pod Base URL', 'mds-audio-providers' ),
					'placeholder' => 'https://open.audio',
					'is_secret'   => false,
					'const'       => array( 'MDS_AP_FUNKWHALE_POD_URL' ),
					'env'         => array( 'MDS_AP_FUNKWHALE_POD_URL' ),
				),
				'client_secret' => array(
					'label'     => __( 'Funkwhale Bearer Token (optional)', 'mds-audio-providers' ),
					'is_secret' => true,
					'const'     => array( 'MDS_AP_FUNKWHALE_TOKEN' ),
					'env'       => array( 'MDS_AP_FUNKWHALE_TOKEN' ),
				),
			),
		);

		$schemas['musicfetch'] = array(
			'namespace'     => 'musicfetch',
			'label'         => __( 'Musicfetch', 'mds-audio-providers' ),
			'description'   => __( 'Optional commercial multi-DSP API key (x-token) shared across MDS Audio Providers and MDS Metadata.', 'mds-audio-providers' ),
			'plugin'        => $plugin,
			'docs_url'      => 'https://musicfetch.io/',
			'test_callback' => array( MusicfetchProvider::class, 'test_connection' ),
			'fields'        => array(
				'api_key' => array(
					'label'     => __( 'Musicfetch API Key (x-token)', 'mds-audio-providers' ),
					'is_secret' => true,
					'const'     => array( 'MDS_AP_MUSICFETCH_API_KEY' ),
					'env'       => array( 'MDS_AP_MUSICFETCH_API_KEY' ),
				),
			),
		);

		return $schemas;
	}

	/**
	 * Checks whether Core's centralized Hub SecretVault facade is available.
	 *
	 * @return bool True when `ExtensionApi::get_secret_with_meta()` is loaded.
	 */
	private static function has_core_vault(): bool {
		return class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			&& method_exists( \MDS\ReleaseManager\Api\ExtensionApi::class, 'get_secret_with_meta' );
	}

	/**
	 * Retrieves decrypted credentials for a provider (delegating to Core's Hub SecretVault when available).
	 *
	 * @param string $provider Provider slug (`soundcloud`, `spotify`, `tidal`, `jamendo`, `funkwhale`, `musicfetch`).
	 * @return array{client_id: string, client_secret: string, source: string}
	 */
	public static function get( string $provider ): array {
		$slug = sanitize_key( $provider );
		$spec = self::CONSTANTS[ $slug ] ?? null;

		if ( null !== $spec && self::has_core_vault() ) {
			$secret_only  = ! empty( $spec['secret_only'] );
			$secret_field = 'musicfetch' === $slug ? 'api_key' : 'client_secret';

			$id_meta = ! $secret_only
				? \MDS\ReleaseManager\Api\ExtensionApi::get_secret_with_meta(
					$slug,
					'client_id',
					array(
						'const' => '' !== $spec['client_id'] ? array( $spec['client_id'] ) : array(),
						'env'   => '' !== $spec['client_id'] ? array( $spec['client_id'] ) : array(),
					)
				)
				: array(
					'value'  => '',
					'source' => 'none',
				);

			$sec_meta = \MDS\ReleaseManager\Api\ExtensionApi::get_secret_with_meta(
				$slug,
				$secret_field,
				array(
					'const' => '' !== $spec['client_secret'] ? array( $spec['client_secret'] ) : array(),
					'env'   => '' !== $spec['client_secret'] ? array( $spec['client_secret'] ) : array(),
				)
			);

			$cid = $id_meta['value'];
			$sec = $sec_meta['value'];

			// Auto-migrate any legacy stored option value into the Hub vault on first read.
			if ( '' === $cid && '' === $sec ) {
				$legacy = self::read_legacy_option( $slug );
				if ( '' !== $legacy['client_id'] || '' !== $legacy['client_secret'] ) {
					if ( '' !== $legacy['client_id'] && ! $secret_only ) {
						\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, 'client_id', $legacy['client_id'] );
						$cid               = $legacy['client_id'];
						$id_meta['source'] = 'vault';
					}
					if ( '' !== $legacy['client_secret'] ) {
						\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, $secret_field, $legacy['client_secret'] );
						$sec                = $legacy['client_secret'];
						$sec_meta['source'] = 'vault';
					}
					self::delete_legacy_option_entry( $slug );
				}
			}

			$is_configured = $secret_only
				? '' !== $sec
				: ( '' !== $cid && ( ! $spec['require_secret'] || '' !== $sec ) );

			$source = 'none';
			if ( $is_configured ) {
				$source = $secret_only
					? $sec_meta['source']
					: ( 'none' !== $id_meta['source'] ? $id_meta['source'] : $sec_meta['source'] );
			}

			return array(
				'client_id'     => $cid,
				'client_secret' => $sec,
				'source'        => $source,
			);
		}

		if ( isset( self::CONSTANTS[ $slug ] ) ) {
			$secret_only  = ! empty( $spec['secret_only'] );
			$id_const     = $spec['client_id'];
			$secret_const = $spec['client_secret'];
			$has_id_const = '' !== $id_const && defined( $id_const );
			$has_sc_const = '' !== $secret_const && defined( $secret_const );
			if ( $secret_only && $has_sc_const ) {
				$sec = trim( (string) constant( $secret_const ) );
				if ( '' !== $sec ) {
					return array(
						'client_id'     => '',
						'client_secret' => $sec,
						'source'        => 'constant',
					);
				}
			} elseif ( ! $secret_only && $has_id_const && ( ! $spec['require_secret'] || $has_sc_const ) ) {
				$cid = trim( (string) constant( $id_const ) );
				$sec = $has_sc_const ? trim( (string) constant( $secret_const ) ) : '';
				if ( '' !== $cid && ( ! $spec['require_secret'] || '' !== $sec ) ) {
					return array(
						'client_id'     => $cid,
						'client_secret' => $sec,
						'source'        => 'constant',
					);
				}
			}
		}

		$legacy         = self::read_legacy_option( $slug );
		$secret_only    = isset( self::CONSTANTS[ $slug ] ) && ! empty( self::CONSTANTS[ $slug ]['secret_only'] );
		$require_secret = isset( self::CONSTANTS[ $slug ] ) ? self::CONSTANTS[ $slug ]['require_secret'] : true;
		$is_configured  = $secret_only
			? '' !== $legacy['client_secret']
			: ( '' !== $legacy['client_id'] && ( ! $require_secret || '' !== $legacy['client_secret'] ) );

		return array(
			'client_id'     => $legacy['client_id'],
			'client_secret' => $legacy['client_secret'],
			'source'        => $is_configured ? 'option' : 'none',
		);
	}

	/**
	 * Reads a legacy entry from `mds_ap_credentials`.
	 *
	 * @param string $slug Provider slug.
	 * @return array{client_id: string, client_secret: string}
	 */
	private static function read_legacy_option( string $slug ): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		if ( ! is_array( $stored ) || ! isset( $stored[ $slug ] ) || ! is_array( $stored[ $slug ] ) ) {
			return array(
				'client_id'     => '',
				'client_secret' => '',
			);
		}
		return array(
			'client_id'     => isset( $stored[ $slug ]['client_id'] ) && is_string( $stored[ $slug ]['client_id'] )
				? trim( $stored[ $slug ]['client_id'] )
				: '',
			'client_secret' => isset( $stored[ $slug ]['client_secret'] ) && is_string( $stored[ $slug ]['client_secret'] )
				? self::decrypt( $stored[ $slug ]['client_secret'] )
				: '',
		);
	}

	/**
	 * Deletes a migrated entry from the legacy `mds_ap_credentials` option.
	 *
	 * @param string $slug Provider slug.
	 * @return void
	 */
	private static function delete_legacy_option_entry( string $slug ): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		$stored = get_option( self::OPTION_NAME, array() );
		if ( is_array( $stored ) && isset( $stored[ $slug ] ) ) {
			unset( $stored[ $slug ] );
			update_option( self::OPTION_NAME, $stored, false );
		}
	}

	/**
	 * Returns safe, masked credential descriptors for the React Admin UI.
	 *
	 * @return array<string, array{configured: bool, source: string, client_id_masked: string}>
	 */
	public static function get_masked_status(): array {
		$out = array();
		foreach ( self::CONSTANTS as $slug => $spec ) {
			$creds       = self::get( $slug );
			$secret_only = ! empty( $spec['secret_only'] );
			$configured  = $secret_only
				? '' !== $creds['client_secret']
				: ( '' !== $creds['client_id'] && ( ! $spec['require_secret'] || '' !== $creds['client_secret'] ) );
			$display_raw = $secret_only ? $creds['client_secret'] : $creds['client_id'];

			$out[ $slug ] = array(
				'configured'       => $configured,
				'source'           => $creds['source'],
				'client_id_masked' => 'funkwhale' === $slug ? $display_raw : self::mask( $display_raw ),
			);
		}
		return $out;
	}

	/**
	 * Saves credentials for a provider (encrypting the client secret at rest).
	 *
	 * Passing an empty string for `client_secret` preserves an existing secret
	 * unless `clear` is true.
	 *
	 * @param string $provider      Provider slug (`soundcloud` or `spotify`).
	 * @param string $client_id     OAuth Client ID.
	 * @param string $client_secret OAuth Client Secret.
	 * @param bool   $clear         Whether to wipe stored credentials for this provider.
	 * @return bool True on save.
	 */
	public static function save( string $provider, string $client_id, string $client_secret = '', bool $clear = false ): bool {
		$slug = sanitize_key( $provider );
		if ( ! isset( self::CONSTANTS[ $slug ] ) ) {
			return false;
		}

		$spec         = self::CONSTANTS[ $slug ];
		$secret_only  = ! empty( $spec['secret_only'] );
		$secret_field = 'musicfetch' === $slug ? 'api_key' : 'client_secret';

		if ( self::has_core_vault() ) {
			if ( $clear ) {
				if ( ! $secret_only ) {
					\MDS\ReleaseManager\Api\ExtensionApi::delete_secret( $slug, 'client_id' );
				}
				\MDS\ReleaseManager\Api\ExtensionApi::delete_secret( $slug, $secret_field );
				self::delete_legacy_option_entry( $slug );
				return true;
			}

			$clean_id     = trim( sanitize_text_field( $client_id ) );
			$clean_secret = trim( $client_secret );

			if ( ! $secret_only && '' !== $clean_id ) {
				\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, 'client_id', $clean_id );
			}
			if ( '' !== $clean_secret ) {
				\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, $secret_field, $clean_secret );
			}
			self::delete_legacy_option_entry( $slug );
			return true;
		}

		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}

		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( $clear ) {
			unset( $stored[ $slug ] );
			return update_option( self::OPTION_NAME, $stored, false );
		}

		$clean_id     = trim( sanitize_text_field( $client_id ) );
		$clean_secret = trim( $client_secret );

		$existing_secret = isset( $stored[ $slug ]['client_secret'] ) && is_string( $stored[ $slug ]['client_secret'] )
			? $stored[ $slug ]['client_secret']
			: '';

		$stored[ $slug ] = array(
			'client_id'     => $clean_id,
			'client_secret' => '' !== $clean_secret ? self::encrypt( $clean_secret ) : $existing_secret,
		);

		return update_option( self::OPTION_NAME, $stored, false );
	}

	/**
	 * Masks an identifier for safe display in admin UIs.
	 *
	 * @param string $value Raw value.
	 * @return string Masked string.
	 */
	private static function mask( string $value ): string {
		$len = strlen( $value );
		if ( 0 === $len ) {
			return '';
		}
		if ( $len <= 6 ) {
			return str_repeat( '•', $len );
		}
		return substr( $value, 0, 3 ) . str_repeat( '•', min( 12, $len - 6 ) ) . substr( $value, -3 );
	}

	/**
	 * Encrypts a secret string using authenticated OpenSSL AES-256-GCM (or AES-256-CBC + HMAC-SHA256).
	 *
	 * @param string $plain Plaintext secret.
	 * @return string Authenticated ciphertext, or prefixed base64 fallback only when OpenSSL is unavailable.
	 */
	private static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'random_bytes' ) ) {
			$key = hash( 'sha256', self::key_material(), true );
			$iv  = random_bytes( 12 );
			$tag = '';
			$enc = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
			if ( is_string( $enc ) && 16 === strlen( $tag ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding binary AES-256-GCM ciphertext.
				return 'gcm:' . base64_encode( $iv . $tag . $enc );
			}

			// Fallback to AES-256-CBC + HMAC-SHA256 if GCM is unsupported by the OpenSSL build.
			$cbc_iv  = random_bytes( 16 );
			$cbc_enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $cbc_iv );
			if ( is_string( $cbc_enc ) ) {
				$mac = hash_hmac( 'sha256', $cbc_iv . $cbc_enc, $key, true );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding binary AES-256-CBC+HMAC ciphertext.
				return 'hmac:' . base64_encode( $mac . $cbc_iv . $cbc_enc );
			}
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Fallback encoding when OpenSSL is unavailable.
		return 'b64:' . base64_encode( $plain );
	}

	/**
	 * Decrypts a stored secret string (supports `gcm:`, `hmac:`, legacy `enc:`, and `b64:`).
	 *
	 * @param string $stored Stored value.
	 * @return string Plaintext secret.
	 */
	private static function decrypt( string $stored ): string {
		if ( str_starts_with( $stored, 'gcm:' ) && function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding binary AES-256-GCM ciphertext.
			$raw = base64_decode( substr( $stored, 4 ), true );
			if ( is_string( $raw ) && strlen( $raw ) > 28 ) {
				$key = hash( 'sha256', self::key_material(), true );
				$iv  = substr( $raw, 0, 12 );
				$tag = substr( $raw, 12, 16 );
				$dec = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
				if ( is_string( $dec ) ) {
					return $dec;
				}
			}
			return '';
		}
		if ( str_starts_with( $stored, 'hmac:' ) && function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding binary AES-256-CBC+HMAC ciphertext.
			$raw = base64_decode( substr( $stored, 5 ), true );
			if ( is_string( $raw ) && strlen( $raw ) > 48 ) {
				$key      = hash( 'sha256', self::key_material(), true );
				$mac      = substr( $raw, 0, 32 );
				$payload  = substr( $raw, 32 );
				$expected = hash_hmac( 'sha256', $payload, $key, true );
				if ( hash_equals( $expected, $mac ) ) {
					$iv  = substr( $payload, 0, 16 );
					$dec = openssl_decrypt( substr( $payload, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
					if ( is_string( $dec ) ) {
						return $dec;
					}
				}
			}
			return '';
		}
		if ( str_starts_with( $stored, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding legacy AES-256-CBC ciphertext.
			$raw = base64_decode( substr( $stored, 4 ), true );
			if ( is_string( $raw ) && strlen( $raw ) > 16 ) {
				$key = hash( 'sha256', self::key_material(), true );
				$iv  = substr( $raw, 0, 16 );
				$dec = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
				if ( is_string( $dec ) ) {
					return $dec;
				}
			}
			return '';
		}
		if ( str_starts_with( $stored, 'b64:' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding fallback secret.
			$dec = base64_decode( substr( $stored, 4 ), true );
			return is_string( $dec ) ? $dec : '';
		}
		return $stored;
	}

	/**
	 * Derives site-specific key material from WordPress auth salts.
	 *
	 * @return string Key material.
	 */
	private static function key_material(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' );
		}
		return defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : 'mds-audio-providers-local';
	}
}
