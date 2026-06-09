#!/usr/bin/env bash
#
# Build the Contact Sheet theme and produce a clean, production-only zip.
# Usage: npm run package
#
set -euo pipefail

SLUG="contact-sheet"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"
ZIP="$ROOT/$SLUG.zip"

cd "$ROOT"

echo "▶ Building block assets…"
npm run build

echo "▶ Staging production files…"
rm -rf "$DIST"
mkdir -p "$STAGE"

# rsync only what WordPress needs at runtime. Everything dev-only is excluded.
rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.claude' \
  --exclude '.codex' \
  --exclude 'node_modules' \
  --exclude 'dist' \
  --exclude 'scripts' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude '.gitignore' \
  --exclude '*.zip' \
  --exclude 'blocks/photo-strip/src' \
  --exclude '.DS_Store' \
  --exclude '*.log' \
  ./ "$STAGE/"

echo "▶ Zipping…"
rm -f "$ZIP"
( cd "$DIST" && zip -rq "$ZIP" "$SLUG" )

rm -rf "$DIST"
echo "✓ Created $ZIP"
