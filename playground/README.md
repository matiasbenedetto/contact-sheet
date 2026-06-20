# Contact Sheet — Playground dev harness

A self-contained local development environment for the Contact Sheet theme, built
on **WordPress Playground** (PHP-WASM + SQLite). No Apache, no MySQL, no Docker —
just Node. It spins up a real WordPress **7.0** site, mounts a clean copy of this
theme into it, installs the **Theme Check** plugin, and seeds demo photo-blog
content (posts full of photos, like [mebenedetto.com](https://mebenedetto.com)) so
you can develop and visually review the theme offline.

This replaces the old `http://localhost/wp3` Apache setup: edits live in this repo,
a one-command `sync` pushes them into the running site, and the whole environment
is disposable and reproducible.

## Requirements

- Node 18+
- `setsid` (Linux/WSL ship it; macOS: `brew install util-linux`)
- ImageMagick (`convert`/`magick`) — only needed once to generate the demo photos
  (they're committed, so skip this unless you want to regenerate them)

## One-time setup

```bash
bash playground/playground.sh bootstrap   # create the WP 7.0 Playground site
bash playground/playground.sh seed         # install Theme Check + demo photo posts
```

`seed` leaves the server running and prints the live URL.

## Daily workflow

```bash
# edit theme files in this repo (templates/, theme.json, blocks/...), then:
bash playground/playground.sh sync         # push changes into the running site
# (theme.json / global styles are cached — flush with:)
bash playground/playground.sh wp -- cache flush
bash playground/playground.sh wp -- transient delete --all
```

Open the site:

```bash
bash playground/playground.sh url           # http://127.0.0.1:<port>/
```

If you stopped the server (or rebooted), bring it back with `ensure` — it reuses
the recorded site and mounts, or starts fresh:

```bash
bash playground/playground.sh ensure
```

## Commands

| Command | What it does |
|---|---|
| `bootstrap` | One-time: syncs the theme and creates the Playground site (records `workdir/.playground/site-dir`). |
| `ensure` | Convergent: reuses a healthy server, or starts one. Safe to run blindly. |
| `sync` | rsync the theme from the repo root into `workdir/theme` (excludes `node_modules`, `.git`, `playground/`, etc.). Run after edits. |
| `wp -- <args>` | Run wp-cli against the same site + mounts (downloads the phar on first use). |
| `seed [--force]` | `ensure` + install Theme Check + activate theme + create demo posts/photos. `--force` tears down and re-creates prior demo content. |
| `stop` | Stop the server and assert nothing survives. |
| `url` | Print the live site URL. |

## Where things live

```
contact-sheet/                 # the theme repo (deliverable)
├── playground/                # this harness (committed)
│   ├── playground.sh          # lifecycle script
│   ├── seed.php               # demo content seeder (run via wp eval-file)
│   └── assets/
│       ├── gen-photos.sh      # regenerates the demo photos (ImageMagick)
│       └── photos/*.jpg       # the demo photos (committed, ~2.8 MB)
└── workdir/                   # agent/server scratch — gitignored wholesale
    ├── .playground/           # Playground run-state: site-dir, mounts, server.*, wp-cli.phar
    └── theme/                 # clean synced copy of the theme, mounted into the site
```

The theme is **synced** (not the whole repo) so `node_modules`, `.git`, and this
`playground/` folder never end up inside `wp-content/themes/contact-sheet/` —
which keeps Theme Check happy and the mount light. The exclusions mirror
`scripts/package.sh` (the production zip builder).

## Demo content

Six published posts, each a short intro paragraph followed by a sequence of image
blocks (3–6 photos), dated across October–November 2025 so the home page's 5-per-page
query paginates. "Hello World" has an approved comment. Site title is `mebenedetto`.
The seeder is idempotent (skips if already done; `seed --force` to redo).

## Notes / gotchas

- **The live URL comes from `workdir/.playground/server.port`** — never read
  `siteurl` from the DB (Playground stores a junk ephemeral port). Use `playground.sh url`.
- **Frontend checks with curl need a cookie jar** (Playground does a one-time 302
  that sets a session cookie): `curl -sL -c workdir/.playground/cookies -b workdir/.playground/cookies <url>`.
- Playground runs on PHP-WASM with SQLite; it's slower than a real host and has no
  MySQL/cron daemon. Fine for theme dev — just don't expect heavy plugin stacks to work.
- To regenerate the demo photos: `bash playground/assets/gen-photos.sh` (overwrites
  `playground/assets/photos/`).
