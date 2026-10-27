# Seasonal Modes GitHub distribution

Revision 1. Source: user request on 2026-10-26 and public repository selection.

- REQ-001: Keep Seasonal Modes as a theme-independent WordPress plugin. Preserve the existing seasonal packs, settings, and external pack overrides.
- REQ-002: Add a public GitHub release update source that offers newer stable versions in WordPress. Ignore drafts, prereleases, malformed responses and releases without the expected installable ZIP; fail safely on network errors. Do not force automatic installation.
- REQ-003: Add GitHub Actions checks and tagged release packaging. The release ZIP must contain a single seasonal-modes/ folder, runtime files, pack assets and credits. Version metadata and tag must match. Publish only after validation succeeds.
- REQ-004: Document installation, adding seasonal packs, updating version numbers, creating releases, checking WordPress for updates and preservation of custom packs.
- REQ-005: Create the public amirrad98/seasonal-modes repository and publish source plus the first release if authenticated GitHub access supports it. Record inaccessible operations as blocked rather than complete.
- REQ-006: Validate updater behavior and package contents with automated checks, including rejected remote data. Record independent Plan Guardian audits and distinguish local checks from a real WordPress installation/update test.

Scope excludes changing the seasonal artwork, adding a specific holiday, changing a live site, and building a theme. Existing 1.0.0 installations must manually install the first updater-enabled version once.
