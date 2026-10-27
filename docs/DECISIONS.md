# Decisions

- Keep the existing plugin: seasonal behavior should persist across theme changes.
- Public repository approved by user. Target amirrad98/seasonal-modes, matching authenticated account and local folder.
- Use stable tagged releases, not every commit, as the WordPress update boundary.
- Use WordPress native Update URI hooks and public GitHub release metadata; no site credentials or separate update service required.
- Keep per-site settings in WordPress options and custom packs outside the replaceable plugin folder.
- First updater-enabled release is 1.1.0. Existing 1.0.0 has no updater and requires one manual replacement.
