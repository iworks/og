<?php
/**
 * Categories Images
 * https://wordpress.org/plugins/categories-images/
 *
 * @since 2.9.7
 */
class iWorks_OpenGraph_Integrations_Categories_Images extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'og_array', array( $this, 'change' ) );
	}

	/**
	 * Change OG array
	 *
	 * @since 2.9.7
	 */
	public function change( $og ) {
		if (
			isset( $og['og'] )
			&& isset( $og['og']['image'] )
			&& ! empty( $og['og']['image'] )
		) {
			return $og;
		}
		if ( ! function_exists( 'z_taxonomy_image_url' ) ) {
			return;
		}
		if ( ! is_tax() && ! is_category() && ! is_tag() ) {
			return $og;
		}
		$image_url = z_taxonomy_image_url();
		if ( empty( $image_url ) ) {
			return $og;
		}
		$image_id = $this->get_attachment_id_by_url( $image_url );
		if ( empty( $image_id ) ) {
			return $og;
		}
		$og['og']['image'] = apply_filters( 'og_get_image_dimensions', $og['og']['image'], $image_id );
		return $og;
	}

	/**
	 * get attachment ID by image url
	 *
	 * @since 2.9.7
	 */
	function get_attachment_id_by_url( $image_src ) {
		global $wpdb;
		$query = $wpdb->prepare( "select id from $wpdb->posts where guid = %s", $image_src );
		$id    = $wpdb->get_var( $query );
		return ( ! empty( $id ) ) ? $id : null;
	}
}

