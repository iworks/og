<?php
/*

Copyright 2023-PLUGIN_TILL_YEAR Marcin Pietrzak (marcin@iworks.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */
defined( 'ABSPATH' ) || exit;


class iWorks_OpenGraph_Integration_Debug_Bar_Panel extends Debug_Bar_Panel {

	function init() {
		$this->title( __( 'OpenGraph', 'og' ) );
	}

	function prerender() {
		$this->set_visible( ! is_admin() );
	}

	function render() {
		echo '<div id="debug-bar-og" class="debug-bar-wp-query-list">';
		printf(
			'<h3>%s</h3>',
			esc_html__( 'OpenGraph', 'og' )
		);
		$og = apply_filters( 'og_get_og_array', array() );
		echo '<table>';
		echo '<thead>';
		printf(
			'<tr><th style="width:10em;text-align:right">%s</th><th style="width:1em;text-align:center">⇒</th><th>%s</th></tr>',
			esc_html__( 'OG Tag', 'og' ),
			esc_html_e( 'OG Value', 'og' )
		);
		echo '</thead>';
		echo '<tbody>';
		$this->echo_array( $og );
		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}

	private function echo_array( $og, $parent = array() ) {
		foreach ( $og as $tag => $data ) {
			$tags = $parent;
			if ( ! is_integer( $tag ) ) {
				$tags[] = $tag;
			}
			if ( is_array( $data ) ) {
				$this->echo_array( $data, $tags );
			} else {
				if ( 'schema' === $tags[0] ) {
					if ( apply_filters( 'og_is_schema_org_enabled', true ) ) {
						$this->echo_one( $tags[1], $data, 'itemprop' );
					}
				} elseif ( 2 < sizeof( $tags ) && $tags[1] === $tags[2] ) {
					$this->echo_one( array( $tags[0], $tags[1] ), $data );
				} else {
					$this->echo_one( $tags, $data );
				}
			}
		}
	}

	/**
	 * Echo one row
	 *
	 * @since 2.4.2
	 */
	private function echo_one( $property, $value, $name = 'property' ) {
		$meta_property = $property;
		if ( is_array( $property ) ) {
			$meta_property = implode( ':', $property );
		}
		$meta_property = preg_replace( '/^og:(image|video):url$/', 'og:$1', $meta_property );
		printf(
			'<tr><th style="width:10em;text-align:right">%s</th><td style="width:1em;text-align:center">⇒</td><td>%s</td></tr>',
			esc_attr( $meta_property ),
			esc_attr( wp_strip_all_tags( $value ) )
		);
	}
}

