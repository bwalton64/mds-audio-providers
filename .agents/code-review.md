# Code Review Report — MDS Bandcamp Integration

- **Date:** 2026-11-22
- **Scope:** Full repository audit (`mds-bandcamp-integration.php`, `includes/`, `blocks/`, `js/`, `css/`, `templates/`)
- **Summary:** 5 Critical / High | 2 Medium / Low

## Executive Summary

All 30 non-test PHP files in `mds-bandcamp-integration` pass PHP syntax checking (`php -l`) with zero parse errors, and all 8 standalone test suites (`tests/bootstrap.php`, `tests/logging.php`, `tests/native-api.php`, `tests/block-metadata.php`, `tests/block-migration.php`, `tests/templates.php`, `tests/release-links-player.php`, `tests/releases-endpoint.php`) pass.

The audit identified **5 Critical / High** issues (1 unbounded legacy migration query on `admin_init` paired with an unindexed transient table scan, 1 unguarded `$package['photos'][0]` access in `bandcamp-merch/render.php` plus uninitialized featured-track keys in `bandcamp-album/render.php`, 1 arbitrary `<iframe>` / inline `<style>` sanitization gap in `bandcamp-embed` and `bandcamp-form`, 1 `'black'` vs. `'#000000'` return-value mismatch in `MDS_Bandcamp_Utilities::get_contrast_color()`, and 1 HTTP status / string-vs-array error check gap in `MDS_Bandcamp_API::fetch_bandcamp_page()`) and **2 Medium / Low** cleanup items (orphaned duplicate shortcode handler & unread transient, plus missing `uninstall.php` and unused block script globals/CSS selectors).

---

## Findings Checklist

> Reference the **ID** (`CR-001`, `CR-002`, etc.) when asking the agent to implement fixes.

### Critical & High Priority

- [ ] **CR-001** `[Runaway State]` — Unbounded `numberposts => -1` migration query on `admin_init` and unindexed `$wpdb->options` scan in `MDS_Bandcamp_Cache::get_transients_list()`
  - **Location:** `includes/migrations/class-mds-bandcamp-migration.php:45-106`, `includes/core/class-mds-bandcamp-cache.php:54-80`
  - **Issue:**
    1. `MDS_Bandcamp_Migration::maybe_migrate_legacy_data()` runs on `admin_init` and queries `release` posts with `meta_key => 'bandcamp_shortcode'` using `'numberposts' => -1`. If even one release contains an empty or unparseable `bandcamp_shortcode` meta value, `$pending > 0` (lines 65–67, 95–105) causes the method to return early **without setting `mds_bandcamp_migration_complete`**, re-running the unbounded query on every subsequent `admin_init` request indefinitely.
    2. `MDS_Bandcamp_Cache::get_transients_list( $search = '' )` uses leading wildcards (`'%' . $wpdb->esc_like( $prefix . $search ) . '%'`) without a `LIMIT`, and when `$search` is empty it scans every transient in `wp_options`.
  - **Recommended Fix:** In `MDS_Bandcamp_Migration::maybe_migrate_legacy_data()`, query in bounded batches (`'posts_per_page' => 100, 'no_found_rows' => true`) and delete or mark unparseable `bandcamp_shortcode` meta keys so `mds_bandcamp_migration_complete` is always reached. In `MDS_Bandcamp_Cache::get_transients_list()`, default `$search` to `self::$plugin_key`, use prefix matching (`$wpdb->esc_like( $prefix . $search ) . '%'`), and add `LIMIT 250`.

- [ ] **CR-002** `[Static Defect]` — Unguarded `$package['photos'][0]` access in `blocks/bandcamp-merch/render.php` and uninitialized `$data['featured_track_*']` keys in `blocks/bandcamp-album/render.php`
  - **Location:** `blocks/bandcamp-merch/render.php:104-114`, `blocks/bandcamp-album/render.php:114-120, 167`
  - **Issue:**
    1. In `blocks/bandcamp-merch/render.php` (lines 104–114), `$package['photos'][0]['large']` and `$package['photos'][0]['medium']` are accessed unconditionally before checking whether `$package['photos']` is non-empty. If a Bandcamp merch item has no product photos (`$package['arts']` is empty), PHP emits `Warning: Undefined array key 0` and `Trying to access array offset on value of type null`.
    2. In `blocks/bandcamp-album/render.php` (lines 114–120), `$data['featured_track_mp3']`, `$data['featured_track_title']`, and `$data['featured_track_artist']` are only assigned inside `if ( $track['id'] == $data['featured_track_id'] )`. If `featured_track_id` does not match any track in `$data['tracks']`, no fallback track is selected and the custom audio player renders `<source src="">`.
  - **Recommended Fix:** Guard `.bandcamp-merch-photos-main` in `blocks/bandcamp-merch/render.php` with `if ( ! empty( $package['photos'][0] ) )` (or fall back to `$data['album_art_medium']`). In `blocks/bandcamp-album/render.php`, initialize `$data['featured_track_mp3']`, `$data['featured_track_title']`, and `$data['featured_track_artist']` before the track loop and fall back to the first track with a non-empty `mp3`.

- [ ] **CR-003** `[Static Defect]` — Arbitrary `<iframe>` origin in `blocks/bandcamp-embed/render.php` and unvalidated CSS interpolation in `blocks/bandcamp-form/render.php` and `blocks/bandcamp-discography/render.php`
  - **Location:** `blocks/bandcamp-embed/render.php:73-82`, `includes/core/class-mds-bandcamp-utilities.php:165-177`, `blocks/bandcamp-form/render.php:48-126`, `blocks/bandcamp-discography/render.php:31-79`
  - **Issue:**
    1. `MDS_Bandcamp_Utilities::kses_additions()` adds `<iframe>` globally to `wp_kses_allowed_html( 'post' )`. In `blocks/bandcamp-embed/render.php` (lines 73–76), when `$embedType === 'iframe'`, raw `$code` is passed through `html_entity_decode( $code )` and echoed via `wp_kses( $output, 'post' )` without verifying that the iframe `src` points to `https://bandcamp.com/EmbeddedPlayer/...`.
    2. In `blocks/bandcamp-form/render.php` (lines 49–125) and `blocks/bandcamp-discography/render.php` (lines 31–78), block attributes are interpolated into inline `<style>` tags using `esc_attr()` / `sanitize_text_field()` rather than strict hex/unit validators (`sanitize_hex_color()`, numeric + unit allowlists).
  - **Recommended Fix:** In `blocks/bandcamp-embed/render.php`, parse the `<iframe>` in `iframe` mode and verify its `src` host is `bandcamp.com` and path starts with `/EmbeddedPlayer/`. In `blocks/bandcamp-form/render.php` and `blocks/bandcamp-discography/render.php`, validate colors with `sanitize_hex_color()` and CSS dimensions/borders against an allowlist pattern.

- [ ] **CR-004** `[Static Defect]` — `MDS_Bandcamp_Utilities::get_contrast_color()` returns `'black'` instead of `'#000000'` for non-6-digit inputs, breaking `sanitize_hex_color()` and Bandcamp iframe `linkcol`
  - **Location:** `includes/core/class-mds-bandcamp-utilities.php:116-136`, `blocks/bandcamp-album/render.php:47, 61-63, 158`
  - **Issue:** `MDS_Bandcamp_Utilities::get_contrast_color()` returns `'#000000'` or `'#ffffff'` for 6-digit hex inputs (line 135), but returns the literal string `'black'` when `strlen( $hex ) !== 6` (line 123, e.g. 3-digit hex `#fff` or empty string). In `blocks/bandcamp-album/render.php`, line 61 passes `$buttonTextColor` to `sanitize_hex_color()` (which rejects `'black'`), and line 158 builds `https://bandcamp.com/EmbeddedPlayer/.../linkcol=black/`, which is invalid for Bandcamp's 6-digit hex URL parameter.
  - **Recommended Fix:** Expand 3-digit hex values in `MDS_Bandcamp_Utilities::get_contrast_color()` and return `'#000000'` instead of `'black'` on invalid input.

- [ ] **CR-005** `[Static Defect]` — `MDS_Bandcamp_API::fetch_bandcamp_page()` only treats HTTP 404 as an error and callers check `isset( $response['error'] )` without `is_array( $response )`
  - **Location:** `includes/api/class-mds-bandcamp-api.php:111-118, 134, 184, 341`, `includes/admin/class-mds-bandcamp-admin.php:288`
  - **Issue:**
    1. `MDS_Bandcamp_API::fetch_bandcamp_page()` (lines 111–117) only checks `if ( 404 === $response_code )` and returns the raw body for other HTTP error statuses (`403`, `429`, `500`, `502`, `503`), causing `ajax_verify_url()` (`mode === 'artist'`) to report success even when Bandcamp returns an HTTP 5xx error page.
    2. Callers in `MDS_Bandcamp_Admin::ajax_verify_url()` (line 288) and `MDS_Bandcamp_API` (lines 134, 184, 341) check `isset( $response['error'] )` on a `string|array` return value without checking `is_array( $response )` first.
  - **Recommended Fix:** In `MDS_Bandcamp_API::fetch_bandcamp_page()`, return an error array whenever `$response_code < 200 || $response_code >= 300`, and guard all callers with `if ( is_array( $response ) && ! empty( $response['error'] ) )`.

---

### Medium & Low Priority (Orphaned Code & Cleanup)

- [ ] **CR-006** `[Orphaned Code]` — Unused duplicate method `MDS_Bandcamp_Frontend::handle_bandcamp_shortcode()` and unread transient `mds_bandcamp_album_id_<md5>`
  - **Location:** `includes/frontend/class-mds-bandcamp-frontend.php:227-281`, `includes/admin/class-mds-bandcamp-admin.php:306-307`
  - **Issue:**
    1. `MDS_Bandcamp_Frontend::handle_bandcamp_shortcode()` has zero references across the codebase; the `[bandcamp]` shortcode is registered by `MDS_Bandcamp_Utilities::register_bandcamp_shortcode()` (`includes/core/class-mds-bandcamp-utilities.php:28`).
    2. `MDS_Bandcamp_Admin::ajax_verify_url()` stores `set_transient( 'mds_bandcamp_album_id_' . md5( $url ), $album_id, HOUR_IN_SECONDS )` on line 307, which is never read anywhere in the plugin.
  - **Recommended Fix:** Remove `MDS_Bandcamp_Frontend::handle_bandcamp_shortcode()`, and either check `get_transient( $cache_key )` before fetching in `ajax_verify_url()` (using the `mds_bandcamp__` prefix so `MDS_Bandcamp_Cache` tracks it) or remove the unused `set_transient()` call.

- [ ] **CR-007** `[Orphaned Code]` — Missing `uninstall.php`, orphaned `.mds-bandcamp-shortcode-input` CSS rule, and unused block attributes/variables
  - **Location:** `css/admin.css:5-7`, `blocks/bandcamp-discography/block.json:36`, `blocks/bandcamp-embed/index.js:41-46`, `blocks/*/index.js:1-33`, `blocks/*/render.php:10-13`
  - **Issue:**
    1. The plugin has no `uninstall.php` to clean up its options (`mds_bandcamp__settings`, `mds_bandcamp_migration_complete`, `mds_bandcamp_audio_player_activated`, `mds_bandcamp_block_namespace_migrated`, `mds_bandcamp_block_migration_cursor`) or transients (`_transient_mds_bandcamp*`, `gf_clear_cache_notice`, `mds_bandcamp_standalone_plugin_deactivated`) upon plugin deletion.
    2. `.mds-bandcamp-shortcode-input` in `css/admin.css:5-7` is unused.
    3. `blocks/bandcamp-discography/block.json:36` defines an unused `display_album_border` attribute; `blocks/bandcamp-embed/index.js:41-46` has an empty `useEffect` body; `blocks/*/index.js` declare unused top-level `var` bindings (`MediaUpload`, `RichText`, `InnerBlocks`, etc.); and `blocks/*/render.php` assign unused `$align` and `$className` variables.
  - **Recommended Fix:** Add an `uninstall.php` cleanup script, remove `.mds-bandcamp-shortcode-input` from `css/admin.css`, wrap block `index.js` files in an IIFE `(function (wp) { ... })(window.wp);` with only used imports, and remove the unused variables/attributes.
