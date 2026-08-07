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
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude 'node_modules' \
  --exclude 'dist' \
  --exclude 'scripts' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude '.gitignore' \
  --exclude '.distignore' \
  --exclude '*.zip' \
  --exclude 'blocks/photo-strip/src' \
  --exclude '.DS_Store' \
  --exclude '*.log' \
  --exclude '*.mjs' \
  --exclude 'README.md' \
  --exclude 'AGENTS.md' \
  --exclude 'AGENTS.local.md' \
  --exclude 'CLAUDE.md' \
  --exclude 'screenshots' \
  --exclude 'playground' \
  --exclude 'workdir' \
  --exclude 'demo-site' \
  --exclude 'social' \
  ./ "$STAGE/"

echo "▶ Zipping…"
rm -f "$ZIP"
( cd "$DIST" && zip -rq "$ZIP" "$SLUG" )

rm -rf "$DIST"

# The exclude list above is a denylist, so anything new and untracked in the
# repo root ships by default. Fail loudly on the symptom — a zip far larger
# than the theme's real payload — rather than publishing the bloat.
MAX_KB=8192
SIZE_KB=$(( $(wc -c < "$ZIP") / 1024 ))
if [ "$SIZE_KB" -gt "$MAX_KB" ]; then
	echo "Refusing to package: ${ZIP} is ${SIZE_KB}KB (limit ${MAX_KB}KB)." >&2
	echo "Something dev-only is leaking in. Inspect with: unzip -l ${ZIP}" >&2
	exit 1
fi

echo "✓ Created $ZIP (${SIZE_KB}KB)"
