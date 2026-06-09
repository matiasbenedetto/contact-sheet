#!/usr/bin/env bash
#
# Deploy the Contact Sheet theme to a live WordPress site.
#
# Usage:
#   DEPLOY_METHOD=rsync  npm run deploy
#   DEPLOY_METHOD=wpcli  npm run deploy
#
# rsync method (sync theme folder over SSH):
#   SSH_HOST=user@host
#   WP_THEMES_DIR=/var/www/html/wp-content/themes   (remote path)
#
# wpcli method (install zip via WP-CLI over SSH alias or local):
#   WP_SSH=user@host/path-to-wordpress   (a @alias or ssh string wp-cli understands)
#
set -euo pipefail

SLUG="contact-sheet"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Always build a fresh clean package first.
npm run package

METHOD="${DEPLOY_METHOD:-rsync}"

case "$METHOD" in
  rsync)
    : "${SSH_HOST:?Set SSH_HOST=user@host}"
    : "${WP_THEMES_DIR:?Set WP_THEMES_DIR=/path/to/wp-content/themes}"
    echo "▶ Unpacking package for sync…"
    TMP="$(mktemp -d)"
    unzip -q "$ROOT/$SLUG.zip" -d "$TMP"
    echo "▶ rsync → $SSH_HOST:$WP_THEMES_DIR/$SLUG"
    rsync -az --delete "$TMP/$SLUG/" "$SSH_HOST:$WP_THEMES_DIR/$SLUG/"
    rm -rf "$TMP"
    echo "✓ Deployed via rsync"
    ;;
  wpcli)
    : "${WP_SSH:?Set WP_SSH=user@host/path-to-wordpress}"
    echo "▶ wp theme install (force) on $WP_SSH"
    wp --ssh="$WP_SSH" theme install "$ROOT/$SLUG.zip" --force
    echo "✓ Deployed via WP-CLI"
    ;;
  *)
    echo "Unknown DEPLOY_METHOD='$METHOD' (use rsync or wpcli)" >&2
    exit 1
    ;;
esac
