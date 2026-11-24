# MDS Bandcamp Integration

Adds six Bandcamp blocks, a legacy `[bandcamp]` shortcode, Bandcamp data caching,
admin settings, REST metadata support, and MDS Release Manager integration.

## Requirements

- WordPress 7.0 or newer (tested up to 7.x)
- PHP 8.0 or newer
- Composer (for PSR-4 autoloading and WPCS linting)
- MDS Release Manager is optional
  - Without it, the six blocks, shortcode, settings, and REST endpoint remain available.
  - Release meta-box and release-page integration require MDS Release Manager.

## Blocks

- `mds-bandcamp/bandcamp-album`
- `mds-bandcamp/bandcamp-discography`
- `mds-bandcamp/bandcamp-embed`
- `mds-bandcamp/bandcamp-form`
- `mds-bandcamp/bandcamp-merch`
- `mds-bandcamp/bandcamp-miniplayer`

Legacy `blocks-for-bandcamp/*` content is migrated and remains renderable.

The Album, Miniplayer, and Merch blocks select their release through a
searchable picker that lists published releases with a verified Bandcamp album
(preloaded in memory via `window.mdsRmCatalog` when MDS Release Manager is
active, or served by the editor-only `GET /mds-bandcamp/v1/releases` endpoint,
requiring `edit_posts`). Matching delegates to the Release Manager search
service, so terms match the release title or its artist's name, and options
display as "Title — Artist". Picking a release copies its album ID and URL into
the block, and a read-only snapshot field is shown when `WP_DEBUG` is enabled.

The Discography block (`mds-bandcamp/bandcamp-discography`) uses a searchable
Artist picker (`window.mdsBcUseArtistPicker`) that lists published
Artists with a configured **Bandcamp Artist URL** (set in the **Bandcamp
Integration** meta box on the Artist edit screen and preloaded via the
`mds_rm_artist_picker_item` filter). Leaving the Artist selection blank
automatically resolves the current context (the current Artist page, or the
linked Artist of the current Release). Appearance controls live in the editor's
Styles tab.

## Release Manager integration

With MDS Release Manager active, the release editor's Bandcamp meta box saves a
verified album URL and ID as `mds_bandcamp_integration` post meta (along with
registered `bandcamp_album_id` and `bandcamp_album_url` release meta keys). The
plugin registers **Bandcamp Album Player** in the **Audio Player Manager** tab
under Releases → Settings (`mds_rm_register_audio_players`), exposing site-wide
player size, height, background color, link/accent color, artwork, tracklist,
and minimal-square controls. When Bandcamp is selected as the active player, it
renders through the theme-overridable `frontend-widget.php` template for
releases with a verified Bandcamp album and automatically falls back to the
core HTML5 Player (`.mp3`) on releases without a Bandcamp album.

 In the block editor, the plugin enriches the preloaded Release Manager catalog
(`mds_rm_release_picker_item`) with each release's Bandcamp `album_id`, `url`,
and `subdomain` so the Album, Miniplayer, Merch, and Discography block pickers
filter and search in memory without extra REST calls.

## Class Layout

Production classes use Composer PSR-4 autoloading under the `MDS\Bandcamp\` root namespace in `src/`:

- `src/Admin/`: `Admin` (Release/Artist meta boxes & AJAX verifier) and `AdminInterface` (settings page & MDS RM tab).
- `src/Api/`: `BandcampApi` (Bandcamp page fetching & parsing) and `RestApi` (REST endpoint & catalog enrichment).
- `src/Core/`: `Cache`, `Dependencies`, `Helpers`, `Logging`, `Templates`, and `Utilities`.
- `src/Blocks/` and `blocks/`: block registration (`Blocks`) and the six block implementations.
- `src/Frontend/`: `Frontend` (Audio Player Manager & Release Links filter) and `Assets` (script/style registration).
- `templates/`, `css/`, and `js/`: frontend template fallback (`templates/frontend-widget.php`), plugin-owned admin meta-box view (`templates/admin/admin-settings.php`), and shared assets.

The template loader checks `mds-bandcamp-integration/<template>` with WordPress
`locate_template()`, which selects a child-theme override before a parent-theme
override, then falls back to the plugin copy. The release/artist meta-box view is
plugin-owned at `templates/admin/admin-settings.php`.

Stored options, post meta keys, block names (`mds-bandcamp/*`), `[bandcamp]`
shortcode, and MDS Release Manager hook contracts are preserved for backwards
compatibility. The entrypoint loads `vendor/autoload.php` and registers each
hook-owning class once.

## External services and data

The plugin requests public Bandcamp pages over HTTPS from the WordPress server
when a Bandcamp URL or artist/album identifier is used by a block, REST request,
or the release meta-box verifier. Requests use the WordPress HTTP API. Bandcamp
receives the site's server IP and request metadata; requested Bandcamp URLs and
identifiers may also appear in the WordPress debug log when `WP_DEBUG` and
`WP_DEBUG_LOG` are enabled.

Successful API responses are stored as WordPress transients for the configured
cache duration (60 minutes by default, configurable from 1 to 1440 minutes).
The site's object-cache implementation may store transients outside the
database. The settings page's Clear Cache action deletes this plugin's
transients and flushes the site's object cache.

When a Bandcamp player, shortcode, or embed is displayed, the visitor's browser
loads the player from Bandcamp. The download-code form sends the entered code
directly to Bandcamp at `https://www.bandcamp.com/yum`. Bandcamp may receive the
visitor's IP address, browser request metadata, and submitted code in those
browser requests. Review [Bandcamp's Privacy Policy](https://bandcamp.com/privacy)
and [Terms of Use](https://bandcamp.com/terms_of_use) and disclose these
connections in your site's privacy information.

## Migration behavior

Migrations run during administrator requests and use these WordPress options
and post meta:

- If `mds_bandcamp__settings` does not exist, the plugin copies
  `gf_blocks_for_bandcamp__settings`; the old option is retained.
- Legacy `bandcamp_shortcode` post meta is converted to
  `mds_bandcamp_integration` when an album ID can be extracted. The old meta is
  removed only after successful conversion. `mds_bandcamp_migration_complete`
  is set after migration completes.
- Legacy block names are updated in batches of 100. The
  `mds_bandcamp_block_migration_cursor` option records progress and is removed
  when the `mds_bandcamp_block_namespace_migrated` completion option is set.
  Only block namespace comments are changed; serialized block attributes are
  preserved.
- The deprecated `mds_rm_bandcamp_template` option is removed after successful
  legacy post-meta migration. An active standalone Blocks for Bandcamp plugin
  is deactivated; its settings option is not deleted.

## Development

Install Composer dependencies (PSR-4 autoloader + WPCS/PHPCompatibilityWP):

```powershell
composer install
composer phpcs
```

Run the focused standalone suites from the plugin root:

```powershell
php tests/bootstrap.php
php tests/logging.php
php tests/native-api.php
php tests/block-metadata.php
php tests/block-defaults.php
php tests/templates.php
php tests/release-links-player.php
php tests/releases-endpoint.php
```

Run PHP syntax checks across the plugin:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

The standalone fixtures do not replace a full oldest-supported-version
WordPress activation/editor test. Distribution rights and a formal plugin
license declaration must be confirmed before packaging for WordPress.org.
