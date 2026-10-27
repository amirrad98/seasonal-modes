# Seasonal Modes

Theme-independent seasonal decorations for WordPress, driven by data packs.
Ships with **Halloween** (ported from the OTE child theme's Halloween mode)
and **Christmas** (garlands, candy-cane stripe, snowflake/gift cursors, a
snowfall intro; 1–31 December).

While a pack is active the site gets a `body.sm-active sm-pack-<slug>` class
and, depending on the pack: accent colours, a stripe under the header, a badge
beside the logo, art hanging from the top corners (cobwebs, garlands), faint corner art behind the content, custom cursors (mouse and
trackpad only), a one-time intro animation and the pack's attribution line in
the footer. When no pack is active nothing is output.

Accessibility: cursors only for `(hover: hover) and (pointer: fine)`, the I-beam
kept on text fields, the intro skipped for `prefers-reduced-motion`, decorations
`aria-hidden` and behind content, hidden in print.

## Install

1. In wp-admin go to **Plugins → Add New Plugin → Upload Plugin**, choose
   `seasonal-modes.zip` from the [latest GitHub release](https://github.com/amirrad98/seasonal-modes/releases/latest), then **Install Now** and **Activate**.
   (Or unzip into `wp-content/plugins/` and run `wp plugin activate seasonal-modes`.)
2. Open **Appearance → Seasonal Modes**, set the site selectors if the theme
   isn't a plain block theme (see *Site adapter*), and preview as admin.
3. Pick the mode: Off, Auto by date, or Force on.

Requires WordPress 6.3+ and PHP 7.4+. Nothing is output while the mode is Off.

## Why a plugin?

Seasonal Modes adds an optional layer to your existing theme. Keeping it as a
plugin lets you change themes and reuse the same seasonal packs on multiple
sites. The site adapter handles each theme's selectors.

## Updates from GitHub

Version 1.1.0 adds native WordPress update notifications. Install this version
manually once if your site currently has 1.0.0: upload `seasonal-modes.zip` and
choose **Replace current with uploaded**. The old version has no updater, so it
cannot discover this first upgrade itself. Keep the folder named `seasonal-modes`.

After that, WordPress checks stable GitHub releases and displays **Update now**
under Plugins when a newer version is available. Use the plugin's **Check for
updates** link to refresh immediately. Normal checks depend on WordPress cron
and traffic; GitHub results are cached for six hours, failed requests for five
minutes. A normal code push alone does not publish an update.

Automatic installation is optional: enable **Auto-updates** in WordPress if you
want it. The plugin never enables this on your behalf. Keep the plugin active
(the seasonal mode may be Off) so its updater can run. Outbound HTTPS access to
GitHub and its release-download hosts is required. No token is needed for this
public repository. GitHub's unauthenticated API rate limits apply, especially
on shared hosting. A failed check leaves the installed plugin unchanged.

Your mode and adapter settings live in the WordPress database and survive
updates. Keep site-only packs in `wp-content/seasonal-modes-packs/`; edits made
inside `wp-content/plugins/seasonal-modes/` are replaced during updates.

### Publish a new version

1. Add or edit packs and code. For a new event, copy an existing pack folder,
   choose a unique slug, set the `MM-DD` window, and update images/credits.
2. Change **both** the plugin header `Version` and `Seasonal_Modes::VERSION`
   in `seasonal-modes.php`, for example to `1.2.0`. Add a `## 1.2.0` entry to
   `CHANGELOG.md`. Update the pack version if its content changed.
3. Run the checks below, commit, and push to `main`.
4. In GitHub open **Actions → Release → Run workflow**, select `main`, and
   enter `v1.2.0`. Alternatively push a matching tag:

   ```sh
   git tag v1.2.0
   git push origin v1.2.0
   ```

The workflow runs tests on PHP 7.4 and 8.4, including a disposable WordPress
6.3/current-version upgrade test, builds `seasonal-modes.zip` and `update.json`,
uploads both to a draft release, then publishes it as the latest release.
The tag must match the plugin version. Drafts, prereleases, missing assets, and
invalid metadata are not offered to WordPress. Use a new version to correct a
published release. If a run stops with an unpublished draft, inspect its logs
and remove that draft before retrying the same version.

Use the attached **seasonal-modes.zip**, not GitHub's automatic source archives.
No hosting passwords or WordPress admin credentials go into GitHub Actions;
it publishes packages, and WordPress downloads them.

### Local checks

```sh
php tests/run.php
node --check assets/js/core.js
python3 scripts/check.py
python3 -m unittest discover -s tests -p 'test_*.py'
python3 scripts/build.py --tag v1.1.0
```

Use the current version's tag in the build command. The output is in `dist/`.
PHP tests use small WordPress substitutes for fast failure-case coverage.
GitHub Actions additionally runs actual WordPress core and its plugin upgrader
with fixture HTTP responses and the real built ZIP. This is separate from a
live-site/browser check. `tests/wordpress-update.php` must only be run on a
disposable test installation; it intentionally replaces plugin files.

`tests/baseline-assets.json` protects the initial assets during this migration.
When intentionally editing existing artwork or shared frontend files later,
update their SHA-256 entries after reviewing the changes. Adding new pack
folders does not require changing existing entries.

## Layout

```
seasonal-modes.php        settings, pack loader, adapter, output
assets/css/core.css       shared rules (all var-driven, scoped to body.sm-active)
assets/js/core.js         header/bottom-nav insets, intro animations
packs/<slug>/pack.json    one season = data
packs/<slug>/images/      pack art
packs/<slug>/CREDITS.md   licences for that art
packs/<slug>/style.css    optional pack-only CSS
packs/<slug>/script.js    optional pack-only JS
```

Site-specific packs can live in `wp-content/seasonal-modes-packs/<slug>/` so
plugin updates don't touch them (same slug overrides the bundled pack).

## pack.json

| Key | Meaning |
| --- | --- |
| `name`, `version` | Shown in settings |
| `window.start`, `window.end` | `MM-DD`, inclusive, site timezone; may wrap New Year (`12-01` → `01-06`) |
| `colors.accent`, `accentSoft`, `ink`, `selectionBackground`, `selectionText` | Hex colours; `colors.dark.*` overrides for dark mode |
| `headerStripe` | `true` for the diagonal accent/ink stripe under the header, or `{ colors: [a, b] }` for your own pair (Christmas: red/white candy cane) |
| `logoBadge` | `{ image, size, rotate }` drawn at the logo's top-right |
| `corners` | `{ image, width, height, opacity, darkOpacity, invertInDark }`; image is the top-left corner, mirrored for the others |
| `topWebs` | Top-corner art (cobwebs, garlands …): `{ opacity, darkOpacity, invertInDark, left, right }`; each side is `{ image, size, mobileSize, rotate, offsetX, offsetY }`. Hung from the window's top corners in front of the content (under the header); give the sides different images and sizes so they look natural. When set, `corners` only fills the bottom corners |
| `cursors.default`, `cursors.pointer` | `{ image, x, y }` 32 px PNG + hotspot |
| `intro` | `{ type, image }`, once per visitor per season: `crawl` (sprites crawl up and leave) or `snow` (flakes drift down and fade); omit for none |
| `credit` | `{ text, links: [{label, url}] }`, text uses `%1$s`, `%2$s` … for the links |

## Site adapter

Per-site selectors, set under **Appearance → Seasonal Modes** or as the
`seasonal_modes_adapter` option (or the `seasonal_modes_adapter` filter):

| Key | Default |
| --- | --- |
| `header` | `header.wp-block-template-part` |
| `logo` | `header .wp-block-site-logo a, header .custom-logo-link` |
| `bottom_nav` | none |
| `dark` | none; `media` for `prefers-color-scheme`, or an ancestor selector such as `html[data-theme="dark"]` |
| `style_deps` | none; stylesheet handles the seasonal CSS should load after |
| `pointer_extra` | none; extra clickable selectors for the pointer cursor |
| `header_stripe`, `logo_badge` | `1`; set `0` where the theme's header doesn't suit them |

Known adapters:

```sh
# Over the Edge (twentytwentyfive-ote-child)
wp option update seasonal_modes_adapter --format=json '{"header":"header.wp-block-template-part","logo":"header .ote-logo-link","bottom_nav":"#mobile-nav","dark":"html[data-theme=\"dark\"]","style_deps":"ote-design-system,ote-footer","pointer_extra":".btn,.icon-btn,.chip,.ote-chip,.ote-tab,.theme-toggle,.ote-theme-toggle,.mobile-menu-theme-toggle,.search-toggle,.mobile-search-toggle,.search-submit,.search-clear,.search-sheet__close,.is-style-ote-card-animated,.card--animated,.ote-news-grid .wp-block-post-template > li,.unbc-modal-close,.unbc-modal-button,.ote-app-bar-nav [class*=\"dark-mode\"]","header_stripe":"1","logo_badge":"1"}'

# SparkLab (transparent header that turns into a pill on scroll, so no stripe)
wp option update seasonal_modes_adapter --format=json '{"header":"#sparklab-header","logo":".sparklab-header .logo-link","bottom_nav":"","dark":"html[data-theme=\"dark\"]","style_deps":"sparklab-theme","pointer_extra":".theme-toggle,.sparklab-hamburger,.nav-cta","header_stripe":"0","logo_badge":"1"}'
```

## Pipeline for a new site

1. Install and activate the plugin (reviewed plugin install/activate plan).
2. Look at the theme's header, logo, bottom bar, dark-mode switch and main
   stylesheet handle; write the adapter (skip for a plain block theme).
3. Preview as admin: `/?seasonal_preview=halloween`, `/?seasonal_preview=christmas`.
4. Check 390, 768 and 1440 px, light and dark, console errors, and that
   `/?seasonal_preview=off` matches the normal site.
5. Turn it on: `wp option update seasonal_modes --format=json '{"mode":"auto","pack":""}'`
   (`auto` with no pack runs whichever pack is in its window, so Halloween
   hands over to Christmas by itself; name a pack to run only that one; `on`
   forces the named pack).
6. Page caches: purge after changing the mode or when a window opens/closes.

## Adding a season

For example, add `packs/nowruz/pack.json` and its images, then publish a new
plugin version. Every site receives the new pack through its normal plugin
update. You do not need a separate plugin or theme for each event.

Date windows repeat annually using the WordPress site timezone. Overlapping
windows select the first matching loaded pack; choose a specific pack or use
non-overlapping windows when you need a predictable winner. Moving holidays
need their dates maintained or a custom `seasonal_modes_active_pack` filter.


Copy `packs/halloween` or `packs/christmas` to `packs/<slug>`, swap the images (record each source
and licence in `CREDITS.md`), edit `pack.json`. New intro styles go in
`core.js` (`INTROS`) so every pack can use them.

## Migrating OTE from the theme's Halloween mode

Turn the theme version off (`wp option update ote_halloween_mode 0`), set the
OTE adapter above, then enable this plugin. Running both at once doubles the
decorations.
