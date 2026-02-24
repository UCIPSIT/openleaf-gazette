# OpenLeaf Gazette

Open-source Joomla 5 content plugin for the UCIPS digital gazette.

## Features

- `embed` mode: uses external flipbook URLs (closest match to FlipHTML5 style).
- `native` mode: self-hosted PDF flipbook rendering with `pdf.js + StPageFlip`.
- Shortcodes supported:
  - `{gacetaflip ...}`
  - `{gacetaflipbook ...}`
  - `{openleaf ...}`

## Installation

1. Joomla Administrator -> `System -> Install -> Extensions`.
2. Upload the plugin zip.
3. Enable `Content - OpenLeaf Gazette`.

## Usage

Embed mode:

```text
{openleaf mode="embed" url="https://online.fliphtml5.com/HmoralesZ/HCJ-gaceta-piloto-01/#p=26"}
```

Native mode:

```text
{openleaf mode="native" file="images/pdfs/ucips-gazette-base3f-27012026-prueba.pdf" start="26" width="560" height="760" maxpages="0"}
```

## License

MIT
