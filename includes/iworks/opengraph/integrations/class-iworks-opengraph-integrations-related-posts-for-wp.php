<?php
/**
 * Related Posts for WordPress
 * https://wordpress.org/plugins/related-posts-for-wp/
 *
 * @since 3.1.0
 */
class iWorks_OpenGraph_Integrations_Related_Posts_for_WordPress extends iWorks_OpenGraph_Integrations {

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
		if ( ! function_exists( 'rp4wp_children' ) ) {
			return $data;
		}
		$pl_manager = new RP4WP_Post_Link_Manager();
		$args       = array(
			'posts_per_page' => 6,
		);
		$related    = $pl_manager->get_children( get_the_ID(), $args );
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


