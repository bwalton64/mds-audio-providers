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
	 * @var array<string, array{client_id: string, client_secret: string}>
	 */
	private const CONSTANTS = array(
		'soundcloud' => array(
			'client_id'     => 'MDS_AP_SOUNDCLOUD_CLIENT_ID',
			'client_secret' => 'MDS_AP_SOUNDCLOUD_CLIENT_SECRET',
		),
		'spotify'    => array(
			'client_id'     => 'MDS_AP_SPOTIFY_CLIENT_ID',
			'client_secret' => 'MDS_AP_SPOTIFY_CLIENT_SECRET',
		),
	);

	/**
	 * Retrieves decrypted credentials for a provider.
	 *
	 * @param string $provider Provider slug (`soundcloud` or `spotify`).
	 * @return array{client_id: string, client_secret: string, source: string}
	 */
	public static function get( string $provider ): array {
		$slug = sanitize_key( $provider );
		if ( isset( self::CONSTANTS[ $slug ] ) ) {
			$id_const     = self::CONSTANTS[ $slug ]['client_id'];
			$secret_const = self::CONSTANTS[ $slug ]['client_secret'];
			if ( defined( $id_const ) && defined( $secret_const ) ) {
				$cid = trim( (string) constant( $id_const ) );
				$sec = trim( (string) constant( $secret_const ) );
				if ( '' !== $cid && '' !== $sec ) {
					return array(
						'client_id'     => $cid,
						'client_secret' => $sec,
						'source'        => 'constant',
					);
				}
			}
		}

		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		if ( ! is_array( $stored ) || ! isset( $stored[ $slug ] ) || ! is_array( $stored[ $slug ] ) ) {
			return array(
				'client_id'     => '',
				'client_secret' => '',
				'source'        => 'none',
			);
		}

		$client_id     = isset( $stored[ $slug ]['client_id'] ) && is_string( $stored[ $slug ]['client_id'] )
			? trim( $stored[ $slug ]['client_id'] )
			: '';
		$client_secret = isset( $stored[ $slug ]['client_secret'] ) && is_string( $stored[ $slug ]['client_secret'] )
			? self::decrypt( $stored[ $slug ]['client_secret'] )
			: '';

		return array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'source'        => ( '' !== $client_id && '' !== $client_secret ) ? 'option' : 'none',
		);
	}

	/**
	 * Returns safe, masked credential descriptors for the React Admin UI.
	 *
	 * @return array<string, array{configured: bool, source: string, client_id_masked: string}>
	 */
	public static function get_masked_status(): array {
		$out = array();
		foreach ( array_keys( self::CONSTANTS ) as $slug ) {
			$creds        = self::get( $slug );
			$out[ $slug ] = array(
				'configured'       => '' !== $creds['client_id'] && '' !== $creds['client_secret'],
				'source'           => $creds['source'],
				'client_id_masked' => self::mask( $creds['client_id'] ),
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
		if ( ! isset( self::CONSTANTS[ $slug ] ) || ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
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
	 * Encrypts a secret string using OpenSSL AES-256-CBC when available.
	 *
	 * @param string $plain Plaintext secret.
	 * @return string Encoded ciphertext or prefixed base64 fallback.
	 */
	private static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'random_bytes' ) ) {
			$key = hash( 'sha256', self::key_material(), true );
			$iv  = random_bytes( 16 );
			$enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			if ( is_string( $enc ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding binary AES-256-CBC ciphertext.
				return 'enc:' . base64_encode( $iv . $enc );
			}
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Fallback encoding when OpenSSL is unavailable.
		return 'b64:' . base64_encode( $plain );
	}

	/**
	 * Decrypts a stored secret string.
	 *
	 * @param string $stored Stored value.
	 * @return string Plaintext secret.
	 */
	private static function decrypt( string $stored ): string {
		if ( str_starts_with( $stored, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding binary AES-256-CBC ciphertext.
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
