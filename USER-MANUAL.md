# MDS Audio Providers — User Manual

**Version:** `2.0.0`  
**Requirements:** WordPress `7.0+` · PHP `8.0+` · **MDS Release Manager `2.0.0+`**

## Table of Contents
1. [Overview](#overview)
2. [Installation & Setup](#installation--setup)
3. [Configuring Audio Providers](#configuring-audio-providers)
4. [Per-Release Audio & Buy Now Controls](#per-release-audio--buy-now-controls)
5. [Troubleshooting & FAQ](#troubleshooting--faq)
6. [Uninstalling & Data Cleanup](#uninstalling--data-cleanup)

---

## 1. Overview

**MDS Audio Providers** connects five major streaming audio sources to **MDS Release Manager's** built-in **Release Player** (`mds-rm/release-player` / `[release-player]`): **Apple Music**, **Deezer**, **Bandcamp**, **SoundCloud**, and **Spotify**.

Audio manifests are resolved **Just-in-Time** when a visitor clicks Play — zero remote API calls run during page loads — and cached cleanly for fast repeat playback.

```mermaid
flowchart LR
    Player["Release Player (Play Click)"] --> API["MDS Release Manager AudioApi"]
    API --> P1["1. Apple Music (Zero-Auth UPC)"]
    P1 -->|"No Match"| P2["2. Deezer (Zero-Auth UPC)"]
    P2 -->|"No Match"| P3["3. Bandcamp (JIT Track / Embed)"]
    P3 -->|"No Match"| P4["4. SoundCloud (OAuth 2.1)"]
    P4 -->|"No Match"| P5["5. Spotify (OAuth 2.0)"]
    P5 -->|"No Match"| Fallback["Core HTML5 .mp3 Preview"]
```

---

## 2. Installation & Setup

1. Ensure **MDS Release Manager `2.0.0+`** is installed and active.
2. Upload `mds-audio-providers-2.0.0.zip` via **Plugins → Add New → Upload Plugin** and click **Activate** (or **Network Activate** on Multisite).
3. Open **Releases → Settings → Audio Providers** to review provider priority and optional OAuth credentials.

---

## 3. Configuring Audio Providers

Navigate to **Releases → Settings → Audio Providers**:

| Provider | Auth Required? | How It Resolves Previews |
| :--- | :--- | :--- |
| **Apple Music** | None (Zero-Auth) | Queries iTunes Search API by Release UPC/EAN for native 30s `.m4a` previews. |
| **Deezer** | None (Zero-Auth) | Queries Deezer API by Release UPC/EAN for native 30s `.mp3` previews. |
| **Bandcamp** | None | Resolves Bandcamp album/track streams from the Release's Bandcamp URL (with automatic embed fallback). |
| **SoundCloud** | OAuth 2.1 Client ID & Secret | Resolves previews/streams from the Release's SoundCloud Track or Playlist URL. |
| **Spotify** | OAuth 2.0 Client ID & Secret | Queries Spotify Catalog API by UPC/EAN for native `preview_url` clips when available. |

### Reordering, Enabling, and Testing Providers
- Use the **Up / Down** arrows on each provider card to set your preferred priority order.
- Toggle a provider **On / Off** directly from its card header.
- Click **Configure** on SoundCloud or Spotify to enter OAuth credentials (encrypted at rest; or define `MDS_AP_SOUNDCLOUD_CLIENT_ID`, `MDS_AP_SOUNDCLOUD_CLIENT_SECRET`, `MDS_AP_SPOTIFY_CLIENT_ID`, `MDS_AP_SPOTIFY_CLIENT_SECRET` in `wp-config.php`).
- Click **Test Connection** to verify live API reachability, or **Clear Audio Cache** to force fresh lookups.

> **Screenshot:** *Releases → Settings → Audio Providers showing provider priority cards, status badges, and connection testing.*

---

## 4. Per-Release Audio & Buy Now Controls

On the **Edit Release** screen, the **Audio Providers** meta box lets you:
- Enter or verify a **Bandcamp Album/Track URL** and click **Use Bandcamp URL** to insert it directly into the Release Manager **Buy Now** repeater under the **Digital Download** format.
- Enter a **SoundCloud Track or Playlist URL** for SoundCloud preview playback.

---

## 5. Troubleshooting & FAQ

- **Do I need API keys for Apple Music or Deezer?** No — both work immediately out of the box using each Release's UPC/EAN.
- **Why did a Release play the local `.mp3` instead of Apple Music or Spotify?** Either no enabled provider had a preview matching that UPC/EAN, or that provider is toggled off in **Releases → Settings → Audio Providers**.
- **Where are OAuth credentials stored?** Encrypted in WordPress options (or overridden via `wp-config.php` constants, which take precedence and lock the admin input fields).

---

## 6. Uninstalling & Data Cleanup

- **Deactivating** the plugin disables the five external providers while keeping the core **Release Player** working with local `.mp3` previews.
- **Deleting** the plugin via **Plugins → Delete** runs `uninstall.php` to remove plugin options and cached audio manifests.
