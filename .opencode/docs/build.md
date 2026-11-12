# Build — Bun bundler

> Toolchain, modes, pipeline, placeholders, release ZIPs, translations.
> Read only when working on the build.

## Toolchain

- Bun (>= 1.3) — package manager, script runner and the bundler runtime
  (`bundler/index.js` modes). `bun.lock` is committed; `package-lock.json` is
  gitignored. Node is not required (no wp-scripts — this plugin has no blocks).
- PHP ≥ 8.0; `wp-cli.phar` (downloaded by the `postinstall` script, gitignored)
  for translations; PHPUnit for tests (not set up in this repo).
- Prettier and terser run **in-process** (`bundler/tools.mjs`,
  `bundler/pipelines.mjs`) — no CLI spawns per file. Only wp-cli and PHPUnit
  are child processes.
- All dist writes are atomic (temp + rename); tool failures print as friendly
  one-liners (`tool — file` + message, no stack trace) and fail the build.
- Migrated from Grunt (grunt parity kept: placeholders, copy rules, banners,
  ZIP flavors); the old `Gruntfile.js` was removed — git history has it.

## Files (`bundler/`)

| File                | Role                                                                                          |
| ------------------- | --------------------------------------------------------------------------------------------- |
| `index.js`          | Modes: `dev` (default) / `compile` / `build` / `build:wporg` / `build:github` / `make-pot` / `test` / `update-libs` |
| `config.mjs`        | Paths, names, JS bundles, banner, placeholder patterns, copy rules per flavor (from `package.json`) |
| `pipelines.mjs`     | JS pipeline (concat → banner → terser → `.min.js`), prettier pass, clean                      |
| `tools.mjs`         | In-process APIs (prettier, atomic writes, glob helpers)                                        |
| `makeZip.mjs`       | Staging copy → placeholder replacement → ZIP (archiver, level 9)                               |
| `makePot.mjs`       | wp-cli i18n: `make-pot` → `update-po` (→ `make-mo` for github)                                 |
| `pluginCheck.mjs`   | wp.org Plugin Check (PCP) on the staged wporg release (build:wporg)                            |
| `spawnAsync.mjs`    | `child_process.spawn` wrapper with captured output                                             |
| `consoleHelpers.mjs`, `progressBar.mjs` | Logging, timing, build progress bar                                        |

Bundle names and copy rules derive from `package.json` `name` at runtime.

## JS bundles (`config.mjs` → `jsBundles`)

| Bundle target | Sources |
| --- | --- |
| `assets/scripts/og-admin.js` | `assets/scripts/src/admin/featured-image-warning-classic.js` |
| `assets/scripts/og-admin-gutenberg.js` | `assets/scripts/src/admin/featured-image-warning-gutenberg.js` |

Each is concat + banner → uglified to `.min.js` (terser, mangle with `jQuery`
reserved). Built bundles are **gitignored** — they are build artifacts (unlike
the old grunt era where they were committed; the enqueue falls back gracefully,
and `bun run build` / `bun run compile` produces them in ~1s).

## Modes (`bun run …`)

| Command                | Description                                                                                  |
| ---------------------- | -------------------------------------------------------------------------------------------- |
| `bun run dev`          | Watcher: prettier on `assets/scripts/src/**` JS change (200 ms debounce); no ZIP, no i18n, no auto browser reload |
| `bun run compile`      | One-shot prettier + JS bundles (old grunt `default`/`js` parity)                              |
| `bun run build`        | Both flavors back-to-back: github + wporg (+ Plugin Check)                                    |
| `bun run build:wporg`  | Production + Plugin Check → `release/wporg/{name}.zip`                                        |
| `bun run build:github` | Production + `.mo` → `release/github/{name}.zip`                                              |
| `bun run make-pot`     | Translations only (`.pot` + `.po` update)                                                     |
| `bun run test`         | PHPUnit (`tests/php/phpunit.xml`) — no test env exists in this repo, fails by design           |
| `bun run update-libs`  | `git pull` inside the `includes/iworks/rate` submodule                                        |
| (env) `DEV_EXIT_AFTER_MS` | Dev mode exits itself after N ms — headless testing                                        |

## Build pipeline (`bun run build:github`, 6 steps)

1. **clean** — remove built JS bundles (`assets/scripts/og-admin*.js`) and
   `languages/*.mo`;
2. **prettier** — format sources in place (`assets/scripts/src/**/*.js`,
   `bundler/**/*.{js,mjs}`, root `*.js`) — the build rewrites sources by design;
3. **js** — concat + banner → `og-admin{,-gutenberg}.js`, terser → `.min.js`
   (missing sources → warning + skip);
4. **i18n** — wp-cli `i18n make-pot` → `languages/og.pot`, then `update-po` per
   existing `.po`; `make-mo` only in `build:github`; timestamp-only `.pot`
   changes are detected and reverted (no diff churn);
5. **zip** — staging copy → placeholder replacement → `release/{flavor}/{version}/{name}/`
   → `release/{flavor}/{name}.zip`;
6. done — progress bar + total time; any failure prints `BUILD FAILED` and exits 1.

`build:wporg` adds a 7th step: **plugin-check** (see below).

## Placeholders (release copies only)

Applied to every staged text file (binary extensions and `.mo` skipped):
`AUTHOR_NAME/URI`, `BUILDTIME`, `IWORKS_RATE_TEXTDOMAIN`,
`IWORKS_OPTIONS_TEXTDOMAIN`, `PLUGIN_DESCRIPTION`, `PLUGIN_GITHUB_WEBSITE`,
`PLUGIN_NAME`, `PLUGIN_REQUIRES_PHP`, `PLUGIN_REQUIRES_WORDPRESS`,
`PLUGIN_TESTED_WORDPRESS`, `PLUGIN_TAGLINE`, `PLUGIN_TAGS`, `PLUGIN_TILL_YEAR`,
`PLUGIN_TITLE`, `PLUGIN_URI`, `PLUGIN_VERSION`, and the `^Version: .*$` header
line — all sourced from `package.json` + build time (`bundler/config.mjs`).

The version lives **only** in `package.json` (also drives the runtime OG
transient key `og_{id}_{version}_{locale}`).

## Release ZIPs

- **WordPress.org** (`bun run build:wporg`) → `release/wporg/{version}/{name}/`.
  Excludes: repo meta (`.git*`, `.editorconfig`, `.opencode/**`, `AGENTS.md`,
  `bundler/`, lock files, `wp-cli.phar`, `package.json`,
  `README.md`, `LICENSE`, `contributing.md`, tests, `release/`,
  `.sass-cache`, `**/*.map`, scratch files), asset sources
  (`assets/scripts/src`), the GitHub self-updater class
  (`class-iworks-opengraph-github.php`), `languages/*.po|*.mo`.
  `readme.txt` **is** included (required by wp.org).
- **GitHub** (`bun run build:github`) → `release/github/{version}/{name}/`.
  Base excludes plus `readme.txt`; asset sources and `.po` **are** included,
  `.mo` compiled by `make-mo` when `.po` files exist. Pairs with the GitHub
  self-updater class.
- Copy rules are ordered and evaluated **first match wins** (grunt semantics) —
  the catch-all `**/*` include must stay last (`bundler/config.mjs`).
- A missing main plugin file logs a warning; staging proceeds.

## Plugin Check (wporg only)

`bun run build:wporg` ends with WordPress.org Plugin Check (PCP): the staged
release is copied to the local WordPress plugins dir under the real slug,
`wp plugin check <slug>` runs, ERROR results fail the build, full output goes
to `plugin-check.log` (gitignored). Skips gracefully when `wp-cli.phar` or the
Plugin Check plugin is missing, or with `PLUGIN_CHECK_SKIP=1`.

As of 3.4.0 the check reports 14 pre-existing errors (translators comments,
escaping-in-filter false positives, deprecated `term_description()` param,
`strip_tags()`, outdated Tested-up-to header, license mismatch, direct file
access in `languages/index.php`-style guards) — these must be fixed in code
before a wp.org release; they are not build problems.

## Tests

- `bun run test` → `phpunit --configuration tests/php/phpunit.xml`. No test
  environment exists in this repo — the mode fails by design.
- jshint was dropped with Grunt; JS sources are prettier-formatted instead.

## Runtime quirks / pitfalls (do not fix without approval)

- The build's prettier step **rewrites source files** — that is intended.
  A diff after `bun run build` containing only JS reformatting is expected
  (first run after the grunt→bun migration reformatted the two admin sources).
- The `.pot` timestamp-only change is auto-reverted by the bundler, but `.po`
  files still get line-reference updates on code moves — review those diffs.
- **Submodule strings are NOT in the `.pot`**: grunt-wp-i18n scanned
  `includes/iworks/rate/` and included its strings (textdomain replaced to
  `og` at build); wp-cli `i18n make-pot` skips git submodule directories, so
  the bun-era `og.pot` covers only the plugin's own strings. Existing `.po`
  translations for rate strings survive until the next `update-po` cycle
  obsoletes them. Fix upstream (vendored) or accept untranslated rate
  notices; do not hand-merge vendor strings into the `.pot`.
- Copy rules: the catch-all `**/*` include must stay last — reordering
  silently leaks sources into the ZIPs.
- The wporg build fails while Plugin Check reports errors (see above); use
  `PLUGIN_CHECK_SKIP=1` locally when only packaging is needed.
- Terser mangles variable names differently than grunt-uglify — minified
  bundles are not byte-identical to old artifacts, only equivalent.
- `bun run make-pot` needs network on the first run (wp-cli.phar download) —
  `postinstall` retries it on every `bun install`.
