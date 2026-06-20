# AGENTS.md — Contact Sheet

Guidance for AI agents (Claude Code and others) working in this repo.

This is the **Contact Sheet** WordPress theme. PRs target the `trunk` branch.

## Local dev

The recommended local development path is the **Playground harness** in
`playground/` (see `playground/README.md`): a disposable WordPress 7.0 site with
the Theme Check plugin installed and demo photo-blog content, no Apache/MySQL.

```bash
bash playground/playground.sh bootstrap   # one-time
bash playground/playground.sh seed         # install Theme Check + demo posts
bash playground/playground.sh sync         # push theme edits into the site
bash playground/playground.sh url          # live URL
```

Machine-specific setup, the legacy Apache dev server, and the deploy workflow
live in local, uncommitted notes, imported below so agents pick them up
automatically when present:

@AGENTS.local.md
