# Refactoring Plan — MDS Bandcamp Integration

> **Audit Date:** 2026-11-22  
> **Source of Truth:** `.agents/rules/project-config.md` and global rules (`wp-general.md`, `wp-php-wpcs.md`, `wp-frontend-scripts.md`, `wp-code-docs.md`, `wp-testing-localwp.md`)  
> **Target Environment:** `http://bgw-dev.local/` (`C:\Users\Brian\Local Sites\bgw-dev\app\public`)

## Target Configuration Summary

| Setting | Current State | Target (`project-config.md`) |
| :--- | :--- | :--- |
| **Plugin Name** | `MDS Bandcamp Integration` | `MDS Bandcamp Integration` |
| **Plugin Slug / Folder** | `mds-bandcamp-integration` | `mds-bandcamp-integration` |
| **Text Domain** | `mds-bandcamp-integration` *(already aligned)* | `mds-bandcamp-integration` |
| **PHP Root Namespace** | Global (`MDS_Bandcamp_*`) | `MDS\Bandcamp\` (`src/` via Composer PSR-4) |
| **Global Class Prefix** | `MDS_Bandcamp_` | `MDS_BC_` *(only where global classes/aliases must remain)* |
| **Function / Hook Prefix** | `mds_bandcamp_` / `mds-bandcamp-` / `gf_` | `mds_bc_` / `mds-bc-` |
| **Constant Prefix** | `MDS_BANDCAMP_*` | `MDS_BC_*` |
| **Minimum WordPress** | `6.3` | `7.0` |
| **Tested Up To WordPress** | `6.x` / unspecified | `7.x` |
| **Minimum PHP** | `8.0` | `8.0` |

---

## Phase 1: Composer, Linters & Build Configuration

### 1.1 Composer & Autoloading (`composer.json`)
- [ ] Verify [composer.json](file:///c:/Users/Brian/Local%20Sites/bgw-dev/app/public/wp-content/plugins/mds-bandcamp-integration/composer.json) PSR-4 mapping (`"MDS\\Bandcamp\\": "src/"`), PHP `>=8.0`, and WPCS/PHPCompatibilityWP dev dependencies *(already configured)*.
- [ ] Run `composer install` in the plugin root to generate `vendor/autoload.php` and install `vendor/bin/phpcs` / `vendor/bin/phpcbf`.
- [ ] Verify `.gitignore` excludes `/vendor/`, `/node_modules/`, and `/build/` (never commit third-party dependencies or unignored build artifacts unless intentionally tracked for distribution).
- [ ] Update [mds-bandcamp-integration.php](file:///c:/Users/Brian/Local%20Sites/bgw-dev/app/public/wp-content/plugins/mds-bandcamp-integration/mds-bandcamp-integration.php) to load `vendor/autoload.php` with a graceful `file_exists()` guard (and admin notice fallback if missing).

### 1.2 PHPCS / WPCS Configuration (`phpcs.xml.dist`)
- [ ] Verify [phpcs.xml.dist](file:///c:/Users/Brian/Local%20Sites/bgw-dev/app/public/wp-content/plugins/mds-bandcamp-integration/phpcs.xml.dist) prefixes (`mds_bc_`, `MDS_BC_`, `MDS\Bandcamp`), text domain (`mds-bandcamp-integration`), `minimum_wp_version=7.0`, and `testVersion=8.0-` *(already configured)*.
- [ ] Add a `PrefixAllGlobals` exclusion for `/tests/*` in `phpcs.xml.dist` (standalone test fixtures intentionally define core/sibling stubs such as `WP_Post`, `WP_Error`, `WP_REST_Request`, `WP_REST_Server`, and `MDS_RM_Release_Search`).
- [ ] Audit global-scope template variables in `blocks/*/render.php` and `templates/frontend-widget.php`: WPCS `PrefixAllGlobals` flags non-prefixed top-level variables (`$album`, `$data`, `$align`, `$className`, `$mds_bandcamp_context_id`). Either wrap block render logic in a `src/Blocks/` render method or prefix top-level template variables with `$mds_bc_`.
- [ ] Run `composer phpcs` after Phase 2–4 to establish a clean baseline; use `composer phpcbf` to normalize mixed indentation (tabs vs. 4-space indentation in `Admin`, `Cache`, `Blocks`, `Frontend`, `Helpers`) and Allman braces (`{` on a new line) to WPCS same-line braces.

### 1.3 Frontend Tooling (`package.json`) & Block Metadata (`block.json`)
- [ ] **Decision:** Decide whether to adopt `@wordpress/scripts` (`package.json` with `build`, `start`, `lint:js`, `lint:css`, `format`) now or in a dedicated frontend phase. Currently the plugin has no `package.json` and authors vanilla JS/CSS directly in `blocks/*/` and `js/`.
  - [ ] If adopting `@wordpress/scripts`: scaffold `package.json` with `@wordpress/scripts`, move editable JS/CSS sources into `src/blocks/*` and `src/js/*`, compile to `build/`, require generated `build/*.asset.php` manifests in `Assets` and `Admin`, and migrate `window.wp.*` globals to ES imports (`@wordpress/blocks`, `@wordpress/block-editor`, `@wordpress/components`, `@wordpress/i18n`).
  - [ ] If keeping vanilla JS for this refactor: still add `wp_set_script_translations( $handle, 'mds-bandcamp-integration' )` for scripts using `wp.i18n.__` (`js/release-picker.js` and block editor scripts).
- [ ] Audit all 6 `blocks/*/block.json` files:
  - `apiVersion: 3`, `textdomain: "mds-bandcamp-integration"`, and `category: "mds-release-manager"` are already aligned across all 6 blocks.
  - [ ] Remove the unused `display_album_border` attribute from `blocks/bandcamp-discography/block.json` (flagged in `.agents/code-review.md` CR-007).
  - [ ] Add `selectors` definitions to `block.json` files where Global Styles support is needed for WordPress 7.x.
  - [ ] **Do NOT rename** `name` (`mds-bandcamp/bandcamp-*`) without a block migration routine (see Phase 5).

### 1.4 Plugin Header & Compatibility Metadata
- [ ] Update [mds-bandcamp-integration.php](file:///c:/Users/Brian/Local%20Sites/bgw-dev/app/public/wp-content/plugins/mds-bandcamp-integration/mds-bandcamp-integration.php):
  - `Requires at least: 6.3` → `7.0`
  - `@package MDS_Bandcamp_Integration` → `@package MDS\Bandcamp`
- [ ] Update `README.md` and `readme.txt` compatibility requirements (`WordPress 7.0`, `Tested up to: 7.x`, `PHP 8.0`) during final wrap-up.

---

## Phase 2: PHP Classes, PSR-4 Namespaces & Autoloading

All 15 production classes currently live in `includes/` as global `final class MDS_Bandcamp_*` classes loaded via manual `require_once` statements in `mds-bandcamp-integration.php`.

> **Cross-Plugin Audit (`mds-release-manager`):** Zero direct references to `MDS_Bandcamp_*` PHP classes or `MDS_BANDCAMP_*` constants exist in `mds-release-manager`. All cross-plugin communication uses `mds_rm_*` WordPress hooks/filters.

### 2.1 Class-to-PSR-4 Migration Map (`includes/` → `src/`)

Every new class file under `src/` must include `declare(strict_types=1);`, `namespace MDS\Bandcamp\...;`, `@package MDS\Bandcamp`, explicit `use` imports for WordPress/sibling global classes (`\WP_Post`, `\WP_Error`, `\WP_REST_Request`, `\WP_REST_Response`, `\WP_REST_Server`, `\WP_Block`, `\MDS_RM_Release_Meta`, `\MDS_RM_Release_Search`, `\MDS_RM_Audio_Players`), PHP 8.0 native types, and complete PHPDoc blocks.

| Legacy File (`includes/`) | Legacy Class | Target PSR-4 File (`src/`) | Target FQCN |
| :--- | :--- | :--- | :--- |
| `includes/admin/class-mds-bandcamp-admin.php` | `MDS_Bandcamp_Admin` | `src/Admin/Admin.php` | `MDS\Bandcamp\Admin\Admin` |
| `includes/admin/class-mds-bandcamp-admin-interface.php` | `MDS_Bandcamp_Admin_Interface` | `src/Admin/AdminInterface.php` | `MDS\Bandcamp\Admin\AdminInterface` |
| `includes/api/class-mds-bandcamp-api.php` | `MDS_Bandcamp_API` | `src/Api/BandcampApi.php` | `MDS\Bandcamp\Api\BandcampApi` |
| `includes/api/class-mds-bandcamp-rest-api.php` | `MDS_Bandcamp_REST_API` | `src/Api/RestApi.php` | `MDS\Bandcamp\Api\RestApi` |
| `includes/blocks/class-mds-bandcamp-blocks.php` | `MDS_Bandcamp_Blocks` | `src/Blocks/Blocks.php` | `MDS\Bandcamp\Blocks\Blocks` |
| `includes/core/class-mds-bandcamp-cache.php` | `MDS_Bandcamp_Cache` | `src/Core/Cache.php` | `MDS\Bandcamp\Core\Cache` |
| `includes/core/class-mds-bandcamp-dependencies.php` | `MDS_Bandcamp_Dependencies` | `src/Core/Dependencies.php` | `MDS\Bandcamp\Core\Dependencies` |
| `includes/core/class-mds-bandcamp-helpers.php` | `MDS_Bandcamp_Helpers` | `src/Core/Helpers.php` | `MDS\Bandcamp\Core\Helpers` |
| `includes/core/class-mds-bandcamp-logging.php` | `MDS_Bandcamp_Logging` | `src/Core/Logging.php` | `MDS\Bandcamp\Core\Logging` |
| `includes/core/class-mds-bandcamp-templates.php` | `MDS_Bandcamp_Templates` | `src/Core/Templates.php` | `MDS\Bandcamp\Core\Templates` |
| `includes/core/class-mds-bandcamp-utilities.php` | `MDS_Bandcamp_Utilities` | `src/Core/Utilities.php` | `MDS\Bandcamp\Core\Utilities` |
| `includes/frontend/class-mds-bandcamp-assets.php` | `MDS_Bandcamp_Assets` | `src/Frontend/Assets.php` | `MDS\Bandcamp\Frontend\Assets` |
| `includes/frontend/class-mds-bandcamp-frontend.php` | `MDS_Bandcamp_Frontend` | `src/Frontend/Frontend.php` | `MDS\Bandcamp\Frontend\Frontend` |
| `includes/migrations/class-mds-bandcamp-migration.php` | `MDS_Bandcamp_Migration` | `src/Migrations/Migration.php` | `MDS\Bandcamp\Migrations\Migration` |
| `includes/migrations/class-mds-bandcamp-block-migration.php` | `MDS_Bandcamp_Block_Migration` | `src/Migrations/BlockMigration.php` | `MDS\Bandcamp\Migrations\BlockMigration` |

### 2.2 Admin View Relocation
- [ ] Move `includes/admin/views/admin-settings.php` → `templates/admin/meta-box.php` (or `views/admin-settings.php`) and update `$default_template_directory` in `MDS\Bandcamp\Core\Templates::render()` so `includes/` can be removed completely once all classes move to `src/`.

### 2.3 Internal Call Sites, `use` Statements & Hook Callbacks
- [ ] Create a lightweight `MDS\Bandcamp\Plugin` (`src/Plugin.php`) or hook all `::register_hooks()` calls inside `mds-bandcamp-integration.php` on `plugins_loaded` (per `wp-php-wpcs.md` lifecycle guidelines) instead of executing hooks at file-include time.
- [ ] Update all internal class references across `src/` to use `use MDS\Bandcamp\...;` imports:
  - Fix the casing bug `MDS_BANDCAMP_Dependencies::is_mds_active()` in `Assets.php:77` → `Dependencies::is_mds_active()`.
  - Ensure all `add_action()` / `add_filter()` callbacks use `[ self::class, 'method' ]` (or `[ $this, 'method' ]` if converted to instance services).
- [ ] Update class references in all 5 block `render.php` templates:
  - `blocks/bandcamp-album/render.php`: `BandcampApi`, `Helpers`, `Utilities`
  - `blocks/bandcamp-discography/render.php`: `BandcampApi`, `Helpers`
  - `blocks/bandcamp-embed/render.php`: `BandcampApi`
  - `blocks/bandcamp-merch/render.php`: `BandcampApi`, `Helpers`
  - `blocks/bandcamp-miniplayer/render.php`: `BandcampApi`, `Helpers`, `Utilities`
- [ ] **Optional Theme Compatibility Shims:** If any child/parent theme override on `http://bgw-dev.local/` calls `MDS_Bandcamp_Helpers` or `MDS_Bandcamp_Utilities` directly, register `class_alias()` shims in `mds-bandcamp-integration.php` (or a dedicated `src/Compat.php`) and deprecate them for removal in a future major version.
- [ ] Update all 8 standalone test files in `tests/` (`bootstrap.php`, `logging.php`, `native-api.php`, `block-metadata.php`, `block-migration.php`, `templates.php`, `release-links-player.php`, `releases-endpoint.php`) to autoload/require `src/` classes and reference `MDS\Bandcamp\*` FQCNs.

---

## Phase 3: Constants, Functions, Hooks, Handles & Text Domain

### 3.1 Constants (`MDS_BANDCAMP_*` → `MDS_BC_*`)
- [ ] Rename all 8 plugin constants in `mds-bandcamp-integration.php` and across all call sites:
  - `MDS_BANDCAMP_VERSION` → `MDS_BC_VERSION`
  - `MDS_BANDCAMP_PATH` → `MDS_BC_PATH`
  - `MDS_BANDCAMP_URL` → `MDS_BC_URL`
  - `MDS_BANDCAMP_PLUGIN_VERSION` → `MDS_BC_PLUGIN_VERSION` *(or consolidate to `MDS_BC_VERSION`)*
  - `MDS_BANDCAMP_PLUGIN_PATH` → `MDS_BC_PLUGIN_PATH`
  - `MDS_BANDCAMP_PLUGIN_URL` → `MDS_BC_PLUGIN_URL`
  - `MDS_BANDCAMP_PLUGIN_BASENAME` → `MDS_BC_PLUGIN_BASENAME`
  - `MDS_BANDCAMP_PLUGIN_SLUG` → `MDS_BC_PLUGIN_SLUG`
- [ ] Update constant usages in:
  - `src/Admin/Admin.php`
  - `src/Blocks/Blocks.php`
  - `src/Core/Templates.php`
  - `src/Frontend/Assets.php`
  - `src/Frontend/Frontend.php`
  - `src/Migrations/BlockMigration.php`
  - `tests/bootstrap.php`, `tests/block-migration.php`, `tests/native-api.php`, `tests/release-links-player.php`, `tests/templates.php`

### 3.2 Procedural Functions & Hooks
- [ ] **Global Procedural Functions:** None exist in production code (audit confirmed zero global `function` definitions outside `tests/`).
- [ ] **Hooks Owned by MDS Release Manager — DO NOT RENAME:**
  - `mds_rm_site_settings_tabs`
  - `mds_rm_register_custom_meta_boxes`
  - `mds_rm_save_custom_meta`
  - `mds_rm_register_audio_players`
  - `mds_rm_release_links_audio_html`
  - `mds_rm_release_picker_item`
  - `mds_rm_artist_picker_item`
- [ ] **Plugin-Owned AJAX, Admin-Post & Nonce Actions — Rename to `mds_bc_`:**
  - `wp_ajax_mds_bandcamp_verify_url` → `wp_ajax_mds_bc_verify_url` (update `src/Admin/Admin.php` and `js/metabox-verify.js`).
  - `admin_post_mds_bandcamp__clear_cache` → `admin_post_mds_bc_clear_cache` (update `src/Core/Cache.php` and form `action` in `src/Admin/AdminInterface.php`).
  - Nonce action/field names: `mds_bandcamp_save` → `mds_bc_save`, `mds_bandcamp_nonce` → `mds_bc_nonce`, `mds_bandcamp_verify_nonce` → `mds_bc_verify_nonce` (update `src/Admin/Admin.php` and meta-box template).
  - Meta-box form field IDs/names (`mds_bandcamp_url`, `mds_bandcamp_album_id`, `mds_bandcamp_artist_url`, `mds_bandcamp_artist_subdomain`, `mds_bandcamp_verify_btn`, `mds_bandcamp_verify_artist_btn`, `mds_bandcamp_status`) → `mds_bc_*` in lockstep across `src/Admin/Admin.php`, the meta-box view, `js/metabox-verify.js`, and `tests/templates.php`.

### 3.3 Script/Style Handles & JS Globals
- [ ] Rename all plugin-owned asset handles to `mds-bc-*`:
  - `mds-bandcamp-blocks` → `mds-bc-blocks` (`Assets.php`, `BlockMigration.php`)
  - `mds-bandcamp-admin` → `mds-bc-admin` (`Assets.php`)
  - `mds-bandcamp-public` → `mds-bc-public` (`Assets.php`, `Frontend.php`)
  - `mds-bandcamp-release-picker` → `mds-bc-release-picker` (`Assets.php`)
  - `mds-bandcamp-metabox-verify` → `mds-bc-metabox-verify` (`Admin.php`)
  - *Keep `mds-rm-release-picker` dependency handle unchanged (owned by MDS Release Manager).*
- [ ] Replace `wp_localize_script()` in `src/Admin/Admin.php` (`enqueue_metabox_assets()`) with `wp_add_inline_script( 'mds-bc-metabox-verify', 'const mdsBcMetabox = ' . wp_json_encode( $data ) . ';', 'before' )` per `wp-frontend-scripts.md`.
- [ ] Rename JS globals in lockstep across PHP and JS:
  - `mdsBandcampMetabox` → `mdsBcMetabox` (`Admin.php`, `js/metabox-verify.js`)
  - `window.mdsBandcampDebug` → `window.mdsBcDebug` (`Assets.php`, `js/release-picker.js`)
  - `window.mdsBandcampUseReleasePicker` → `window.mdsBcUseReleasePicker` (`js/release-picker.js`, `blocks/bandcamp-{album,merch,miniplayer}/index.js`)
  - `window.mdsBandcampUseArtistPicker` → `window.mdsBcUseArtistPicker` (`js/release-picker.js`, `blocks/bandcamp-discography/index.js`)
  - *Keep `window.mdsRmToolkit` and `window.mdsRmCatalog` unchanged (owned by MDS Release Manager).*

### 3.4 Internationalization (Text Domain)
- [ ] Confirmed via audit: **100% of PHP translation calls and `block.json` files already use `'mds-bandcamp-integration'`**.
- [ ] Register script translations via `wp_set_script_translations( $handle, 'mds-bandcamp-integration' )` for `mds-bc-release-picker` and block editor scripts.

---

## Phase 4: Data & Backward-Compatibility Risks (`http://bgw-dev.local/`)

Renaming persisted WordPress identifiers without a migration routine will break existing posts, settings, or theme overrides on `http://bgw-dev.local/`. Review each risk tier before executing:

### 4.1 High Risk — DO NOT RENAME (Recommended: Keep As-Is)

| Category | Identifier(s) | Impact if Renamed | Recommendation |
| :--- | :--- | :--- | :--- |
| **Block Names** | `mds-bandcamp/bandcamp-album`, `bandcamp-discography`, `bandcamp-embed`, `bandcamp-form`, `bandcamp-merch`, `bandcamp-miniplayer` | Breaks every saved block in `wp_posts.post_content` (renders "This block contains unexpected or invalid content"). | **Keep `mds-bandcamp/*`**. Already migrated once from `blocks-for-bandcamp/*`. |
| **Shortcode** | `[bandcamp]` | Breaks any legacy posts/pages using `[bandcamp ...]` shortcodes. | **Keep `[bandcamp]`**. |
| **Audio Player ID** | `'bandcamp'` in `mds_rm_register_audio_players` (`mds_rm_audio_players` / `mds_rm_audio_player_settings`) | Disconnects saved Audio Player Manager selection and styling settings in MDS Release Manager (`MDS_RM_Audio_Players` explicitly normalizes `blocks_for_bandcamp` → `bandcamp`). | **Keep `'bandcamp'`**. |
| **Theme Template Override Dir** | `mds-bandcamp-integration/frontend-widget.php` | Breaks child/parent theme overrides on `http://bgw-dev.local/`. | **Keep `mds-bandcamp-integration/`** (already matches plugin slug). |
| **Release Post Meta** | `mds_bandcamp_integration`, `bandcamp_album_id`, `bandcamp_album_url`, legacy `bandcamp_shortcode` | Breaks Bandcamp Album Player, Release pickers, and dynamic blocks on all existing Releases. | **Keep existing meta keys** (or implement a batched read-fallback + write-through migration before deprecating). |
| **Artist Post Meta** | `mds_bandcamp_artist_integration`, `bandcamp_artist_url`, `bandcamp_artist_subdomain` | Breaks Bandcamp Discography Artist picker and contextual Artist resolution on all existing Artists. | **Keep existing meta keys** (or implement a batched migration). |

### 4.2 High Risk — Requires One-Time Migration if Renamed

| Category | Current Key(s) | Proposed `mds_bc_` Key(s) | Risk & Required Migration |
| :--- | :--- | :--- | :--- |
| **Plugin Settings Option** | `mds_bandcamp__settings` | `mds_bc_settings` | Resets saved `transient` TTL to default `60` if renamed directly. **Migration:** On `admin_init`, if `mds_bc_settings` is absent and `mds_bandcamp__settings` exists, copy it once and keep or delete the legacy key. |
| **Migration State Flags** | `mds_bandcamp_migration_complete`, `mds_bandcamp_audio_player_activated`, `mds_bandcamp_block_namespace_migrated`, `mds_bandcamp_block_migration_cursor` | `mds_bc_migration_complete`, `mds_bc_audio_player_activated`, `mds_bc_block_namespace_migrated`, `mds_bc_block_migration_cursor` | **Critical:** If renamed without migrating existing flags, `Migration` and `BlockMigration` will re-run on `http://bgw-dev.local/` and overwrite `mds_rm_audio_players`! **Migration:** Check legacy option keys first, copy flags to `mds_bc_*`, then delete legacy flags. |

### 4.3 Medium Risk — Safe to Rename in Lockstep

| Category | Current Identifier(s) | Proposed `mds_bc_` Identifier(s) | Notes |
| :--- | :--- | :--- | :--- |
| **Transients** | `mds_bandcamp__*` (API cache), `gf_clear_cache_notice`, `mds_bandcamp_standalone_plugin_deactivated`, unused `mds_bandcamp_album_id_<md5>` | `mds_bc_*` (API cache), `mds_bc_clear_cache_notice`, `mds_bc_standalone_plugin_deactivated` | Ephemeral cache repopulates automatically. Update `Cache::handle_clear_cache()` to purge both `mds_bc_` and legacy `mds_bandcamp_` transients. Remove unused `mds_bandcamp_album_id_<md5>` (see `code-review.md` CR-006). |
| **REST Route** | `GET /mds-bandcamp/v1/releases` | Keep `mds-bandcamp/v1/releases` *(recommended)* or rename to `mds-bc/v1/releases` | Editor-only (`edit_posts`), consumed solely by `js/release-picker.js`. Keeping `mds-bandcamp/v1` matches the plugin slug; if renamed, update `RestApi.php`, `js/release-picker.js`, and `tests/releases-endpoint.php` together. |
| **Settings Page Slug & Meta Box ID** | `options-general.php?page=mds_bandcamp`, meta box `mds_bandcamp_settings` | `options-general.php?page=mds_bc`, meta box `mds_bc_settings` | Low impact: resets per-user collapsed/sorted meta-box positions (`wp_usermeta`) and breaks any bookmarked direct settings URL. |

---

## Phase 5: Execution & Verification Checklist

Execute phases in order so the plugin stays functional at every commit:

- [ ] **Step 1 — Tooling Setup:** Run `composer install`, verify `vendor/autoload.php`, adjust `phpcs.xml.dist` `/tests/*` rule if needed.
- [ ] **Step 2 — PSR-4 Class Migration:** Create `src/` classes with `declare(strict_types=1);` and `MDS\Bandcamp\` namespaces, relocate `admin-settings.php`, update `mds-bandcamp-integration.php` bootstrap and `blocks/*/render.php`, remove `includes/`.
- [ ] **Step 3 — Constants, Hooks & Assets:** Rename `MDS_BANDCAMP_*` → `MDS_BC_*`, AJAX/admin-post/nonce actions → `mds_bc_*`, script/style handles → `mds-bc-*`, JS globals → `mdsBc*`, replace `wp_localize_script()` with `wp_add_inline_script()`, add `wp_set_script_translations()`.
- [ ] **Step 4 — Data Migrations (if renaming options/transients):** Implement idempotent option/flag migration in `MDS\Bandcamp\Migrations\Migration` and dual-prefix transient cleanup in `MDS\Bandcamp\Core\Cache`.
- [ ] **Step 5 — WPCS & Code Quality:** Run `composer phpcbf` and `composer phpcs`; resolve all remaining WPCS/PHPCompatibilityWP errors (tab indentation, same-line braces, Yoda conditions, PHPDoc `@package MDS\Bandcamp`, `@since`).
- [ ] **Step 6 — Tests & LocalWP Verification:**
  - [ ] Run `php -l` across all PHP files.
  - [ ] Update and run all 8 standalone test suites (`tests/*.php`).
  - [ ] Verify on `http://bgw-dev.local/` (Release & Artist meta boxes, AJAX URL verification, Audio Player Manager Bandcamp player + HTML5 fallback, Gutenberg Album/Miniplayer/Merch/Discography pickers, and Settings → Clear Cache).
- [ ] **Step 7 — Wrap-Up:** Run `/wrap-up` to sync `.agents/technical-blueprint.md`, `README.md`, `readme.txt`, and `changelog.md`.
