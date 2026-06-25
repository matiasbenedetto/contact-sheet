#!/usr/bin/env bash
#
# Release a new version of the Contact Sheet theme.
#
# Interactively (arrow-key menus) or via CLI flags:
#   1. pick a release type        -> patch | minor | major
#   2. pick changelog entries     -> toggle which commits since the last
#      release become changelog bullets (auto-filtered, editable)
#   3. pick publish               -> yes | no  (commit, tag, push, GitHub release)
#
# Then bumps the version in style.css / readme.txt / package.json, prepends
# the changelog section, builds the block assets, produces a production-only
# ${SLUG}.zip, and (if you publish) commits, tags, pushes and attaches the
# zip to a GitHub release for the new version.
#
# Usage:
#   bash scripts/release.sh                      # fully interactive
#   bash scripts/release.sh patch                # type pre-selected
#   bash scripts/release.sh patch --commit       # type + publish pre-selected
#   DRY_RUN=1 bash scripts/release.sh patch      # preview, no writes/build
#
# Flags can be combined; any flag you pass skips its interactive prompt so
# the same command works in CI / non-TTY contexts.
#
set -euo pipefail

SLUG="contact-sheet"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DRY_RUN="${DRY_RUN:-0}"

# --- cleanup: always restore the terminal cursor + scratch files -----------

NOTES_FILE=""
cleanup() {
	printf '\e[?25h' >&2
	[ -n "$NOTES_FILE" ] && rm -f "$NOTES_FILE"
}
trap cleanup EXIT

# --- helpers ---------------------------------------------------------------

usage() {
	cat <<EOF
Usage: bash scripts/release.sh [patch|minor|major] [--commit] [-h]

Bumps the theme version, updates the changelog, builds the assets and
produces ${SLUG}.zip. Runs interactively (arrow-key menus) when options are
not given on the command line.

  patch    1.2.3 -> 1.2.4
  minor    1.2.3 -> 1.3.0
  major    1.2.3 -> 2.0.0

  --commit   Commit the bump, tag it, push, and publish a GitHub release
             with ${SLUG}.zip attached (git tag = version).
  DRY_RUN=1  Preview the version bump and changelog without writing/building.
EOF
}

bump_version() {
	# bump_version <current> <type> -> new version
	local current="$1" type="$2"
	# shellcheck disable=SC2206
	local parts=( ${current//./ } )
	local major="${parts[0]:-0}" minor="${parts[1]:-0}" patch="${parts[2]:-0}"
	case "$type" in
		patch) patch=$(( patch + 1 )) ;;
		minor) minor=$(( minor + 1 )); patch=0 ;;
		major) major=$(( major + 1 )); minor=0; patch=0 ;;
		*) echo "Unknown release type: $type" >&2; exit 1 ;;
	esac
	echo "${major}.${minor}.${patch}"
}

# Width of the terminal for truncating long commit subjects.
term_width() { tput cols 2>/dev/null || echo 80; }

truncate_line() {
	# truncate_line <text> <max-width>
	local s="$1" max="$2"
	if [ "${#s}" -le "$max" ]; then
		printf '%s' "$s"
	else
		printf '%s…' "${s:0:$(( max - 1 ))}"
	fi
}

# Read one keypress and name it: up/down/enter/space/escape/quit/<char>.
read_key() {
	local key
	IFS= read -rsn1 key 2>/dev/null || echo "eof"
	case "$key" in
		$'\x1b')
			local seq1 seq2
			IFS= read -rsn1 -t 0.1 seq1 2>/dev/null || true
			IFS= read -rsn1 -t 0.1 seq2 2>/dev/null || true
			case "$seq1$seq2" in
				'[A') echo up ;;
				'[B') echo down ;;
				*)   echo escape ;;
			esac
			;;
		'')  echo enter ;;
		' ') echo space ;;
		q|Q) echo quit ;;
		*)   echo "$key" ;;
	esac
}

# Single-select arrow menu.
#   select_menu <prompt> <option1> <option2> ...
# Echoes the selected option string to stdout. Returns 130 on cancel.
select_menu() {
	local prompt="$1"; shift
	local opts=( "$@" )
	local n=${#opts[@]}
	local sel=0
	local width; width=$(term_width)
	printf '\e[?25l' >&2        # hide cursor
	printf '%s\n' "$prompt" >&2
	while true; do
		local i
		for (( i=0; i<n; i++ )); do
			if [ "$i" -eq "$sel" ]; then
				printf '\e[36m❯ %s\e[0m\n' "$(truncate_line "${opts[$i]}" $(( width - 2 )))" >&2
			else
				printf '  %s\n' "$(truncate_line "${opts[$i]}" $(( width - 2 )))" >&2
			fi
		done
		local key; key="$(read_key)"
		case "$key" in
			up)      sel=$(( (sel - 1 + n) % n )) ;;
			down)    sel=$(( (sel + 1) % n )) ;;
			enter)   printf '\e[?25h' >&2; echo "${opts[$sel]}"; return 0 ;;
			quit|escape) printf '\e[?25h' >&2; return 130 ;;
		esac
		printf '\e[%dA' "$n" >&2   # move cursor back up to redraw
	done
}

# Multi-select checkbox menu.
#   select_many <prompt> <opt1> <opt2> ...
# Pre-checked states are read from the global MS_INIT array ("1"/"0" per item).
# Echoes each checked option string to stdout. Returns 130 on cancel.
MS_INIT=()
select_many() {
	local prompt="$1"; shift
	local opts=( "$@" )
	local n=${#opts[@]}
	local sel=0
	local checked=()
	local i
	for (( i=0; i<n; i++ )); do checked[$i]="${MS_INIT[$i]:-1}"; done
	local width; width=$(term_width)
	printf '\e[?25l' >&2
	printf '%s\n' "$prompt" >&2
	while true; do
		for (( i=0; i<n; i++ )); do
			local mark="☐"
			[ "${checked[$i]}" = "1" ] && mark="☑"
			if [ "$i" -eq "$sel" ]; then
				printf '\e[36m❯ %s %s\e[0m\n' "$mark" "$(truncate_line "${opts[$i]}" $(( width - 4 )))" >&2
			else
				printf '  %s %s\n' "$mark" "$(truncate_line "${opts[$i]}" $(( width - 4 )))" >&2
			fi
		done
		local key; key="$(read_key)"
		case "$key" in
			up)   sel=$(( (sel - 1 + n) % n )) ;;
			down) sel=$(( (sel + 1) % n )) ;;
			space) checked[$sel]=$(( 1 - checked[$sel] )) ;;
			a|A) for (( i=0; i<n; i++ )); do checked[$i]=1; done ;;
			n|N) for (( i=0; i<n; i++ )); do checked[$i]=0; done ;;
			enter)
				printf '\e[?25h' >&2
				for (( i=0; i<n; i++ )); do
					[ "${checked[$i]}" = "1" ] && echo "${opts[$i]}"
				done
				return 0
				;;
			quit|escape) printf '\e[?25h' >&2; return 130 ;;
		esac
		printf '\e[%dA' "$n" >&2
	done
}

# Interactive yes/no menu. Echoes "yes" or "no".
confirm() {
	local prompt="$1"
	local choice
	choice="$(select_menu "$prompt" "no" "yes")" || choice="no"
	echo "$choice"
}

# --- changelog detection ---------------------------------------------------

# Subjects that are not user-facing theme behaviour and should not appear in
# the changelog. Matched case-insensitively against the start of the subject.
NON_USER_FACING='^(Add|Update|Refresh|Tweak) .*(screenshot|readme|README|image credits)|Playground|Theme Check|AGENTS|CLAUDE|\.gitignore|\.distignore|changelog|Changelog|Bump theme version|Merge|local dev|deploy|CI|workflow|\.github'

is_user_facing() {
	# is_user_facing <subject> -> echoes 1 or 0
	local subject="$1"
	if printf '%s' "$subject" | grep -qiE "$NON_USER_FACING"; then
		echo 0
	else
		echo 1
	fi
}

# --- parse args ------------------------------------------------------------

TYPE=""
COMMIT_FLAG=0
INTERACTIVE=1

for arg in "$@"; do
	case "$arg" in
		patch|minor|major) TYPE="$arg" ;;
		--commit) COMMIT_FLAG=1 ;;
		-h|--help) usage; exit 0 ;;
		*) echo "Unknown argument: $arg" >&2; usage; exit 1 ;;
	esac
done

# Need a TTY for the menus. If stdin/stdout isn't a terminal, require the
# relevant flag to be passed so CI stays non-interactive.
if [ "$TYPE" = "" ] && [ "$COMMIT_FLAG" -eq 0 ]; then
	if [ ! -t 0 ] || [ ! -t 1 ]; then
		echo "Not a TTY: pass the release type and --commit explicitly." >&2
		echo "  e.g. bash scripts/release.sh patch --commit" >&2
		exit 1
	fi
fi

# --- current state ---------------------------------------------------------

CURRENT="$(grep -E '^Version:' style.css | awk '{print $2}')"
if [ -z "$CURRENT" ]; then
	echo "Could not read current Version from style.css" >&2
	exit 1
fi

# --- option 1: release type ------------------------------------------------

if [ -z "$TYPE" ]; then
	PATCH_V="$(bump_version "$CURRENT" patch)"
	MINOR_V="$(bump_version "$CURRENT" minor)"
	MAJOR_V="$(bump_version "$CURRENT" major)"
	CHOSEN="$(select_menu \
		"Select release type  (↑/↓ move, enter select)" \
		"patch   ${CURRENT} → ${PATCH_V}" \
		"minor   ${CURRENT} → ${MINOR_V}" \
		"major   ${CURRENT} → ${MAJOR_V}")" \
		|| { echo "Cancelled." >&2; exit 130; }
	TYPE="${CHOSEN%% *}"   # first word is the type
fi
NEW="$(bump_version "$CURRENT" "$TYPE")"

# --- gather commits since last release -------------------------------------

# Scope the changelog to commits since the previous release. Releases are
# tagged with their version (e.g. "1.2.3"), so the latest version tag is the
# canonical marker — far more reliable than grepping commit messages, whose
# wording has drifted ("Bump theme version to" vs "Bump version to").
LAST_TAG="$(git tag --list --sort=-v:refname '[0-9]*.[0-9]*.[0-9]*' | head -n1)"
if [ -n "$LAST_TAG" ]; then
	RANGE="${LAST_TAG}..HEAD"
else
	RANGE="HEAD"
fi

# All non-merge commit subjects since the last release.
mapfile -t ALL_SUBJECTS < <(git log "$RANGE" --no-merges --format='%s' || true)

# --- option 2: changelog entries -------------------------------------------

NOTES=""
if [ "${#ALL_SUBJECTS[@]}" -gt 0 ]; then
	# Build menu options + initial checked states from the auto-filter.
	MENU_OPTS=()
	MS_INIT=()
	for s in "${ALL_SUBJECTS[@]}"; do
		MENU_OPTS+=( "$s" )
		MS_INIT+=( "$(is_user_facing "$s")" )
	done

	# Only show the picker if there's something to toggle.
	if [ -t 0 ] && [ -t 1 ]; then
		SELECTED="$(select_many \
			"Select changelog entries  (↑/↓ move, space toggle, a all, n none, enter confirm)" \
			"${MENU_OPTS[@]}")" \
			|| { echo "Cancelled." >&2; exit 130; }
		NOTES="$SELECTED"
	else
		# Non-TTY: fall back to the auto-filtered set.
		for i in "${!MENU_OPTS[@]}"; do
			[ "${MS_INIT[$i]}" = "1" ] && NOTES+="${MENU_OPTS[$i]}"$'\n'
		done
	fi
fi

echo
echo "▶ Releasing ${SLUG}"
echo "  current version: ${CURRENT}"
echo "  new version:     ${NEW}  (${TYPE})"
echo "  changelog scope: ${RANGE}"
if [ -z "$NOTES" ]; then
	echo "  (no user-facing changes selected — a maintenance note will be added)"
fi
echo

if [ "$DRY_RUN" = "1" ]; then
	echo "DRY_RUN=1 — proposed changelog entry:"
	echo
	printf '= %s =\n' "$NEW"
	if [ -n "$NOTES" ]; then
		while IFS= read -r line; do [ -n "$line" ] && printf '* %s\n' "$line"; done <<< "$NOTES"
	else
		echo '* Maintenance and behind-the-scenes updates.'
	fi
	exit 0
fi

# --- option 3: commit, tag & publish --------------------------------------

if [ "$COMMIT_FLAG" -eq 0 ]; then
	ANSWER="$(confirm "Commit, tag, push & publish GitHub release ${NEW}?")"
	[ "$ANSWER" = "yes" ] && COMMIT_FLAG=1
fi

# --- bump version in all the places ----------------------------------------

sed -i -E "s/^(Version: ).*/\1${NEW}/" style.css
sed -i -E "s/^(Stable tag: ).*/\1${NEW}/" readme.txt
# package.json: keep the line pretty (two-space indent).
python3 - <<PY
import json, pathlib
p = pathlib.Path("package.json")
data = json.loads(p.read_text())
data["version"] = "${NEW}"
p.write_text(json.dumps(data, indent=2) + "\n")
PY

# --- prepend new changelog section in readme.txt ---------------------------

python3 - <<PY
import pathlib

new_version = "${NEW}"
notes = """${NOTES}""".splitlines()
notes = [n for n in notes if n.strip()]

lines = pathlib.Path("readme.txt").read_text().splitlines()
out = []
i = 0
inserted = False
while i < len(lines):
	out.append(lines[i])
	if lines[i].strip() == "== Changelog ==" and not inserted:
		out.append("")
		out.append(f"= {new_version} =")
		if notes:
			for n in notes:
				out.append(f"* {n}")
		else:
			out.append("* Maintenance and behind-the-scenes updates.")
		out.append("")
		inserted = True
		if i + 1 < len(lines) and lines[i + 1].strip() == "":
			i += 1
	i += 1

pathlib.Path("readme.txt").write_text("\n".join(out) + "\n")
PY

echo "✓ Bumped version to ${NEW} (style.css, readme.txt, package.json)"
echo "✓ Updated changelog in readme.txt"
echo

# --- build + zip -----------------------------------------------------------

echo "▶ Building assets and packaging zip…"
npm run package

ZIP="${ROOT}/${SLUG}.zip"
if [ -f "$ZIP" ]; then
	echo "✓ Created ${ZIP}"
else
	echo "Expected ${ZIP} but it was not found" >&2
	exit 1
fi

# --- commit, tag, push & publish GitHub release ----------------------------

if [ "$COMMIT_FLAG" -eq 1 ]; then
	BRANCH="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
	if [ "$BRANCH" != "trunk" ]; then
		echo "Refusing to publish: current branch is '${BRANCH:-detached}' but releases must come from 'trunk'." >&2
		echo "Switch to trunk (git checkout trunk) and re-run, or publish manually." >&2
		exit 1
	fi
	echo "▶ Committing and tagging ${NEW}…"
	git add style.css readme.txt package.json
	git commit -m "Bump theme version to ${NEW} and update changelog"
	git tag "$NEW"
	echo "✓ Committed and tagged ${NEW}"

	echo "▶ Pushing commit and tag to origin…"
	git push origin HEAD
	git push origin "$NEW"
	echo "✓ Pushed ${NEW}"

	echo "▶ Publishing GitHub release ${NEW}…"
	if ! command -v gh >/dev/null 2>&1; then
		echo "The 'gh' CLI is required to publish a GitHub release." >&2
		echo "Install it from https://cli.github.com, then create the release manually:" >&2
		echo "  gh release create ${NEW} \"${ZIP}\" --title \"${NEW}\" --notes-file <changelog>" >&2
		exit 1
	fi
	NOTES_FILE="$(mktemp)"
	{
		if [ -n "$NOTES" ]; then
			while IFS= read -r line; do [ -n "$line" ] && printf '* %s\n' "$line"; done <<< "$NOTES"
		else
			echo '* Maintenance and behind-the-scenes updates.'
		fi
	} > "$NOTES_FILE"
	gh release create "$NEW" "$ZIP" \
		--title "$NEW" \
		--notes-file "$NOTES_FILE"
	rm -f "$NOTES_FILE"
	echo "✓ Published GitHub release ${NEW} with ${ZIP}"
	echo
	echo "Done. Release ${NEW} is live on GitHub."
	echo "Deploy with: npm run deploy"
else
	echo
	echo "Next steps (run from trunk):"
	echo "  git add style.css readme.txt package.json"
	echo "  git commit -m 'Bump theme version to ${NEW} and update changelog'"
	echo "  git tag ${NEW}"
	echo "  git push origin HEAD && git push origin ${NEW}"
	echo "  gh release create ${NEW} ${ZIP} --title ${NEW} --notes-file <changelog>"
	echo "  Deploy with: npm run deploy"
fi
