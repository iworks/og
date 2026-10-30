# Project mechanics — OG — Better Share on Social Media

> Loading flow, OG array lifecycle, caching, output machinery, integrations, i18n, conventions.
> Read only when the task involves these areas.

## Overview

A single-purpose, settings-free WordPress plugin (textdomain `og`): on every
front-end request it builds an array of Open Graph / Twitter Card / Schema.org
values for the current query and prints it as `<meta>` tags in `<head>`.
There is no options page and no base class — the extension surface is a
catalog of `og_*` filters (see below) plus optional integrations.

The version lives only in `package.json`; the main file header and the class
property `$version` both use the `'PLUGIN_VERSION'` placeholder.

## Directory structure

```
/
├── og.php                          # Bootstrap: plugin header (placeholders) + require + new iWorks_OpenGraph()
├── includes/iworks/
│   ├── class-iworks-opengraph.php  # Main class (~1.8k lines) — all plugin logic
│   ├── rate/                       # Git submodule: iworks-rate vendor (admin rating notices)
│   └── opengraph/
│       ├── class-iworks-opengraph-github.php       # GitHub self-updater (standalone)
│       ├── class-iworks-opengraph-integrations.php # Abstract base for integrations
│       └── integrations/           # One class per supported external plugin (10 files)
├── assets/
│   ├── images/logo.png             # Logo for the rate admin notice
│   └── scripts/
│       ├── src/admin/              # Admin JS sources (featured-image warning, ES5)
│       └── og-admin*.js            # Built bundles (+ .min.js) — see build.md
├── languages/                      # og.pot + .po/.mo (de_DE, es_ES, nl_NL, pl_PL, ro_RO, uk, zh_TW)
├── release/                        # Built ZIPs (gitignored artifacts)
└── readme.txt                      # WordPress.org readme (CRLF; real changelog entries)
```

Every directory carries an `index.php` silent-guard file.

## Loading flow

1. WordPress loads `og.php`: header (placeholders), then
   `require_once includes/iworks/class-iworks-opengraph.php` and `new iWorks_OpenGraph()`.
2. `iWorks_OpenGraph::__construct()`:
   - `$debug` — `apply_filters( 'og_debug', WP_DEBUG )`;
   - resolves `$root` / `$plugin_file` from three directory levels up;
   - `$image_size` — `og_image_size` filter (default `'full'`);
   - registers hooks:
     - `edit_attachment` → `delete_transient_cache`;
     - `init` → `load_textdomain`; `init` (PHP_INT_MAX) → `action_init_register_iworks_rate`;
     - `save_post` ×3 → `add_vimeo_thumbnails`, `add_youtube_thumbnails`, `delete_transient_cache`;
     - `wp_head` at priority `og_wp_head_priority` (default 9) → `wp_head`;
     - `language_attributes` → `filter_add_html_itemscope_itemtype` (Schema.org itemscope on `<html>`);
     - `plugin_row_meta` → rating ask;
   - registers own filters: `og_schema_datePublished`, `og_get_image_dimensions` (by attachment ID),
     `og_twitter_array` (priority 99 → `add_reading_time`), `og_get_og_array`;
   - `init` at priority 117 → `load_integrations()` (see below);
   - includes `class-iworks-opengraph-github.php` unconditionally (`is_file()` + `new`);
   - adds filter `og/is_active` → `__return_true`.

## OG array lifecycle (`get_og_array()`)

Built once per request and memoized in `$this->og`; also reachable from outside
via the `og_get_og_array` filter. Skeleton:

```
og       => image[], video[], description, type='website', locale, site_name, logo
article  => tag[]
twitter  => partner='ogwp', site, creator, widgets[], player[]
schema   => {}
```

Before building it removes `fpp_head_action` (plugin: Facebook Page Publish) and
adds `orphan_skip_replacement` (plugin: Orphans; removed again after `wp_head`).

Branches on the query:

- **singular** — `og:type = 'article'`, cached build (see Caching):
  - featured image (`og_allow_to_use_thumbnail`) — `array_unshift`, so featured is always first;
  - YouTube thumbnails from post meta `iworks_yt_thumbnails` (`og_allow_to_use_youtube`)
    → `og:image` + `og:video` + `twitter:player` (`youtu.be` links);
  - Vimeo thumbnails from post meta `_og_vimeo_thumbnails` (`og_allow_to_use_vimeo`)
    → image (640w) + video + twitter player;
  - attachment pages: image / video / audio typing with props from `_wp_attachment_metadata`;
  - content images (`og_allow_to_use_content_image`) **only when there is no featured image**:
    `wp-image-{id}` class → attachment lookup, else same-host `src` → GUID lookup
    (`get_attachment_id()`), else dimensions via `getimagesize()`;
  - title (`strip_white_chars()`), permalink, description (excerpt, or
    `wp_trim_words` over shortcode-stripped content, `og_description_words` = 55;
    falls back to the title) — skipped for password-protected posts;
  - tags, `article:published_time` / `article:modified_time` (GMT, ISO-8601),
    `og:updated_time`, categories → `article.section`, tags → `article.tag`,
    author → `article.author` + `og:profile` (`og_profile` filter);
  - post format: `audio` → `og:type music`, `video` → `og:type video`;
  - attached media (video/audio) → `og:rich_attachment` + `og:video`/`og:audio` entries;
  - `twitter:card = 'summary'`, then `set_twitter_image()`: switches to
    `summary_large_image` when the first image is wider than 519px, adds
    `twitter:image` + `twitter:image:alt`.
- **author** — `og:type profile`, 512px avatar (`default => 404`), bio as description.
- **search** — search link.
- **archive** — term link + term description + term image from term meta
  (`og/term/meta/thumbnail_id_name`, default `image`; or URL meta
  `og/term/meta/thumbnail_url`, default `image_url`), post-type archive link,
  date archive links.
- **home/front page** — `og:type website`, blog title/description,
  `page_for_posts` permalink for the posts page.

Fallbacks and post-processing: missing title ← `wp_get_document_title()`;
missing image ← site icon; twitter mirrors `title`/`description`/`url` from og
(twitter description trimmed in a loop to ≤ 200 chars); Schema.org mapping
(`name`, `headline`, `description`, `datePublished`, `dateModified`; tagline only
behind `og_allow_to_use_schema_tagline`, off by default); `<link rel="image_src">`
and `msapplication-TileImage` extras (behind `og_head_link_rel_image_src_enabled` /
`og_head_meta_title_image_enabled`); finally section filters
`og_og_array`, `og_article_array`, `og_twitter_array`, `og_schema_array`, then the
whole-array `og_array` filter.

`og:logo` is produced only when the `allow_og_logo` filter returns true (uses the
custom_logo theme mod, size via `og_logo_size`).

## Caching

- Transient key: `og_{post_id}_{version}_{locale}` — **`$version` is the
  `'PLUGIN_VERSION'` placeholder**, so a release bump invalidates all cached output.
- Stored for one day (`og_set_transient_expiration` filter), only for singular builds.
- Deleted on `save_post` and `edit_attachment`.
- Not used at all when `$debug` is on (`og_debug` / `WP_DEBUG`).

## Output (`wp_head()` → `echo_array()` → `echo_one()`)

Prints `<!-- OG: {version} -->`, fires `iworks_og_before`, echoes the array,
fires `iworks_og_after`, prints `<!-- /OG -->`. `echo_array()` recurses
(pretty-printed section comments in debug mode) with special cases:
`twitter/labels` → `echo_twiter_labels()` (`twitter:labelN` / `twitter:dataN`),
`og/logo` → `echo_one_with_array_of_params()`, `schema/*` → printed with
`itemprop` instead of `property`. `echo_one()` collapses `og:image:url` /
`og:video:url` to `og:image` / `og:video`, applies `og_{tag}_value` then prints
`<meta property="…" content="…">` through `og_{tag}_meta`; values pass through
`prepare_value_for_output()` (scalars only — arrays/objects from filters are
dropped) and are escaped with `esc_attr()`. Each tag is separated by `PHP_EOL`
only in debug mode.

## Video thumbnails (on `save_post`)

- YouTube: regexes for `youtu.be/{id}` and `youtube.com/watch?v={id}`; for each
  ID probes `img.youtube.com` sizes (`maxresdefault`, `hqdefault`, `0`) with
  `wp_remote_head()` + `getimagesize()`; stores meta `iworks_yt_thumbnails`.
- Vimeo: regex for `vimeo.com/{id}`; fetches `vimeo.com/api/v2/video/{id}.php`
  and unserializes with `allowed_classes => false`; stores meta
  `_og_vimeo_thumbnails`.
- Both skip revisions/non-published posts (`og_check_add_video_thumbnails_by_post`).

## Integrations

`load_integrations()` runs on `init` 117, reads the `active_plugins` option and
`preg_match`es each basename; matched classes are `include_once`d and
instantiated. They hook into the section filters (`og_array`, `og_og_array`,
`og_twitter_array`, `og_article_array`) — details in `classes.md`.

## Admin: featured image width warning

`@since 3.4.0` — on post edit screens (classic + block editor, thumbnail-supporting
post types) a dismissible warning appears above the featured image when its full
size is narrower than the recommended 1200 px (`og:image` best practice):

- classic editor: `admin_post_thumbnail_html` filter prepends a
  `.notice.notice-warning.inline` block (server-side, exact width from
  `wp_get_attachment_image_src( $id, 'full' )`);
- block editor: `og-admin-gutenberg.js` wraps the featured image component via the
  `editor.PostFeaturedImage` JS filter, width from `core.getMedia().media_details`;
- × triggers `admin-ajax.php` action `og_dismiss_featured_image_warning`
  (nonce + `edit_posts` cap) → per-user global opt-out in user meta
  `_og_featured_image_warning_dismissed` → script no longer enqueued, notice not rendered.

## Translations (i18n)

- Textdomain `og`; `load_plugin_textdomain()` runs in the main class **and** in
  the GitHub updater (duplicated, harmless).
- `languages/og.pot` + `.po` per locale; `.mo` gitignored, compiled during
  `grunt build:github` (see `build.md`).

## Conventions

- Formatting: tabs for PHP (`.editorconfig`), WordPress-style docblocks, aligned
  `=>` arrays, `@since X.Y.Z` tags on every meaningful change.
- Every class file: `defined( 'ABSPATH' ) || exit;` plus a
  `if ( class_exists( … ) ) { return; }` guard.
- Filters: `og_*` (e.g. `og_array`) or `og/...` (e.g. `og/is_active`) — keep the
  filter contract backward compatible; integrations and third parties rely on it.

## Filter catalog (extension API)

| Filter | Purpose |
| --- | --- |
| `og_array` | Whole final array (last chance) |
| `og_og_array` / `og_article_array` / `og_twitter_array` / `og_schema_array` / `og_profile` | Per-section arrays |
| `og_get_og_array` | Read access to the built array (e.g. Debug Bar panel) |
| `og_{tag}_value` / `og_{tag}_meta` | Single tag value / whole `<meta>` line (e.g. `og_og_title_value`) |
| `og_debug`, `og_wp_head_priority`, `og_image_size` | Behavior switches |
| `og_allow_to_use_{thumbnail,youtube,vimeo,content_image}` | Toggle image sources |
| `og_description_words` | Excerpt length (default 55) |
| `og_set_transient_expiration`, `og_check_add_video_thumbnails_by_post` | Caching / video parsing |
| `og_is_schema_org_enabled`, `og_allow_to_use_schema_tagline` | Schema.org output |
| `og_head_link_rel_image_src_enabled`, `og_head_meta_title_image_enabled` | Extra `<head>` lines |
| `og_get_locale`, `og_twitter_site`, `og_twitter_creator` | Locale / twitter metadata |
| `og/term/meta/thumbnail_id_name`, `og/term/meta/thumbnail_url` | Term image meta keys |
| `og/admin/featured_image/min_width` | Featured image warning threshold (default 1200 px) |
| `og/admin/featured_image/warning_html` | Whole featured-image warning HTML override |
| `og/is_active` | Always true — marker for other plugins |
| `og_get_image_dimensions`, `og_image_init`, `og_video_init` | Image/video entry points |
| `iworks_og_before` / `iworks_og_after` | Actions around the printed block |

## Known pitfalls (do not fix without approval)

- Source placeholders (`PLUGIN_VERSION`, `AUTHOR_NAME`, …) are intentional —
  never replace them in the repo (see `build.md`).
- `get_og_array()` contains **two near-identical tag loops** (`get_the_tags()`
  around line 657, `wp_get_post_tags()` around line 685). Since 3.4.0 the first
  one is unescaped and the second never was — check which one you are editing.
- YouTube meta name `iworks_yt_thumbnails` has **no leading underscore**, while
  the Vimeo meta name `_og_vimeo_thumbnails` does.
- The WPML integration converts `og:locale` from a string into an array
  (base + `alternate`) — consumers of the array must tolerate that; the output
  layer drops non-scalars.
- The Categories Images integration `return;`s (null) when its plugin function
  is missing — works only because the loader guarantees the plugin is active.
- `grunt build:wporg` is not a registered task; `grunt release` works because
  Grunt falls back to the registered `build` task for unknown `build:wporg`.
- `.gitignore`'s first line is broken (the `sass-cache` entry was swallowed by
  the comment); `package-lock.json` and `node_modules/` are listed there and are
  indeed untracked. Leave as-is.
- `readme.txt` is CRLF — do not let editors/tools convert it to LF.
