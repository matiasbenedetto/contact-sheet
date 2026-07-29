# demo-site/scripts

## generate-images.php

Renders the photo galleries described in [`../plan/posts.md`](../plan/posts.md)
into real images, using the Google Imagen image client from the sibling
**builder** project (`/home/matias/dev/a8c/builder`).

For every image listed under a post's **Image gallery**, the script:

1. splits the gallery line
   (`subject … | location | camera & film | aspect ratio`),
2. composes an Imagen prompt from the subject + location + camera/film and the
   post's shared **Image grade**,
3. maps the aspect-ratio field to an Imagen ratio
   (landscape → `4:3`, portrait → `3:4`, square → `1:1`),
4. generates the image and saves it under `../images/<NN-post-slug>/`.

A `../images/manifest.json` records every prompt, file, aspect ratio and status
(for the later content-seeding step). Generated images are **git-ignored** — the
prompts in `posts.md` are the source of truth; re-run to reproduce.

### Requirements

- PHP 8.1+
- The builder repo checked out (default `/home/matias/dev/a8c/builder`, or set
  `BUILDER_DIR` / `--builder=<dir>`), with `GOOGLE_VERTEX_API_TOKEN` in its
  `.env` — the same token the builder uses for its own image generation.

### Usage

```bash
# Preview the composed prompts and target files — no API calls, no cost:
php demo-site/scripts/generate-images.php --dry-run

# Generate everything (skips images that already exist):
php demo-site/scripts/generate-images.php

# A cheap sample: one image from post 1:
php demo-site/scripts/generate-images.php --only=1 --limit=1
```

### Options

| Option | Effect |
|--------|--------|
| `--dry-run` | Parse + compose prompts and print them; make no API calls. |
| `--only=<n\|slug>` | Only this post (by number, e.g. `1`, or a slug substring). |
| `--limit=<n>` | At most N images per post (handy for sampling). |
| `--force` | Regenerate even if the output file already exists. |
| `--size=1K\|2K` | Imagen sample size (default `1K`). |
| `--posts=<path>` | Override the `posts.md` path. |
| `--out=<dir>` | Override the output image directory. |
| `--builder=<dir>` | Override the builder repo path (or set `BUILDER_DIR`). |

The run is idempotent: existing files are skipped, so an interrupted batch can
be resumed by simply running it again.
