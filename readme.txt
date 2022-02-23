=== OG — Better Share on Social Media ===
Contributors: iworks
Donate link: https://ko-fi.com/iworks?utm_source=og&utm_medium=readme-donate
Tags: OpenGraph, Open Graph, social, share, facebook, meta, graph api, twitter, social share, share links, meta headers, pinterest
Requires at least: PLUGIN_REQUIRES_WORDPRESS
Tested up to: 6.0
Stable tag: PLUGIN_VERSION
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple method to add Open Graph metadata to your entries so that they look great when shared on sites.

== Description ==

The [Open Graph protocol][] enables any web page to become a rich object in a social graph.  Most notably, this allows for these pages to be used with Facebook's [Like Button][] and [Graph API][].

The Open Graph plugin inserts the Open Graph metadata into page head section and provides filters for other plugins and themes to override this data, or to provide additional Open Graph data.

***No configuration, pure power.***

Plugin grabs data from content and if contains YouTube URL, then plugin try to get movie thumbnail and use it in og:image.

If the post contains YouTube or Vimeo links, this plugin saves as post meta video thumbnail link and add it to og:image as post thumbnail.

Rich filters implementation to allow data change.

[Open Graph Protocol]: http://ogp.me/
[Like Button]: https://developers.facebook.com/docs/reference/plugins/like
[Graph API]: https://developers.facebook.com/docs/reference/api/
[Simple SEO Improvements]: https://wordpress.org/plugins/simple-seo-improvements/

== Installation ==

There are 3 ways to install this plugin:

= The super easy way =

1. **Login** to your WordPress Admin panel.
1. **Go to Plugins > Add New.**
1. **Type** ‘OG’ into the Search Plugins field and hit Enter. Once found, you can view details such as the point release, rating, and description.
1. **Click** Install Now. After clicking the link, you’ll be asked if you’re sure you want to install the plugin.
1. **Click** Yes, and WordPress completes the installation.
1. **Activate** the plugin.
1. That's all. ***The plugin does not have any configuration.***

***

= The easy way =

1. Download the plugin (.zip file) on the right column of this page
1. In your Admin, go to menu Plugins > Add
1. Select button `Upload Plugin`
1. Upload the .zip file you just downloaded
1. Activate the plugin
1. That's all. ***The plugin does not have any configuration.***

***

= The old and reliable way (FTP) =

1. Upload `OG` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. That's all. ***The plugin does not have any configuration.***

== Frequently Asked Questions ==

= How do I configure the Open Graph plugin? =

There is nothing to configure and there is no admin page. By default, it will use standard WordPress data which can to populate the Open Graph. There are very simple and powerful filters which you can use to modify or extend the metadata returned by the plugin. More information below.

= What plugin add for all type of content? =

* og:locale - site locale
* og:site_name - blog title
* og:title - post/page/archive/tag/... title
* og:url - the post/page permalink
* og:type - "article" for single content and "website" for all others
* og:description - site description
* og:site_name - site name

= What plugin add for single content? =

* og:image: From a specific custom field of the post/page, or if not set from the post/page featured/thumbnail image, or if it doesn't exist from the first image in the post content, or if it doesn't exist from the first image on the post media gallery, or if it doesn't exist from the default image defined in the options menu. The same image chosen here will be used and enclosure/media:content on the RSS feed.
* og:video - add links to YouTube movies.
* article:author - author of post link
* article:published_time - date of first article publication
* article:modified_time - date of last article modification
* article:tag - tags used in post
* twitter:card - summary
* twitter:title - the same line og:title
* twitter:description - the same like og:description
* twitter:image - the same like og:image
* twitter:player - the same like og:video

= What plugin add for a single WooCommerce product? =

* og:price:amount - price amount
* og:price:currency - price currency
* og:availability - stock status

= I installed OG and ... nothing happens! =

Please be patient, sometimes you need more a day to see results. The reason of this is cache on Facebook. But check your plugins too and if you use and caching plugins, try to do "flush cache" on your site.

You can force FB to refresh OpenGraph data by using this page https://developers.facebook.com/tools/debug/sharing/. Just go to Sharing Debugger, enter your URL and hit the button "Scrap Again".

= How to filter values? =

Use auto filters. If you have value like this:

    <meta property="og:title" content="WordPress Trunk" />

Then auto filter is created like this:

og_ + (word before ":") + _ + (word after ":") + _value

In this case:

og_og_title_value

    add_filter('og_og_title_value', 'my_og_og_title_value');
    function my_og_og_title_value($title)
    {
        if ( is_home() ) {
            return __('This is extra home title!', 'translate-domain');
        }
        return $title;
    }

= How to filter whole meta tag? =

Use auto filters. If you have value like this:

    <meta property="og:title" content="WordPress Trunk" />

Then auto filter is created like this:

og_ + (word before ":") + _ + (word after ":") + _meta

In this case:

og_og_title_meta

    add_filter('og_og_title_meta', 'my_og_og_title_meta');
    function my_og_og_title_meta($title)
    {
        if ( is_home() ) {
            return '<meta property="og:title" content="WordPress Title" />';
        }
        return $title;
    }

= How to setup default image? =

You can use [Simple SEO Improvements][] plugin, which is integrated with OG.

Or use the filter "og_image_init":

    add_filter('og_image_init', 'my_og_image_init');
    function my_og_image_init($images)
    {
        if ( is_front_page() || is_home() ) {
            $images[] = 'http://wordpress/wp-content/uploads/2014/11/DSCN0570.jpg';
        }
        return $images;
    }

= How to setup image on the front page? =

Use filter "og_image_init":

    add_filter('og_og_image_value', 'my_og_og_image_value');
    function my_og_og_image_value($images)
    {
        if ( empty($images) ) {
            $images[] = 'http://wordpress/wp-content/uploads/2014/11/DSCN0570.jpg';
        }
        return $images;
    }

= How to add Facebook app_id? =

You can use [Simple SEO Improvements][] plugin, which is integrated with OG.

Or  you can use `og_array` filter to add (or modify) OpenGraph tags.

    add_filter( 'og_array', 'add_og_facebook_data' );
    function add_og_facebook_data( $og ) {
        $og['fb'] = array(
            'app_id' => 'my-app-id',
            'pages' => 'foo, bar',
        );
        return $og;
    }


= How to add twitter:site? =

You can use [Simple SEO Improvements][] plugin, which is integrated with OG.

= How to disable author URL (article:author)? =

Use "og_article_author_value" filter, to return empty value for
"article:author" key:

    add_filter( 'og_article_author_value', '__return_empty_string' );

= How to disable featured image as og:image? =

Use "og_allow_to_use_thumbnail" filter and return false.

add_filter( 'og_allow_to_use_thumbnail', '__return_false' );

= How to avoid WhatsApp issue with og:image tag?

In some cases WhatsApp doesn't show `og:image` and some times you can avoid it pushing og tags fairly close to the top of the `<head>`. 

If you have similar issue you can change priority for `wp_action`.

Use this code to change it to `0` (default is `9`).

    add_filter( 'og_wp_head_priority', '__return_zero' );

= How to disable Schema.org output? =

Just add this code:

    add_filter( 'og_is_schema_org_enabled', '__return_false' ) ;

== Changelog ==

= 3.0.3 (2022-02-23) =
* Added filter `og_is_schema_org_enabled` to disable Schema.org output.
* Updated iWorks Rate to 2.1.0.

= 3.0.2 (2022-02-12) =
* Fixed misspelled filter name `og_wp_head_prioryty` into `og_wp_head_priority`. Props for [Armsportstore.com](https://wordpress.org/support/users/armbreakersweden/).

= 3.0.1 (2022-02-10) =
* Excluded `itemscope` for WP-Sitemap stylesheet. Props for [Jasper](https://wordpress.org/support/users/lucydog/)
* Fixed `article:author` values, according to [ogp.me](https://ogp.me/)
* Unified `article:author` and `profile` values.

= 3.0.0 (2022-02-09) =
* Added `article:expiration_time` as integration with [PublishPress Future: Automatically Unpublish WordPress Posts](https://wordpress.org/plugins/post-expirator/).
* Added few PHP_EOL character for non-debug output. Props for [Guido](https://profiles.wordpress.org/guido07111975/).
* Improved checking integrations - removed usage of `class_exists` function.

= 2.9.9 (2022-02-08) =
* Fixed older PHP issues.

= 2.9.8 (2022-02-08) =
* Added for itemscope itemtype to HTML using `language_attributes` filter. Props for [Michał Ruszczyk](https://profiles.wordpress.org/mruszczyk/).

= 2.9.7 (2022-02-03) =
* Added integration with plugin [Categories Images](https://wordpress.org/plugins/categories-images/).
* Added filter `filter_og_get_image_dimensions_by_id` to allow get image data by attachment_ID.
* Added `twitter:image:alt`.
* Shorted `twitter:description` length into 200 characters. More: [Cards](https://developer.twitter.com/en/docs/twitter-for-websites/cards/overview/markup).

= 2.9.6 (2022-01-20) =
* Updated iWorks Rate to 2.0.6.

= 2.9.5 (2021-12-29) =
* Added filter `og_wp_head_priority` to allow to change `wp_head` priority.

= 2.9.4 (2021-11-19) =
* Added `og_og_array` filter to a part of OpenGraph array.
* Added `og_article_array` filter to a part of OpenGraph array.
* Added `og_twitter_array` filter to a part of OpenGraph array.
* Added `og_schema_array` filter to a part of OpenGraph array.
* Added `og_profile_array` filter to a part of OpenGraph array.
* Added integration with [Reading Time WP](https://wordpress.org/plugins/reading-time-wp/) for Twitter.
* Added support for `twitter:label` and `twitter:data`.

= 2.9.3 (2021-11-08) =
* Added author gravatar as twitter:image on author archive.
* Added Schema.org html meta tags.
* Fixed missing og:url on author archive page.
* Fixed missing Twitter on single page.
* Improved og:url for search results page.
* Renamed plugin into "OG - Better Share on Social Media".
* Updated iWorks Rate to 2.0.4.

= 2.9.2 (2021-06-30) =
* Added og:brand as integration with few plugins.
* Added og:description into author page (from user bio).
* Added product:category for WooCommerce product categories.
* Added product:retailer_item_id for WooCommerce SKU.
* Added product:tags for WooCommerce product tags.
* Updated iWorks Rate to 2.0.3.

= 2.9.1 (2021-06-23) =
* Added check image exists instead just processing.
* Renamed directory `vendor` into `includes`.
* Updated iWorks Rate to 2.0.0.

= 2.9.0 (2021-03-31 =
* Added `og_allow_to_use_thumbnail` filter to disable feature image as og:image.
* Added `og_allow_to_use_vimeo` filter to disable Vimeo movie thumbnail as og:image.
* Added `og_allow_to_use_youtube` filter to disable YouTube movie thumbnail as og:image.
* Added `og_check_add_video_thumbnails_by_post` to disable video from post, by post (second parameter is $post).
* Added `og_get_locale` to filter locale.
* Added `og_set_transient_expiration` filter, default is DAY_IN_SECONDS.

= 2.8.9 (2020-10-21) =
* Added response code check for YouTube thumbnails. Props for [Biblioteka Targówek](https://wordpress.org/support/users/bekatarg/).
* Added fallback for YouTube thumbnails: `maxresdefault` -> `hqdefault` -> `0`.

= 2.8.8 (2020-10-16) =
* Added `is_author()` page with `og:profile` values and user gravatar as `og:image`.

= 2.8.7 (2020-10-05) =
* Fixed `og:image` order issue, move thumbnail was offered first, instead entry featured image. Props for Maurício Varallo II.

= 2.8.5 (2020-06-20) =
* Fixed minor issue with og:type.

= 2.8.4 (2020-06-20) =
* Added Pinterest `og:see_also` tag when [YARPP](https://wordpress.org/plugins/yet-another-related-posts-plugin/) plugin is used and post has related posts.
* Fixed `og:image:alt` value, now we use ALT first.
* Improved og:audio tag on audio attachment page.
* Improved og:video tag on video attachment page.
* Improved usage with [Orphans](https://wordpress.org/plugins/sierotki/) plugin.

= 2.8.3 (2020-06-03) =
* Fixed problem with wrong database query param.

= 2.8.2 (2020-06-03) =
* Added Vimeo video support for og:image and og:video.
* Improved default YouTube images, now it is saved with SSL.
* Removed array sum code it is incompatible with older PHP.

= 2.8.1 (2020-06-03) =
* Added dimensions and type for YouTube images if it is possible.
* Added filter `og_twitter_creator` to easy setup Twitter @username of creator.
* Added multiple `og:image` for all YouTube movies.
* Removed post meta for YouTube images when movies was deleted from entry.
* Use SSL for YouTube images if site is on SSL.

= 2.8.0 (2020-06-03) =
* Removed Facebook check for allowed locales.
* Fixed Twitter `summary_large_image` issue.
* Added filter `og_twitter_site` to easy setup Twitter @username for site owner.

= 2.7.9 (2020-06-02) =
* Improved cache key - now it include plugin version, to avoid get older cache.

= 2.7.8 (2020-06-02) =
* Fixed an issue with no feature image, but wit multiple images in content. Props for [anthonykung](https://wordpress.org/support/users/anthonykung/)

= 2.7.7 (2020-06-01) =
* Added `og:image:secure_url` for images with https url. Props for [mociofiletto](https://wordpress.org/support/users/mociofiletto/)
* Improved attachment page OpenGraph tags.
* For entry without thumbnail get all content images into og:image.

= 2.7.6 (2019-08-18) =
* Added filter `og_profile` to allow change `profile` values. Props for [edmorrow](https://wordpress.org/support/users/edmorrow/)

= 2.7.5 (2019-05-15) =
* Fixed missing `og:image:alt` for featured image.

= 2.7.4 (2019-04-25) =
* Fixed a problem with `og:image` content for document with featured image and images in the content. Props for [sudoranger](https://wordpress.org/support/users/sudoranger/)

= 2.7.3 (2019-04-23) =
* Added "summary_large_image" for twitter:card if attached image has width bigger then 520px. Props for [Bobby Eberle](https://www.facebook.com/bobby.eberle).


= 2.7.2 (2019-04-13) =
* Fixed `sprintf()` issue. Props for [John Glynn](https://www.linkedin.com/in/john-glynn-2a233426/)

= 2.7.1 (2019-04-13) =
* Added locale string into cache settings to be able handle languages. Props for [Oleksandr Omelchenko](https://wordpress.org/support/users/konusua/).
* Added cache locale value inside the class object to.

= 2.7.0 (2018-10-21) =
* Added proper og:url for custom post archive page. Props for [cabaltc](https://wordpress.org/support/users/cabaltc/).
* Added proper og:url for a day, month and year archive page.
* Added proper og:url for a search result.
* Added proper og:url for taxonomy archive page.
* Removed OpenGraph from 404 page.

= 2.6.2 (2018-10-11) =
* Fixed blog posts page og:url. Props for [cabaltc](https://wordpress.org/support/users/cabaltc/).

= 2.6.1 (2018-09-06) =
* Added `esc_url` for image src value.
* Striped tags from OG tag value.

= 2.6.0 (2018-06-04) =
* Added attached audio files to `og:audio` tag.
* Added attached video files to `og:video` tag.
* Added `og:update_time` tag.
* Added transient cache for single entries to decrease DB usage.
* Updated Facebook locales list.

= 2.5.3 (2018-05-09) =
* Remove debug function, which broke whole plugin.

= 2.5.2 (2018-05-08) =
* Added filter `og_description_words` to allow change `og:description` length.
* Fixed a problem with striping last word. Props for [intrex](https://wordpress.org/support/users/intrex/).
* We are back to trim `og:description` to 55 words (it is default for `wp_trim_words()` function).

= 2.5.1 (2018-04-16) =
* Added og:image:width and og:image:height for first content image from site URL.

= 2.5.0 (2018-04-14) =
* Added og:image:width and og:image:height for featured image.

= 2.4.9 (2018-02-27) =
* Remove filter "the_content" to avoid incompatibility with some plugins.

= 2.4.8 (2018-02-19) =
* Added first content image to og:image if featured image is not set. Props for [andreyenkin](https://wordpress.org/support/users/andreyenkin/).

= 2.4.7 (2017-09-26) =
* Added filter "og_[og_name]_value" to change single og value.
* Added og:type for post formats "audio" and "video".
* Fixed og:type for WooCommerce product. Props for [shaharsol](https://wordpress.org/support/users/shaharsol/).

= 2.4.6 (2017-09-13) =
* Removed limit for og:description.

= 2.4.5 (2017-06-13) =
* Added filter "og_array" which allows to change whole OG array before print it.


= 2.4.4 (2017-05-20) =
* Fixed site crash when WooCommerce is active. Props for [JLY](https://wordpress.org/support/users/jose-luis-yanez/).

= 2.4.3 (2017-05-09) =
* Update "Rate" module to 1.0.1 - fixed wrong rate URL for non-English.

= 2.4.2 (2017-05-03) =
* Added tags "og:video" and "twitter:player" for YouTube embed movies.
* Added WooCommerce integration for tags: "og:price:amount", "og:price:currency" and "og:availability".
* Improved description tag, when entry content is empty, add entry title as description.

= 2.4.1 (2016-10-26) =
* Fixed problem for pages and another single content. At this moment OG works for all types of single entries.
* Added ask for the rating on the plugin page.
* Short twitter description.

= 2.4.0 (2016-04-10) =
* Fixed the problem with proper preparation for localization.
* Fixed the profile with grabbing YouTube image.
* Implement WordPress code standard for PHP code used in the plugin.

= 2.3.0 (2016-02-03) =
* Added categories as og:section.
* Added esc_attr to headers tags.
* Added msapplication-TileImage.
* Added og:site_name.
* Added profile:first_name, profile:last_name & profile:username props for [Arek](http://arek.bibliotekarz.com/).
* Added tags.
* Added twitter tags.
* Added usage of site icon when is no icon - all cases.

= 2.2.0 (2015-08-19) =
* IMPROVEMENT: added the site icon as og:image for the home page.

= 2.1.0 (2015-05-21) =
* IMPROVEMENT: added checking site locale with facebook allowed locale.

= 2.0.0 (2014-12-11) =
* IMPROVEMENT: added check to post_content exists for CPT without this field.
* IMPROVEMENT: added og:author link
* IMPROVEMENT: big refactoring
* IMPROVEMENT: added filters, see [FAQ](https://wordpress.org/plugins/og/faq/) section

= 1.0.0 (2014-10-02) =
Init.

