---
name: verify
description: post-work verification — lint, build sanity, and release-ZIP leak check
---

When a task that changed code (PHP, build config, readme, languages) is finished, **before reporting success** run:

## 1. Sanity-check diffs

- `git diff --stat` — compare the changes against the list of files you actually
  edited. **Any changed file you did not edit is an alarm signal** (a stray
  build/format step overwriting sources).
- `includes/iworks/rate/` is a git submodule — any diff there means vendor code
  got touched; revert it.
- Compiled/built artifacts (`release/**`, `languages/*.mo`, `node_modules/`) are
  gitignored — they must never appear in a diff.

## 2. Verification by changed area

- **PHP (`og.php`, `includes/**`)**: `php -l` on every changed or new file.
  There is no phpcs and no working PHPUnit suite in this repo (`grunt test`
  fails at phpunit by design) — do not claim WPCS compliance; match the
  surrounding style (tabs, aligned arrays, `@since X.Y.Z` docblocks,
  `defined( 'ABSPATH' ) || exit;` + `class_exists` guards).
- **Output/filter changes**: grep the filter name to confirm the registration
  and every `apply_filters` call site agree; remember printed values pass
  through `prepare_value_for_output()` (scalars only) and the per-tag
  `og_{tag}_value` / `og_{tag}_meta` filters.
- **`readme.txt` edits**: `file readme.txt` must still report
  "CRLF line terminators" after editing.
- **Build/i18n changes (`Gruntfile.js`, `package.json`, `languages/`)**:
  `node --check Gruntfile.js`, then run the relevant pipeline end-to-end —
  `grunt i18n` minimum, `grunt build:github` when copy rules or placeholders
  changed. Inspect the staged tree `release/github/{version}/og/`: placeholders
  replaced, and `AGENTS.md`, `.opencode/`, `node_modules/`, `Gruntfile.js`
  absent. A `.pot`/`.po` timestamp diff after `makepot` is expected — check
  nothing else moved.
- **Version bumps**: bump only `package.json`; confirm sources still carry
  `PLUGIN_VERSION` placeholders (never real values).

## 3. Report

Summarize what was run and the list of verified files. If a check fails — fix
it before reporting task completion (or clearly list the blockers for the user
to decide). Never write "done" without completing step 2.
