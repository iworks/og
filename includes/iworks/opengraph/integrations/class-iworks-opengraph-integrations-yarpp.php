<?php
/**
 * YARPP – Yet Another Related Posts Plugin
 * https://wordpress.org/plugins/yet-another-related-posts-plugin/
 *
 * @since 3.1.0
 */
class iWorks_OpenGraph_Integrations_YARPP extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'og_og_array', array( $this, 'change' ) );
	}

	/**
	 * Add Pintrest og:see_also
	 *
	 * @since 3.1.0
	 */
	public function change( $data ) {
		if ( ! is_singular() ) {
			return $data;
		}
		global $yarpp;
		if ( ! is_object( $yarpp ) ) {
			return $data;
		}
		if ( ! method_exists( $yarpp, 'get_related' ) ) {
			return $data;
		}
		$related = $yarpp->get_related( get_the_ID(), array( 'limit' => 6 ) );
		if ( empty( $related ) ) {
			return $data;
		}
		if ( ! isset( $data['see_also'] ) || ! is_array( $data['see_also'] ) ) {
			$data['see_also'] = array();
		}
		foreach ( $related as $one ) {
			$data['see_also'][] = get_permalink( $one->ID );
		}
		$data['see_also'] = array_unique( $data['see_also'] );
		return $data;
	}
}


