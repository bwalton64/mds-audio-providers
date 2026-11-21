# MDS Bandcamp Integration

Adds six Bandcamp blocks, a legacy `[bandcamp]` shortcode, Bandcamp data caching,
admin settings, REST metadata support, and MDS Release Manager integration.

## Requirements

- WordPress 6.3 or newer
- PHP 8.0 or newer
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

## Release Manager integration

With MDS Release Manager active, the release editor's Bandcamp meta box saves a
verified album URL and ID as `mds_bandcamp_integration` post meta. On the
frontend, the plugin supplies the small album player to the Release Links block
through the `mds_rm_release_links_audio_html` filter, rendered via the
theme-overridable `frontend-widget.php` template. The Release Pages Player
setting (default enabled) on the plugin settings page or the Bandcamp tab under
Releases → Settings turns this player off.

## Class Layout

Production classes use the `MDS_Bandcamp_` prefix and are grouped by function:

- `includes/admin/` and `includes/admin/views/`: admin interfaces and plugin-owned views.
- `includes/api/`: Bandcamp page fetching and REST endpoints.
- `includes/core/`: cache, dependencies, helpers, logging, templates, and utilities.
- `includes/blocks/`: registration of blocks stored under `blocks/`.
- `includes/frontend/`: frontend integration and asset registration.
- `includes/migrations/`: legacy settings, shortcode metadata, and block namespace migration.
- `templates/`, `css/`, and `js/`: frontend template fallback and shared assets.

The template loader checks `mds-bandcamp-integration/<template>` with WordPress
`locate_template()`, which selects a child-theme override before a parent-theme
override, then falls back to the plugin copy. The release meta-box view is
plugin-owned at `includes/admin/views/admin-settings.php`.

Public class names, aliases, hooks, block names, and stored attribute schemas
are preserved for compatibility. The entrypoint loads classes from the
functional folders and registers each hook-owning class once.

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

Run the focused standalone suites from the plugin root:

```powershell
php tests/bootstrap.php
php tests/logging.php
php tests/native-api.php
php tests/block-metadata.php
php tests/block-migration.php
php tests/templates.php
php tests/release-links-player.php
```

Run PHP syntax checks across the plugin:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

The standalone fixtures do not replace a full oldest-supported-version
WordPress activation/editor test. Distribution rights and a formal plugin
license declaration must be confirmed before packaging for WordPress.org.
