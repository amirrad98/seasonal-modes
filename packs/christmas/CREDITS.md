# Christmas pack asset credits

Same method as the Halloween pack: Twemoji for the small icons (credited in the
footer while the pack is active, `credit` in `pack.json`), everything else drawn
for this plugin. Only graphics that permit commercial use and redistribution.

> Christmas graphics: Twemoji (CC BY 4.0, resized).

Twemoji SVGs were checked against a strict allowlist (`svg`, `path` and
`circle` with fill/geometry attributes only) by `tools/make_christmas.py` before
copying; anything else is refused.

| File | Source | Author | License | Changes |
| --- | --- | --- | --- | --- |
| `images/tree.svg` | Twemoji 15.1.0, U+1F384 Christmas Tree, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/svg/1f384.svg | Twitter, Inc and other contributors (jdecked/twemoji) | [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) | Allowlist-checked, unchanged |
| `images/snowflake.svg` | Twemoji 15.1.0, U+2744 Snowflake, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/svg/2744.svg | Twitter, Inc and other contributors | CC BY 4.0 | Allowlist-checked, unchanged |
| `images/cursor-snowflake-32.png` | Twemoji 15.1.0 72×72 PNG, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/72x72/2744.png | Twitter, Inc and other contributors | CC BY 4.0 | Resized to 28 px on a 32 px canvas, thin white outline added for contrast (`tools/make_cursors.js`) |
| `images/cursor-gift-32.png` | Twemoji 15.1.0 72×72 PNG, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/72x72/1f381.png | Twitter, Inc and other contributors | CC BY 4.0 | Resized to 32 px |
| `images/garland-top-left.svg`, `images/garland-top-right.svg` | Drawn for this plugin by `tools/make_christmas.py` in the build folder | Seasonal Modes | [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/) | Pine swags of different lengths and sag, baubles on uneven strings, fairy lights, a bow; no credit needed |
| `images/snowflakes-corner.svg` | Drawn for this plugin by `tools/make_christmas.py` | Seasonal Modes | CC0 1.0 | Loose cluster of line snowflakes; no credit needed |
