#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$ROOT_DIR/dist}"
MANIFEST_FILE="$ROOT_DIR/gacetaflipbook.xml"

if [[ ! -f "$MANIFEST_FILE" ]]; then
  echo "Manifest not found: $MANIFEST_FILE" >&2
  exit 1
fi

VERSION="$(sed -n 's:.*<version>\([^<]*\)</version>.*:\1:p' "$MANIFEST_FILE" | head -n 1)"

if [[ -z "$VERSION" ]]; then
  echo "Unable to read plugin version from $MANIFEST_FILE" >&2
  exit 1
fi

PKG_NAME="plg_content_openleaf_gazette-${VERSION}.zip"
PKG_PATH="$OUT_DIR/$PKG_NAME"

STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGE_DIR"' EXIT

mkdir -p "$OUT_DIR" "$STAGE_DIR/assets"

cp "$ROOT_DIR/gacetaflipbook.php" "$STAGE_DIR/"
cp "$ROOT_DIR/gacetaflipbook.xml" "$STAGE_DIR/"
cp "$ROOT_DIR/index.html" "$STAGE_DIR/"
cp "$ROOT_DIR/LICENSE" "$STAGE_DIR/"
cp "$ROOT_DIR/assets/gacetaflipbook.css" "$STAGE_DIR/assets/"
cp "$ROOT_DIR/assets/gacetaflipbook.js" "$STAGE_DIR/assets/"
cp "$ROOT_DIR/assets/index.html" "$STAGE_DIR/assets/"

(
  cd "$STAGE_DIR"
  zip -r "$PKG_PATH" . >/dev/null
)

echo "$PKG_PATH"
