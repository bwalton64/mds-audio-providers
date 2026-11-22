# Code Review Report — MDS Bandcamp Integration

- **Date:** 2026-11-22
- **Scope:** Full repository audit (`mds-bandcamp-integration.php`, `src/`, `blocks/`, `js/`, `css/`, `templates/`, `composer.json`, `phpcs.xml.dist`)
- **Summary:** 1 Critical | 3 High | 1 Medium | 1 Low

## Executive Summary

Following the PSR-4 migration to `MDS\Bandcamp\*` under `src/`, all 18 core files (`src/`, `mds-bandcamp-integration.php`, `templates/`) pass `composer phpcs` (WPCS 3.x + PHPCompatibilityWP for PHP 8.0+) with **0 errors and 0 warnings**, and all 8 standalone test suites pass. Previously reported defects in `Utilities::get_contrast_color()`, `Admin::ajax_verify_url()`, and orphaned `Frontend::handle_bandcamp_shortcode()` / unread transients have been resolved.

The audit identified **1 Critical / 3 High** issues (1 unbounded legacy migration query + unindexed transient scan, 1 unguarded `$package['photos'][0]` access and uninitialized featured-track fallback, 1 arbitrary `<iframe>` / inline `<style>` sanitization gap in `bandcamp-embed` / `bandcamp-form` / `bandcamp-discography`, and 1 HTTP status-code check gap in `BandcampApi::fetch_bandcamp_page()`) plus **1 Medium / 1 Low** cleanup items (legacy WPCS violations across `blocks/*/render.php`, missing `uninstall.php`, and minor orphaned JS/CSS/block-attribute declarations).

---

## Findings Checklist

> Reference the **ID** (`CR-001`, `CR-002`, etc.) when asking the agent to implement fixes.

### Critical / High Priority

- [x] **CR-001** `[Runaway State]` — Unbounded `numberposts => -1` migration query on `admin_init` and unindexed `$wpdb->options` scan in `Cache::get_transients_list()`
  - **Location:** `src/Migrations/Migration.php:45-123`, `src/Core/Cache.php:50-82`
  - **Issue:**
    1. `MDS\Bandcamp\Migrations\Migration::maybe_migrate_legacy_data()` runs on `admin_init` and queries `release` posts with `meta_key => 'bandcamp_shortcode'` using `'numberposts' => -1`. If even one release contains an empty or unparseable `bandcamp_shortcode` meta value, `$pending > 0` causes the method to return early **without setting `mds_bandcamp_migration_complete`**, re-running the unbounded query on every subsequent `admin_init` request indefinitely.
    2. `MDS\Bandcamp\Core\Cache::get_transients_list( string $search = '' )` uses leading wildcards (`'%' . $wpdb->esc_like( $prefix . $search ) . '%'`) without a `LIMIT`, and when `$search` is empty it scans every transient in `wp_options`.
  - **Recommended Fix:** In `Migration::maybe_migrate_legacy_data()`, query in bounded batches (`'posts_per_page' => 100, 'no_found_rows' => true`) and delete or mark unparseable `bandcamp_shortcode` meta keys so `mds_bandcamp_migration_complete` is always reached. In `Cache::get_transients_list()`, default `$search` to `self::$plugin_key`, use prefix matching (`$wpdb->esc_like( $prefix . $search ) . '%'`), and add `LIMIT 250`.

- [x] **CR-002** `[Static Defect]` — Unguarded `$package['photos'][0]` access in `blocks/bandcamp-merch/render.php` and uninitialized `$data['featured_track_*']` fallback in `blocks/bandcamp-album/render.php`
  - **Location:** `blocks/bandcamp-merch/render.php:104-114`, `blocks/bandcamp-album/render.php:114-120, 167`
  - **Issue:**
    1. In `blocks/bandcamp-merch/render.php` (lines 104–114), `$package['photos'][0]['large']` and `$package['photos'][0]['medium']` are accessed unconditionally before checking whether `$package['photos']` is non-empty. If a Bandcamp merch item has no product photos (`$package['arts']` is empty), PHP emits `Warning: Undefined array key 0` and `Trying to access array offset on value of type null`.
    2. In `blocks/bandcamp-album/render.php` (lines 114–120), `$data['featured_track_mp3']`, `$data['featured_track_title']`, and `$data['featured_track_artist']` are only assigned inside `if ( $track['id'] == $data['featured_track_id'] )`. If `featured_track_id` does not match any track in `$data['tracks']`, no fallback track is selected and the custom audio player renders `<source src="">`.
  - **Recommended Fix:** Guard `.bandcamp-merch-photos-main` in `blocks/bandcamp-merch/render.php` with `if ( ! empty( $package['photos'][0] ) )` (or fall back to `$data['album_art_medium']`). In `blocks/bandcamp-album/render.php`, initialize `$data['featured_track_mp3']`, `$data['featured_track_title']`, and `$data['featured_track_artist']` before the track loop and fall back to the first track with a non-empty `mp3`.

- [x] **CR-003** `[Static Defect]` — Arbitrary `<iframe>` origin in `blocks/bandcamp-embed/render.php` and unvalidated CSS interpolation in `blocks/bandcamp-form/render.php` and `blocks/bandcamp-discography/render.php`
  - **Location:** `blocks/bandcamp-embed/render.php:73-82`, `src/Core/Utilities.php:166-178`, `blocks/bandcamp-form/render.php:48-126`, `blocks/bandcamp-discography/render.php:31-79`
  - **Issue:**
    1. `MDS\Bandcamp\Core\Utilities::kses_additions()` adds `<iframe>` globally to `wp_kses_allowed_html( 'post' )`. In `blocks/bandcamp-embed/render.php` (lines 73–76), when `$embedType === 'iframe'`, raw `$code` is passed through `html_entity_decode( $code )` and echoed via `wp_kses( $output, 'post' )` without verifying that the iframe `src` points to `https://bandcamp.com/EmbeddedPlayer/...`.
    2. In `blocks/bandcamp-form/render.php` (lines 49–125) and `blocks/bandcamp-discography/render.php` (lines 31–78), block attributes are interpolated into inline `<style>` tags using `esc_attr()` / `sanitize_text_field()` rather than strict hex/unit validators (`sanitize_hex_color()`, numeric + unit allowlists).
  - **Recommended Fix:** In `blocks/bandcamp-embed/render.php`, parse the `<iframe>` in `iframe` mode and verify its `src` host is `bandcamp.com` and path starts with `/EmbeddedPlayer/`. In `blocks/bandcamp-form/render.php` and `blocks/bandcamp-discography/render.php`, validate colors with `sanitize_hex_color()` and CSS dimensions/borders against an allowlist pattern.

- [x] **CR-004** `[Static Defect]` — `BandcampApi::fetch_bandcamp_page()` only treats HTTP 404 as an error
  - **Location:** `src/Api/BandcampApi.php:129-138`
  - **Issue:** `MDS\Bandcamp\Api\BandcampApi::fetch_bandcamp_page()` checks only `if ( 404 === $response_code )` and returns the raw response body for other non-2xx HTTP statuses (`403`, `429`, `500`, `502`, `503`). In `Admin::ajax_verify_url()` (`mode === 'artist'`), this causes Bandcamp 5xx error pages or rate-limit responses to be reported as verified Artist URLs.
  - **Recommended Fix:** Return an error array whenever `$response_code < 200 || $response_code >= 300` (keeping the specific 404 message for `404 === $response_code`).

---

### Medium / Low Priority (Orphaned Code & Cleanup)

- [x] **CR-005** `[Static Defect]` — Legacy WPCS violations across all 6 `blocks/*/render.php` templates (`1,438` errors, `156` warnings)
  - **Location:** `blocks/bandcamp-{album,discography,embed,form,merch,miniplayer}/render.php`
  - **Issue:** While all 18 files in `src/`, `mds-bandcamp-integration.php`, and `templates/` pass `composer phpcs` with 0 errors and 0 warnings, the 6 legacy `blocks/*/render.php` templates still contain CRLF line endings, missing WPCS spacing/braces, unprefixed top-level template variables (including leftover `$mds_bandcamp_context_id` / `$mds_bandcamp_context_album`), unused `$align` / `$className` variables, and unguarded `$_REQUEST['context']` reads. This prevents bare `composer phpcs` from exiting `0`.
  - **Recommended Fix:** Run `composer phpcbf` on `blocks/*/render.php`, normalize line endings to `LF`, remove unused `$align`/`$className` variables, and wrap each block's render logic inside a dedicated `MDS\Bandcamp\Blocks\*` renderer or prefix top-level template variables with `$mds_bc_`.

- [x] **CR-006** `[Orphaned Code]` — Missing `uninstall.php`, orphaned CSS selector, unused block attribute, and unused JS globals
  - **Location:** `css/admin.css:5-7`, `blocks/bandcamp-discography/block.json:36`, `blocks/bandcamp-embed/index.js:41-46`, `blocks/*/index.js:1-33`
  - **Issue:**
    1. The plugin has no `uninstall.php` to clean up its options (`mds_bandcamp__settings`, `mds_bandcamp_migration_complete`, `mds_bandcamp_audio_player_activated`, `mds_bandcamp_block_namespace_migrated`, `mds_bandcamp_block_migration_cursor`) or transients (`_transient_mds_bandcamp*`, `_transient_mds_bc_*`) upon plugin deletion.
    2. `.mds-bandcamp-shortcode-input` in `css/admin.css:5-7` has zero references anywhere in the plugin.
    3. `blocks/bandcamp-discography/block.json:36` defines an unused `display_album_border` attribute; `blocks/bandcamp-embed/index.js:41-46` has an empty `useEffect` body; and `blocks/*/index.js` declare unused top-level `var` bindings (`MediaUpload`, `RichText`, `InnerBlocks`, etc.) in global scope.
  - **Recommended Fix:** Add an `uninstall.php` cleanup script, remove `.mds-bandcamp-shortcode-input` from `css/admin.css`, remove `display_album_border` from `blocks/bandcamp-discography/block.json`, and wrap block `index.js` files in an IIFE `(function (wp) { ... })(window.wp);` with only used imports.
