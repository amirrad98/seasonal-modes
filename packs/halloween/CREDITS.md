# Halloween pack asset credits

Ported unchanged from the OTE child theme's Halloween mode. Only third-party
graphics that permit commercial use and redistribution are used. While the pack
is active the plugin prints this attribution line in the page footer
(`credit` in `pack.json`):

> Halloween graphics: Twemoji (CC BY 4.0, resized) and a cobweb by tom via Openclipart (CC0).

All SVGs were passed through an allowlist sanitizer (only `svg`, `g`, `path`,
`ellipse` and similar shapes with presentation attributes; no scripts, event
handlers, styles, links, images, `foreignObject` or external references).

| File | Source | Author | License | Changes |
| --- | --- | --- | --- | --- |
| `images/pumpkin.svg` | Twemoji 15.1.0, U+1F383 Jack-O-Lantern, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/svg/1f383.svg | Twitter, Inc and other contributors (jdecked/twemoji) | [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) | Sanitized/reserialized |
| `images/spider.svg` | Twemoji 15.1.0, U+1F577 Spider, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/svg/1f577.svg | Twitter, Inc and other contributors | CC BY 4.0 | Sanitized/reserialized |
| `images/cursor-pumpkin-32.png` | Twemoji 15.1.0 72×72 PNG, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/72x72/1f383.png | Twitter, Inc and other contributors | CC BY 4.0 | Resized to 32 px |
| `images/cursor-spider-32.png` | Twemoji 15.1.0 72×72 PNG, https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/72x72/1f577.png | Twitter, Inc and other contributors | CC BY 4.0 | Resized to 32 px, thin white outline added for contrast on dark backgrounds |
| `images/cobweb-corner.svg` | "Spiders web.svg", https://commons.wikimedia.org/wiki/File:Spiders_web.svg (originally Openclipart, http://openclipart.org/media/files/tom/11877) | tom (Openclipart) | [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/) | Sanitized, Inkscape metadata removed, coordinates rounded, reduced to one quadrant (viewBox cropped) so it sits in a corner |
| `images/cobweb-top-left.svg`, `images/cobweb-top-right.svg` | Drawn for this plugin by `tools/make_webs.py` in the build folder | Seasonal Modes | [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/) | Irregular spokes, sagging rings and torn threads; no credit needed |

Twemoji (https://github.com/jdecked/twemoji, continuing twitter/twemoji): graphics
licensed under CC BY 4.0. The project's README accepts a website footer mention as
attribution; the footer line provides it. No Twemoji code is used.
