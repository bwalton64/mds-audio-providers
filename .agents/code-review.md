# Code Review — MDS Audio Providers (`mds-audio-providers`)

- **Date:** 2026-11-25
- **Scope:** Full plugin repository (15 PHP files, 2 JS files; excluding `vendor/`, `tests/`)
- **Summary:** `0 Critical | 1 High | 4 Medium | 3 Low`

## Executive Summary

`mds-audio-providers` is well-structured with clean SSRF hardening on Bandcamp URL verification (`https://*.bandcamp.com` + `wp_safe_remote_get()`), clean REST `permission_callback`s, and PHP 8.0 compatibility. One **High** performance/timeout blocker was found in `SoundCloudProvider::resolve()` (up to 40 sequential synchronous HTTP requests per playlist), alongside 4 **Medium** findings (unauthenticated AES-256-CBC encryption + silent plaintext base64 fallback in `Credentials`, incomplete `uninstall.php` missing multisite loop and `mds_ap_*` transient cleanup, missing `current_user_can( 'edit_post', $post_id )` defense-in-depth + Hub guard in `Admin`, and missing `wpml-config.xml`) and 3 **Low** hygiene items.

## Findings Checklist

### Critical / High Priority

- [x] **CR-001 [High] — Unbounded N+1 synchronous HTTP loop in `SoundCloudProvider::resolve()` (up to 40 blocking requests)**
  - **Location:** `src/Providers/SoundCloudProvider.php:119-140, 206-239`
  - **Evidence:** For playlists of up to 20 tracks, `resolve()` calls `resolve_track_stream()` inside a `foreach` loop, and each track makes up to 2 sequential `wp_safe_remote_get()` calls (`/preview` + fallback `/streams`, each with an 8–10s timeout). On a cold cache, a single JIT frontend `/audio` request can trigger up to 40 blocking HTTP calls and exceed PHP `max_execution_time`.
  - **Fix:** Cap synchronous playlist track resolution (e.g. first 5–8 tracks or a hard wall-clock budget of ~6 seconds), or resolve individual track streams lazily / in parallel using `Requests::request_multiple()`.

### Medium / Low Priority

- [x] **CR-002 [Medium] — Unauthenticated AES-256-CBC and silent `b64:` plaintext fallback in `Credentials`**
  - **Location:** `src/Providers/Credentials.php:183-226`
  - **Evidence:** `encrypt()` uses `aes-256-cbc` without an HMAC authentication tag (susceptible to padding oracle / silent tampering) and silently falls back to unencrypted `base64_encode()` (`b64:`) if OpenSSL is unavailable.
  - **Fix:** Upgrade to authenticated encryption (`aes-256-gcm` with 12-byte nonce + 16-byte tag, or `aes-256-cbc` + `hash_hmac('sha256', ...)` verified with `hash_equals()`), keeping legacy `enc:` read-compatibility for seamless migration.

- [x] **CR-003 [Medium] — Incomplete `uninstall.php` (missing `mds_ap_*` transients and Multisite network loop)**
  - **Location:** `uninstall.php:15-20`
  - **Evidence:** `uninstall.php` only deletes `mds_ap_credentials`, `mds_ap_cached_keys`, and the 2 OAuth token transients on the current site. It does not delete cached provider transients (`_transient_mds_ap_%` / `_transient_timeout_mds_ap_%`) and does not iterate over all sites on Multisite networks (`switch_to_blog` inside `try / finally`).
  - **Fix:** Purge `_transient_mds_ap_%` and `_transient_timeout_mds_ap_%` rows via `$wpdb->prepare()` and loop across all network sites on Multisite inside `try / finally { restore_current_blog(); }`.

- [x] **CR-004 [Medium] — Missing capability check in `Admin::save_release_meta()` and Hub guard in `Admin::register_meta_box()`**
  - **Location:** `src/Admin/Admin.php:87-100, 184-192`
  - **Evidence:** `save_release_meta()` verifies the nonce (`mds_ap_nonce`) but omits `current_user_can( 'edit_post', $post_id )`, and `register_meta_box()` does not check `ExtensionApi::is_hub()` (unlike `mds-woocommerce`).
  - **Fix:** Add `if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }` to `save_release_meta()` and gate `register_meta_box()` on `ExtensionApi::is_hub()`.

- [x] **CR-005 [Medium] — Missing `wpml-config.xml` for multilingual parity**
  - **Location:** Plugin root (`mds-audio-providers/wpml-config.xml` absent)
  - **Evidence:** Release meta keys `bandcamp_album_id`, `bandcamp_album_url`, `mds_bandcamp_integration`, and `mds_ap_soundcloud_url` are not declared in `wpml-config.xml` (`action="copy"`), so translated releases in WPML/Polylang will not automatically inherit Bandcamp/SoundCloud audio metadata.
  - **Fix:** Add `wpml-config.xml` with `<custom-field action="copy">` entries for those four meta keys.

- [x] **CR-006 [Low] — Orphaned Bandcamp Artist verification code in `js/metabox-verify.js`**
  - **Location:** `js/metabox-verify.js:9-11, 156-164`
  - **Evidence:** References `#mds_bc_artist_url`, `#mds_bc_artist_subdomain`, and `#mds_bc_verify_artist_btn` from the retired Bandcamp Artist metabox, which no longer exists in `mds-audio-providers`.
  - **Fix:** Remove the dead artist-verification selectors and click handler.

- [x] **CR-007 [Low] — Direct coupling to internal `AudioApi` class instead of `ExtensionApi`**
  - **Location:** `src/Api/RestApi.php:146-148, 214-216`
  - **Evidence:** Calls `\MDS\ReleaseManager\Core\Audio\AudioApi::get_provider_descriptors()` directly instead of `\MDS\ReleaseManager\Api\ExtensionApi::get_audio_providers()`.
  - **Fix:** Use `ExtensionApi::get_audio_providers()` (and add a public `ExtensionApi::reset_audio_cache()` wrapper if needed).

- [x] **CR-008 [Low] — Missing `wp_set_script_translations()` and `filemtime()` cache-busting on metabox script**
  - **Location:** `src/Admin/Admin.php:239-245`, `src/Admin/AdminInterface.php:148-154`
  - **Evidence:** Neither `mds-ap-admin` nor `mds-bc-metabox` calls `wp_set_script_translations( $handle, 'mds-audio-providers' )`, and `mds-bc-metabox` uses `MDS_AP_VERSION` instead of `filemtime()`.
  - **Fix:** Call `wp_set_script_translations()` on both handles and use `filemtime()` for `js/metabox-verify.js`.
