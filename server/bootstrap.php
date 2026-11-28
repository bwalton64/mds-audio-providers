<?php
/**
 * MDS Audio Providers — Proprietary Gateway Server Module Bootstrap.
 *
 * Loaded automatically by `MDS\CloudGateway\Core\ProxyRouter::discover_modules()`:
 * - Dev (`.local`): `../mds-audio-providers/server/bootstrap.php`
 * - SQA / Prod:     `mds-cloud-gateway/services/audio-providers/bootstrap.php`
 *
 * NEVER shipped in customer plugin ZIPs (excluded via `.distignore` and release workflow).
 *
 * @package   MDS\AudioProviders\Server
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   Proprietary — Musty Dungeon Studios Commercial Server Module
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/GatewayCredentials.php';
require_once __DIR__ . '/BandcampScraper.php';
require_once __DIR__ . '/AudioResolver.php';

if ( isset( $router ) && is_object( $router ) && method_exists( $router, 'register_service' ) ) {
	/**
	 * `audio-resolve` — Resolves a normalized AudioManifest for a single DSP provider.
	 *
	 * Registered at `free` minimum tier so zero-cost providers (Apple Music, Deezer,
	 * Bandcamp, Audius, Mixcloud, Audiomack) work on Free-tier keys, while OAuth / API-key
	 * providers (Spotify, SoundCloud, TIDAL, Jamendo, Musicfetch) enforce `pro`+ inside
	 * `AudioResolver::resolve()`.
	 */
	$router->register_service(
		'audio-resolve',
		static function ( array $payload, array $token_claims ): array {
			return \MDS\AudioProviders\Server\AudioResolver::handle_request( $payload, $token_claims );
		},
		'free',
		1
	);

	/**
	 * `audio-bandcamp-verify` — SSRF-hardened server-side Bandcamp Album ID extractor.
	 *
	 * Zero credit cost; available to Free-tier and Pro+ licenses.
	 */
	$router->register_service(
		'audio-bandcamp-verify',
		static function ( array $payload, array $token_claims ): array {
			unset( $token_claims );
			$url = isset( $payload['url'] ) && is_string( $payload['url'] ) ? trim( $payload['url'] ) : '';
			return \MDS\AudioProviders\Server\BandcampScraper::verify_url( $url );
		},
		'free',
		0
	);
}
