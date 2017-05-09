=== OG ===
Contributors: iworks
Donate link: http://iworks.pl/donate/og.php
Tags: open graph, facebook, social, thumbnail, featured image, og, FB, meta, share
Requires at least: 3.5
Tested up to: 4.7.4
Stable tag: 2.4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple and tiny Open Graph WordPress plugin to handle Facebook data.

== Description ==

This is a simple, tiny plugin to produce og:tags. Just that and only
that. No configuration, pure power.

Plugin grabs data from content and if contains YouTube URL, then plugin
try to get movie thumbnail and use it in og:image.

If post contains YouTube links, this plugin saves as post meta video
thumbnail link and add it to og:image as post thumbnail.

The Facebook Open Graph Tags inserted by this plugin are:

= for all type of content =

* og:locale - site locale
* og:site_name - blog title
* og:title - post/page/archive/tag/... title
* og:url - the post/page permalink
* og:type - "website" for the homepage, "article" for single content and blog for all others
* og:description - site description
* og:site_name - site name

= for single content =

* og:image: From a specific custom field of the post/page, or if not set from the post/page featured/thumbnail image, or if it doesn't exist from the first image in the post content, or if it doesn't exist from the first image on the post media gallery, or if it doesn't exist from the default image defined in the options menu. The same image chosen here will be used and enclosure/media:content on the RSS feed.
* og:video - add links to YouTube movies.
* article:author - author of post link
* article:published_time - date of first article publication
* article:modified_time - date of last article modification
* article:tag - tags used in post
* twitter:card - "summary.
* twitter:title - the same line og:title
* twitter:description - the same like og:description
* twitter:image - the same like og:image
* twitter:player - the same like og:video

= for single WooCommerce product =

* og:price:amount - price amount
* og:price:currency - price currency
* og:availability - stock status

== Installation ==

There are 3 ways to install this plugin:

= The super easy way =

1. **Log in** to your WordPress Admin panel.
1. **Go to Plugins > Add New.**
1. **Type** ‘OG’ into the Search Plugins field and hit Enter. Once found, you can view details such as the point release, rating, and description.
1. **Click** Install Now. After clicking the link, you’ll be asked if you’re sure you want to install the plugin.
1. **Click** Yes, and WordPress completes the installation.
1. **Activate** the plugin.
1. That's all. The plugin does not have any configuration.

***

= The easy way =

1. Download the plugin (.zip file) on the right column of this page
1. In your Admin, go to menu Plugins > Add
1. Select button `Upload Plugin`
1. Upload the .zip file you just downloaded
1. Activate the plugin
1. That's all. The plugin does not have any configuration.

***

= The old and reliable way (FTP) =

1. Upload `OG` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. That's all. The plugin does not have any configuration.

== Frequently Asked Questions ==

= I installed OG and ... nothing happen! =

Please be patient, sometimes you need more a day to see results. The reason
of this is cache on Facebook. But check your plugins too and if you use
and caching plugins, try to do "flush cache" on your site.

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

Use filter "og_image_init":

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


== Changelog ==

= 2.4.3 (2017-05-09) =

* Update "Rate" module to 1.0.1 - fixed wrong rate URL for non English.

= 2.4.2 (2017-05-03) =

* Added tags "og:video" and "twitter:player" for YouTube embed movies.
* Added WooCommerce integration for tags: "og:price:amount", "og:price:currency" and "og:availability".
* Improved description tag, when entry content is empty, add entry title as description.

= 2.4.1 (2016-10-26) =

* Fixed problem for pages and another single content. At this moment OG works for all types of single entries.
* Added ask for rating on plugins page.
* Short twitter description.

= 2.4 (2016-04-10) =

* Fixed the problem with properly preparation for localization.
* Fixed the profile with grabbing YouTube image.
* Implement WordPress code standard for PHP code used in the plugin.

= 2.3 (2016-02-03) =

* Added categories as og:section.
* Added esc_attr to headers tags.
* Added msapplication-TileImage.
* Added og:site_name.
* Added profile:first_name, profile:last_name & profile:username props for [Arek](http://arek.bibliotekarz.com/)
* Added tags.
* Added twitter tags.
* Added usage of site icon when is no icon - all cases.

= 2.2 (2015-08-19) =

* IMPROVEMENT: added the site icon as og:image for the home page.

= 2.1 (2015-05-21) =

* IMPROVEMENT: added checking site locale with facebook allowed locale.

= 2.0 (2014-12-11) =

* IMPROVEMENT: added check to post_content exists for CPT without this field.
* IMPROVEMENT: added og:author link
* IMPROVEMENT: big refactoring
* IMPROVEMENT: added filters, see [FAQ](https://wordpress.org/plugins/og/faq/) section

= 1.0 (2014-10-02) =

Init.
