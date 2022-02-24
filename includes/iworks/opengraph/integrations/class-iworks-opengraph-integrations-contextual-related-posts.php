<?php
/**
 * Contextual Related Posts
 * https://wordpress.org/plugins/contextual-related-posts/
 *
 * @since 3.1.0
 */
class iWorks_OpenGraph_Integrations_Contextual_Related_Posts extends iWorks_OpenGraph_Integrations {

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
		if ( ! function_exists( 'get_crp_posts_id' ) ) {
			return $data;
		}
		$args    = array(
			'strict_limit' => true,
			'limir'        => 6,
		);
		$related = get_crp_posts_id( $args );
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

