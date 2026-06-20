#!/usr/bin/env bash
# playground.sh — local WordPress Playground lifecycle for Contact Sheet theme dev.
#
# A self-contained dev harness: spins up a real WordPress site on WordPress
# Playground (PHP-WASM + SQLite, no Docker, no Apache, no MySQL), mounts a clean
# copy of THIS theme into it, installs the Theme Check plugin, and seeds demo
# photo-blog content so the theme can be developed and reviewed offline.
#
# Adapted from the proven lifecycle in Automattic/wp-skill (scripts/playground.sh):
# four convergent verbs plus two project-specific ones (sync, seed). State lives
# under workdir/.playground/ (gitignored wholesale).
#
# Verbs:
#   playground.sh bootstrap                 one-time: sync theme, create WP 7.0 site
#   playground.sh ensure                    idempotent: converge to a running server
#   playground.sh sync                      rsync theme repo -> workdir/theme (run after edits)
#   playground.sh wp -- <wp-cli args>       run wp-cli against the same site + mounts
#   playground.sh seed [--force]            install Theme Check + create demo photo posts
#   playground.sh stop                      stop server (asserts clean teardown)
#   playground.sh url                       print the live site URL
#
# Mounts (recorded in workdir/.playground/mounts, replayed by `wp`):
#   workdir/theme                       -> /wordpress/wp-content/themes/contact-sheet
#   <repo>/playground                   -> /playground   (seed.php + assets/photos)
#
# Pinned versions (bump only after re-verifying the full bootstrap->seed->frontend chain):
PLAYGROUND_VERSION="${PLAYGROUND_VERSION:-3.1.38}"   # @wp-playground/cli
WP_VERSION="${WP_VERSION:-7.0}"                       # WordPress release
PHP_VERSION="${PHP_VERSION:-8.3}"
WPCLI_PHAR_URL="https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar"
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLI=(npx -y "@wp-playground/cli@${PLAYGROUND_VERSION}")
WORKDIR="$ROOT/workdir"
PG="$WORKDIR/.playground"
PHAR="$PG/wp-cli.phar"
THEME_SYNC="$WORKDIR/theme"
THEME_SLUG="contact-sheet"
THEME_MOUNT="$THEME_SYNC:/wordpress/wp-content/themes/$THEME_SLUG"
PG_MOUNT="$ROOT/playground:/playground"

die() { echo "playground.sh: $*" >&2; exit 1; }

command -v setsid >/dev/null 2>&1 || \
  die "setsid not found (Linux/WSL have it; on macOS: brew install util-linux)"

free_port() {
  local p
  for p in $(seq 9400 9499); do
    if ! (exec 3<>"/dev/tcp/127.0.0.1/$p") 2>/dev/null; then echo "$p"; return 0; fi
    exec 3>&- 3<&- || true
  done
  die "no free port in 9400-9499"
}

site_dir() {
  test -s "$PG/site-dir" || die "run 'bootstrap' first (no $PG/site-dir)"
  local d; d=$(cat "$PG/site-dir")
  [ -d "$d" ] || die "recorded site dir is gone ($d) — delete $PG/site-dir and re-run bootstrap"
  echo "$d"
}

mount_args() {
  MOUNTS=("--mount=$THEME_MOUNT" "--mount=$PG_MOUNT")
}

pgid_is_ours() {
  local g="$1"
  [ -n "$g" ] || return 1
  ps -eo pgid=,args= | awk -v g="$g" '$1 == g' | grep -q 'wp-playground\|@wp-playground/cli'
}

server_alive() {
  [ -f "$PG/server.port" ] && [ -f "$PG/server.pid" ] && [ -f "$PG/server.pgid" ] || return 1
  kill -0 "$(cat "$PG/server.pid")" 2>/dev/null || return 1
  pgid_is_ours "$(cat "$PG/server.pgid")" || return 1
  curl -fs -o /dev/null --max-time 2 "http://127.0.0.1:$(cat "$PG/server.port")/" 2>/dev/null
}

kill_recorded() {
  local pgid="" pid=""
  [ -f "$PG/server.pgid" ] && pgid=$(cat "$PG/server.pgid")
  [ -f "$PG/server.pid" ]  && pid=$(cat "$PG/server.pid")
  if [ -n "$pgid" ] && pgid_is_ours "$pgid"; then
    kill -TERM -- "-$pgid" 2>/dev/null || true
  elif [ -n "$pid" ]; then
    kill "$pid" 2>/dev/null || true
  fi
}

# rsync the theme from the repo root into workdir/theme, excluding dev-only cruft
# (same exclusions as scripts/package.sh, plus playground/workdir/AGENTS/CLAUDE).
cmd_sync() {
  mkdir -p "$WORKDIR"
  [ -f "$WORKDIR/.gitignore" ] || printf '*\n' > "$WORKDIR/.gitignore"
  mkdir -p "$THEME_SYNC"
  rsync -a --delete \
    --exclude '.git' \
    --exclude '.github' \
    --exclude '.claude' \
    --exclude '.codex' \
    --exclude 'node_modules' \
    --exclude 'dist' \
    --exclude 'workdir' \
    --exclude 'playground' \
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
    --exclude 'AGENTS*.md' \
    --exclude 'CLAUDE.md' \
    --exclude 'screenshots' \
    "$ROOT/" "$THEME_SYNC/"
  echo "synced theme -> $THEME_SYNC"
}

cmd_bootstrap() {
  mkdir -p "$PG"
  [ -f "$WORKDIR/.gitignore" ] || printf '*\n' > "$WORKDIR/.gitignore"
  if [ -s "$PG/site-dir" ] && [ -d "$(cat "$PG/site-dir")" ]; then
    echo "already bootstrapped: $(cat "$PG/site-dir")"; return 0
  fi
  cmd_sync
  rm -f "$PG/site-dir"
  # Bootstrap against a NEUTRAL empty dir, not the theme: `start` auto-detects the
  # project type from --path, and pointing it at a theme dir would auto-mount it
  # under the folder name ("theme") and activate that — leaving a broken theme
  # record. A plain WP 7.0 site with a default theme is created here; the theme is
  # mounted and activated by `ensure`/`seed`.
  local seeddir="$WORKDIR/site-seed"
  mkdir -p "$seeddir"
  local port; port=$(free_port)
  setsid nohup "${CLI[@]}" start \
    --path="$seeddir" --wp="$WP_VERSION" --php="$PHP_VERSION" \
    --skip-browser --port="$port" \
    > "$PG/bootstrap.log" 2>&1 &
  local pid=$!
  local pgid; pgid=$(ps -o pgid= -p "$pid" | tr -d ' ')
  local i
  for i in $(seq 1 60); do
    kill -0 "$pid" 2>/dev/null || break
    grep -q "Site files stored at:" "$PG/bootstrap.log" 2>/dev/null && \
      curl -fs -o /dev/null --max-time 2 "http://127.0.0.1:$port/" 2>/dev/null && break
    sleep 2
  done
  sed -n 's/.*Site files stored at: //p' "$PG/bootstrap.log" | head -1 > "$PG/site-dir"
  if ! [ -s "$PG/site-dir" ] || ! [ -d "$(cat "$PG/site-dir")" ]; then
    [ -n "$pgid" ] && kill -TERM -- "-$pgid" 2>/dev/null || true
    rm -f "$PG/site-dir"
    echo "--- last lines of $PG/bootstrap.log:" >&2; tail -5 "$PG/bootstrap.log" >&2 || true
    die "bootstrap failed; full log: $PG/bootstrap.log"
  fi
  kill -TERM -- "-$pgid" 2>/dev/null || kill "$pid" 2>/dev/null || true
  sleep 1
  echo "site-dir: $(cat "$PG/site-dir")"
}

cmd_ensure() {
  mkdir -p "$PG"
  local sdir; sdir=$(site_dir)
  local want="$THEME_MOUNT"$'\n'"$PG_MOUNT" have=""
  [ -f "$PG/mounts" ] && have=$(cat "$PG/mounts")
  if server_alive && [ "$want" = "$have" ]; then
    echo "reusing server on port $(cat "$PG/server.port")"
    print_curl_hint; return 0
  fi
  if server_alive; then echo "mounts changed — restarting server with the new mount set"; fi
  kill_recorded
  rm -f "$PG"/server.{pid,pgid,port}
  printf '%s\n%s\n' "$THEME_MOUNT" "$PG_MOUNT" > "$PG/mounts"
  local MOUNTS; mount_args
  local port; port=$(free_port)
  setsid nohup "${CLI[@]}" server --port="$port" --login \
    --wp="$WP_VERSION" --php="$PHP_VERSION" \
    --mount-before-install="$sdir:/wordpress" \
    --wordpress-install-mode=install-from-existing-files-if-needed \
    "${MOUNTS[@]}" \
    > "$PG/server.log" 2>&1 &
  local pid=$!
  echo "$pid" > "$PG/server.pid"
  ps -o pgid= -p "$pid" | tr -d ' ' > "$PG/server.pgid"
  echo "$port" > "$PG/server.port"
  local i
  for i in $(seq 1 60); do
    if ! kill -0 "$pid" 2>/dev/null; then
      kill_recorded
      rm -f "$PG"/server.{pid,pgid,port}
      echo "--- last lines of $PG/server.log:" >&2; tail -5 "$PG/server.log" >&2 || true
      die "server process died during startup; full log: $PG/server.log"
    fi
    if curl -fs -o /dev/null --max-time 2 "http://127.0.0.1:$port/"; then
      echo "server ready: http://127.0.0.1:$port (log: $PG/server.log)"
      print_curl_hint; return 0
    fi
    sleep 2
  done
  kill_recorded
  rm -f "$PG"/server.{pid,pgid,port}
  die "server did not become ready in 120s; see $PG/server.log"
}

print_curl_hint() {
  local p; p=$(cat "$PG/server.port")
  echo "frontend check: curl -sL -c $PG/cookies -b $PG/cookies http://127.0.0.1:$p/"
}

cmd_wp() {
  local sdir; sdir=$(site_dir)
  [ "${1:-}" = "--" ] && shift
  [ "$#" -gt 0 ] || die "usage: playground.sh wp -- <wp-cli args>"
  if [ ! -s "$PHAR" ]; then
    curl -fsL -o "$PHAR" "$WPCLI_PHAR_URL" || die "wp-cli phar download failed"
  fi
  local MOUNTS; mount_args
  "${CLI[@]}" php \
    --mount-before-install="$sdir:/wordpress" \
    --wordpress-install-mode=install-from-existing-files-if-needed \
    --mount="$PG:/host" \
    "${MOUNTS[@]}" \
    -- /host/wp-cli.phar "$@"
}

cmd_seed() {
  cmd_ensure >/dev/null
  local force="0"
  [ "${1:-}" = "--force" ] && force="1"
  echo "▶ installing Theme Check plugin…"
  cmd_wp -- plugin install theme-check --activate || \
    echo "  (theme-check already installed or install skipped)"
  echo "▶ activating theme…"
  cmd_wp -- theme activate "$THEME_SLUG"
  echo "▶ seeding demo photo posts…"
  if [ "$force" = "1" ]; then touch "$PG/seed-force"; fi
  cmd_wp -- eval-file /playground/seed.php
  rm -f "$PG/seed-force"
  echo "▶ flushing rewrite rules…"
  cmd_wp -- rewrite flush || true
  cmd_wp -- cache flush || true
  echo "✓ seed complete"
  echo "Test it: $(cmd_url)"
}

cmd_stop() {
  [ -f "$PG/server.pgid" ] || { echo "no recorded server"; return 0; }
  local port=""; [ -f "$PG/server.port" ] && port=$(cat "$PG/server.port")
  local pgid; pgid=$(cat "$PG/server.pgid")
  kill_recorded
  local i
  for i in $(seq 1 10); do pgid_is_ours "$pgid" || break; sleep 1; done
  if [ -n "$port" ] && curl -fs -o /dev/null --max-time 2 "http://127.0.0.1:$port/" 2>/dev/null; then
    die "ASSERT FAILED: server still answering on port $port"
  fi
  if [ -n "$port" ] && pgrep -f "wp-playgroun[d].*--port=$port" >/dev/null 2>&1; then
    die "ASSERT FAILED: playground process still owns port $port"
  fi
  if pgid_is_ours "$pgid"; then die "ASSERT FAILED: recorded process group $pgid still alive"; fi
  rm -f "$PG"/server.{pid,pgid,port}
  echo "stopped clean (curl fails, no surviving process)"
}

cmd_url() {
  [ -f "$PG/server.port" ] || die "no server running — try 'playground.sh ensure'"
  echo "http://127.0.0.1:$(cat "$PG/server.port")/"
}

case "${1:-}" in
  bootstrap) shift; cmd_bootstrap "$@";;
  ensure)    shift; cmd_ensure "$@";;
  sync)      shift; cmd_sync "$@";;
  wp)        shift; cmd_wp "$@";;
  seed)      shift; cmd_seed "$@";;
  stop)      shift; cmd_stop "$@";;
  url)       cmd_url;;
  *) sed -n '3,17p' "$0"; exit 1;;
esac
