<?php
/**
 * OG — Better Share on Social Media
 *
 * @package           PLUGIN_NAME
 * @author            AUTHOR_NAME
 * @copyright         2014-PLUGIN_TILL_YEAR Marcin Pietrzak
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       OG — Better Share on Social Media
 * Plugin URI:        PLUGIN_URI
 * Description:       PLUGIN_DESCRIPTION
 * Version:           PLUGIN_VERSION
 * Requires at least: PLUGIN_REQUIRES_WORDPRESS
 * Requires PHP:      PLUGIN_REQUIRES_PHP
 * Author:            AUTHOR_NAME
 * Author URI:        AUTHOR_URI
 * Text Domain:       og
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

require_once dirname( __FILE__ ) . '/includes/iworks/class-iworks-opengraph.php';
new iWorks_OpenGraph();


