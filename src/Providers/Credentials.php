<?php
/**
 * Secure credential storage and masking for self-hosted Funkwhale pods.
 *
 * All cloud-backed DSP credentials (Spotify, SoundCloud, TIDAL, Jamendo, Musicfetch)
 * are managed centrally by MDS Cloud Gateway so customer sites remain zero-config.
 * Only self-hosted Funkwhale pod credentials stay local in Core's Hub SecretVault.
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
 * Reads, encrypts, and masks local Funkwhale pod credentials.
 *
 * @since 2.0.0
 */
final class Credentials {

	/**
	 * Option key storing legacy encrypted provider credentials.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'mds_ap_credentials';

	/**
	 * Constant map for `wp-config.php` overrides.
	 *
	 * @var array<string, array{client_id: string, client_secret: string, require_secret: bool}>
	 */
	private const CONSTANTS = array(
		'funkwhale' => array(
			'client_id'      => 'MDS_AP_FUNKWHALE_POD_URL',
			'client_secret'  => 'MDS_AP_FUNKWHALE_TOKEN',
			'require_secret' => false,
		),
	);

	/**
	 * Registers the Funkwhale schema with MDS Release Manager's centralized Credentials page.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'mds_rm_register_secret_schemas', array( self::class, 'register_secret_schemas' ) );
	}

	/**
	 * Registers only the self-hosted Funkwhale pod schema in Core's Hub SecretVault.
	 *
	 * @since 2.2.0
	 *
	 * @param array<string, array<string, mixed>> $schemas Existing schemas.
	 * @return array<string, array<string, mixed>> Updated schemas.
	 */
	public static function register_secret_schemas( array $schemas ): array {
		$schemas['funkwhale'] = array(
			'namespace'     => 'funkwhale',
			'label'         => __( 'Funkwhale', 'mds-audio-providers' ),
			'description'   => __( 'Optional Pod Base URL and Bearer token for self-hosted or private Funkwhale pods.', 'mds-audio-providers' ),
			'plugin'        => __( 'MDS Audio Providers', 'mds-audio-providers' ),
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

		return $schemas;
	}

	/**
	 * Checks whether Core's centralized Hub SecretVault facade is available.
	 *
	 * @return bool
	 */
	private static function has_core_vault(): bool {
		return class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class )
			&& method_exists( \MDS\ReleaseManager\Api\ExtensionApi::class, 'get_secret_with_meta' );
	}

	/**
	 * Retrieves decrypted credentials for a local provider (`funkwhale`).
	 *
	 * @param string $provider Provider slug (`funkwhale`).
	 * @return array{client_id: string, client_secret: string, source: string}
	 */
	public static function get( string $provider ): array {
		$slug = sanitize_key( $provider );
		$spec = self::CONSTANTS[ $slug ] ?? null;
		if ( null === $spec ) {
			return array(
				'client_id'     => '',
				'client_secret' => '',
				'source'        => 'none',
			);
		}

		if ( self::has_core_vault() ) {
			$id_meta = \MDS\ReleaseManager\Api\ExtensionApi::get_secret_with_meta(
				$slug,
				'client_id',
				array(
					'const' => array( $spec['client_id'] ),
					'env'   => array( $spec['client_id'] ),
				)
			);

			$sec_meta = \MDS\ReleaseManager\Api\ExtensionApi::get_secret_with_meta(
				$slug,
				'client_secret',
				array(
					'const' => array( $spec['client_secret'] ),
					'env'   => array( $spec['client_secret'] ),
				)
			);

			$cid = $id_meta['value'];
			$sec = $sec_meta['value'];

			if ( '' === $cid && '' === $sec ) {
				$legacy = self::read_legacy_option( $slug );
				if ( '' !== $legacy['client_id'] || '' !== $legacy['client_secret'] ) {
					if ( '' !== $legacy['client_id'] ) {
						\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, 'client_id', $legacy['client_id'] );
						$cid               = $legacy['client_id'];
						$id_meta['source'] = 'vault';
					}
					if ( '' !== $legacy['client_secret'] ) {
						\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, 'client_secret', $legacy['client_secret'] );
						$sec                = $legacy['client_secret'];
						$sec_meta['source'] = 'vault';
					}
					self::delete_legacy_option_entry( $slug );
				}
			}

			$is_configured = '' !== $cid;
			$source        = $is_configured
				? ( 'none' !== $id_meta['source'] ? $id_meta['source'] : $sec_meta['source'] )
				: 'none';

			return array(
				'client_id'     => $cid,
				'client_secret' => $sec,
				'source'        => $source,
			);
		}

		$id_const     = $spec['client_id'];
		$secret_const = $spec['client_secret'];
		if ( defined( $id_const ) ) {
			$cid = trim( (string) constant( $id_const ) );
			$sec = defined( $secret_const ) ? trim( (string) constant( $secret_const ) ) : '';
			if ( '' !== $cid ) {
				return array(
					'client_id'     => $cid,
					'client_secret' => $sec,
					'source'        => 'constant',
				);
			}
		}

		$legacy = self::read_legacy_option( $slug );
		return array(
			'client_id'     => $legacy['client_id'],
			'client_secret' => $legacy['client_secret'],
			'source'        => '' !== $legacy['client_id'] ? 'option' : 'none',
		);
	}

	/**
	 * Returns safe, masked credential descriptors for the React Admin UI.
	 *
	 * @return array<string, array{configured: bool, source: string, client_id_masked: string}>
	 */
	public static function get_masked_status(): array {
		$creds = self::get( 'funkwhale' );
		return array(
			'funkwhale' => array(
				'configured'       => '' !== $creds['client_id'],
				'source'           => $creds['source'],
				'client_id_masked' => $creds['client_id'],
			),
		);
	}

	/**
	 * Saves credentials for a local provider (`funkwhale`).
	 *
	 * @param string $provider      Provider slug (`funkwhale`).
	 * @param string $client_id     Funkwhale Pod Base URL.
	 * @param string $client_secret Optional Bearer token.
	 * @param bool   $clear         Whether to wipe stored credentials.
	 * @return bool
	 */
	public static function save( string $provider, string $client_id, string $client_secret = '', bool $clear = false ): bool {
		$slug = sanitize_key( $provider );
		if ( ! isset( self::CONSTANTS[ $slug ] ) ) {
			return false;
		}

		if ( self::has_core_vault() ) {
			if ( $clear ) {
				\MDS\ReleaseManager\Api\ExtensionApi::delete_secret( $slug, 'client_id' );
				\MDS\ReleaseManager\Api\ExtensionApi::delete_secret( $slug, 'client_secret' );
				self::delete_legacy_option_entry( $slug );
				return true;
			}

			$clean_id     = trim( sanitize_text_field( $client_id ) );
			$clean_secret = trim( $client_secret );
			if ( '' !== $clean_id ) {
				\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, 'client_id', $clean_id );
			}
			if ( '' !== $clean_secret ) {
				\MDS\ReleaseManager\Api\ExtensionApi::set_secret( $slug, 'client_secret', $clean_secret );
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

		$clean_id        = trim( sanitize_text_field( $client_id ) );
		$clean_secret    = trim( $client_secret );
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
	 * Encrypts a secret string when Core's Hub SecretVault is not active.
	 *
	 * @param string $plain Plaintext secret.
	 * @return string
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
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Fallback encoding when OpenSSL is unavailable.
		return 'b64:' . base64_encode( $plain );
	}

	/**
	 * Decrypts a stored legacy secret string.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	private static function decrypt( string $stored ): string {
		if ( str_starts_with( $stored, 'gcm:' ) && function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding binary AES-256-GCM ciphertext.
			$raw = base64_decode( substr( $stored, 4 ), true );
			if ( is_string( $raw ) && strlen( $raw ) > 28 ) {
				$key = hash( 'sha256', self::key_material(), true );
				$dec = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
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
	 * @return string
	 */
	private static function key_material(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' );
		}
		return defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : 'mds-audio-providers-local';
	}
}
