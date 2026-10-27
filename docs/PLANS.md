# Plan

Revision 2. Added actual WordPress upgrade verification in CI where local Docker access is unavailable.

## M1: Plugin release infrastructure

Dependencies: existing source inspected; public repository selected by user.

Deliverables: native WordPress update integration using the Update URI hostname hook; stable GitHub release lookup and bounded caching; tag/version validation; reproducible ZIP builder; CI and release workflows; updater and package checks; release instructions.

Acceptance:
- REQ-001: Existing packs/assets remain intact, plugin settings keys remain unchanged, and external override loading remains present. Verify hashes against the baseline and exercise pack discovery and date selection in tests.
- REQ-002: Updater scopes responses to this plugin; a newer stable release with the exact ZIP yields an update; malformed version/payload, wrong asset URL/name, missing ZIP, drafts, prereleases and network failures yield no unsafe update. Cache successful and failed lookups and expose a capability/nonce protected manual refresh. Test these cases in PHP; do not claim stubs establish real WordPress behavior.
- REQ-003: Tests must continue to pass after adding a third event and bumping to a future version. Manual release dispatch must reject an existing tag that resolves to a different commit. Build accepts matching stable semantic versions only, rejects version mismatch, validates JSON pack data and referenced assets, retains credits and excludes development files. CI runs on pushes/PRs; tag workflow reruns checks then creates a release containing seasonal-modes.zip. Verify ZIP paths and execute build/check commands locally.
- REQ-004: README gives one-time manual installation of the first updater-enabled version, release-only update semantics, exact publish commands, update timing and pack extension/customization guidance.
- REQ-006 (local): Run `php tests/run.php`, `node --check assets/js/core.js`, `python3 scripts/check.py`, `python3 scripts/build.py --tag v1.1.0`, and PHP syntax checks. Guardian independently checks implementation and records limitations.

## M2: GitHub publication and verification

Dependencies: M1 audit PASS.

- REQ-005: Create public amirrad98/seasonal-modes, upload audited source, run GitHub Actions, publish v1.1.0 and verify its ZIP asset. Verify using GitHub API/UI and workflow logs. If access prevents a step, retain prepared files and exact blocker without declaring completion.
- REQ-006 (integration): Run the disposable WordPress integration jobs in GitHub Actions on WordPress 6.3/PHP 7.4 and current WordPress/PHP 8.4. They must prove that core offers a newer release, Plugin_Upgrader installs the built ZIP, settings and custom packs survive, activation persists, and an equal version is no longer offered. Network release responses are fixtures; real GitHub publication/download is verified separately. If CI access is unavailable, record this verification as BLOCKED. No user site changes are authorized.

Final gate: guardian audit covering both milestones before any completion claim or completion commit. Failed findings require correction and re-audit. Blocked external checks remain blocked.
