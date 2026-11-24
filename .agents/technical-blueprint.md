# Technical Blueprint — MDS Bandcamp Integration

## Architecture Overview

- **Plugin Slug / Text Domain:** `mds-bandcamp-integration`
- **PHP Root Namespace:** `MDS\Bandcamp\` (Composer PSR-4 autoloaded from `src/` via `vendor/autoload.php`)
- **Prefixes:** `MDS_BC_` (constants), `mds_bc_` (AJAX/admin-post actions, nonces, ephemeral transients), `mds-bc-` (script/style handles)
- **Compatibility:** WordPress `7.0+` (`Tested up to: 7.x`), PHP `8.0+`
- **Build System:** Composer (`composer.json` + `phpcs.xml.dist` with WPCS 3.x and PHPCompatibilityWP). Frontend assets (`blocks/*/index.js`, `js/*.js`, `css/*.css`) are currently authored directly without an npm/webpack build step.
- **Optional Dependency:** **MDS Release Manager** (`MDS\Bandcamp\Core\Dependencies::is_mds_active()`). Standalone blocks, `[bandcamp]` shortcode, caching, and Settings API work without it; Release/Artist meta boxes, Audio Player Manager registration, and preloaded editor catalogs activate when MDS Release Manager is present.

## Directory Structure

```text
mds-bandcamp-integration/
├── composer.json                          # PSR-4 autoloading ("MDS\\Bandcamp\\": "src/") + WPCS/PHPCompatibilityWP
├── phpcs.xml.dist                         # WPCS + PHPCompatibilityWP ruleset (mds_bc_ / MDS_BC_ / MDS\Bandcamp)
├── mds-bandcamp-integration.php           # Plugin header, MDS_BC_* constants, vendor/autoload.php, ::register_hooks() bootstrap
├── src/
│   ├── Admin/
│   │   ├── Admin.php                      # MDS\Bandcamp\Admin\Admin — Release & Artist meta boxes, AJAX URL verifier
│   │   └── AdminInterface.php             # MDS\Bandcamp\Admin\AdminInterface — Settings API page + MDS RM settings tab
│   ├── Api/
│   │   ├── BandcampApi.php                # MDS\Bandcamp\Api\BandcampApi — HTTPS Bandcamp fetcher & HTML/JSON parser
│   │   └── RestApi.php                    # MDS\Bandcamp\Api\RestApi — GET /mds-bandcamp/v1/releases + catalog enrichment
│   ├── Blocks/
│   │   ├── BlockDefaults.php              # MDS\Bandcamp\Blocks\BlockDefaults — Site-wide defaults (mds_bc_block_defaults) & render_block_data resolution
│   │   ├── BlockRenderer.php              # MDS\Bandcamp\Blocks\BlockRenderer — Shared Album, Discography & Miniplayer renderer
│   │   └── Blocks.php                     # MDS\Bandcamp\Blocks\Blocks — Block registration (3 blocks) & category
│   ├── Core/
│   │   ├── Cache.php                      # MDS\Bandcamp\Core\Cache — Transient inspection, clearing, and cache duration
│   │   ├── Dependencies.php               # MDS\Bandcamp\Core\Dependencies — MDS Release Manager availability checks
│   │   ├── Helpers.php                    # MDS\Bandcamp\Core\Helpers — Release/Artist meta accessors, context resolution
│   │   ├── Logging.php                    # MDS\Bandcamp\Core\Logging — WP_DEBUG / WP_DEBUG_LOG wrapper
│   │   ├── Templates.php                  # MDS\Bandcamp\Core\Templates — Theme-overridable & admin template loader
│   │   └── Utilities.php                  # MDS\Bandcamp\Core\Utilities — [bandcamp] shortcode, kses rules, contrast helper
│   ├── Frontend/
│   │   ├── Assets.php                     # MDS\Bandcamp\Frontend\Assets — Admin React UI, editor defaults, and frontend CSS/JS enqueueing
│   │   ├── Frontend.php                   # MDS\Bandcamp\Frontend\Frontend — Audio Player Manager & Release Links filter
│   │   └── Shortcodes.php                 # MDS\Bandcamp\Frontend\Shortcodes — [bandcamp-album], [bandcamp-discography], [bandcamp-miniplayer]
│   └── Integrations/
│       └── Elementor/
│           ├── AlbumWidget.php            # Elementor Bandcamp Album widget (dashicons-album)
│           ├── DiscographyWidget.php      # Elementor Bandcamp Discography widget (dashicons-album)
│           ├── Elementor.php              # Elementor widget registration hook
│           └── MiniplayerWidget.php       # Elementor Bandcamp Miniplayer widget (dashicons-album)
├── blocks/                                # Three Gutenberg blocks (bandcamp-album, bandcamp-discography, bandcamp-miniplayer)
├── css/                                   # admin.css, blocks.css, public.css
├── js/                                    # blocks.js (lightbox), metabox-verify.js, release-picker.js
├── templates/
│   ├── frontend-widget.php                # Theme-overridable Bandcamp player wrapper
│   └── admin/admin-settings.php           # Plugin-owned Release/Artist meta-box view
└── tests/                                 # Standalone PHP test suites (no WP core required)
```

## Data Storage Contracts (Backwards-Compatible)

### WordPress Options

| Option Key | Purpose |
| :--- | :--- |
| `mds_bandcamp__settings` | Plugin settings array (`transient` cache TTL in minutes, 1–1440, default 60). Preserved for backwards compatibility. |
| `mds_bandcamp_migration_complete` | Flag set after legacy `bandcamp_shortcode` post-meta migration finishes. |
| `mds_bandcamp_audio_player_activated` | One-time migration flag for activating the Bandcamp Audio Player in MDS Release Manager. |
| `mds_bandcamp_block_migration_cursor` | Batch cursor (100 posts/batch) while migrating `blocks-for-bandcamp/*` blocks; deleted on completion. |
| `mds_bandcamp_block_namespace_migrated` | Flag set when legacy block namespace migration completes. |

### Post Meta

| Post Type | Meta Key | Value |
| :--- | :--- | :--- |
| `release` | `mds_bandcamp_integration` | Associative array: `url`, `album_id`, `saved_at`. |
| `release` | `bandcamp_album_id` | Flat string album ID (registered via `MDS_RM_Release_Meta::register_extension_meta`). |
| `release` | `bandcamp_album_url` | Flat string album URL (registered via `MDS_RM_Release_Meta::register_extension_meta`). |
| `artist` | `mds_bandcamp_artist_integration` | Associative array: `url` (`https://<subdomain>.bandcamp.com`), `subdomain`, `saved_at`. |
| `artist` | `bandcamp_artist_url` | Flat canonical artist Bandcamp URL. |
| `artist` | `bandcamp_artist_subdomain` | Flat normalized Bandcamp artist subdomain. |

### Transients

- Cached Bandcamp API responses are stored with the `mds_bandcamp__` transient prefix for `transient` minutes (default `60`).
- Admin notices use `mds_bc_clear_cache_notice` and `mds_bc_standalone_plugin_deactivated`.
- Clearing cache purges both `mds_bc_` and legacy `mds_bandcamp` transients.

## Hook, REST & AJAX Contracts

### MDS Release Manager Integration Hooks

| Hook | Type | Handler | Purpose |
| :--- | :--- | :--- | :--- |
| `mds_rm_site_settings_tabs` | Filter | `MDS\Bandcamp\Admin\AdminInterface::register_release_manager_tab` | Registers the **Bandcamp Integration** tab under Releases → Settings. |
| `mds_rm_register_custom_meta_boxes` | Action | `MDS\Bandcamp\Admin\Admin::register_meta_box` | Registers the **Bandcamp Integration** meta box (`mds_bc_settings`) on `release` and `artist` post types. |
| `mds_rm_save_custom_meta` | Action | `MDS\Bandcamp\Admin\Admin::save_meta` | Persists Release (`album_id`, `url`) and Artist (`artist_url`, `subdomain`) Bandcamp metadata. |
| `mds_rm_after_buy_now_field` | Action | `MDS\Bandcamp\Admin\Admin::render_buy_now_button` | Outputs the **Use Bandcamp URL** button in the Release Commerce meta box to populate a `Digital Download` / `Bandcamp` Buy Now row. |
| `init` (priority `20`) | Action | `MDS\Bandcamp\Admin\Admin::ensure_digital_download_media_format` | Ensures the `digital-download` `release_media_format` term and `fas fa-download` icon exist on the Hub site. |
| `mds_rm_register_audio_players` | Filter | `MDS\Bandcamp\Frontend\Frontend::register_audio_player` | Registers **Bandcamp Album Player** (`bandcamp`) in the Audio Player Manager with declarative styling fields. |
| `mds_rm_release_links_audio_html` | Filter | `MDS\Bandcamp\Frontend\Frontend::filter_release_links_audio` | Supplies the rendered Bandcamp player (or empty string to fall back to core HTML5 `.mp3`) to the Release Links block. |
| `mds_rm_release_picker_item` | Filter | `MDS\Bandcamp\Api\RestApi::enrich_release_picker_item` | Appends `album_id`, `url`, and `subdomain` to preloaded `window.mdsRmCatalog` Release records. |
| `mds_rm_artist_picker_item` | Filter | `MDS\Bandcamp\Api\RestApi::enrich_artist_picker_item` | Appends `artist_url` and `subdomain` to preloaded `window.mdsRmCatalog` Artist records. |

### REST API & AJAX Endpoints

- **`GET /mds-bandcamp/v1/releases`** (`MDS\Bandcamp\Api\RestApi::handle_releases_request`)
  - **Permission:** `current_user_can( 'edit_posts' )`
  - **Args:** `search` (`string`), `page` (`int >= 1`)
  - **Behavior:** Delegates matching to `MDS_RM_Release_Search` and filters to published Releases with a verified Bandcamp `album_id`.
- **`POST /mds-bandcamp/v1/settings`** (`MDS\Bandcamp\Api\RestApi::handle_save_settings_request`)
  - **Permission:** `current_user_can( 'manage_options' )`
  - **Args:** `transient` (`int` 1–1440, required)
  - **Behavior:** Sanitizes and persists `mds_bandcamp__settings` via AJAX (`js/admin-settings.js`) without reloading or losing the `#tab-bandcamp` tab.
- **`POST /mds-bandcamp/v1/cache/clear`** (`MDS\Bandcamp\Api\RestApi::handle_clear_cache_request`)
  - **Permission:** `current_user_can( 'manage_options' )`
  - **Behavior:** Calls `MDS\Bandcamp\Core\Cache::clear_all()` to purge `mds_bandcamp*` and `mds_bc_*` transients and flush object cache, returning JSON for in-place table updates.
- **`wp_ajax_mds_bc_verify_url`** (`MDS\Bandcamp\Admin\Admin::ajax_verify_url`)
  - Nonce-protected (`mds_bc_verify_nonce`) AJAX endpoint used by `js/metabox-verify.js` (`mdsBcMetabox`) to verify Bandcamp album or artist URLs.
- **`admin_post_mds_bc_clear_cache`** (`MDS\Bandcamp\Core\Cache::handle_clear_cache`)
  - Non-JS fallback handler that calls `Cache::clear_all()` and redirects back to `wp_get_referer()` (appending `#tab-bandcamp` on Release Manager settings).
- **`heartbeat_received`** (`MDS\Bandcamp\Core\Cache::handle_heartbeat_received`)
  - Native WordPress Heartbeat API filter that appends `mds_bc_cache` (`transients`, `total_size`) when `data.mds_bc_refresh_cache` is requested by a `manage_options` user while the Bandcamp settings pane is active.

### Editor & Frontend Runtime Contracts

- **`js/release-picker.js`** exposes `window.mdsBcUseReleasePicker` (Album, Miniplayer, Merch blocks) and `window.mdsBcUseArtistPicker` (Discography block), preferring in-memory filtering of `window.mdsRmCatalog` with REST fallback.
- **Asset Handles:** `mds-bc-blocks`, `mds-bc-admin`, `mds-bc-public`, `mds-bc-release-picker`, `mds-bc-metabox`.
- **Templates:** `MDS\Bandcamp\Core\Templates::render()` resolves frontend templates (`frontend-widget.php`) via `locate_template( 'mds-bandcamp-integration/<template>' )` before falling back to `templates/`, while `admin-settings.php` is strictly plugin-owned under `templates/admin/`.
