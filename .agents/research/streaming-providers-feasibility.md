# Streaming Audio Providers — Ecosystem & Feasibility Research

> **Plugin Scope:** `mds-audio-providers` (Paid Tier extension for `mds-release-manager` Core)  
> **Date:** 2026-11-26  
> **Target Audience:** Indie artists, independent record labels, DJs/electronic collectives, and multi-site label networks running WordPress `7.0+` / PHP `8.0+`  
> **Baseline Providers Already Shipped (`v2.0.3`):** Apple Music (iTunes Lookup API, zero-auth UPC, 30s `.m4a`), Deezer (zero-auth UPC, 30s `.mp3`), Bandcamp (JIT `tralbum_details` → `data-player-data` → `EmbeddedPlayer` iframe), SoundCloud (OAuth 2.1 `/preview` & `/streams`), Spotify (OAuth 2.0 UPC lookup, conditional `preview_url`).

---

## 1. Executive Summary & Ecosystem Reality Check (2026)

Expanding `mds-audio-providers` requires distinguishing between two fundamentally different playback capabilities supported by `MDS\ReleaseManager\Core\Audio\AudioProviderInterface`:

1. **Native HTML5 Stream Providers (`tracks[].streamUrl`):** Return direct `.mp3`, `.m4a`, or HLS audio URLs that play natively inside Core's unified mobile-first **Release Player** (with unified tracklists, Media Session controls, and automatic 20s sampler caps for full-length streams).
2. **Official Embed / oEmbed Providers (`embedHtml`):** Return an official, Terms-of-Service-compliant `<iframe>` player (just like `BandcampProvider`'s final fallback) for DSPs that prohibit raw audio stream extraction.

### Key Market Finding

Across the major streaming industry in 2026, **raw audio preview URLs have contracted sharply** among corporate DSPs:
- **Apple Music (iTunes Lookup)** and **Deezer**—which `mds-audio-providers` *already ships*—remain the **gold standard** for zero-auth, UPC-driven 30-second native HTML5 previews.
- **TIDAL**, **Qobuz**, **Amazon Music**, **Beatport**, and **Audiomack** either restrict APIs to commercial partners or prohibit raw stream URLs outside their official SDK/iframe embeds.
- **YouTube / YouTube Music** strictly prohibits audio-only stripping, hidden iframes, or background playback under its Terms of Service.
- **Open & Indie Platforms** (**Audius**, **Jamendo**, **Funkwhale**, **Mixcloud**, **Audiomack embeds**, **TIDAL embeds**, and **Spotify official embed fallback**) offer the highest-leverage, legally clean expansion paths—especially for independent labels, electronic/hip-hop artists, DJ mixes, and self-hosted/federated catalogs.

---

## 2. Prioritized Roadmap

### Phase 1 (Tier 1 — Implemented)
1. **Per-Provider Options & Embed Fallback Toggle** (`ProviderOptions`, Spotify + Bandcamp drawers)
2. **Spotify Official Embed Fallback** (`open.spotify.com/embed/{type}/{id}`)
3. **Audius** (`AudiusProvider`, priority `45`, native 320kbps HTML5 streams)
4. **Mixcloud** (`MixcloudProvider`, priority `70`, zero-auth oEmbed + iframe widget)
5. **Audiomack** (`AudiomackProvider`, priority `75`, zero-auth iframe embed)

### Phase 2 (Tier 2 — Implemented)
6. **TIDAL** (`TidalProvider`, priority `65`, OAuth 2.0 `openapi.tidal.com/v2` UPC lookup + `embed.tidal.com` iframe)
7. **Jamendo** (`JamendoProvider`, priority `55`, free `client_id` API v3.0 native `.mp3` streams)
8. **Funkwhale** (`FunkwhaleProvider`, priority `58`, self-hosted/Fediverse REST API native HTML5 streams)

### Phase 3 (Tier 3 — Optional Paid / Future)
9. **Musicfetch** (`MusicfetchProvider`, BYO paid API key, \$50–\$200/mo, 30-DSP UPC/ISRC lookup)
10. **7digital** (Enterprise B2B, optional BYO OAuth credentials) & **Mirlo / Ampwall** (monitor for stabilized public oEmbed endpoints)
