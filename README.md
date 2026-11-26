# MDS Audio Providers

Streaming audio preview providers (**Apple Music**, **Deezer**, **Bandcamp**, **Audius**, **SoundCloud**, **Jamendo**, **Funkwhale**, **Spotify**, **TIDAL**, **Mixcloud**, **Audiomack**, and optional **Musicfetch**) for **MDS Release Manager**'s Unified Audio API and mobile-first **Release Player**.

## Overview

MDS Audio Providers registers twelve audio preview providers into MDS Release Manager's `mds_rm_audio_providers` registry. When a visitor plays a release through core's **Release Player** (`mds-rm/release-player` block, `[release-player]` shortcode, or the Release Links audio slot), MDS Release Manager queries enabled providers in your configured priority order until one returns a playable preview, falling back to core's local HTML5 Audio Preview URL if needed.

| Provider | Slug | Default Priority | Auth Required | Playback Mode | Lookup Key | Cache TTL |
| :--- | :--- | :---: | :--- | :--- | :--- | :---: |
| **Apple Music** | `apple_music` | `20` | None (zero-auth) | Native HTML5 (30s `.m4a`) | Release `upc_ean` | 12 hours |
| **Deezer** | `deezer` | `30` | None (zero-auth) | Native HTML5 (30s `.mp3`) | Release `upc_ean` | 12 hours |
| **Bandcamp** | `bandcamp` | `40` | None (zero-auth) | Native HTML5 JIT + optional Embed toggle | Bandcamp Album ID | 30 minutes |
| **Audius** | `audius` | `45` | None (zero-auth) | Native HTML5 (320kbps `.mp3`) | Audius URL | 1 hour |
| **SoundCloud** | `soundcloud` | `50` | OAuth 2.1 Client Credentials | Native HTML5 (`/preview` / `/streams`) | SoundCloud URL | 15 minutes |
| **Jamendo** | `jamendo` | `55` | Free `client_id` | Native HTML5 (`.mp3`) | Jamendo URL | 12 hours |
| **Funkwhale** | `funkwhale` | `58` | None (public) / optional Bearer token | Native HTML5 (self-hosted pod) | Funkwhale URL | 1 hour |
| **Spotify** | `spotify` | `60` | OAuth 2.0 (or URL-only Embed) | Native `preview_url` + optional Embed toggle | Release `upc_ean` / `spotify_url` | 12 hours |
| **TIDAL** | `tidal` | `65` | Optional OAuth 2.0 v2 (or URL-only) | Official `embed.tidal.com` iframe | Release `upc_ean` / TIDAL URL | 12 hours |
| **Mixcloud** | `mixcloud` | `70` | None (zero-auth) | Official Mixcloud iframe widget | Mixcloud URL | 12 hours |
| **Audiomack** | `audiomack` | `75` | None (zero-auth) | Official Audiomack iframe embed | Audiomack URL | 12 hours |
| **Musicfetch** | `musicfetch` | `80` | Optional Paid BYO `x-token` | Native HTML5 (30s previews across 30+ DSPs) | Release `upc_ean` | 12 hours |

## Requirements

- **WordPress:** `7.0` or newer (tested up to `7.x`)
- **PHP:** `8.0` or newer (OpenSSL recommended for AES-256-GCM credential encryption)
- **Required Plugin:** **MDS Release Manager** (`mds-release-manager`) with the Unified Audio API
- **Development Tools:** Composer (for PSR-4 autoloading and WPCS/PHPCompatibilityWP linting)

## Installation & Configuration

1. Install and activate **MDS Release Manager**.
2. Install this plugin into `wp-content/plugins/mds-audio-providers` and activate it (or **Network Activate** on Multisite).
3. Open **Releases → Settings → Audio Providers** to:
   - Reorder provider priority and toggle individual providers on or off.
   - Toggle **"Allow iframe embed fallback"** per provider on **Bandcamp** and **Spotify**.
   - Configure optional credentials (**SoundCloud**, **Spotify**, **TIDAL**, **Jamendo**, **Funkwhale**, or **Musicfetch**) and run live **Test Connection** checks.
   - Inspect active cached provider lookups and selectively clear the provider transient cache.
4. On individual **Release** edit screens (Hub site on Multisite):
   - **Apple Music, Deezer, Spotify, TIDAL, and Musicfetch** resolve automatically from the release's **Barcode (UPC/EAN)**.
   - The **Audio Providers** metabox conditionally displays fields **only for enabled URL-based providers** (Bandcamp, Audius, SoundCloud, Jamendo, Funkwhale, TIDAL, Mixcloud, Audiomack), and automatically detects matching URLs from Core's Release Links meta when present.

### `wp-config.php` Credential Overrides

All secrets stored in the database are encrypted at rest with AES-256-GCM (falling back to AES-256-CBC + HMAC-SHA256). You can also override any credential via `wp-config.php` constants:

```php
define( 'MDS_AP_SOUNDCLOUD_CLIENT_ID', '...' );
define( 'MDS_AP_SOUNDCLOUD_CLIENT_SECRET', '...' );

define( 'MDS_AP_SPOTIFY_CLIENT_ID', '...' );
define( 'MDS_AP_SPOTIFY_CLIENT_SECRET', '...' );

define( 'MDS_AP_TIDAL_CLIENT_ID', '...' );
define( 'MDS_AP_TIDAL_CLIENT_SECRET', '...' );

define( 'MDS_AP_JAMENDO_CLIENT_ID', '...' );

define( 'MDS_AP_FUNKWHALE_POD_URL', 'https://open.audio' );
define( 'MDS_AP_FUNKWHALE_TOKEN', '...' ); // Optional, private pods only.

define( 'MDS_AP_MUSICFETCH_API_KEY', '...' ); // Optional BYO paid key.
```

## Architecture Summary

- **Namespace & Autoloading:** PSR-4 under `MDS\AudioProviders\` mapped to `src/` via Composer (`vendor/autoload.php`).
- **Blocks & Shortcodes:** This plugin intentionally registers **no standalone blocks or shortcodes**. All playback renders through MDS Release Manager's unified **Release Player** (`mds-rm/release-player` / `[release-player]`).
- **Multilingual & Uninstall:** Includes `languages/mds-audio-providers.pot`, `wpml-config.xml` (`copy` rules for all provider URL/ID release meta), and `uninstall.php` (single-site and Multisite cleanup of plugin options and `mds_ap_*` transients while preserving Release post metadata).

## Development Workflow (LocalWP & Composer)

```powershell
composer install
composer phpcs
composer phpcbf
php tests/providers.php
```

Run PHP syntax checks across all source files:

```powershell
Get-ChildItem -Path src, tests, mds-audio-providers.php, uninstall.php -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Admin JS (`js/admin-settings.js`, `js/metabox-verify.js`) and CSS (`css/admin.css`) are shipped directly with no npm build step required.

## Documentation

- [User Manual](USER-MANUAL.md) — End-user and site-administrator guide.
- [Changelog](changelog.md) — Full version history.
- [Technical Blueprint](.agents/technical-blueprint.md) — Architecture, hooks, REST endpoints, and storage contracts.
- [Streaming Providers Feasibility Study](.agents/research/streaming-providers-feasibility.md) — Ecosystem research and phased expansion roadmap.
