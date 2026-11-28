# Changelog

## [Unreleased]

### Added
- Migrated `mds-audio-providers` to a thin-client architecture backed by **MDS Cloud Gateway (`mds-cloud-gateway`)** using the single-codebase `server/bootstrap.php` module (`server/AudioResolver.php`, `server/BandcampScraper.php`, `server/GatewayCredentials.php`) — automatically discovered in LocalWP dev and excluded from customer ZIPs via `.distignore` and `.github/workflows/release.yml`.
- Bundled `src/Gateway/GatewayClient.php`, `src/Gateway/GatewayBridge.php`, `src/Providers/ReleaseMeta.php`, and `src/Providers/GatewayProxyProvider.php` with Free vs. Pro tier gating:
  - **Free Tier (`MDS-FREE-...`):** Apple Music, Deezer, Bandcamp (+ server-side `audio-bandcamp-verify`), Audius, Mixcloud, and Audiomack.
  - **Pro Tier (`MDS-PRO-...`+):** Spotify, SoundCloud, TIDAL, Jamendo, Musicfetch (centrally managed MDS keys, zero customer API key setup), and local `FunkwhaleProvider` for private/self-hosted pods.
- Added **MDS Cloud Gateway License** activation/deactivation controls and tier status badges to **Release Manager → Settings → Audio Providers** (`POST /mds-ap/v1/license/activate`, `POST /mds-ap/v1/license/deactivate`).

### Changed
- Switched plugin header `Update URI` to `https://api.mustydungeon.com` using `GatewayBridge::register_updater()` (preserving the `.git` dev-checkout auto-update guard).
- Simplified `Credentials` to manage only local self-hosted **Funkwhale** pod credentials in Core's Hub `SecretVault` and removed the redundant `mds_rm_default_media_formats` filter (already built into `mds-release-manager` Core).
- Removed legacy local DSP HTTP provider classes from `src/Providers/` so zero proprietary DSP orchestration or Bandcamp scraping logic ships in customer plugin ZIPs.

## [2.1.2] - 2026-11-27

### Changed
- Updated plugin branding to Musty Dungeon Studios (Walton International Business Group LLC), added official SVG/PNG brand assets (`assets/icon.svg`, `icon-128x128.png`, `icon-256x256.png`), and standardized on `GPL-3.0-or-later` + `COMMERCIAL-LICENSE.md`.
- Updated the Settings tab screen-hook check (`_page_mds-release-settings`) for compatibility with MDS Release Manager `2.2.0`'s renamed **Release Manager** top-level menu.

## [2.1.1] - 2026-11-26

### Changed
- Updated plugin licensing to `GPL-3.0-or-later` (`LICENSE` + `COMMERCIAL-LICENSE.md`) and added official Musty Dungeon Studios plugin icons under `assets/`.

### Security
- Scoped `FunkwhaleProvider` Bearer token transmission (`FunkwhaleProvider::should_send_auth_token()`) strictly to requests matching the configured HTTPS Pod Base URL origin, preventing token leakage to third-party hosts.

### Fixed
- Aligned `Admin::save_release_meta()` cache invalidation (`Admin::invalidate_provider_url_cache()`) with `JamendoProvider` and `TidalProvider` `type:id` cache keys and invalidated previous keys before saving updated URLs.
- Added localized `title` attributes to official embed `<iframe>` outputs across `BandcampProvider`, `SpotifyProvider`, `TidalProvider`, `MixcloudProvider`, and `AudiomackProvider`, pruned unused legacy `.mds-bc-admin-app*` rules from `css/admin.css`, and synced the `GPL-3.0-or-later` license reference in `USER-MANUAL.md`.

## [2.1.0] - 2026-11-26

### Added
- Added `ProviderOptions` (`mds_ap_provider_options`) and per-provider **Allow iframe embed fallback** toggles in **Releases → Settings → Audio Providers** for **Spotify** and **Bandcamp**, plus official Spotify iframe embed fallback (`open.spotify.com/embed/{type}/{id}`) when `preview_url` is unavailable or a Release `spotify_url` is present without OAuth keys.
- Added 7 new streaming providers to `ProviderRegistry` (12 providers total): **Audius** (zero-auth 320kbps native HTML5 streams), **Mixcloud** (zero-auth oEmbed/widget iframe), **Audiomack** (zero-auth official iframe embed), **TIDAL** (OAuth 2.0 Open API v2 UPC lookup + TIDAL URL embed + live connection test), **Jamendo** (free `client_id` API v3.0 native `.mp3` streams + live connection test), **Funkwhale** (self-hosted/Fediverse pod native HTML5 streams + optional Bearer token + live pod test), and **Musicfetch** (optional commercial BYO `x-token` UPC resolver with `Retry-After` handling + live connection test).
- Extended `Credentials` with AES-256-GCM encrypted secret-only storage (`MDS_AP_MUSICFETCH_API_KEY`), single-key storage (`MDS_AP_JAMENDO_CLIENT_ID`), and `wp-config.php` overrides for TIDAL and Funkwhale (`MDS_AP_TIDAL_CLIENT_ID/SECRET`, `MDS_AP_FUNKWHALE_POD_URL/TOKEN`).
- Made Release **Audio Providers** metabox fields conditional on each provider's enabled state (`Admin::is_provider_enabled()`), added automatic fallback detection from Core Release Links meta keys, updated `wpml-config.xml` and `uninstall.php`, and expanded `tests/providers.php` to cover all 12 providers.

### Fixed
- Guarded GitHub updater registration in `mds-audio-providers.php` when `.git/` is present so WordPress auto-updates never overwrite a local Git development checkout.

## [2.0.3] - 2026-11-25

### Fixed
- Hardened Bandcamp URL verification (`BandcampProvider::fetch_page_html()`, `Admin::extract_album_id()`) with full browser headers, explicit HTTP 403/429/503 and Cloudflare bot-challenge detection, and `bc-page-properties` / `"album_id"` JSON fallback extractors.
- Enabled manual Bandcamp Album ID entry (`src/Admin/Admin.php`) and client-side Bandcamp Share/Embed snippet parsing (`js/metabox-verify.js`, with `.text()` status escaping) so admins can still link albums when a hosting datacenter IP is blocked by Bandcamp.

## [2.0.0] - 2026-11-25

### Added
- Unified the **Audio Providers** settings tab (`js/admin-settings.js`) with MDS Release Manager's shared `SectionInfo` and `RepeaterCard` design primitives (inline enable toggles, priority arrows, status badges, collapsible credential drawers, no duplicate heading), restyled the Release Audio Providers metabox (`src/Admin/Admin.php`) with `.mds-section-info` / `.mds-control-group`, and wired `js/metabox-verify.js` to the `mds-rm:add-buy-now-link` CustomEvent bridge for React Buy Now state compatibility.
- Repurposed `mds-bandcamp-integration` into **MDS Audio Providers** (`MDS\AudioProviders\`, `mds-audio-providers.php`) registering 5 providers into MDS Release Manager's Unified Audio API (`mds_rm_audio_providers`): **Apple Music** (zero-auth iTunes UPC lookup, native 30s `.m4a` previews), **Deezer** (zero-auth UPC lookup, native 30s `.mp3` previews), **Bandcamp** (JIT `tralbum_details` → `data-player-data` → `EmbeddedPlayer` iframe fallback), **SoundCloud** (OAuth 2.1 Client Credentials `/preview` & `/streams`), and **Spotify** (OAuth 2.0 Client Credentials UPC lookup + conditional native `preview_url`).
- Added encrypted/masked OAuth credential storage (`MDS\AudioProviders\Providers\Credentials` with `wp-config.php` constant overrides), a SoundCloud Track/Playlist URL field in the Release Audio Providers metabox, and a rebuilt WP 7.x React **Audio Providers** admin UI (`js/admin-settings.js`, `src/Admin/AdminInterface.php`, `mds-ap/v1` REST routes) for provider priority ordering, enable/disable toggles, live connection testing, and cache diagnostics.

### Removed
- Retired all legacy Bandcamp-specific Gutenberg blocks (`bandcamp-album`, `bandcamp-discography`, `bandcamp-miniplayer`), shortcodes, Elementor widgets, block-defaults backend, and legacy frontend templates in favor of `mds-release-manager`'s unified mobile-first **Release Player**.

### Added (Legacy Bandcamp Integration)
- Added a **Use Bandcamp URL** button to the MDS Release Manager Commerce meta box (`mds_rm_after_buy_now_field`) that inserts the release's verified Bandcamp URL into the Multi-Format Buy Now repeater with the **Digital Download** (`digital-download`) media format and **Bandcamp** store label.
- Added `Admin::ensure_digital_download_media_format()` on `init` (priority 20) to ensure the `digital-download` `release_media_format` term and its `fas fa-download` icon exist on the Hub site.
- Added centralized site-wide default styling and display configuration (`MDS\Bandcamp\Blocks\BlockDefaults`, option `mds_bc_block_defaults`, `GET`/`POST /mds-bandcamp/v1/settings`) applied across Gutenberg blocks, shortcodes, and Elementor widgets.
- Added a unified PHP renderer (`MDS\Bandcamp\Blocks\BlockRenderer`), supporting shortcodes (`[bandcamp-album]`, `[bandcamp-discography]`, `[bandcamp-miniplayer]`), and Elementor widgets (*Bandcamp Album*, *Bandcamp Discography*, *Bandcamp Miniplayer* with `dashicons-album`).

### Changed
- Hardened `MDS\Bandcamp\Core\Cache` and `MDS\Bandcamp\Api\BandcampApi`: removed global `wp_cache_flush()`, added lightweight key tracking (`mds_bc_cached_keys`) for Redis/Memcached compatibility, switched Heartbeat cache inspection to SQL `LENGTH(option_value)` to avoid transferring full serialized blobs, added 3-minute error backoff caching on upstream Bandcamp failures, added targeted single-item invalidation on Release/Artist save, wired `mds_rm_purge_cache` on cache clear and block-default saves (safe no-op when standalone), and registered an optional `mds_bandcamp_api` adapter on `mds_rm_cache_adapters`.
- Rebuilt the **Bandcamp Integration** settings workspace (`js/admin-settings.js`, `AdminInterface.php`, `css/admin.css`) as a WordPress 7.x React Admin UI with role-separated **Sync & Cache** and **Block Defaults** tabs, a togglable closed-by-default Live Preview pane, `SnackbarList` notices, Heartbeat-refreshed cache diagnostics, and optional Command Palette commands.
- Updated the three remaining Bandcamp block inspectors (`bandcamp-album`, `bandcamp-discography`, `bandcamp-miniplayer`) to inherit centralized site defaults, expose per-block reset controls, and use modern `@wordpress/components` layout primitives.
- Replaced all legacy inline Bandcamp logo SVGs in block notices and frontend Bandcamp buttons with Font Awesome `fab fa-bandcamp` while keeping `dashicons-album` as the official plugin, block, and Elementor widget icon.

### Removed
- Removed legacy `bandcamp_shortcode` post-meta migration (`src/Migrations/Migration.php` and the `src/Migrations/` directory).
- Removed legacy `blocks-for-bandcamp/*` block compatibility and migration (`src/Migrations/BlockMigration.php` and `tests/block-migration.php`).
- Removed the deprecated **Bandcamp Embed** (`bandcamp-embed`), **Bandcamp Form** (`bandcamp-form`), and **Bandcamp Merch** (`bandcamp-merch`) blocks and their asset references.

## [1.0.1] - 2026-11-22

### Added
- Integrated the native WordPress Heartbeat API (`heartbeat_received` / `heartbeat-send` / `heartbeat-tick`) to live-refresh the Cached Data table and total cache size while the Bandcamp settings pane is visible, without custom polling timers or page reloads.
- Added authenticated REST endpoints `POST /mds-bandcamp/v1/settings` and `POST /mds-bandcamp/v1/cache/clear` paired with `js/admin-settings.js` so Save Settings and Clear Cache operate via AJAX in place without losing the active Releases → Settings `#tab-bandcamp` tab or dying on `admin-post.php`.
- Added `uninstall.php` to remove plugin options and transient cache entries when the plugin is deleted.
- Add a **Bandcamp Integration** meta box to the Artist custom post type edit screen (`mds_bandcamp_artist_integration`, `bandcamp_artist_url`, `bandcamp_artist_subdomain`) with URL verification and subdomain extraction.
- Add a searchable, zero-REST Artist picker (`window.mdsBandcampUseArtistPicker`, enriched via `mds_rm_artist_picker_item`) to the **Bandcamp Discography** block (`mds-bandcamp/bandcamp-discography`) with automatic Artist/Release context fallback when left blank.
- Register **Bandcamp Album Player** in the MDS Release Manager **Audio Player Manager** (`mds_rm_register_audio_players`) with declarative styling controls (`size`, `height`, `bgcol`, `linkcol`, `artwork`, `tracklist`, `minimal`) and automatic fallback to the core HTML5 Player (`.mp3`) on releases without a Bandcamp album.
- Integrate with the MDS Release Manager Extension Developer Toolkit: enrich the preloaded editor catalog (`mds_rm_release_picker_item`) for zero-REST release picking in Gutenberg, register `bandcamp_album_id` and `bandcamp_album_url` post meta (`MDS_RM_Release_Meta::register_extension_meta`), and resolve contextual releases across Release, Artist, Product, and editor preview contexts (`MDS_Bandcamp_Helpers::resolve_context_release_id`).
- Added consistent Block Metadata API schemas and text domains across all six blocks.
- Added standalone block metadata validation and settings sanitization coverage.
- Added template-resolution tests for plugin-owned admin views and frontend theme overrides.
- Added a Release Pages Player setting (default enabled) that controls the Bandcamp player in the Release Links block.
- Added a searchable Release picker to the Album, Miniplayer, Merch, and Discography blocks, backed by a new editor-only `GET /mds-bandcamp/v1/releases` endpoint that lists published releases with a verified Bandcamp album.
- Blocks placed on a release now automatically use that release's verified Bandcamp album (or its artist subdomain for the Discography block) when the block has no saved selection.

### Changed
- Wrapped the Bandcamp Integration settings and Cached Data sections in `.mds-panel` containers matching the core MDS Release Manager settings layout (with standalone fallback styles in `css/admin.css`).
- Migrated all 15 production classes from legacy `includes/` (`MDS_Bandcamp_*`) to Composer PSR-4 autoloaded classes under `src/` (`MDS\Bandcamp\*`) with `declare(strict_types=1);`, PHP 8.0 native types, complete PHPDoc annotations, and WPCS/PHPCompatibilityWP compliance.
- Standardized plugin constants (`MDS_BC_*`), AJAX/admin-post actions (`mds_bc_verify_url`, `mds_bc_clear_cache`), nonces (`mds_bc_*`), script/style handles (`mds-bc-*`), ephemeral transients (`mds_bc_*`), and JS globals (`mdsBc*`), while preserving backwards-compatible `wp_options`, post meta, block names (`mds-bandcamp/*`), `[bandcamp]` shortcode, and MDS Release Manager hook contracts.
- Replaced `wp_localize_script()` with `wp_add_inline_script()` for meta-box configuration and added `wp_set_script_translations()` for editor picker scripts.
- Updated minimum WordPress compatibility metadata to `7.0` (`Tested up to: 7.x`) and moved the plugin-owned meta-box view to `templates/admin/admin-settings.php`.
- Supply the Bandcamp player to the Release Links block through the Release Manager `mds_rm_release_links_audio_html` filter, rendering the migrated `mds_bandcamp_integration` album data through the theme-overridable frontend-widget template.
- Release pickers in the Album, Miniplayer, Merch, and Discography blocks fetch only while their block is selected, deduplicate identical lookups, retry transient failures, and display "Title — Artist" labels without identifiers. A read-only snapshot field (album ID or artist subdomain) appears when `WP_DEBUG` is enabled.
- The editor releases endpoint delegates matching to the Release Manager `MDS_RM_Release_Search` service, so searches match the Release title, its Artist's name, or an exact UPC/EAN exactly like the core pickers; without MDS Release Manager the endpoint returns no items.
- Use the Dashicons `album` icon for all six blocks in the editor inserter, matching the MDS Release Manager blocks.
- Renamed the Releases → Settings extension tab from "Bandcamp" to "Bandcamp Integration".
- Replaced the manual Album URL / "Fetch Album ID" workflow in the Album, Miniplayer, and Merch blocks, and the Artist subdomain field in the Discography block, with the searchable Release picker; legacy saved block attributes keep rendering unchanged.
- Moved block appearance controls (colors, borders, padding, art opacity, aspect ratio, layout columns, grid gap, and title alignment) into the block editor's Styles tab.

### Removed
- Remove the deprecated **Release Pages Player** checkbox (`mds_bandcamp__settings[release_player]`) from the **Bandcamp Integration** settings tab; player activation is now managed through the **Audio Player Manager** tab.
- Removed the unused `POST /mds-bandcamp/v1/meta` endpoint; the release meta-box verifier continues to use its admin-ajax action.
- Consolidated production classes into one class per file and updated bootstrap loading.
- Grouped production classes and the release meta-box view by function while preserving theme template lookup and root asset URLs.
- Updated plugin documentation and compatibility metadata for WordPress 6.3 and PHP 8.0.
- Documented external Bandcamp requests, transient retention, migration state, optional Release Manager integration, and the exact standalone test commands.
- Localized admin and block-template strings and escaped template output by context while preserving public method signatures and block attributes.
- Kept admin views plugin-owned while testing the frontend child/parent theme override lookup.
- Replaced the WordPress 6.4-only admin notice helper with WordPress 6.3-compatible markup.
- Preserved the legacy `[bandcamp]` utility implementation and registered REST metadata hooks.
- Sanitized the plugin settings array and constrained transient cache durations.

### Fixed
- Resolved all WPCS/PHPCompatibilityWP violations across all 6 `blocks/*/render.php` templates (closure-scoped variables, LF line endings, WPCS formatting) and cleaned up orphaned CSS/JS/block-metadata declarations (`css/admin.css`, `blocks/bandcamp-discography/block.json`, `blocks/*/index.js`).
- Bounded legacy `bandcamp_shortcode` migration queries to 100 posts per batch (marking unparseable meta with `_mds_bc_shortcode_unparseable` so `admin_init` never loops indefinitely) and added prefix matching + `LIMIT 250` to `Cache::get_transients_list()`.
- Guarded missing merch product photos in `blocks/bandcamp-merch/render.php` (falling back to album art) and added a first-available-track fallback in `blocks/bandcamp-album/render.php`.
- Hardened `blocks/bandcamp-embed/render.php` (`iframe` mode) to allow only `https://bandcamp.com/EmbeddedPlayer/` origins and validated inline `<style>` attributes in `blocks/bandcamp-form/render.php` and `blocks/bandcamp-discography/render.php` with `sanitize_hex_color()` and strict CSS allowlists.
- Treated all non-2xx HTTP status codes as errors in `BandcampApi::fetch_bandcamp_page()`.
- Prevented legacy `bandcamp_shortcode` metadata from being deleted or migration from being marked complete when an album ID cannot be extracted.
- Removed missing view-script references from three blocks and ensured legacy album/merch blocks enqueue their shared lightbox script.
- Restored the Bandcamp player on release pages: it stopped rendering after the legacy `bandcamp_shortcode` meta was migrated, because the previous frontend listener read the deleted meta key on a `mds_rm_after_release_meta` hook that MDS Release Manager never fires. That dead listener has been removed.
