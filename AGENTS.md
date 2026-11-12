# AGENTS.md — OG — Better Share on Social Media

> Working rules only. Technical knowledge lives in topic docs — read on demand, never preload.

## What the project is

A zero-configuration WordPress plugin that injects Open Graph, Twitter Card and
Schema.org metadata into `<head>` (`og.php` → `includes/iworks/class-iworks-opengraph.php`),
with a GitHub self-updater and per-plugin integrations under `includes/iworks/opengraph/`.
Built with a bun bundler (`bundler/` → `release/` ZIPs, wporg + github flavors;
see `.opencode/docs/build.md`). This checkout is the **development branch**:
sources contain build-time placeholders (`PLUGIN_VERSION`, `AUTHOR_NAME`, …)
replaced by the bundler — never replace them in sources. The version lives only
in `package.json`.

## Docs map — read only what the task needs

| Task | Read first |
| --- | --- |
| OG array lifecycle, image chain, caching, output tags, filter catalog | `.opencode/docs/project.md` |
| Main class internals, GitHub updater, integrations, new integration | `.opencode/docs/classes.md` |
- Bun bundler, build, dev watcher, ZIPs, placeholders, translations (.pot/.po), Plugin Check | `.opencode/docs/build.md` |
| After any task that changed code | skill `verify` (`.opencode/skills/verify/SKILL.md`) |

- Read ONLY the docs relevant to the current task — do not load everything upfront.
- When a change alters documented behavior, update the corresponding doc in the same task.

## Evergreen rules (every task)

- All documentation and README in English.
- `readme.txt` uses **CRLF** line endings (`.editorconfig`: `*.txt` → crlf) — preserve them.
  Its `== Changelog ==` section carries **real** versions and dates in this dev branch
  (only file headers use placeholders): add new entries at the top, never reformat
  or reorder existing ones.
- Never commit, push, amend or create PRs without an explicit request.
- Naming: kebab-case files (`class-iworks-opengraph*.php`); class names use two
  historical styles — `iWorks_OpenGraph` (main class + integrations) and lowercase
  `iworks_opengraph_github` — follow the file you are extending. Integration
  filenames alternate between `integrations-{plugin}.php` and `integration-{plugin}.php`
  — keep the precedent of the closest sibling (see `classes.md`).
- Version lives only in `package.json` — placeholders are replaced by Grunt during
  build; never hardcode real values in sources. The main class property `$version`
  (`'PLUGIN_VERSION'`) is part of the OG transient cache key, so a version bump also
  invalidates cached output (desirable after output-affecting changes).
- `includes/iworks/rate/` is a **git submodule** (vendored iWorks Rate) — treat it as
  vendor: do not modify; report issues upstream (https://github.com/iworks/iworks-rate).
- Do not fix known typos/pitfalls without approval (see topic docs).

## Commands

`bun run dev` (watcher) · `bun run compile` (one-shot JS bundles) ·
`bun run build` (both ZIPs) · `bun run build:wporg` (wp.org ZIP + Plugin
Check) · `bun run build:github` (GitHub ZIP) · `bun run make-pot`
(translations) · `bun run update-libs` (submodule pull)
