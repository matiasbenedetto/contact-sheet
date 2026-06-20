# Contact Sheet

A minimal photography blog theme for WordPress that presents each post's images
as a film-style **contact sheet**. On the home page, every post's photos render
in a horizontal scrolling strip — like a photographer's proof sheet — using the
bundled Photo Strip block. Single posts drop the strip and show the images full
width, with diffuse drop shadows so they read like printed photographs.

Live demo: **[mebenedetto.com](https://mebenedetto.com)** (running this theme).

![Home page](screenshots/home.png)

## Features

- **Photo Strip block** — renders a post's images as a horizontal, scrollable
  contact-sheet strip on listing pages, with a gray placeholder for image-less
  posts.
- **Photo-first single posts** — full-width images with printed-photo drop
  shadows, a clean comments section, and labelled previous/next post navigation.
- **Two-color palette** with style variations.
- **Full-site editing** — block templates for home, single, archive, search,
  page, and 404, all editable in the Site Editor.
- Cal Sans + system-ui typography, translation-ready.

## Screenshots

### Single post

Full-width photos with printed-photo shadows.

![Single post](screenshots/single.png)

### Archive

Date and term archives reuse the contact-sheet strips.

![Archive](screenshots/archive.png)

### Search

![Search results](screenshots/search.png)

### 404

![404 page](screenshots/404.png)

## Requirements

- WordPress 6.8+
- PHP 7.2+

## Installation

1. In your admin panel, go to **Appearance → Themes** and click **Add New**.
2. Click **Upload Theme**, choose the theme's `.zip`, and click **Install Now**.
3. Click **Activate**.

To build a production zip from source: `npm install && npm run package` →
`contact-sheet.zip`.

## Development

The fastest way to develop the theme locally is the **Playground harness** in
[`playground/`](playground/README.md) — a disposable WordPress 7.0 site with the
Theme Check plugin and demo photo-blog content, no Apache/MySQL/Docker required:

```bash
bash playground/playground.sh bootstrap
bash playground/playground.sh seed
bash playground/playground.sh url      # open the live site
# after editing: bash playground/playground.sh sync && bash playground/playground.sh wp -- cache flush
```

The Photo Strip block source lives in `blocks/photo-strip/src/` and is built
into `blocks/photo-strip/build/`:

```bash
npm install
npm run build      # build the Photo Strip block
npm run package    # build + create contact-sheet.zip
```

Theme structure:

- `templates/*.html` — block templates (home, single, archive, search, 404, …)
- `parts/*.html` — template parts (header, footer)
- `theme.json` / `styles.css` — design tokens, palette, and custom CSS
- `blocks/photo-strip/` — the bundled Photo Strip block

See [`AGENTS.md`](AGENTS.md) for contributor guidance.

## License

GPLv2 or later. See [`readme.txt`](readme.txt) for the full changelog,
copyright, and bundled-resource credits (Cal Sans font, SIL OFL 1.1).
