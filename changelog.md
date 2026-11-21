# Changelog

## [Unreleased]

### Added
- Added consistent Block Metadata API schemas and text domains across all six blocks.
- Added standalone block metadata validation and settings sanitization coverage.
- Added template-resolution tests for plugin-owned admin views and frontend theme overrides.

### Changed
- Consolidated production classes into one class per file and updated bootstrap loading.
- Grouped production classes and the release meta-box view by function while preserving theme template lookup and root asset URLs.
- Updated plugin documentation and compatibility metadata for WordPress 6.3 and PHP 8.0.
- Documented external Bandcamp requests, transient retention, migration state, optional Release Manager integration, and the exact standalone test commands.
- Localized admin and block-template strings and escaped template output by context while preserving public method signatures and block attributes.
- Kept admin views plugin-owned while testing the frontend child/parent theme override lookup.
- Replaced the WordPress 6.4-only admin notice helper with WordPress 6.3-compatible markup.
- Preserved the legacy `[bandcamp]` utility implementation and registered REST metadata hooks.
- Sanitized the plugin settings array and constrained transient cache durations.

### Fixed
- Prevented legacy `bandcamp_shortcode` metadata from being deleted or migration from being marked complete when an album ID cannot be extracted.
- Removed missing view-script references from three blocks and ensured legacy album/merch blocks enqueue their shared lightbox script.
