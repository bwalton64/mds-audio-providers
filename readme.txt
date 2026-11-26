=== MDS Audio Providers ===
Contributors: mustydungeon
Tags: music, audio, preview, apple-music, deezer, bandcamp, soundcloud, spotify, tidal, audius, mixcloud, audiomack
Requires at least: 7.0
Tested up to: 7.x
Requires PHP: 8.0
Stable tag: 2.1.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Twelve streaming audio preview providers (Apple Music, Deezer, Bandcamp, Audius, SoundCloud, Jamendo, Funkwhale, Spotify, TIDAL, Mixcloud, Audiomack, and Musicfetch) for MDS Release Manager's Unified Audio API and Release Player.

== Description ==

MDS Audio Providers extends **MDS Release Manager** with twelve streaming audio preview providers that plug directly into the core **Release Player** (`mds-rm/release-player` / `[release-player]`):

* **Apple Music** — Zero-configuration UPC/EAN lookup via the iTunes Lookup API with native 30-second `.m4a` previews.
* **Deezer** — Zero-configuration UPC/EAN lookup via Deezer's public API with native 30-second `.mp3` previews.
* **Bandcamp** — Just-in-Time track/album stream resolution with a per-provider `EmbeddedPlayer` iframe fallback toggle, manual Album ID / Share-Embed snippet support, and a **Use Bandcamp URL** Buy Now helper.
* **Audius** — Zero-configuration native 320kbps HTML5 streaming via the Audius Open Audio Protocol.
* **SoundCloud** — OAuth 2.1 Client Credentials preview and stream resolution using each release's SoundCloud Track or Playlist URL.
* **Jamendo** — Direct HTML5 `.mp3` streaming via Jamendo API v3.0 using a free Jamendo Client ID.
* **Funkwhale** — Self-hosted / Fediverse pod native HTML5 streaming (zero-auth for public pods; optional Bearer token for private pods).
* **Spotify** — OAuth 2.0 Client Credentials UPC lookup for native 30-second `preview_url` tracks with a per-provider official Spotify iframe embed fallback toggle.
* **TIDAL** — OAuth 2.0 Open API v2 UPC/barcode lookup (or direct TIDAL URL parsing) rendering the official TIDAL embed player.
* **Mixcloud** — Zero-configuration official Mixcloud oEmbed/iframe widget for DJ sets, radio shows, and long-form mixes.
* **Audiomack** — Zero-configuration official Audiomack iframe embed for songs, albums, and playlists.
* **Musicfetch (Optional BYO Paid Key)** — Commercial multi-DSP UPC preview resolver covering 30+ services with encrypted API key storage and `Retry-After` rate-limit handling.

Manage provider priority order, enable/disable toggles, per-provider iframe embed fallback toggles, encrypted credentials (with `wp-config.php` constant overrides), live connection tests, and selective transient cache diagnostics directly inside **Releases → Settings → Audio Providers**.

== Installation ==

1. Install and activate **MDS Release Manager**.
2. Upload the `mds-audio-providers` folder to `wp-content/plugins/` (or install the ZIP via **Plugins → Add New → Upload Plugin**).
3. Activate **MDS Audio Providers** (or **Network Activate** on Multisite).
4. Open **Releases → Settings → Audio Providers** to reorder providers, toggle individual services and iframe fallback options, and optionally configure API credentials.

== Frequently Asked Questions ==

= Do I need API keys to get started? =

No. Apple Music and Deezer resolve previews automatically from each release's UPC/EAN, and Bandcamp, Audius, Mixcloud, Audiomack, Funkwhale (public pods), TIDAL (URL mode), and Spotify (URL embed mode) work with zero API keys required.

= What if my hosting server is blocked when clicking "Verify URL" for Bandcamp? =

You can paste Bandcamp's Share/Embed `<iframe>` snippet directly into the **Bandcamp Release URL** field (which extracts the Album ID and URL locally in your browser) or type the numeric **Bandcamp Album ID** manually.

= What happens if a release has no preview on any third-party provider? =

MDS Release Manager's core **Release Player** automatically falls back to the built-in HTML5 Audio Preview URL saved on the release.

== Changelog ==

= 2.1.1 =

* Security: Scoped `FunkwhaleProvider` Bearer token transmission strictly to requests matching the configured HTTPS Pod Base URL origin.
* Fixed Release metabox cache invalidation for `JamendoProvider` and `TidalProvider` (`type:id` cache keys) and cleared previous keys before saving updated URLs.
* Added localized `title` attributes to official embed `<iframe>` players (`Bandcamp`, `Spotify`, `TIDAL`, `Mixcloud`, `Audiomack`), pruned unused legacy CSS in `css/admin.css`, updated licensing to `GPL-3.0-or-later` (`LICENSE` + `COMMERCIAL-LICENSE.md`), and added official plugin icons under `assets/`.

= 2.1.0 =

* Added per-provider **Allow iframe embed fallback** toggles (`ProviderOptions`) for **Spotify** and **Bandcamp**, enabling official Spotify iframe embed fallback when native `preview_url` tracks are unavailable or when a Spotify URL is present without API keys.
* Expanded the Unified Audio API registry from 5 to 12 providers: added **Audius** (zero-auth 320kbps native HTML5 streams), **Mixcloud** (zero-auth oEmbed/widget iframe), **Audiomack** (zero-auth official iframe embed), **TIDAL** (OAuth 2.0 Open API v2 UPC lookup + TIDAL URL embed), **Jamendo** (free `client_id` API v3.0 direct `.mp3` streams), **Funkwhale** (self-hosted/Fediverse pod native streams), and **Musicfetch** (optional BYO paid `x-token` multi-DSP UPC resolver with `Retry-After` handling).
* Updated the Release **Audio Providers** metabox to show URL fields only for currently enabled providers while automatically detecting matching URLs from Core Release Links meta.
* Guarded GitHub updater registration when `.git/` is present so local development checkouts are never overwritten by WordPress auto-updates.

= 2.0.3 =

* Hardened Bandcamp URL verification (`BandcampProvider::fetch_page_html()` & `Admin::extract_album_id()`) with full browser headers, Cloudflare/bot-challenge detection, and `bc-page-properties` / `"album_id"` JSON fallback extractors.
* Enabled manual Bandcamp Album ID entry and client-side Bandcamp Share/Embed `<iframe>` snippet parsing in `js/metabox-verify.js` when a hosting server's IP is rate-limited by Bandcamp.

= 2.0.0 =

* Rebuilt as **MDS Audio Providers** (`MDS\AudioProviders\`) registering Apple Music, Deezer, Bandcamp, SoundCloud, and Spotify into MDS Release Manager's Unified Audio API (`mds_rm_audio_providers`).
* Integrated the React **Audio Providers** settings workspace directly into **Releases → Settings → Audio Providers** using MDS Release Manager's shared `SectionInfo` and `RepeaterCard` design primitives.
* Added encrypted/masked OAuth credential storage (`wp-config.php` override support), live provider connection testing, SoundCloud Track/Playlist URL support, and `mds-rm:add-buy-now-link` Buy Now integration.
* Retired legacy Bandcamp-specific blocks, shortcodes, and Elementor widgets in favor of core's unified mobile-first **Release Player**.

= 1.0.1 =

* Added WordPress Heartbeat cache diagnostics, AJAX REST settings saves, and `uninstall.php` cleanup.
