MDS Bandcamp Integration
========================

MDS Bandcamp Integration includes the Bandcamp blocks, shortcode, API, cache,
logging, and settings previously provided by Blocks for Bandcamp.

Requirements
------------

* PHP 8.0 or newer
* WordPress 6.3 or newer
* MDS Release Manager is optional. It is required only for the release meta box
  and release-page integration.

Code layout
-----------

* includes/admin/ and includes/admin/views/: settings, release meta box, and admin views.
* includes/api/: Bandcamp page fetching and REST API.
* includes/core/: dependencies, cache, logging, templates, and utilities.
* includes/blocks/ and blocks/: block registration and the six block implementations.
* includes/frontend/: frontend integration and asset registration.
* includes/migrations/: legacy settings, shortcode, and block namespace migrations.
* templates/, css/, and js/: frontend template fallback and shared assets.

Frontend overrides remain available at
mds-bandcamp-integration/<template>. WordPress resolves child-theme templates
before parent-theme templates; plugin templates are the fallback.

Included blocks
---------------

* Bandcamp Album: album information, track player/playlist, and associated merch.
* Bandcamp Discography: a responsive grid of an artist's releases.
* Bandcamp Embed: Bandcamp players from shortcode, iframe, or URL input.
* Bandcamp Form: a customizable download-code redemption form.
* Bandcamp Merch: merch listings in grid or list layouts.
* Bandcamp Miniplayer: a customizable player for a selected album track.

Settings
--------

Use Settings > MDS Bandcamp Integration to configure transient cache duration,
inspect cached data, and clear the cache. The existing settings option is
registered through the WordPress Settings API.
When MDS Release Manager is active, the same controls are also available under
Releases > Settings > Bandcamp.

The cache table lists database-backed transients. An external object cache may
store transients outside that table. Clearing the cache also flushes the site's
object cache.

Bandcamp pages are fetched through WordPress's safe HTTP API, which validates
the requested URL and redirects and can reject unsafe network destinations.

External services
-----------------

When a Bandcamp URL or artist/album identifier is used in a block, REST
request, or release meta-box verifier, the WordPress server makes an HTTPS
request to Bandcamp to retrieve public page data. The request sends the URL or
identifier and the site's server IP and request metadata. Requested URLs may be
included in the WordPress debug log when WP_DEBUG and WP_DEBUG_LOG are enabled.

Successful responses are cached as WordPress transients for the configured
duration (60 minutes by default; 1 to 1440 minutes). An external object cache
may store transients outside the database.

Bandcamp players, shortcode embeds, and embed blocks load player content from
Bandcamp in the visitor's browser. The download-code form sends the entered
code directly to https://www.bandcamp.com/yum. These browser requests may
disclose the visitor's IP address and browser request metadata to Bandcamp. See the
<a href="https://bandcamp.com/privacy">Bandcamp Privacy Policy</a> and
<a href="https://bandcamp.com/terms_of_use">Bandcamp Terms of Use</a>.

Migration
---------

On administrator requests, the plugin copies gf_blocks_for_bandcamp__settings
to mds_bandcamp__settings only when the new option is absent; the old option is
retained. Legacy bandcamp_shortcode post meta is converted to
mds_bandcamp_integration when an album ID can be extracted, and the old meta is
removed only after conversion succeeds. Migration state uses the
mds_bandcamp_migration_complete option.

Legacy block namespace conversion runs in batches of 100. The
mds_bandcamp_block_migration_cursor option records progress until
mds_bandcamp_block_namespace_migrated is set and the cursor is removed.
Attribute data is preserved. The deprecated mds_rm_bandcamp_template option is
removed after successful legacy post-meta migration. An active standalone
Blocks for Bandcamp plugin is deactivated; its settings are not deleted.

Debug logging
-------------

Set both WP_DEBUG and WP_DEBUG_LOG to true in wp-config.php to write plugin
messages to the WordPress debug log (normally wp-content/debug.log).
WP_DEBUG_LOG may instead be a custom log file path. The settings page no longer
displays or clears logs. Existing JSON log files are not deleted or migrated.

Development tests
-----------------

Run these commands from the plugin directory:

* php tests/bootstrap.php
* php tests/logging.php
* php tests/native-api.php
* php tests/block-metadata.php
* php tests/block-migration.php
* php tests/templates.php

On PowerShell, lint all PHP files with:

* Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
