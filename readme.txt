=== MDS Audio Providers ===
Contributors: mustydungeon
Tags: music, audio, preview, apple-music, deezer, bandcamp, soundcloud, spotify
Requires at least: 7.0
Tested up to: 7.x
Requires PHP: 8.0
Stable tag: 2.0.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Audio preview providers (Apple Music, Deezer, Bandcamp, SoundCloud, and Spotify) for MDS Release Manager's Unified Audio API and Release Player.

== Description ==

MDS Audio Providers extends **MDS Release Manager 2.0.0+** with five streaming audio preview providers that plug directly into the core **Release Player** (`mds-rm/release-player` / `[release-player]`):

* **Apple Music** — Zero-configuration UPC/EAN lookup via the iTunes Search API with native 30-second `.m4a` previews.
* **Deezer** — Zero-configuration UPC/EAN lookup with native 30-second `.mp3` previews.
* **Bandcamp** — Just-in-Time track/album stream resolution with automatic embed fallback and a **Use Bandcamp URL** Buy Now helper.
* **SoundCloud** — OAuth 2.1 Client Credentials preview/stream resolution using each release's SoundCloud Track or Playlist URL.
* **Spotify** — OAuth 2.0 Client Credentials UPC lookup with native `preview_url` support when provided by Spotify.

Manage provider priority order, enable/disable toggles, encrypted OAuth credentials (with `wp-config.php` constant overrides), live connection tests, and cache diagnostics directly inside **Releases → Settings → Audio Providers**.

== Installation ==

1. Install and activate **MDS Release Manager 2.0.0+**.
2. Upload the `mds-audio-providers` folder to `wp-content/plugins/` (or install the ZIP via **Plugins → Add New → Upload Plugin**).
3. Activate **MDS Audio Providers** (or **Network Activate** on Multisite).
4. Open **Releases → Settings → Audio Providers** to reorder providers, toggle individual services, and optionally add SoundCloud or Spotify OAuth credentials.

== Frequently Asked Questions ==

= Do I need API keys to use Apple Music or Deezer? =

No. Apple Music and Deezer resolve previews automatically from each release's UPC/EAN with zero credentials required.

= What happens if a release has no preview on any third-party provider? =

MDS Release Manager's core **Release Player** automatically falls back to the built-in HTML5 Audio Preview (`.mp3`) URL saved on the release.

== Changelog ==

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
