# Build — Grunt

> Toolchain, tasks, pipeline, placeholders, release ZIPs, translations, tests.
> Read only when working on the build.

## Toolchain

- Node + npm + Grunt 1.x (`Gruntfile.js`, tasks loaded with `load-grunt-tasks`).
  `package-lock.json` is gitignored; `node_modules/` is gitignored.
- PHP ≥ 8.0 for the plugin itself. No bun, no wp-scripts, no Composer.
- **JS**: `assets/scripts/src/admin/*.js` (ES5, jshint-clean) → concat →
  `assets/scripts/og-admin.js` (classic) and `og-admin-gutenberg.js` (block editor)
  → uglified to `.min.js` (`conf.js_files_concat`). No SCSS — `assets/` also holds
  `images/logo.png`; the sass/cssmin tasks remain no-ops.

## Tasks (`grunt …`)

| Command | Description |
| --- | --- |
| `grunt` (default) | `clean:temp` + concat + uglify + sass + cssmin — builds the two admin JS bundles (`.js` + `.min.js`); css tasks are no-ops |
| `grunt js` / `grunt css` | Asset halves (js = the bundles; css = no-op) |
| `grunt i18n` | `checktextdomain` (validates gettext usage in `og.php` + `includes/**/*.php` against domains `og`, `IWORKS_RATE_TEXTDOMAIN`, `IWORKS_OPTIONS_TEXTDOMAIN`) + `makepot` (grunt-wp-i18n) |
| `grunt build` | wp.org release: default → i18n → clean:wporg → copy:wporg → replace → compress:wporg |
| `grunt build:github` | GitHub release: default → i18n → `potomo` (.po → .mo) → clean:github → copy:github → replace → compress:github |
| `grunt release` | `build:wporg` (see pitfalls) + `build:github` + `notes` (manual release checklist printer) |
| `grunt test` | phpunit + jshint + notes — phpunit has **no test env in this repo** (`tests/php/` does not exist), so the task fails at phpunit by design |
| `grunt watch` | sass/js watchers — nothing to watch (no sources) |

## Build pipeline (`grunt build`, wp.org)

1. **default** — asset tasks, no-ops (see above);
2. **i18n** — `checktextdomain`, then `makepot`: scans PHP (excluding
   `node_modules`, `.git`, `.sass-cache`, `release`, non-PHP files) →
   `languages/og.pot` and **updates existing `.po` files** (`updatePoFiles: true`);
3. **clean** — removes `release/wporg/{version}/` and `release/wporg/og.zip`;
4. **copy** — stages the plugin into `release/wporg/{version}/og/` per
   `excludeCopyFilesWPorg` (see Release ZIPs);
5. **replace** — placeholder substitution in every staged text file
   (binary types and `.mo` skipped);
6. **compress** — zips to `release/wporg/og.zip`.

## Placeholders (release copies only)

Replaced in staged files, sourced from `package.json` + build time
(`conf.replace_patterns`): `AUTHOR_NAME`, `AUTHOR_URI`, `BUILDTIME`,
`IWORKS_RATE_TEXTDOMAIN`, `IWORKS_OPTIONS_TEXTDOMAIN`, `PLUGIN_DESCRIPTION`,
`PLUGIN_GITHUB_WEBSITE`, `PLUGIN_NAME`, `PLUGIN_REQUIRES_PHP`,
`PLUGIN_REQUIRES_WORDPRESS`, `PLUGIN_TESTED_WORDPRESS`, `PLUGIN_TAGLINE`,
`PLUGIN_TAGS`, `PLUGIN_TILL_YEAR`, `PLUGIN_TITLE`, `PLUGIN_URI`,
`PLUGIN_VERSION`, plus the `^Version: .*$` header line.

The version lives **only** in `package.json` (`"version"`) — bumping it also
changes the runtime OG transient key (`og_{id}_{version}_{locale}`), which is
the intended way to invalidate stale cached output.

## Release ZIPs

- **WordPress.org** (`grunt build`) → `release/wporg/{version}/og/` → `og.zip`.
  Base excludes (`excludeCopyFiles`): VCS/dev files (`.git*`, `.editorconfig`,
  `Gruntfile.js`, `package.json`, `package-lock.json`, `node_modules/**`,
  `README.md`, `contributing.md`, `LICENSE`, `release/**`, `.sass-cache/**`,
  `tests/**`, source maps), plus WPorg-specific: asset sources, `languages/*.po|*.mo`,
  the **GitHub self-updater class** (`class-iworks-opengraph-github.php`),
  `includes/pro/**`, and `AGENTS.md` / `.opencode/**` (agent files).
  `readme.txt` **is** included (required by wp.org).
- **GitHub** (`grunt build:github`) → `release/github/{version}/og/` → `og.zip`.
  Base excludes plus `readme.txt` and `includes/pro/**`; asset sources and
  `.po` **are** included, `.mo` compiled by `potomo`. Pairs with the GitHub
  self-updater class.
- The three pattern lists derive from one base array
  (`excludeCopyFiles.slice(0).concat(...)`) — edits to the base list apply to
  **both** flavors. Any new top-level dev file (agent configs, scratch scripts)
  must be excluded there or it ships inside the ZIPs.

## Tests

- `grunt test` references `tests/php/phpunit.xml` + `bootstrap.php`, which do
  **not exist** in this repo — there is no working PHPUnit suite. Verification
  is `php -l` per file plus the `verify` skill; jshint covers `Gruntfile.js`
  and `assets/scripts/src/**` (empty).

## Runtime quirks / pitfalls (do not fix without approval)

- `grunt build:wporg` is **not registered**; Grunt falls back to the registered
  `build` task for unknown `task:arg` names. `grunt release` relies on this
  fallback — renaming `build` breaks it silently.
- `.gitignore`'s first line is broken (the `sass-cache` entry was swallowed by
  the preceding comment). `/release`, `/node_modules`, `/languages/*.mo` are
  properly ignored; artifacts on disk are untracked leftovers.
- `package-lock.json` is listed in `.gitignore` and untracked — npm's lockfile
  is intentionally not shared here.
- The exclusion lists put the `**` catch-all first and negations after it —
  this is the established, working grunt scheme; do not reorder without
  running a full build and inspecting the staged tree.
- `makepot` rewrites `languages/og.pot` and every `.po` with a new timestamp —
  expect a diff after every build; review that only timestamps changed.
- `grunt jshint` fails on **pre-existing** Gruntfile.js issues (duplicate
  `exclude` key in the makepot options, trailing comma in
  `grunt.registerTask('build', …)`) — the source files under
  `assets/scripts/src/` must stay clean; do not "fix" the Gruntfile without
  approval.
- The `notes` task is a manual checklist printer (forum, translations, archive
  check, wp.org + GitHub release, git tag), not automation.
