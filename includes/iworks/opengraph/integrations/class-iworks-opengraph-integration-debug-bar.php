<?php
/**
 * Debug Bar
 * https://wordpress.org/plugins/debug-bar/
 *
 * @since 3.3.0
 */
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'iWorks_OpenGraph_Integration_Debug_Bar' ) ) {
	return;
}

class iWorks_OpenGraph_Integration_Debug_Bar extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'debug_bar_panels', array( $this, 'filter_debug_bar_panels' ) );
	}

	public function filter_debug_bar_panels( $panels ) {
		include_once __DIR__ . '/class-iworks-opengraph-integration-debug-bar-panel.php';
		$panels[] = new iWorks_OpenGraph_Integration_Debug_Bar_Panel();
		return $panels;
	}
}

