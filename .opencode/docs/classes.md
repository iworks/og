# Classes — OG — Better Share on Social Media

> Reference for work on PHP classes: the main class, the GitHub updater,
> integrations, and the vendored iWorks Rate module.
> Read only when the task involves these areas.

## Class inventory

```
iWorks_OpenGraph                                (main class, instantiated in og.php)
iWorks_OpenGraph_Integrations                   (abstract base for integrations)
├── iWorks_OpenGraph_Integrations_YARPP
├── iWorks_OpenGraph_Integrations_Reading_Time_WP
├── iWorks_OpenGraph_Integrations_Categories_Images
├── iWorks_OpenGraph_Integrations_Post_Expirator
├── iWorks_OpenGraph_Integrations_Contextual_Related_Posts
├── iWorks_OpenGraph_Integrations_Related_Posts_for_WordPress
├── iWorks_OpenGraph_Integrations_Twitter
├── iWorks_OpenGraph_Integrations_Sitepress_Multilingual_CMS
├── iWorks_OpenGraph_Integration_WooCommerce        (singular "Integration")
└── iWorks_OpenGraph_Integration_Debug_Bar          (singular "Integration")
iWorks_OpenGraph_Integration_Debug_Bar_Panel    (extends Debug_Bar_Panel, loaded by the wrapper)
iworks_opengraph_github                         (standalone self-updater, lowercase name)
iworks_rate                                     (vendored git submodule — vendor, do not touch)
```

Every class file: `defined( 'ABSPATH' ) || exit;` + `class_exists` guard.
Exception: the Debug Bar **panel** class has no guard — it is included only by
its wrapper and must extend `Debug_Bar_Panel`.

## Main class (includes/iworks/class-iworks-opengraph.php)

`iWorks_OpenGraph` — no inheritance, no base class. Key members:

Properties:

- `$youtube_meta_name = 'iworks_yt_thumbnails'` (no leading underscore),
  `$vimeo_meta_name = '_og_vimeo_thumbnails'`;
- `$version = 'PLUGIN_VERSION'` — printed in the `<!-- OG: … -->` comment **and**
  part of the transient cache key;
- `$schema_org_mapping` — Schema.org itemprop → og array path map;
- `$is_schema_org_enabled = true` (filter `og_is_schema_org_enabled`);
- `$image_size = 'full'` (filter `og_image_size`);
- `$og` — memoized per-request OG array; `$root`, `$plugin_file`, `$locale`, `$debug`.

Constructor + hooks — see `project.md` (Loading flow).

Methods worth knowing when editing:

| Method | Role |
| --- | --- |
| `get_og_array()` | Builds the whole array per query type (see `project.md`) |
| `wp_head()` | Prints the block; debug comment, `iworks_og_before`/`after` actions |
| `echo_array()` / `echo_one()` / `echo_one_with_array_of_params()` / `echo_twiter_labels()` | Output machinery + per-tag filters |
| `prepare_value_for_output()` | Scalar-only guard for printed values (`@since 3.4.0`) |
| `add_youtube_thumbnails()` / `add_vimeo_thumbnails()` / `check_add_video_thumbnails_by_post()` | `save_post` video parsing into post meta |
| `get_image_dimensions()` | Builds url/secure_url/width/height/alt/type from attachment data |
| `get_attachment_id()` | GUID lookup (recursive for `-WxH` sized URLs), returns `(int)` |
| `get_transient_key()` / `delete_transient_cache()` | `og_{id}_{version}_{locale}` cache |
| `set_twitter_image()` | twitter card/image selection (large card > 519px width) |
| `get_site_logo()` | `og:logo` behind `allow_og_logo` (custom_logo theme mod) |
| `get_the_author_meta_array()` | `og:profile` values (`og_profile` filter) |
| `map_itemscope_itemtype()` / `filter_add_html_itemscope_itemtype()` / `get_type()` | Schema.org itemscope on `<html>` |
| `load_integrations()` | Sniffs `active_plugins`, loads integration classes |
| `add_reading_time()` | Twitter label, WPM via `iworks/og/twitter/time_to_read/words-per-minute` (190) |
| `filter_og_get_og_array()` | Public read access to the built array |
| `action_init_register_iworks_rate()` | Registers the plugin with the vendored rate module |
| `filter_admin_post_thumbnail_html()` | Classic editor: warning above the featured image when narrower than the minimum |
| `action_admin_enqueue_scripts()` | Enqueues `og-admin` / `og-admin-gutenberg` on post edit screens (skipped when the user opted out) |
| `ajax_dismiss_featured_image_warning()` | AJAX: per-user global opt-out (user meta `_og_featured_image_warning_dismissed`) |
| `get_featured_image_min_width()` / `get_featured_image_warning_message()` / `is_featured_image_warning_dismissed()` | Private helpers for the warning |
| `plugin_row_meta()`, `iworks_rate_css()`, `filter_plugin_logo()` | Rating-notice plumbing |

## GitHub self-updater (class-iworks-opengraph-github.php)

`iworks_opengraph_github` — repo `iworks/og`, slug `og`:

- `plugins_api` → `plugin_popup()` (plugin info popup);
- `pre_set_site_transient_update_plugins` → `modify_transient()` (version compare
  against the latest GitHub release, `zipball_url` as package);
- `upgrader_post_install` → `install_update()` (renames the unzipped dir, reactivates).

**Excluded from the WordPress.org ZIP** (wp.org releases must not self-update
from GitHub) — see the copy rules in `build.md`.

Known pitfall: `plugin_popup()`, `modify_transient()` and `install_update()`
reference `$this->base` / `$this->plugin_file`, which are **never initialized**
in this class — the update flow effectively no-ops. Do not "fix" without approval.

## Integrations

Base: abstract `iWorks_OpenGraph_Integrations` with one helper,
`is_singular_on_front()` (not admin + `is_singular()`).

Loaded by `iWorks_OpenGraph::load_integrations()` on `init` 117: reads
`active_plugins`, `preg_match`es each basename, `include_once` + `new`.

| Trigger (basename regex) | Class | Hooks | What it does |
| --- | --- | --- | --- |
| `yarpp\.php$` | `…_Integrations_YARPP` | `og_og_array` | `og:see_also` (Pinterest) from YARPP related (limit 6) |
| `rt-reading-time\.php$` | `…_Integrations_Reading_Time_WP` | `og_twitter_array` (123) | Reading-time label via `Reading_Time_WP` + `rt_reading_time_options` |
| `categories-images\.php$` | `…_Integrations_Categories_Images` | `og_array` | Term image fallback via `z_taxonomy_image_url()` when no image yet |
| `post-expirator\.php$` | `…_Integrations_Post_Expirator` | `og_article_array` | `article:expiration_time` from `_expiration-date` meta |
| `contextual-related-posts\.php$` | `…_Integrations_Contextual_Related_Posts` | `og_og_array` | `og:see_also` via `get_crp_posts_id()` |
| `related-posts-for-wp\.php$` | `…_Integrations_Related_Posts_for_WordPress` | `og_og_array` | `og:see_also` via `RP4WP_Post_Link_Manager` |
| `twitter\.php$` | `…_Integrations_Twitter` | `og_twitter_array`, `twitter_card` → null, `option_{…}` → null | Syncs the official Twitter plugin card + widget colors; bails early without the plugin's classes |
| `sitepress\.php$` | `…_Integrations_Sitepress_Multilingual_CMS` | `og_og_array` | WPML: alternate locales into `og:locale` array |
| `woocommerce\.php$` | `…_Integration_WooCommerce` | `og_array` | Products: `og:type product`, SKU, availability, weight, price/sale price (+dates), categories, tags, brand (5 taxonomies); drops `article` section |
| `debug-bar\.php$` | `…_Integration_Debug_Bar` | `debug_bar_panels` | Registers the OpenGraph panel (prints the OG array as a table) |

Integration quirks (do not fix without approval):

- Filename scheme is inconsistent on purpose: older files use plural
  `integrations-{plugin}.php`, newer ones singular `integration-{plugin}.php`
  (WooCommerce, Debug Bar). Follow the closest sibling when adding a file.
- Contextual Related Posts passes a misspelled arg `'limir' => 6` — works with
  CRP as-is; do not "correct" it blindly.
- The Twitter integration bails out of its constructor when the Twitter plugin's
  classes are absent — hooks are only registered when the plugin is really usable.

## Adding a new integration

1. Create `includes/iworks/opengraph/integrations/class-iworks-opengraph-integrations-{plugin}.php`
   (match the singular/plural precedent of the sibling you model on) with:
   the `defined( 'ABSPATH' ) || exit;` guard, the `class_exists` guard, a
   `@since X.Y.Z` docblock naming the external plugin + URL, class extending
   `iWorks_OpenGraph_Integrations`.
2. In the constructor, hook into the appropriate section filter (`og_array`,
   `og_og_array`, `og_twitter_array`, `og_article_array`) and bail out early when
   the external plugin's function/class is missing.
3. Add a branch in `iWorks_OpenGraph::load_integrations()` with the basename
   `preg_match`, `include_once` and `new` (keep the existing comment style with
   the plugin URL and `@since`).
4. Remember: values printed as meta tags pass through `prepare_value_for_output()`
   — only scalars survive; build nested arrays per the OG array shape
   (see `project.md`).
5. Update the docs (`project.md` if behavior/hooks change, `classes.md` table)
   and, for user-visible changes, add a `readme.txt` changelog entry at release time.

## Vendored iWorks Rate (includes/iworks/rate/)

Git submodule (`iworks/iworks-rate`): "please rate" admin notices, anniversary
messages and dashboard widgets, wired via `iworks-register-plugin`,
`iworks_rate_css`, `iworks_rate_notice_logo_style`. Separate repo with its own
README — treat as vendor, never refactor or restyle it here.
